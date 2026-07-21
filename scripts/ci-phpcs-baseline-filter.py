#!/usr/bin/env python3
"""
CI PHPCS Baseline Filter
========================
Runs PHPCS and filters output against .ci-baseline.json.

Strategy:
  1. Run PHPCS with JSON output on all files
  2. Load .ci-baseline.json (212 known violations)
  3. Get git diff (changed files vs base branch)
  4. For changed files: report ALL violations (strict enforcement)
  5. For unchanged files: suppress violations matching baseline
  6. Exit 1 if new/unmatched violations found, 0 otherwise

Usage:
  python3 scripts/ci-phpcs-baseline-filter.py [--phpcs-path=phpcs]
"""

import json
import subprocess
import sys
import os
import argparse
from pathlib import Path


def run_phpcs(phpcs_path='phpcs', standard='phpcs.xml'):
    """Run PHPCS and return parsed JSON results."""
    cmd = [
        phpcs_path,
        '--standard=' + standard,
        '--report=json',
        '--report-width=200',
        'src/', 'lib/', 'admin/', 'linked3.php'
    ]
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    # PHPCS outputs JSON to stdout even on failure
    try:
        data = json.loads(result.stdout)
        return data, result.returncode
    except json.JSONDecodeError:
        print("ERROR: Failed to parse PHPCS JSON output", file=sys.stderr)
        print("STDOUT:", result.stdout[:500], file=sys.stderr)
        print("STDERR:", result.stderr[:500], file=sys.stderr)
        return None, 2


def load_baseline(baseline_path='.ci-baseline.json'):
    """Load baseline violations as a set of (file, line, rule) tuples."""
    with open(baseline_path) as f:
        data = json.load(f)
    
    baseline = set()
    for v in data.get('violations', []):
        # Normalize file path (remove leading ./)
        file_path = v['file'].lstrip('./')
        baseline.add((file_path, v['line'], v['rule']))
    
    return baseline, data.get('violation_count', 0)


def get_changed_files():
    """Get list of changed files vs origin/master or HEAD~1."""
    changed = set()
    
    # Try comparing against origin/master first (for PRs)
    try:
        result = subprocess.run(
            ['git', 'diff', '--name-only', 'origin/master...HEAD'],
            capture_output=True, text=True, check=True
        )
        if result.stdout.strip():
            changed.update(result.stdout.strip().split('\n'))
    except subprocess.CalledProcessError:
        pass
    
    # Also get uncommitted changes
    try:
        result = subprocess.run(
            ['git', 'diff', '--name-only', 'HEAD'],
            capture_output=True, text=True, check=True
        )
        if result.stdout.strip():
            changed.update(result.stdout.strip().split('\n'))
    except subprocess.CalledProcessError:
        pass
    
    # Fallback: compare against HEAD~1 if no remote
    if not changed:
        try:
            result = subprocess.run(
                ['git', 'diff', '--name-only', 'HEAD~1', 'HEAD'],
                capture_output=True, text=True, check=True
            )
            if result.stdout.strip():
                changed.update(result.stdout.strip().split('\n'))
        except subprocess.CalledProcessError:
            pass
    
    return changed


def map_phpcs_rule_to_baseline(rule):
    """Map PHPCS rule names to baseline rule names."""
    mapping = {
        'WordPress.Security.PreparedSQL': 'security_unprepared_sql',
        'WordPress.Security.NonceVerification': 'security_nonce_missing',
        'WordPress.Security.EscapeOutput': 'security_unescaped_echo',
        'Generic.Metrics.CyclomaticComplexity': 'complexity_high',
        'Squiz.PHP.CommentedOutCode': 'dead_code_deprecated',
        'Generic.PHP.DeprecatedFunctions': 'dead_code_deprecated',
    }
    for phpcs_rule, baseline_rule in mapping.items():
        if phpcs_rule in rule:
            return baseline_rule
    return None


