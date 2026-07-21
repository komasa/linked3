#!/usr/bin/env python3
"""
class_alias Coverage Verification Script
=========================================
Addresses: risk-assessment-20260721.md §4.2, Open Item #5

Extracts all old→new class name mappings from:
1. LegacyAliasRegistry (179 aliases via LegacyAliasRegistry::add())
2. OSAliasRegistry (35 aliases via linked3_os_register_alias())

Then searches the entire codebase for references to each old class name
to determine coverage gaps.

Output: coverage-report.json + coverage-report.md
"""
import re
import json
import os
from pathlib import Path
from collections import defaultdict

REPO_ROOT = Path(__file__).resolve().parent.parent

def extract_legacy_aliases():
    """Extract old→new mappings from LegacyAliasRegistry.php"""
    registry_file = REPO_ROOT / "src/Includes/Compat/LegacyAliasRegistry.php"
    content = registry_file.read_text()
    # Match: LegacyAliasRegistry::add('OldClass', 'NewClass');
    pattern = r"LegacyAliasRegistry::add\(\s*'([^']+)'\s*,\s*'([^']+)'\s*\)"
    matches = re.findall(pattern, content)
    return [(old, new, "LegacyAliasRegistry") for old, new in matches]

def extract_os_aliases():
    """Extract old→new mappings from OSAliasRegistry.php"""
    registry_file = REPO_ROOT / "src/Classes/OS/OSAliasRegistry.php"
    content = registry_file.read_text()
    # Match: linked3_os_register_alias('OldClass', 'NewClass');
    pattern = r"linked3_os_register_alias\(\s*'([^']+)'\s*,\s*'([^']+)'\s*\)"
    matches = re.findall(pattern, content)
    return [(old, new, "OSAliasRegistry") for old, new in matches]

def check_function_defined():
    """Check if linked3_os_register_alias function is defined anywhere"""
    php_files = REPO_ROOT.rglob("*.php")
    for f in php_files:
        if "vendor" in str(f):
            continue
        content = f.read_text(errors="ignore")
        if re.search(r"function\s+linked3_os_register_alias\s*\(", content):
            return str(f)
    return None

def search_references(class_name):
    """Search for references to a class name in all PHP files"""
    refs = []
    php_files = REPO_ROOT.rglob("*.php")
    for f in php_files:
        rel = str(f.relative_to(REPO_ROOT))
        if "vendor" in rel or rel.startswith("tests/"):
            continue
        content = f.read_text(errors="ignore")
        # Search for class name as a word (not inside comments ideally, but be conservative)
        # Escape special regex chars
        escaped = re.escape(class_name)
        # Match as word boundary
        pattern = rf"\b{escaped}\b"
        matches = list(re.finditer(pattern, content))
        if matches:
            # Count occurrences
            count = len(matches)
            refs.append({"file": rel, "count": count})
    return refs

