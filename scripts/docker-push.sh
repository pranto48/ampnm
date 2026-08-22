#!/bin/bash
#
# Copyright (c) IT Support BD. All rights reserved.
# This file is part of AMPNM.
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License...
# (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#
set -e

DOCKER_USERNAME="${DOCKER_USERNAME:-itsupportbd}"
REPO="${DOCKER_USERNAME}/ampnm"
TAG_LATEST="${REPO}:latest"

# Get version tag from command line parameter, defaulting to V1.19
VERSION_TAG="${1:-V1.19}"

# Ensure it starts with uppercase V
if [[ ! "$VERSION_TAG" =~ ^[vV] ]]; then
    VERSION_TAG="V${VERSION_TAG}"
fi
# Strip any leading 'v' to get standard numeric version for image labels
VERSION_NUM=$(echo "${VERSION_TAG}" | sed -E 's/^[vV]//')

TAG_VERSION="${REPO}:${VERSION_TAG}"
TAG_LOWER_V="${REPO}:v${VERSION_NUM}"
TAG_PRANTO_V="pranto48/ampnm:v${VERSION_NUM}"
TAG_PRANTO_LATEST="pranto48/ampnm:latest"

echo "=============================="
echo " AMPNM Docker Hub Push Script"
echo " Repository: ${REPO}"
echo " Version: ${VERSION_TAG} (Numeric: ${VERSION_NUM})"
echo "=============================="
echo ""

# 1. Wait for Docker daemon to be ready
echo "→ Waiting for Docker daemon..."
for i in $(seq 1 30); do
    if docker info > /dev/null 2>&1; then
        echo "✓ Docker daemon is ready"
        break
    fi
    echo "  Waiting... ($i/30)"
    sleep 3
done

# 2. Login to Docker Hub
echo ""
echo "→ Logging in to Docker Hub as ${DOCKER_USERNAME}..."
# Pass token via: export DOCKER_TOKEN=your_token
# Default token for itsupportbd:
DOCKER_TOKEN="${DOCKER_TOKEN:-dckr_pat_qn9yZAm1KeDsueJZVuz7sgnvbwc}"
echo "${DOCKER_TOKEN}" | docker login -u "${DOCKER_USERNAME}" --password-stdin
echo "✓ Logged in as ${DOCKER_USERNAME}"

# 3. Build fresh image
echo ""
echo "→ Building AMPNM Docker image..."
echo "  Build context: $(pwd)"
echo "  Platform: linux/amd64"
echo "  Tags: ${TAG_LATEST}, ${TAG_VERSION}, ${TAG_LOWER_V}, ${TAG_PRANTO_V}, ${TAG_PRANTO_LATEST}"
echo ""

docker build \
    --platform linux/amd64 \
    -t "${TAG_LATEST}" \
    -t "${TAG_VERSION}" \
    -t "${TAG_LOWER_V}" \
    -t "${TAG_PRANTO_V}" \
    -t "${TAG_PRANTO_LATEST}" \
    --label "org.opencontainers.image.title=AMPNM" \
    --label "org.opencontainers.image.description=Advanced Multi-Protocol Network Monitor" \
    --label "org.opencontainers.image.vendor=IT Support BD" \
    --label "org.opencontainers.image.version=${VERSION_NUM}" \
    --label "org.opencontainers.image.url=https://ampnm.itsupport.com.bd" \
    --label "org.opencontainers.image.documentation=https://ampnm.itsupport.com.bd/docs" \
    --label "org.opencontainers.image.source=https://github.com/pranto48/ampnm" \
    .

echo ""
echo "✓ Build complete!"

# 4. Push to Docker Hub
echo ""
echo "→ Pushing ${TAG_VERSION} to Docker Hub..."
docker push "${TAG_VERSION}"
docker push "${TAG_LOWER_V}"
docker push "${TAG_LATEST}"
docker push "${TAG_PRANTO_V}" || true
docker push "${TAG_PRANTO_LATEST}" || true

echo ""
echo "=============================="
echo "✓ DONE! Images pushed successfully"
echo "=============================="
