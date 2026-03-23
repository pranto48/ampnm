#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)
VERSION=${VERSION:-0.1.0}
RPMROOT="$ROOT/packaging/rpm/rpmbuild"
DIST_DIR="$ROOT/dist"
SPEC_SRC="$ROOT/packaging/rpm/SPECS/ampnm-agent.spec"
SPEC_WORK="$RPMROOT/SPECS/ampnm-agent.spec"

rm -rf "$RPMROOT"
mkdir -p "$RPMROOT"/{BUILD,BUILDROOT,RPMS,SOURCES,SPECS,SRPMS}
mkdir -p "$DIST_DIR"

cp "$ROOT/docker-ampnm/assets/linux-agent/ampnm-agent.sh" "$RPMROOT/SOURCES/ampnm-agent.sh"
cp "$ROOT/docker-ampnm/assets/linux-agent/ampnm-agent.service" "$RPMROOT/SOURCES/ampnm-agent.service"
cp "$ROOT/packaging/common/config.env.example" "$RPMROOT/SOURCES/config.env.example"
cp "$SPEC_SRC" "$SPEC_WORK"
sed -i "s/^Version:.*/Version:        $VERSION/" "$SPEC_WORK"

rpmbuild --define "_topdir $RPMROOT" -bb "$SPEC_WORK"
find "$RPMROOT/RPMS" -name '*.rpm' -exec cp {} "$DIST_DIR/" \;
echo "Built RPM packages in $DIST_DIR"
