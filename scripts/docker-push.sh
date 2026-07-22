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

DOCKER_USERNAME="itsupportbd"
REPO="${DOCKER_USERNAME}/ampnm"
TAG_LATEST="${REPO}:latest"

# Get version tag from command line parameter, defaulting to V1.10
VERSION_TAG="${1:-V1.10}"

# Ensure it starts with uppercase V
if [[ ! "$VERSION_TAG" =~ ^[vV] ]]; then
    VERSION_TAG="V${VERSION_TAG}"
fi
# Strip any leading 'v' to get standard numeric version for image labels
VERSION_NUM=$(echo "${VERSION_TAG}" | sed -E 's/^[vV]//')

TAG_VERSION="${REPO}:${VERSION_TAG}"

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
# Then run this script: bash scripts/docker-push.sh
if [ -z "${DOCKER_TOKEN:-}" ]; then
    echo "  ERROR: DOCKER_TOKEN env var not set."
    echo "  Run: export DOCKER_TOKEN=<your_docker_pat>"
    exit 1
fi
echo "${DOCKER_TOKEN}" | docker login -u "${DOCKER_USERNAME}" --password-stdin
echo "✓ Logged in as ${DOCKER_USERNAME}"

# 3. Remove old local images if they exist
echo ""
echo "→ Removing old local AMPNM images..."
docker rmi "${TAG_LATEST}" 2>/dev/null && echo "  Removed: ${TAG_LATEST}" || echo "  None to remove (${TAG_LATEST})"
docker rmi "${TAG_VERSION}" 2>/dev/null && echo "  Removed: ${TAG_VERSION}" || echo "  None to remove (${TAG_VERSION})"

# 4. Build fresh image
echo ""
echo "→ Building AMPNM Docker image..."
echo "  Build context: $(pwd)"
echo "  Platform: linux/amd64"
echo "  Tags: ${TAG_LATEST}, ${TAG_VERSION}"
echo ""

docker build \
    --platform linux/amd64 \
    --progress=plain \
    -t "${TAG_LATEST}" \
    -t "${TAG_VERSION}" \
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

# 5. Show image info
echo ""
echo "→ Built images:"
docker images | grep -E "ampnm|REPOSITORY"

# 6. Push to Docker Hub
echo ""
echo "→ Pushing ${TAG_VERSION} to Docker Hub..."
docker push "${TAG_VERSION}"

echo ""
echo "→ Pushing ${TAG_LATEST} to Docker Hub..."
docker push "${TAG_LATEST}"

echo ""
echo "=============================="
echo "✓ DONE! Images pushed to:"
echo "  https://hub.docker.com/r/${REPO}"
echo ""
echo "  Pull with:"
echo "  docker pull ${TAG_VERSION}"
echo "  docker pull ${TAG_LATEST}"
echo "=============================="
