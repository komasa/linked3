#!/usr/bin/env python3
"""
PSR-4 Migration Script for Linked3 AI Codebase
================================================

Migrates WordPress-style class naming to PSR-4 compliant naming:
  - File: class-linked3-stt-manager.php → SttManager.php
  - Class: Linked3_STT_Manager → SttManager (namespace provides prefix)
  - Updates all references across the codebase

Usage:
  python3 migrate-psr4.py --module src/Classes/STT           # migrate one module
  python3 migrate-psr4.py --module src/Classes/STT --dry-run # preview changes
  python3 migrate-psr4.py --all --dry-run                    # preview all
"""

import os
import re
import sys
import argparse
from pathlib import Path
from collections import defaultdict

class PSR4Migrator:
    def __init__(self, project_root: str, dry_run: bool = False):
        self.root = Path(project_root)
        self.dry_run = dry_run
        self.changes = []
        self.references_updated = 0
        
    def extract_namespace(self, filepath: Path) -> str | None:
        """Extract namespace from a PHP file."""
        try:
            content = filepath.read_text(encoding='utf-8', errors='replace')
        except Exception:
            return None
        match = re.search(r'^namespace\s+([\w\\]+)\s*;', content, re.MULTILINE)
        return match.group(1) if match else None
    
    def extract_class_name(self, content: str) -> str | None:
        """Extract the primary class/trait/interface name from PHP content.
        
        Strips comments (// and /* */ and #) before matching to avoid
        false positives like matching "trait so" in a comment.
        """
        # Strip /* ... */ block comments
        content_clean = re.sub(r'/\*.*?\*/', '', content, flags=re.DOTALL)
        # Strip // line comments and # line comments
        content_clean = re.sub(r'(?://|#).*?$', '', content_clean, flags=re.MULTILINE)
        
        # Match: final class, abstract class, class, final trait, trait, interface
        patterns = [
            r'(?:final\s+|abstract\s+)?class\s+(\w+)',
            r'(?:final\s+)?trait\s+(\w+)',
            r'interface\s+(\w+)',
        ]
        for pattern in patterns:
            match = re.search(pattern, content_clean)
            if match:
                return match.group(1)
        return None
    
    def class_to_pascalcase(self, class_name: str) -> str:
        """
        Convert Linked3_STT_Manager → SttManager
        Keep namespace prefix separate.
        """
        # Remove Linked3_ prefix if present
        if class_name.startswith('Linked3_'):
            name = class_name[len('Linked3_'):]
        else:
            name = class_name
        
        # Convert STT_Manager → SttManager
        # NOTE: Use part[0].upper() + part[1:] instead of part.capitalize()
        # because capitalize() lowercases all chars after the first, which
        # breaks existing PascalCase segments (e.g. "RateLimiter" → "Ratelimiter")
        parts = name.split('_')
        result = ''.join(
            (part[0].upper() + part[1:]) if part else part
            for part in parts
        )
        return result
    
    def class_to_filename(self, pascal_name: str, symbol_type: str = 'class') -> str:
        """Convert PascalCase class name to PSR-4 filename."""
        if symbol_type == 'trait':
            return f"{pascal_name}.php"  # PSR-4 doesn't use trait- prefix
        elif symbol_type == 'interface':
            return f"{pascal_name}.php"
        else:
            return f"{pascal_name}.php"
    
    def detect_symbol_type(self, content: str) -> str:
        """Detect if file contains class, trait, or interface."""
        if re.search(r'\btrait\s+\w+', content):
            return 'trait'
        if re.search(r'\binterface\s+\w+', content):
            return 'interface'
        return 'class'
    
    def find_all_php_files(self, directory: Path) -> list[Path]:
        """Find all PHP files in a directory."""
        return sorted(directory.rglob('*.php'))
    
    def find_all_config_files(self, directory: Path) -> list[Path]:
        """Find all YAML/JSON config files that may contain class name references."""
        files = []
        for pattern in ['*.yaml', '*.yml', '*.json']:
            files.extend(directory.rglob(pattern))
        return sorted(files)
    
    def find_references(self, old_class: str, search_root: Path) -> list[Path]:
        """Find all files that reference the old class name.
        
        Searches PHP files + YAML/JSON config files across the entire project root
        (covers src/, admin/, lib/, root files). Skips dependency/VCS/build dirs.
        YAML/JSON are included because they often contain class name strings
        used for dynamic dispatch (e.g. handler: "Linked3_Foo::method").
        """
        references = []
        skip_dirs = {'vendor', 'node_modules', '.git', '.svn', 'cache', 'tmp',
                     'dist', 'build', 'uploads'}
        skip_suffixes = {'.min.js', '.min.css', '.map'}

        # Search PHP files + config files (YAML/JSON)
        search_files = self.find_all_php_files(search_root) + \
                        self.find_all_config_files(search_root)

        for filepath in search_files:
            # Skip files inside dependency/VCS/build directories
            if any(part in skip_dirs for part in filepath.parts):
                continue
            # Skip minified/compressed assets
            if any(filepath.name.endswith(suffix) for suffix in skip_suffixes):
                continue
            try:
                content = filepath.read_text(encoding='utf-8', errors='replace')
                if old_class in content:
                    references.append(filepath)
            except Exception:
                continue
        return references
    
    def migrate_file(self, filepath: Path) -> dict:
        """
        Migrate a single PHP file to PSR-4 naming.
        Returns migration info.
        """
        try:
            content = filepath.read_text(encoding='utf-8', errors='replace')
        except Exception as e:
            return {'error': str(e), 'file': str(filepath)}
        
        old_class = self.extract_class_name(content)
        if not old_class:
            return {'skipped': True, 'reason': 'No class found', 'file': str(filepath)}
        
        # Skip if already PascalCase (no underscores)
        if '_' not in old_class:
            return {'skipped': True, 'reason': 'Already PSR-4 compliant', 'file': str(filepath)}
        
        new_class = self.class_to_pascalcase(old_class)
        symbol_type = self.detect_symbol_type(content)
        new_filename = self.class_to_filename(new_class, symbol_type)
        new_filepath = filepath.parent / new_filename
        
        namespace = self.extract_namespace(filepath)
        if not namespace:
            return {'skipped': True, 'reason': 'No namespace found', 'file': str(filepath)}
        
        # Find all references to the old class name across the entire project
        search_root = self.root
        references = self.find_references(old_class, search_root)
        
        migration_info = {
            'file': str(filepath),
            'old_class': old_class,
            'new_class': new_class,
            'old_filename': filepath.name,
            'new_filename': new_filename,
            'namespace': namespace,
            'symbol_type': symbol_type,
            'references': [str(r) for r in references],
            'new_filepath': str(new_filepath),
        }
        
        if self.dry_run:
            return migration_info
        
        # ── Execute migration ──
        
        # 1. Update class name in the file itself
        new_content = content.replace(old_class, new_class)
        
        # 2. Add declare(strict_types=1) if not present
        if 'declare(strict_types=1)' not in new_content:
            # Insert after <?php and optional file comment
            new_content = re.sub(
                r'^(<\?php\s*\n)',
                r'\1\ndeclare(strict_types=1);\n',
                new_content,
                count=1
            )
        
        # 3. Write new file
        new_filepath.write_text(new_content, encoding='utf-8')
        
        # 4. Delete old file
        if filepath != new_filepath:
            filepath.unlink()
        
        # 5. Update all references
        for ref_file in references:
            try:
                ref_content = ref_file.read_text(encoding='utf-8', errors='replace')
                ref_content = ref_content.replace(old_class, new_class)
                ref_file.write_text(ref_content, encoding='utf-8')
                self.references_updated += 1
            except Exception:
                continue
        
        self.changes.append(migration_info)
        return migration_info
    
    def migrate_module(self, module_path: str) -> list[dict]:
        """Migrate all files in a module directory."""
        module = self.root / module_path
        if not module.exists():
            print(f"Module not found: {module}")
            return []
        
        results = []
        for php_file in self.find_all_php_files(module):
            result = self.migrate_file(php_file)
            results.append(result)
        
        return results
    
    def generate_report(self, results: list[dict]) -> str:
        """Generate a migration report."""
        migrated = [r for r in results if not r.get('skipped') and not r.get('error')]
        skipped = [r for r in results if r.get('skipped')]
        errors = [r for r in results if r.get('error')]
        
        report = [
            "# PSR-4 Migration Report",
            f"Mode: {'DRY RUN' if self.dry_run else 'EXECUTED'}",
            f"Files processed: {len(results)}",
            f"Files migrated: {len(migrated)}",
            f"Files skipped: {len(skipped)}",
            f"Errors: {len(errors)}",
            f"References updated: {self.references_updated}",
            "",
        ]
        
        if migrated:
            report.append("## Migrated Files")
            for m in migrated:
                report.append(
                    f"- `{m['old_class']}` → `{m['new_class']}` "
                    f"({m['old_filename']} → {m['new_filename']}) "
                    f"[{len(m.get('references', []))} refs]"
                )
            report.append("")
        
        if skipped:
            report.append("## Skipped Files")
            for s in skipped:
                report.append(f"- {s['file']}: {s.get('reason', 'unknown')}")
            report.append("")
        
        if errors:
            report.append("## Errors")
            for e in errors:
                report.append(f"- {e['file']}: {e['error']}")
        
        return '\n'.join(report)


def main():
    parser = argparse.ArgumentParser(description='PSR-4 Migration Tool')
    parser.add_argument('--module', help='Module directory to migrate (e.g., src/Classes/STT)')
    parser.add_argument('--all', action='store_true', help='Migrate all modules')
    parser.add_argument('--dry-run', action='store_true', help='Preview without changes')
    parser.add_argument('--project-root', default='.', help='Project root directory')
    
    args = parser.parse_args()
    
    migrator = PSR4Migrator(args.project_root, dry_run=args.dry_run)
    
    if args.module:
        results = migrator.migrate_module(args.module)
    elif args.all:
        # Migrate all modules in src/Classes/
        classes_dir = Path(args.project_root) / 'src' / 'Classes'
        results = []
        for module_dir in sorted(classes_dir.iterdir()):
            if module_dir.is_dir():
                results.extend(migrator.migrate_module(str(module_dir.relative_to(args.project_root))))
    else:
        parser.print_help()
        sys.exit(1)
    
    report = migrator.generate_report(results)
    print(report)


if __name__ == '__main__':
    main()
