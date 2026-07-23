#!/usr/bin/env bash

set -euo pipefail

#########################################
# Configuration
#########################################

IMAGE_NAME="seo-ai-checker"

DOCKERHUB_USER="bmericc"
GHCR_USER="bmericc"

VERSION="${1:-latest}"

DOCKERFILE="docker/php/Dockerfile"
CONTEXT="."

PLATFORMS="linux/amd64,linux/arm64"

#########################################

echo "==> Checking buildx..."

docker buildx inspect multiarch >/dev/null 2>&1 || \
docker buildx create \
    --name multiarch \
    --driver docker-container \
    --use

docker buildx use multiarch
docker buildx inspect --bootstrap

echo
echo "==> Building and publishing ${VERSION}"
echo

TAGS=(
    "-t" "${DOCKERHUB_USER}/${IMAGE_NAME}:${VERSION}"
    "-t" "ghcr.io/${GHCR_USER}/${IMAGE_NAME}:${VERSION}"
)

if [[ "${VERSION}" != "latest" ]]; then
    TAGS+=(
        "-t" "${DOCKERHUB_USER}/${IMAGE_NAME}:latest"
        "-t" "ghcr.io/${GHCR_USER}/${IMAGE_NAME}:latest"
    )
fi

docker buildx build \
    --platform "${PLATFORMS}" \
    -f "${DOCKERFILE}" \
    "${TAGS[@]}" \
    --push \
    "${CONTEXT}"

echo
echo "========================================"
echo "Published successfully!"
echo
echo "Docker Hub : ${DOCKERHUB_USER}/${IMAGE_NAME}:${VERSION}"
echo "GHCR       : ghcr.io/${GHCR_USER}/${IMAGE_NAME}:${VERSION}"
echo "Platforms  : ${PLATFORMS}"
echo "========================================"
