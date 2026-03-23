#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)
VERSION=${VERSION:-0.1.0}
PKG_ROOT="$ROOT/packaging/deb/pkgroot"
DIST_DIR="$ROOT/dist"

rm -rf "$PKG_ROOT"
mkdir -p \
  "$PKG_ROOT/DEBIAN" \
  "$PKG_ROOT/opt/ampnm-agent" \
  "$PKG_ROOT/etc/systemd/system" \
  "$PKG_ROOT/usr/share/ampnm-agent"

cp "$ROOT/packaging/deb/DEBIAN/control" "$PKG_ROOT/DEBIAN/control"
sed -i "s/^Version: .*/Version: $VERSION/" "$PKG_ROOT/DEBIAN/control"
cp "$ROOT/packaging/deb/DEBIAN/postinst" "$PKG_ROOT/DEBIAN/postinst"
cp "$ROOT/packaging/deb/DEBIAN/prerm" "$PKG_ROOT/DEBIAN/prerm"
chmod 0755 "$PKG_ROOT/DEBIAN/postinst" "$PKG_ROOT/DEBIAN/prerm"

install -m 0755 "$ROOT/docker-ampnm/assets/linux-agent/ampnm-agent.sh" "$PKG_ROOT/opt/ampnm-agent/ampnm-agent.sh"
install -m 0644 "$ROOT/docker-ampnm/assets/linux-agent/ampnm-agent.service" "$PKG_ROOT/etc/systemd/system/ampnm-agent.service"
install -m 0644 "$ROOT/packaging/common/config.env.example" "$PKG_ROOT/usr/share/ampnm-agent/config.env.example"

mkdir -p "$DIST_DIR"
dpkg-deb --build "$PKG_ROOT" "$DIST_DIR/ampnm-agent_${VERSION}_all.deb"
echo "Built $DIST_DIR/ampnm-agent_${VERSION}_all.deb"
