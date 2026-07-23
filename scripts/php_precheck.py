#!/usr/bin/env python3
"""
Linked3 AI — Local PHP Quality Pre-Check (Python)
==================================================

Since no PHP runtime is available in this environment, this script
provides local static analysis using Python regex/tokenization to
catch common issues before pushing to CI.

Checks:
  1. Syntax structure validation (balanced braces/parentheses)
  2. Type declaration coverage (params + return types)
  3. Method length (lines, PSR-12 max: 50 lines warning, 80 fail)
  4. File length (max: 500 lines)
  5. Cyclomatic complexity estimation (max: 15)
  6. Missing declare(strict_types=1)
  7. Missing namespace
  8. WordPress-style class naming (Linked3_X_Y → should be PascalCase)
  9. Direct file access guard (ABSPATH check)

Usage:
  python3 php_precheck.py                    # check all src/ files
  python3 php_precheck.py src/Classes/STT/   # check specific module
  python3 php_precheck.py --fail-on warning  # exit 1 on warnings
"""

import os
import re
import sys
import argparse
from pathlib import Path
from dataclasses import dataclass, field
from collections import defaultdict
from typing import List, Dict, Tuple


@dataclass
class Issue:
    level: str  # 'error', 'warning', 'info'
    rule: str
    message: str
    file: str
    line: int = 0


@dataclass
class FileMetrics:
    path: str
    lines: int = 0
    classes: int = 0
    methods: int = 0
    typed_params: int = 0
    untyped_params: int = 0
    typed_returns: int = 0
    untyped_returns: int = 0
    max_complexity: int = 0
    max_method_length: int = 0
    has_strict_types: bool = False
    has_namespace: bool = False
    has_abspath_guard: bool = False
    issues: List[Issue] = field(default_factory=list)


