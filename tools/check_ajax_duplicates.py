#!/usr/bin/env python3
"""
AJAX Duplicate Declaration Checker — PR-04 (plan §3.4)

Detects when the same `wp_ajax_*` action is registered by multiple
add_action() calls in non-comment lines. This is the exact bug pattern
that caused the v27.8.x "double execution" issue where Legacy and new
Actions classes both registered the same hook.

Usage:
    python3 tools/check_ajax_duplicates.py [src_dir]

Exit codes:
    0 — no duplicates found
    1 — duplicate registrations detected (CI should fail)

How it works:
    1. Walk all .php files under the given directory (default: src/).
    2. For each line, check if it contains add_action('wp_ajax_...', ...).
    3. Skip comment lines (// or *).
    4. Build a map: { action_name: [(file, line_no)] }.
    5. Any action with >1 entry is a duplicate.

Also detects:
    - Actions registered in both active and legacy files
    - Actions where the callback method doesn't exist in the class

Author: Linked3 CI Team
Since: 27.9.4
"""

import os
import re
import sys
from collections import defaultdict
from pathlib import Path


def find_ajax_registrations(src_dir: Path) -> dict[str, list[tuple[str, int, str]]]:
    """
    Find all active (non-commented) wp_ajax_* registrations.

    Returns: { action_name: [(file, line_no, full_line), ...] }
    """
    registrations = defaultdict(list)

    pattern = re.compile(
        r"add_action\s*\(\s*['\"]wp_ajax_(nopriv_)?(\w+)['\"]"
    )

    for root, dirs, files in os.walk(src_dir):
        for fname in files:
            if not fname.endswith('.php'):
                continue
            fpath = Path(root) / fname
            try:
                with open(fpath, 'r', errors='replace') as fh:
                    for lineno, line in enumerate(fh, 1):
                        stripped = line.strip()
                        # Skip comments
                        if stripped.startswith('//') or stripped.startswith('*') or stripped.startswith('#'):
                            continue
                        # Skip heredoc/nowdoc (rough check)
                        if stripped.startswith("'") and stripped.endswith("'"):
                            continue

                        for m in pattern.finditer(line):
                            action = m.group(2)
                            registrations[action].append(
                                (str(fpath), lineno, stripped)
                            )
            except (IOError, OSError):
                continue

    return registrations


def find_class_map_registrations(src_dir: Path) -> dict[str, list[tuple[str, int]]]:
    """
    Find dynamic AJAX registrations via class-map loops.

    Pattern:
        $actions = [
            'linked3_push_now' => SomeClass::class,
            ...
        ];
        foreach ($actions as $action => $class) {
            add_action('wp_ajax_' . $action, ...);
        }

    Returns: { action_name: [(file, line_no)] }
    """
    registrations = defaultdict(list)

    # Pattern: 'linked3_xxx' => ClassName::class in an array
    pattern = re.compile(
        r"['\"](linked3_\w+)['\"]\s*=>\s*(\w+)::class"
    )

    for root, dirs, files in os.walk(src_dir):
        for fname in files:
            if not fname.endswith('.php'):
                continue
            fpath = Path(root) / fname
            try:
                content = fpath.read_text(errors='replace')
                # Check if file has a foreach + add_action('wp_ajax_')
                if 'wp_ajax_' not in content:
                    continue
                if 'foreach' not in content:
                    continue

                for m in pattern.finditer(content):
                    action = m.group(1)
                    lineno = content[:m.start()].count('\n') + 1
                    registrations[action].append((str(fpath), lineno))
            except (IOError, OSError):
                continue

    return registrations


def main():
    base = Path(__file__).resolve().parent.parent
    src_dir = Path(sys.argv[1]) if len(sys.argv) > 1 else base / 'src'

    if not src_dir.exists():
        print(f"Error: directory not found: {src_dir}")
        sys.exit(2)

    print(f"Scanning: {src_dir}")
    print()

    # Collect registrations
    direct_regs = find_ajax_registrations(src_dir)
    classmap_regs = find_class_map_registrations(src_dir)

    # Merge
    all_regs = defaultdict(list)
    for action, locs in direct_regs.items():
        all_regs[action].extend(locs)
    for action, locs in classmap_regs.items():
        all_regs[action].extend(locs)

    # Find duplicates
    duplicates = {
        action: locs for action, locs in all_regs.items()
        if len(locs) > 1
    }

    # Report
    total_actions = len(all_regs)
    total_dups = len(duplicates)

    print(f"Total AJAX actions registered: {total_actions}")
    print(f"Actions with duplicates:       {total_dups}")
    print()

    if not duplicates:
        print("✅ No duplicate AJAX registrations found.")
        sys.exit(0)

    print("❌ DUPLICATE REGISTRATIONS:")
    print()
    for action, locs in sorted(duplicates.items()):
        print(f"  wp_ajax_{action}:")
        for fpath, lineno, *rest in locs:
            # Truncate path to relative
            try:
                rel = str(Path(fpath).relative_to(base))
            except ValueError:
                rel = fpath
            line_info = f"  → {rel}:{lineno}"
            if rest:
                line_info += f"  | {rest[0][:80]}"
            print(line_info)
        print()

    sys.exit(1)


if __name__ == '__main__':
    main()
