#!/usr/bin/env python3
"""
CI self::METHOD() cross-class reference checker.

Detects `self::METHOD()` calls where METHOD is NOT defined in the same class.
This is the exact bug pattern that caused the v27.8.0/v27.8.1 Fatal Errors:
after class splits, callers still used `self::` to reference methods that had
been moved to other classes.

Usage:
    python3 ci_check_self_refs.py [src_dir]

Exit codes:
    0 — no issues found
    1 — cross-class self:: references detected (CI should fail)

How it works:
    1. Parse every .php file under src/ with the glayzzle php-parser (Node).
    2. Build a map: { ClassName: set(method_names_defined_in_it) }.
    3. For each `self::METHOD()` call inside a class, check if METHOD is in
       that class's method set. If not → flag as cross-class reference.
    4. Also checks `self::CONSTANT` references against the class's constants.

Excludes:
    - self::class (PHP magic constant, always valid)
    - Calls inside comments/strings (parser handles this)
    - Methods/constants inherited from parent classes (tracked via extends)
"""
import json
import subprocess
import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent / "linked3"
if len(sys.argv) > 1:
    BASE = Path(sys.argv[1])

# Node script that does the actual parsing
NODE_SCRIPT = r"""
const fs = require('fs');
const path = require('path');
const phpParser = require('php-parser');

const engine = new phpParser.Engine({
    parser: { extractDoc: false, php7: true },
    ast: { withPositions: true },
});

// Collect: for each class, its defined methods + constants + parent class
const classes = {};  // { FQCN: { methods: Set, constants: Set, parent: string|null, file: string } }

function walk(node, fn) {
    if (!node || typeof node !== 'object') return;
    fn(node);
    for (const key of Object.keys(node)) {
        const val = node[key];
        if (Array.isArray(val)) {
            for (const item of val) {
                if (item && typeof item === 'object') walk(item, fn);
            }
        } else if (val && typeof val === 'object' && val.kind) {
            walk(val, fn);
        }
    }
}

function getFQCN(decl, namespace) {
    if (!decl.name) return null;
    const name = decl.name.name || decl.name;
    return namespace ? namespace + '\\' + name : name;
}

function analyzeFile(filepath) {
    let code;
    try {
        code = fs.readFileSync(filepath, 'utf-8');
    } catch (e) { return; }

    let ast;
    try {
        ast = engine.parseCode(code, path.basename(filepath));
    } catch (e) {
        return; // skip unparseable files
    }

    let namespace = '';
    // First pass: find namespace
    walk(ast, (node) => {
        if (node.kind === 'namespace' && node.name) {
            namespace = node.name;
        }
    });

    // Second pass: find class declarations
    walk(ast, (node) => {
        if (node.kind === 'class' && node.name) {
            const fqcn = namespace ? namespace + '\\' + node.name.name : node.name.name;
            const methods = new Set();
            const constants = new Set();
            let parent = null;

            if (node.extends && node.extends.name) {
                parent = node.extends.name;
            }

            if (node.body) {
                for (const member of node.body) {
                    if (member.kind === 'method' && member.name) {
                        methods.add(member.name.name || member.name);
                    } else if (member.kind === 'classconstant' && member.name) {
                        constants.add(member.name.name || member.name);
                    }
                }
            }

            classes[fqcn] = { methods: [...methods], constants: [...constants], parent, file: filepath };
        }
    });
}

// Collect all PHP files
function findPhpFiles(dir) {
    const results = [];
    function walk(dir) {
        const entries = fs.readdirSync(dir, { withFileTypes: true });
        for (const e of entries) {
            const full = path.join(dir, e.name);
            if (e.isDirectory()) walk(full);
            else if (e.name.endsWith('.php')) results.push(full);
        }
    }
    walk(dir);
    return results;
}

const baseDir = process.argv[2];
const files = findPhpFiles(baseDir);
for (const f of files) analyzeFile(f);

// Now find self::METHOD() and self::CONSTANT references that don't resolve
const issues = [];

function resolveMethod(fqcn, methodName, visited) {
    if (visited.has(fqcn)) return false;
    visited.add(fqcn);
    const cls = classes[fqcn];
    if (!cls) return false;
    if (cls.methods.includes(methodName)) return true;
    if (cls.parent) {
        // Try resolving parent in same namespace or as-is
        const ns = fqcn.includes('\\') ? fqcn.substring(0, fqcn.lastIndexOf('\\')) : '';
        const parentFqcn = cls.parent.includes('\\') ? cls.parent : (ns ? ns + '\\' + cls.parent : cls.parent);
        if (resolveMethod(parentFqcn, methodName, visited)) return true;
    }
    return false;
}

function resolveConstant(fqcn, constName, visited) {
    if (visited.has(fqcn)) return false;
    visited.add(fqcn);
    const cls = classes[fqcn];
    if (!cls) return false;
    if (cls.constants.includes(constName)) return true;
    if (cls.parent) {
        const ns = fqcn.includes('\\') ? fqcn.substring(0, fqcn.lastIndexOf('\\')) : '';
        const parentFqcn = cls.parent.includes('\\') ? cls.parent : (ns ? ns + '\\' + cls.parent : cls.parent);
        if (resolveConstant(parentFqcn, constName, visited)) return true;
    }
    return false;
}

for (const [fqcn, cls] of Object.entries(classes)) {
    let code;
    try {
        code = fs.readFileSync(cls.file, 'utf-8');
    } catch (e) { continue; }

    let ast;
    try {
        ast = engine.parseCode(code, path.basename(cls.file));
    } catch (e) { continue; }

    // Find this class's AST node
    walk(ast, (node) => {
        if (node.kind === 'class' && node.name) {
            const nodeFqcn = ast.children && ast.children.find(c => c.kind === 'namespace');
            // Walk inside class body for static calls
            if (node.body) {
                for (const member of node.body) {
                    walk(member, (inner) => {
                        if (inner.kind === 'staticcall' && inner.what && inner.what.kind === 'self' && inner.name) {
                            const methodName = inner.name.name || inner.name;
                            if (methodName === 'class') return; // self::class is magic
                            if (!resolveMethod(fqcn, methodName, new Set())) {
                                issues.push({
                                    file: cls.file,
                                    class: fqcn,
                                    ref: 'self::' + methodName + '()',
                                    line: inner.loc ? inner.loc.start.line : 0,
                                    kind: 'method'
                                });
                            }
                        }
                        if (inner.kind === 'staticproperty' && inner.what && inner.what.kind === 'self' && inner.name) {
                            // self::CONSTANT is parsed as staticproperty with name being identifier
                            const constName = typeof inner.name === 'string' ? inner.name : (inner.name.name || inner.name);
                            if (constName === 'class') return;
                            if (!resolveConstant(fqcn, constName, new Set())) {
                                issues.push({
                                    file: cls.file,
                                    class: fqcn,
                                    ref: 'self::' + constName,
                                    line: inner.loc ? inner.loc.start.line : 0,
                                    kind: 'constant'
                                });
                            }
                        }
                    });
                }
            }
        }
    });
}

console.log(JSON.stringify({ classes_count: Object.keys(classes).length, issues }, null, 2));
"""