def main():
    print("=" * 60)
    print("class_alias Coverage Verification")
    print("=" * 60)

    # 1. Extract all aliases
    legacy_aliases = extract_legacy_aliases()
    os_aliases = extract_os_aliases()
    all_aliases = legacy_aliases + os_aliases

    print(f"\n[1] Alias Registry Extraction:")
    print(f"    LegacyAliasRegistry: {len(legacy_aliases)} aliases")
    print(f"    OSAliasRegistry:     {len(os_aliases)} aliases")
    print(f"    Total:               {len(all_aliases)} aliases")

    # 2. Check if OS function is defined
    func_loc = check_function_defined()
    os_func_defined = func_loc is not None
    print(f"\n[2] OS Alias Function Status:")
    if os_func_defined:
        print(f"    linked3_os_register_alias defined in: {func_loc}")
    else:
        print(f"    *** FATAL: linked3_os_register_alias() is CALLED but NEVER DEFINED ***")
        print(f"    All {len(os_aliases)} OS aliases are NON-FUNCTIONAL (fatal error on load)")

    # 3. Extract unique old class names
    old_names = list(set(old for old, new, src in all_aliases))
    # Also get bare (global) names
    bare_names = set()
    for old, new, src in all_aliases:
        # If it contains backslash, also add the last part as bare name
        if "\\" in old:
            bare = old.rsplit("\\", 1)[-1]
            bare_names.add(bare)
        else:
            bare_names.add(old)

    print(f"\n[3] Unique old class names: {len(old_names)}")
    print(f"    Unique bare (global) names: {len(bare_names)}")

    # 4. Search for references to each old class name
    print(f"\n[4] Searching for references in codebase...")
    ref_results = {}
    no_refs = []
    has_refs = []

    for old_name in sorted(bare_names):
        refs = search_references(old_name)
        # Filter out the registry files themselves
        refs = [r for r in refs if "AliasRegistry" not in r["file"] and "LegacyAlias" not in r["file"]]
        if refs:
            has_refs.append(old_name)
            ref_results[old_name] = refs
        else:
            no_refs.append(old_name)

    print(f"    Old names with active references: {len(has_refs)}")
    print(f"    Old names with NO references (dead aliases): {len(no_refs)}")

    # 5. Check which old names map to new classes that actually exist
    print(f"\n[5] Checking if target new classes exist as files...")
    new_class_files = {}
    missing_targets = []

    for old, new, src in all_aliases:
        # Convert FQCN to file path
        # Linked3\Classes\Genesis\GenesisBootstrap → src/Classes/Genesis/GenesisBootstrap.php
        clean = new.replace("\\", "/")
        if clean.startswith("Linked3/"):
            clean = clean[len("Linked3/"):]
        # Try to find the file
        possible_paths = [
            REPO_ROOT / "src" / f"{clean}.php",
            REPO_ROOT / f"{clean}.php",
        ]
        found = False
        for p in possible_paths:
            if p.exists():
                found = True
                break
        if not found:
            missing_targets.append({"old": old, "new": new, "registry": src})

    print(f"    New class files found: {len(all_aliases) - len(missing_targets)}")
    print(f"    New class files MISSING: {len(missing_targets)}")

    # 6. Generate report
    report = {
        "summary": {
            "total_aliases_registered": len(all_aliases),
            "legacy_aliases": len(legacy_aliases),
            "os_aliases": len(os_aliases),
            "os_function_defined": os_func_defined,
            "unique_old_names": len(bare_names),
            "old_names_with_active_refs": len(has_refs),
            "old_names_no_refs": len(no_refs),
            "missing_new_class_targets": len(missing_targets),
        },
        "os_function_fatal_bug": not os_func_defined,
        "missing_targets": missing_targets,
        "dead_aliases_no_refs": sorted(no_refs),
        "active_references": {k: v for k, v in sorted(ref_results.items())},
        "all_aliases": [{"old": o, "new": n, "registry": s} for o, n, s in all_aliases],
    }

    # Write JSON
    report_path = REPO_ROOT / "scripts" / "coverage-report.json"
    report_path.parent.mkdir(exist_ok=True)
    report_path.write_text(json.dumps(report, indent=2, ensure_ascii=False))

    # Write Markdown summary
    md = []
    md.append("# class_alias Coverage Report\n")
    md.append(f"Generated by verify-alias-coverage.py\n")
    md.append(f"## Summary\n")
    md.append(f"| Metric | Value |\n|--------|-------|")
    md.append(f"| Total aliases registered | {len(all_aliases)} |")
    md.append(f"| LegacyAliasRegistry aliases | {len(legacy_aliases)} |")
    md.append(f"| OSAliasRegistry aliases | {len(os_aliases)} |")
    md.append(f"| OS register function defined | {'YES' if os_func_defined else '**NO — FATAL BUG**'} |")
    md.append(f"| Unique old class names | {len(bare_names)} |")
    md.append(f"| Old names with active references | {len(has_refs)} |")
    md.append(f"| Old names with NO references (dead) | {len(no_refs)} |")
    md.append(f"| Missing new class target files | {len(missing_targets)} |")
    md.append(f"\n## Critical Finding\n")
    if not os_func_defined:
        md.append(f"### FATAL BUG: `linked3_os_register_alias()` is called 35 times but NEVER DEFINED\n")
        md.append(f"All 35 OS module aliases are non-functional. Loading OSAliasRegistry.php causes fatal error.\n")
        md.append(f"**Affected old class names:**\n")
        for old, new, src in os_aliases:
            clean = new.replace("\\", "/")
            p1 = REPO_ROOT / "src" / f"{clean}.php"
            p2 = REPO_ROOT / f"{clean}.php"
            exists = p1.exists() or p2.exists()
            md.append(f"- `{old}` → `{new}` (target file: {'EXISTS' if exists else 'MISSING'})")
    md.append(f"\n## Old Names with Active Code References\n")
    md.append(f"These old class names are still referenced in the codebase and NEED working aliases:\n")
    for name in sorted(has_refs):
        refs = ref_results[name]
        total = sum(r["count"] for r in refs)
        md.append(f"- `{name}` ({total} refs in {len(refs)} files)")
    md.append(f"\n## Dead Aliases (no code references)\n")
    md.append(f"These old class names have aliases registered but are never referenced:\n")
    for name in sorted(no_refs):
        md.append(f"- `{name}`")
    if missing_targets:
        md.append(f"\n## Missing Target Class Files\n")
        md.append(f"These aliases point to new classes whose files cannot be found:\n")
        for m in missing_targets:
            md.append(f"- `{m['old']}` → `{m['new']}` (in {m['registry']})")

    md_path = REPO_ROOT / "scripts" / "coverage-report.md"
    md_path.write_text("\n".join(md))

    print(f"\n[6] Reports written:")
    print(f"    {report_path}")
    print(f"    {md_path}")

    # Print critical findings
    if not os_func_defined:
        print(f"\n{'!'*60}")
        print(f"CRITICAL: linked3_os_register_alias() function is CALLED but NEVER DEFINED")
        print(f"All 35 OS module aliases are non-functional (fatal error on load)")
        print(f"{'!'*60}")

if __name__ == "__main__":
    main()
