#!/bin/bash
set -e

DOCKER_USERNAME="itsupportbd"
REPO="${DOCKER_USERNAME}/ampnm"
TAG_LATEST="${REPO}:latest"
TAG_V19="${REPO}:V1.9"

echo "=============================="
echo " AMPNM Docker Hub Push Script"
echo " Repository: ${REPO}"
echo " Version: V1.9"
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
docker rmi "${TAG_V19}" 2>/dev/null && echo "  Removed: ${TAG_V19}" || echo "  None to remove (${TAG_V19})"

# 4. Build fresh image
echo ""
echo "→ Building AMPNM Docker image..."
echo "  Build context: $(pwd)"
echo "  Platform: linux/amd64"
echo "  Tags: ${TAG_LATEST}, ${TAG_V19}"
echo ""

docker build \
    --platform linux/amd64 \
    --progress=plain \
    -t "${TAG_LATEST}" \
    -t "${TAG_V19}" \
    --label "org.opencontainers.image.title=AMPNM" \
    --label "org.opencontainers.image.description=Advanced Multi-Protocol Network Monitor" \
    --label "org.opencontainers.image.vendor=IT Support BD" \
    --label "org.opencontainers.image.version=1.9" \
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
echo "→ Pushing ${TAG_V19} to Docker Hub..."
docker push "${TAG_V19}"

echo ""
echo "→ Pushing ${TAG_LATEST} to Docker Hub..."
docker push "${TAG_LATEST}"

echo ""
echo "=============================="
echo "✓ DONE! Images pushed to:"
echo "  https://hub.docker.com/r/${REPO}"
echo ""
echo "  Pull with:"
echo "  docker pull ${TAG_V19}"
echo "  docker pull ${TAG_LATEST}"
echo "=============================="
