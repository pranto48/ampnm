#!/usr/bin/env bash
set -euo pipefail

# AMPNM - Build and Push Docker Image
echo "========================================================="
echo "    Building and Pushing AMPNM to Docker Hub..."
echo "========================================================="
echo ""

# 1. Build image locally
echo "→ Building local image ampnm-app:latest..."
export DOCKER_BUILDKIT=0
docker compose build --no-cache
echo "✓ Local image built successfully."

# 2. Tag image
echo "→ Tagging image as pranto48/ampnm:latest..."
docker tag ampnm-app:latest pranto48/ampnm:latest
echo "✓ Image tagged successfully."

# 3. Push to Docker Hub
echo "→ Pushing image pranto48/ampnm:latest to Docker Hub..."
docker push pranto48/ampnm:latest

echo ""
echo "========================================================="
echo "🎉 SUCCESS: Image pushed to pranto48/ampnm:latest"
echo "========================================================="
echo ""