class PHPChecker:
    """Local PHP quality checker using regex-based static analysis."""

    # Patterns
    RE_NAMESPACE = re.compile(r'^namespace\s+([\w\\]+)\s*;', re.MULTILINE)
    RE_CLASS = re.compile(
        r'(?:final\s+|abstract\s+)?(?:class|trait|interface)\s+(\w+)', re.MULTILINE
    )
    RE_METHOD = re.compile(
        r'(?:public|protected|private)\s+(?:static\s+)?'
        r'(?:function)\s+(\w+)\s*\(([^)]*)\)'
        r'(?:\s*:\s*([^\s{]+))?',
        re.MULTILINE
    )
    RE_STRICT_TYPES = re.compile(r'declare\s*\(\s*strict_types\s*=\s*1\s*\)')
    RE_ABSPATH = re.compile(r"defined\s*\(\s*['\"]ABSPATH['\"]\s*\)")
    RE_WORDPRESS_CLASS = re.compile(r'class\s+(Linked3_\w+)')
    RE_CONTROL_FLOW = re.compile(
        r'\b(?:if|elseif|else|for|foreach|while|do|switch|case|'
        r'catch|and|or|xor|&&|\|\|)\b'
    )

    def __init__(self, project_root: str):
        self.root = Path(project_root)
        self.metrics: List[FileMetrics] = []
        self.all_issues: List[Issue] = []

    def check_file(self, filepath: Path) -> FileMetrics:
        """Run all checks on a single PHP file."""
        try:
            content = filepath.read_text(encoding='utf-8', errors='replace')
        except Exception as e:
            m = FileMetrics(path=str(filepath))
            m.issues.append(Issue('error', 'read', f'Cannot read file: {e}', str(filepath)))
            return m

        lines = content.split('\n')
        m = FileMetrics(
            path=str(filepath.relative_to(self.root)) if filepath.is_relative_to(self.root) else str(filepath),
            lines=len(lines),
        )

        # ── Check 1: Basic syntax structure ──
        open_braces = content.count('{')
        close_braces = content.count('}')
        if open_braces != close_braces:
            m.issues.append(Issue(
                'error', 'syntax',
                f'Unbalanced braces: {open_braces} open vs {close_braces} close',
                m.path
            ))

        open_parens = content.count('(')
        close_parens = content.count(')')
        if open_parens != close_parens:
            m.issues.append(Issue(
                'warning', 'syntax',
                f'Unbalanced parentheses: {open_parens} open vs {close_parens} close',
                m.path
            ))

        # ── Check 2: declare(strict_types=1) ──
        m.has_strict_types = bool(self.RE_STRICT_TYPES.search(content))
        if not m.has_strict_types:
            m.issues.append(Issue(
                'warning', 'strict_types',
                'Missing declare(strict_types=1)',
                m.path
            ))

        # ── Check 3: Namespace ──
        ns_match = self.RE_NAMESPACE.search(content)
        m.has_namespace = bool(ns_match)
        if not m.has_namespace and 'src/' in m.path:
            m.issues.append(Issue(
                'warning', 'namespace',
                'Missing namespace declaration',
                m.path
            ))

        # ── Check 4: ABSPATH guard ──
        m.has_abspath_guard = bool(self.RE_ABSPATH.search(content))
        if not m.has_abspath_guard and 'src/' in m.path:
            m.issues.append(Issue(
                'info', 'security',
                'Missing ABSPATH direct-access guard',
                m.path
            ))

        # ── Check 5: WordPress-style class naming ──
        for match in self.RE_WORDPRESS_CLASS.finditer(content):
            class_name = match.group(1)
            if '_' in class_name:
                m.issues.append(Issue(
                    'warning', 'psr4-naming',
                    f'Class uses underscore naming: {class_name} → should be PascalCase',
                    m.path,
                    content[:match.start()].count('\n') + 1
                ))
            m.classes += 1

        # ── Check 6: File length ──
        if m.lines > 500:
            m.issues.append(Issue(
                'error', 'file-length',
                f'File too long: {m.lines} lines (max 500)',
                m.path
            ))
        elif m.lines > 300:
            m.issues.append(Issue(
                'warning', 'file-length',
                f'File approaching limit: {m.lines} lines (warn at 300, max 500)',
                m.path
            ))

        # ── Check 7: Method analysis ──
        for match in self.RE_METHOD.finditer(content):
            method_name = match.group(1)
            params_str = match.group(2).strip()
            return_type = match.group(3)

            m.methods += 1

            # Analyze parameters
            if params_str:
                params = self._split_params(params_str)
                for param in params:
                    param = param.strip()
                    if not param or param.startswith('//'):
                        continue
                    # Skip variadic and default-only params
                    if re.match(r'^\s*(\.\.\.|&)', param):
                        continue
                    # Check if param has type hint
                    # Type hint patterns: int $x, ?string $x, array $x, ClassName $x
                    if re.match(r'^\s*\??[\w\\]+\s+\$|\^\s*\??[\w\\]+\s+\$', param):
                        m.typed_params += 1
                    elif '$' in param:
                        m.untyped_params += 1

            # Check return type
            if return_type and return_type.strip():
                m.typed_returns += 1
            else:
                m.untyped_returns += 1

            # Method length
            method_start_line = content[:match.start()].count('\n') + 1
            method_body = self._extract_method_body(content, match.end())
            method_lines = method_body.count('\n')
            if method_lines > m.max_method_length:
                m.max_method_length = method_lines
            if method_lines > 80:
                m.issues.append(Issue(
                    'error', 'method-length',
                    f'Method {method_name}() is {method_lines} lines long (max 80)',
                    m.path, method_start_line
                ))
            elif method_lines > 50:
                m.issues.append(Issue(
                    'warning', 'method-length',
                    f'Method {method_name}() is {method_lines} lines long (warn at 50)',
                    m.path, method_start_line
                ))

            # Cyclomatic complexity
            complexity = self._estimate_complexity(method_body)
            if complexity > m.max_complexity:
                m.max_complexity = complexity
            if complexity > 15:
                m.issues.append(Issue(
                    'error', 'complexity',
                    f'Method {method_name}() complexity={complexity} (max 15)',
                    m.path, method_start_line
                ))
            elif complexity > 10:
                m.issues.append(Issue(
                    'warning', 'complexity',
                    f'Method {method_name}() complexity={complexity} (warn at 10)',
                    m.path, method_start_line
                ))

        # ── Check 8: Type coverage summary ──
        total_params = m.typed_params + m.untyped_params
        if total_params > 0:
            coverage = (m.typed_params / total_params) * 100
            if coverage < 100:
                m.issues.append(Issue(
                    'warning', 'type-coverage',
                    f'Param type coverage: {m.typed_params}/{total_params} ({coverage:.0f}%)',
                    m.path
                ))

        total_returns = m.typed_returns + m.untyped_returns
        if total_returns > 0:
            coverage = (m.typed_returns / total_returns) * 100
            if coverage < 100:
                m.issues.append(Issue(
                    'warning', 'type-coverage',
                    f'Return type coverage: {m.typed_returns}/{total_returns} ({coverage:.0f}%)',
                    m.path
                ))

        self.metrics.append(m)
        self.all_issues.extend(m.issues)
        return m

    def _split_params(self, params_str: str) -> List[str]:
        """Split parameter string by commas, respecting nesting."""
        result = []
        depth = 0
        current = ''
        for char in params_str:
            if char in '([{':
                depth += 1
            elif char in ')]}':
                depth -= 1
            if char == ',' and depth == 0:
                result.append(current)
                current = ''
            else:
                current += char
        if current.strip():
            result.append(current)
        return result

    def _extract_method_body(self, content: str, start_pos: int) -> str:
        """Extract method body between { and matching }."""
        brace_start = content.find('{', start_pos)
        if brace_start == -1:
            return ''
        depth = 0
        i = brace_start
        while i < len(content):
            if content[i] == '{':
                depth += 1
            elif content[i] == '}':
                depth -= 1
                if depth == 0:
                    return content[brace_start:i+1]
            i += 1
        return content[brace_start:]

    def _estimate_complexity(self, body: str) -> int:
        """Estimate cyclomatic complexity of a method body."""
        complexity = 1  # Base
        complexity += len(self.RE_CONTROL_FLOW.findall(body))
        return complexity

    def check_directory(self, directory: str, exclude: List[str] = None) -> List[FileMetrics]:
        """Check all PHP files in a directory."""
        dir_path = self.root / directory if not Path(directory).is_absolute() else Path(directory)
        exclude = exclude or ['vendor', 'node_modules', 'assets', 'languages', 'docs']

        if not dir_path.exists():
            print(f"Directory not found: {dir_path}")
            return []

        for php_file in sorted(dir_path.rglob('*.php')):
            # Check exclusions
            if any(ex in str(php_file) for ex in exclude):
                continue
            self.check_file(php_file)

        return self.metrics

    def generate_report(self) -> str:
        """Generate a summary report."""
        errors = [i for i in self.all_issues if i.level == 'error']
        warnings = [i for i in self.all_issues if i.level == 'warning']
        infos = [i for i in self.all_issues if i.level == 'info']

        total_params = sum(m.typed_params for m in self.metrics)
        total_untyped = sum(m.untyped_params for m in self.metrics)
        total_returns = sum(m.typed_returns for m in self.metrics)
        total_untyped_returns = sum(m.untyped_returns for m in self.metrics)

        report = [
            "=" * 70,
            "Linked3 AI — Local PHP Quality Pre-Check Report",
            "=" * 70,
            f"Files checked: {len(self.metrics)}",
            f"Total lines: {sum(m.lines for m in self.metrics):,}",
            f"Classes: {sum(m.classes for m in self.metrics)}",
            f"Methods: {sum(m.methods for m in self.metrics)}",
            "",
            f"Errors:   {len(errors)}",
            f"Warnings: {len(warnings)}",
            f"Info:     {len(infos)}",
            "",
            "── Type Coverage ──",
        ]

        if total_params + total_untyped > 0:
            pct = (total_params / (total_params + total_untyped)) * 100
            report.append(f"  Params:  {total_params}/{total_params + total_untyped} ({pct:.1f}%)")
        if total_returns + total_untyped_returns > 0:
            pct = (total_returns / (total_returns + total_untyped_returns)) * 100
            report.append(f"  Returns: {total_returns}/{total_returns + total_untyped_returns} ({pct:.1f}%)")

        report.append("")
        report.append("── Files Needing Attention (sorted by issue count) ──")

        # Sort by issue count
        sorted_metrics = sorted(self.metrics, key=lambda m: len(m.issues), reverse=True)
        for m in sorted_metrics[:30]:
            if m.issues:
                err_count = sum(1 for i in m.issues if i.level == 'error')
                warn_count = sum(1 for i in m.issues if i.level == 'warning')
                report.append(f"  {m.path} ({m.lines} lines, {m.methods} methods) "
                              f"[E:{err_count} W:{warn_count}]")

        # List files over 300 lines
        big_files = [m for m in self.metrics if m.lines > 300]
        if big_files:
            report.append("")
            report.append("── Files Over 300 Lines ──")
            for m in sorted(big_files, key=lambda m: m.lines, reverse=True)[:20]:
                report.append(f"  {m.lines:>5} lines — {m.path}")

        # List errors
        if errors:
            report.append("")
            report.append("── Errors (must fix) ──")
            for issue in errors[:50]:
                line_info = f":{issue.line}" if issue.line else ""
                report.append(f"  [{issue.rule}] {issue.file}{line_info} — {issue.message}")

        if len(errors) > 50:
            report.append(f"  ... and {len(errors) - 50} more errors")

        report.append("")
        report.append("=" * 70)

        return '\n'.join(report)


def main():
    parser = argparse.ArgumentParser(description='Local PHP Quality Pre-Check')
    parser.add_argument('path', nargs='?', default='src/', help='Path to check')
    parser.add_argument('--project-root', default='.', help='Project root')
    parser.add_argument('--fail-on', choices=['error', 'warning'], default='error',
                        help='Exit code 1 if issues at this level exist')
    args = parser.parse_args()

    checker = PHPChecker(args.project_root)
    checker.check_directory(args.path)
    report = checker.generate_report()
    print(report)

    # Exit code
    if args.fail_on == 'error' and any(i.level == 'error' for i in checker.all_issues):
        sys.exit(1)
    elif args.fail_on == 'warning' and any(i.level in ('error', 'warning') for i in checker.all_issues):
        sys.exit(1)
    sys.exit(0)


if __name__ == '__main__':
    main()
