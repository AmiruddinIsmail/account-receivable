#!/bin/bash

set -e

AWS_REGION="ap-southeast-5"
AWS_ACCOUNT_ID="573068821721"

ECR_NAMESPACE="renewngo"
ECR_REPO="account-receivable"

IMAGE_NAME="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_NAMESPACE}/${ECR_REPO}"

IMAGE_TAG=$(git rev-parse --short HEAD)

echo "Image Tag: ${IMAGE_TAG}"

echo "Logging into ECR..."
aws ecr get-login-password \
    --region ${AWS_REGION} \
| docker login \
    --username AWS \
    --password-stdin \
    ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com

echo "Creating buildx builder..."
docker buildx create --use --name builder 2>/dev/null || true
docker buildx inspect --bootstrap

echo "Building and pushing image..."

docker buildx build \
    --platform linux/amd64 \
    --pull \
    --cache-from type=registry,ref=${IMAGE_NAME}:buildcache \
    --cache-to type=registry,ref=${IMAGE_NAME}:buildcache,mode=max \
    -t ${IMAGE_NAME}:${IMAGE_TAG} \
    -t ${IMAGE_NAME}:latest \
    -f docker/php/Dockerfile \
    . \
    --push

echo "Done!"
echo "Pushed:"
echo "  ${IMAGE_NAME}:${IMAGE_TAG}"
echo "  ${IMAGE_NAME}:latest"