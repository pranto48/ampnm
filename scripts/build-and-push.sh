#!/usr/bin/env bash
#
# Copyright (c) IT Support BD. All rights reserved.
# This file is part of AMPNM.
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License...
# (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#
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
echo "→ Tagging image as arifmahmudpranto/ampnm:latest..."
docker tag ampnm-app:latest arifmahmudpranto/ampnm:latest
echo "✓ Image tagged successfully."

# 3. Push to Docker Hub
echo "→ Pushing image arifmahmudpranto/ampnm:latest to Docker Hub..."
docker push arifmahmudpranto/ampnm:latest

echo ""
echo "========================================================="
echo "🎉 SUCCESS: Image pushed to arifmahmudpranto/ampnm:latest"
echo "========================================================="
echo ""
