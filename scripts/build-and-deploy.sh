#!/usr/bin/env bash
# ========================================================
# Indra Hotel CMS - Homelab K3s Build & Deploy Script
# ========================================================

set -e

BUILD_TAG="v$(date +%Y%m%d%H%M%S)"
IMAGE_NAME="hotel-cms:${BUILD_TAG}"
IMAGE_LATEST="hotel-cms:latest"
NAMESPACE="hotel-cms"
WITH_DB_RESET=false

for arg in "$@"; do
    case $arg in
        --with-db-reset|--reset-db)
            WITH_DB_RESET=true
            shift
            ;;
    esac
done

echo "========================================================"
echo " 🛠️  Building Local Docker Image: ${IMAGE_NAME}"
echo "========================================================"
docker build --no-cache -t ${IMAGE_NAME} -t ${IMAGE_LATEST} .

echo "========================================================"
echo " 🚚 Importing Image into K3s Containerd Engine"
echo "========================================================"

CTR_BIN=""
if command -v k3s &> /dev/null; then
    CTR_BIN="k3s ctr"
elif [ -f "/usr/local/bin/k3s" ]; then
    CTR_BIN="/usr/local/bin/k3s ctr"
elif [ -f "/usr/bin/k3s" ]; then
    CTR_BIN="/usr/bin/k3s ctr"
elif command -v ctr &> /dev/null; then
    CTR_BIN="ctr"
elif [ -f "/usr/local/bin/ctr" ]; then
    CTR_BIN="/usr/local/bin/ctr"
elif [ -f "/usr/bin/ctr" ]; then
    CTR_BIN="/usr/bin/ctr"
fi

if [ -n "${CTR_BIN}" ]; then
    echo "Importing image ${IMAGE_NAME} into containerd (k8s.io namespace)..."
    docker save ${IMAGE_NAME} ${IMAGE_LATEST} | sudo ${CTR_BIN} -n k8s.io images import -
else
    echo "⚠️  Neither k3s nor ctr binary found directly in PATH. Skipping ctr import step (assuming shared Docker socket or microk8s/minikube)."
fi

echo "========================================================"
echo " 🚀 Applying K3s Kubernetes Manifests & Updating Unique Image Tag"
echo "========================================================"
kubectl apply -f k8s/
echo "Setting deployment container image to [${IMAGE_NAME}]..."
kubectl set image deployment/hotel-cms-app app=${IMAGE_NAME} -n ${NAMESPACE}

echo "========================================================"
echo " ⏳ Waiting for MySQL StatefulSet to become ready..."
echo "========================================================"
kubectl rollout status statefulset/mysql -n ${NAMESPACE} --timeout=120s || true

echo "========================================================"
echo " 🗄️ Initializing & Syncing Database Schema"
echo "========================================================"
if [ "$WITH_DB_RESET" = true ]; then
    echo "⚠️  Running Database Reset & Re-creation..."
    ./scripts/db-sync.sh reset
else
    echo "Running Schema Migration & Table Sync..."
    ./scripts/db-sync.sh migrate
fi

echo "========================================================"
echo " ⏳ Waiting for App Deployment to complete rollout..."
echo "========================================================"
kubectl rollout status deployment/hotel-cms-app -n ${NAMESPACE} --timeout=120s || true

echo "========================================================"
echo " 🏃 Running Post-Deployment PHP Database Installer"
echo "========================================================"
kubectl exec -n ${NAMESPACE} deployment/hotel-cms-app -- php install.php || true

echo "========================================================"
echo " ✅ Deployment Status Summary:"
echo "========================================================"
kubectl get pods,svc,pvc,ingress -n ${NAMESPACE}

echo ""
echo "🎉 Code and Database Deployment Complete! Updated to build ${BUILD_TAG}."