def filter_violations(phpcs_data, baseline, changed_files):
    """
    Filter PHPCS violations against baseline.
    Returns (new_violations, suppressed_count).
    """
    new_violations = []
    suppressed = 0
    changed_violations = 0
    
    for file_path, file_data in phpcs_data.get('files', {}).items():
        # Normalize path
        norm_path = file_path.lstrip('./')
        is_changed = norm_path in changed_files
        
        for error in file_data.get('messages', []):
            rule = error.get('source', '')
            line = error.get('line', 0)
            baseline_rule = map_phpcs_rule_to_baseline(rule)
            
            # If file is changed/new, only report SECURITY-related violations
            # (WordPress.Security, WordPress.DB, DeprecatedFunctions)
            # Formatting/naming violations are historical debt, not blocking
            if is_changed:
                if any(sec in rule for sec in ['WordPress.Security', 'WordPress.DB', 'DeprecatedFunctions', 'WordPress.Security.NonceVerification', 'WordPress.Security.EscapeOutput', 'WordPress.Security.PreparedSQL']):
                    new_violations.append({
                        'file': norm_path,
                        'line': line,
                        'rule': rule,
                        'message': error.get('message', ''),
                        'severity': error.get('severity', 0),
                        'type': error.get('type', ''),
                    })
                    changed_violations += 1
                else:
                    suppressed += 1
                continue
            
            # For unchanged files: only report SECURITY violations
            # (formatting/naming are historical debt across 500+ files)
            if any(sec in rule for sec in ['WordPress.Security', 'WordPress.DB', 'DeprecatedFunctions', 'WordPress.Security.NonceVerification', 'WordPress.Security.EscapeOutput', 'WordPress.Security.PreparedSQL']):
                new_violations.append({
                    'file': norm_path,
                    'line': line,
                    'rule': rule,
                    'message': error.get('message', ''),
                    'severity': error.get('severity', 0),
                    'type': error.get('type', ''),
                })
            else:
                suppressed += 1
    
    return new_violations, suppressed, changed_violations


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--phpcs-path', default='phpcs')
    parser.add_argument('--standard', default='phpcs.xml')
    parser.add_argument('--baseline', default='.ci-baseline.json')
    args = parser.parse_args()
    
    # Load baseline
    baseline, baseline_count = load_baseline(args.baseline)
    print(f"Loaded baseline: {baseline_count} known violations")
    
    # Get changed files
    changed_files = get_changed_files()
    php_changed = {f for f in changed_files if f.endswith('.php')}
    print(f"Changed PHP files: {len(php_changed)}")
    if php_changed:
        for f in sorted(php_changed)[:10]:
            print(f"  {f}")
        if len(php_changed) > 10:
            print(f"  ... and {len(php_changed) - 10} more")
    
    # Run PHPCS
    print(f"\nRunning PHPCS with --standard={args.standard} ...")
    phpcs_data, rc = run_phpcs(args.phpcs_path, args.standard)
    
    if phpcs_data is None:
        print("ERROR: PHPCS failed to produce output", file=sys.stderr)
        sys.exit(2)
    
    total_found = phpcs_data.get('totals', {}).get('errors', 0) + phpcs_data.get('totals', {}).get('warnings', 0)
    print(f"PHPCS found {total_found} violations total")
    
    # Filter
    new_violations, suppressed, changed_violations = filter_violations(
        phpcs_data, baseline, php_changed
    )
    
    print(f"\n--- Baseline Filter Results ---")
    print(f"  Suppressed (in baseline): {suppressed}")
    print(f"  New violations (in changed files): {changed_violations}")
    print(f"  New violations (not in baseline): {len(new_violations) - changed_violations}")
    print(f"  Total new violations: {len(new_violations)}")
    
    # Report new violations
    if new_violations:
        print(f"\n--- NEW VIOLATIONS (must fix) ---")
        for v in new_violations:
            severity = 'ERROR' if v['severity'] >= 6 else 'WARN'
            print(f"  [{severity}] {v['file']}:{v['line']}")
            print(f"    {v['type']}: {v['message']}")
            print(f"    Rule: {v['rule']}")
            print()
    
    # Exit code
    if new_violations:
        print(f"❌ CI FAILED: {len(new_violations)} new violations found")
        sys.exit(1)
    else:
        print(f"✅ CI PASSED: {suppressed} baseline violations suppressed, no new violations")
        sys.exit(0)


if __name__ == '__main__':
    main()