def main():
    node_script_path = Path(__file__).parent / "_ci_check_self_refs_node.js"
    node_script_path.write_text(NODE_SCRIPT)

    result = subprocess.run(
        ['node', str(node_script_path), str(BASE)],
        capture_output=True, text=True, timeout=300
    )

    if result.returncode != 0:
        print(f"Node script failed: {result.stderr}")
        sys.exit(2)

    try:
        data = json.loads(result.stdout)
    except json.JSONDecodeError:
        print(f"Failed to parse node output: {result.stdout[:500]}")
        sys.exit(2)

    issues = data.get('issues', [])
    classes_count = data.get('classes_count', 0)

    print("=" * 70)
    print("CI self:: Cross-Class Reference Check")
    print("=" * 70)
    print(f"Scanned {classes_count} classes in {BASE}")
    print()

    if not issues:
        print("RESULT: PASS — no cross-class self:: references found.")
        sys.exit(0)

    print(f"RESULT: FAIL — {len(issues)} cross-class self:: reference(s) found:")
    print()
    for issue in sorted(issues, key=lambda x: (x['file'], x['line'])):
        rel = str(Path(issue['file']).relative_to(BASE)) if BASE in Path(issue['file']).parents else issue['file']
        print(f"  {rel}:{issue['line']}")
        print(f"    {issue['class']} calls {issue['ref']} ({issue['kind']} not defined in this class)")
        print()
    sys.exit(1)

if __name__ == "__main__":
    main()
