#!/usr/bin/env bash

set -euo pipefail

#########################################
# Ayarlar
#########################################

IMAGE_NAME="seo-ai-checker"

DOCKERHUB_USER="bmericc"
GHCR_USER="bmericc"

VERSION="${1:-latest}"

DOCKERFILE="docker/php/Dockerfile"
CONTEXT="."

#########################################

echo "==> Building image..."

docker build \
    -f "${DOCKERFILE}" \
    -t "${IMAGE_NAME}:${VERSION}" \
    "${CONTEXT}"

echo "==> Tagging images..."

docker tag "${IMAGE_NAME}:${VERSION}" \
    "${DOCKERHUB_USER}/${IMAGE_NAME}:${VERSION}"

docker tag "${IMAGE_NAME}:${VERSION}" \
    "ghcr.io/${GHCR_USER}/${IMAGE_NAME}:${VERSION}"

if [ "${VERSION}" != "latest" ]; then
    docker tag "${IMAGE_NAME}:${VERSION}" \
        "${DOCKERHUB_USER}/${IMAGE_NAME}:latest"

    docker tag "${IMAGE_NAME}:${VERSION}" \
        "ghcr.io/${GHCR_USER}/${IMAGE_NAME}:latest"
fi

echo "==> Pushing Docker Hub..."

docker push "${DOCKERHUB_USER}/${IMAGE_NAME}:${VERSION}"

if [ "${VERSION}" != "latest" ]; then
    docker push "${DOCKERHUB_USER}/${IMAGE_NAME}:latest"
fi

echo "==> Pushing GitHub Container Registry..."

docker push "ghcr.io/${GHCR_USER}/${IMAGE_NAME}:${VERSION}"

if [ "${VERSION}" != "latest" ]; then
    docker push "ghcr.io/${GHCR_USER}/${IMAGE_NAME}:latest"
fi

echo
echo "===================================="
echo "Publish completed successfully."
echo
echo "Docker Hub : ${DOCKERHUB_USER}/${IMAGE_NAME}:${VERSION}"
echo "GHCR       : ghcr.io/${GHCR_USER}/${IMAGE_NAME}:${VERSION}"
echo "===================================="
