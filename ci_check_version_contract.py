#!/usr/bin/env python3
"""
ci_check_version_contract.py — Verify version alignment across the plugin.

Checks that version numbers are consistent in:
  1. Plugin header (linked3.php: "Version: X.Y.Z")
  2. PHP constant (linked3.php: define('LINKED3_VERSION', 'X.Y.Z'))
  3. composer.json ("version": "X.Y.Z")
  4. PHP requirement (linked3.php header + guard)
"""

import json
import re
import sys
from pathlib import Path

EXPECTED_VERSION = "29.0.0"
EXPECTED_PHP = "8.0"


def check():
    root = Path(__file__).parent
    errors = []

    # --- 1. Plugin header ---
    plugin_file = root / "linked3.php"
    content = plugin_file.read_text()

    header_match = re.search(r'^\s*\*\s*Version:\s*(\S+)', content, re.MULTILINE)
    header_version = header_match.group(1) if header_match else "(not found)"

    php_header_match = re.search(r'^\s*\*\s*Requires PHP:\s*(\S+)', content, re.MULTILINE)
    php_header = php_header_match.group(1) if php_header_match else "(not found)"

    # --- 2. PHP constant ---
    const_match = re.search(r"define\(\s*['\"]LINKED3_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)", content)
    const_version = const_match.group(1) if const_match else "(not found)"

    # --- 3. PHP guard ---
    guard_match = re.search(r"version_compare\(\s*PHP_VERSION\s*,\s*['\"]([^'\"]+)['\"]", content)
    php_guard = guard_match.group(1) if guard_match else "(not found)"

    # --- 4. composer.json ---
    composer_file = root / "composer.json"
    composer = json.loads(composer_file.read_text())
    composer_version = composer.get("version", "(missing)")
    composer_php = composer.get("require", {}).get("php", "(missing)")

    # --- Report ---
    print(f"Plugin header:    {header_version}")
    print(f"LINKED3_VERSION:  {const_version}")
    print(f"composer.json:    {composer_version}")
    print(f"PHP (header):     {php_header}")
    print(f"PHP (composer):   {composer_php}")
    print(f"PHP (guard):      {php_guard}")

    # --- Assertions ---
    checks = [
        (header_version == EXPECTED_VERSION, f"Plugin header version: expected {EXPECTED_VERSION}, got {header_version}"),
        (const_version == EXPECTED_VERSION, f"LINKED3_VERSION constant: expected {EXPECTED_VERSION}, got {const_version}"),
        (composer_version == EXPECTED_VERSION, f"composer.json version: expected {EXPECTED_VERSION}, got {composer_version}"),
        (php_header == EXPECTED_PHP, f"PHP header requirement: expected {EXPECTED_PHP}, got {php_header}"),
        (EXPECTED_PHP in composer_php, f"composer.json PHP requirement: expected >={EXPECTED_PHP}, got {composer_php}"),
        (php_guard == EXPECTED_PHP + ".0", f"PHP guard: expected {EXPECTED_PHP}.0, got {php_guard}"),
    ]

    for ok, msg in checks:
        if not ok:
            errors.append(msg)

    if errors:
        print("\nFAIL: Version contract violations:")
        for e in errors:
            print(f"  ✗ {e}")
        sys.exit(1)

    print("\nPASS: All version contracts aligned")
    sys.exit(0)


if __name__ == "__main__":
    check()
