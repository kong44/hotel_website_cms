#!/usr/bin/env bash
# ========================================================
# Indra Hotel CMS - Homelab K3s Build & Deploy Script
# ========================================================

set -e

IMAGE_NAME="hotel-cms:latest"
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
docker build --no-cache -t ${IMAGE_NAME} .

echo "========================================================"
echo " 🚚 Importing Image into K3s Containerd Engine"
echo "========================================================"

if command -v k3s &> /dev/null; then
    echo "Importing image into K3s (k8s.io namespace)..."
    docker save ${IMAGE_NAME} | sudo k3s ctr -n k8s.io images import -
elif command -v ctr &> /dev/null; then
    echo "Importing image into containerd k8s.io namespace..."
    docker save ${IMAGE_NAME} | sudo ctr -n k8s.io images import -
else
    echo "⚠️  Neither k3s nor ctr binary found directly in PATH. Skipping ctr import step (assuming shared Docker socket or microk8s/minikube)."
fi

echo "========================================================"
echo " 🚀 Applying K3s Kubernetes Manifests & Triggering Deployment Rollout"
echo "========================================================"
kubectl apply -f k8s/
kubectl patch deployment hotel-cms-app -n ${NAMESPACE} -p "{\"spec\":{\"template\":{\"metadata\":{\"annotations\":{\"build.id\":\"$(date +%s)\"}}}}}" || true
kubectl rollout restart deployment/hotel-cms-app -n ${NAMESPACE}

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
echo "🎉 Code and Database Deployment Complete!"
