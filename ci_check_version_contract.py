#!/usr/bin/env python3
"""
CI Guard: Version Contract Consistency
检查三处版本号 + PHP 最低版本一致性
"""
import json, re, sys, pathlib

root = pathlib.Path(sys.argv[1] if len(sys.argv) > 1 else '.')
errors = []

# 1. 插件头版本
plugin_php = (root / 'linked3.php').read_text()
header_ver = None
for m in re.finditer(r'Version:\s*(\d+\.\d+\.\d+)', plugin_php):
    header_ver = m.group(1)
    break

# 2. LINKED3_VERSION
const_ver = None
for m in re.finditer(r"define\('LINKED3_VERSION',\s*'([^']+)'\)", plugin_php):
    const_ver = m.group(1)
    break

# 3. composer.json 版本
composer = json.loads((root / 'composer.json').read_text())
composer_ver = composer.get('version', '')

# 4. PHP 最低版本
header_php = None
for m in re.finditer(r'Requires PHP:\s*(\d+\.\d+)', plugin_php):
    header_php = m.group(1)
    break

guard_php = None
for m in re.finditer(r"version_compare\(PHP_VERSION,\s*'([^']+)'", plugin_php):
    guard_php = m.group(1)
    break

composer_php = composer.get('require', {}).get('php', '')
composer_php_num = re.search(r'(\d+\.\d+)', composer_php)

# 校验
if header_ver != const_ver:
    errors.append(f"插件头版本({header_ver}) != LINKED3_VERSION({const_ver})")
if header_ver != composer_ver:
    errors.append(f"插件头版本({header_ver}) != composer.json版本({composer_ver})")
if header_php and guard_php and not guard_php.startswith(header_php):
    errors.append(f"插件头PHP({header_php}) != runtime guard({guard_php})")
if header_php and composer_php_num and header_php != composer_php_num.group(1):
    errors.append(f"插件头PHP({header_php}) != composer.json PHP({composer_php_num.group(1)})")

if errors:
    print("[FAIL] 版本契约不一致:")
    for e in errors:
        print(f"  ❌ {e}")
    sys.exit(1)
else:
    print(f"[OK] 版本契约一致: v{header_ver}, PHP >={header_php}")
