#!/bin/bash
# sync-gallery.sh — 图标图库同步（在部署宿主机执行一次，产物落 data/gallery/，卷挂载进容器）
# 库源：homarr-labs/dashboard-icons（webp，全彩服务图标，MIT）
#       simple-icons/simple-icons（svg，品牌 Logo，CC0）
# 用法：cd 到部署目录（含 data/ 的那层）后执行 bash sync-gallery.sh；或传应用根目录参数
set -eu
DEST="${1:-$(pwd)}/data/gallery"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$DEST"

echo '== 1/3 Dashboard Icons (webp) =='
curl -fsSL -m 300 -o "$TMP/dash.tar.gz" https://codeload.github.com/homarr-labs/dashboard-icons/tar.gz/refs/heads/main
mkdir -p "$TMP/dash"
tar xzf "$TMP/dash.tar.gz" -C "$TMP/dash" --wildcards '*/webp/*'
mkdir -p "$DEST/dashboard"
find "$TMP/dash" -name '*.webp' -exec cp {} "$DEST/dashboard/" \;

echo '== 2/3 Simple Icons (svg) =='
BRANCH=master
curl -fsSL -m 300 -o "$TMP/si.tar.gz" "https://codeload.github.com/simple-icons/simple-icons/tar.gz/refs/heads/$BRANCH" \
  || { BRANCH=develop; curl -fsSL -m 300 -o "$TMP/si.tar.gz" "https://codeload.github.com/simple-icons/simple-icons/tar.gz/refs/heads/$BRANCH"; }
mkdir -p "$TMP/si"
tar xzf "$TMP/si.tar.gz" -C "$TMP/si" --wildcards '*/icons/*.svg'
mkdir -p "$DEST/simple-icons"
find "$TMP/si" -name '*.svg' -exec cp {} "$DEST/simple-icons/" \;

echo '== 3/3 生成 index.json =='
python3 - "$DEST" << 'PYEOF'
import json, os, sys
dest = sys.argv[1]
idx = {}
for setname, ext in (('dashboard', 'webp'), ('simple-icons', 'svg')):
    d = os.path.join(dest, setname)
    idx[setname] = sorted(f[:-len(ext) - 1] for f in os.listdir(d) if f.endswith('.' + ext)) if os.path.isdir(d) else []
with open(os.path.join(dest, 'index.json'), 'w') as fp:
    json.dump(idx, fp, separators=(',', ':'))
print({k: len(v) for k, v in idx.items()})
PYEOF

chown -R 1000:999 "$DEST" 2>/dev/null || true
echo "DONE: $(find "$DEST" -type f | wc -l) files in $DEST"
