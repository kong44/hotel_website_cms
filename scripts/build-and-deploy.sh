#!/usr/bin/env bash
# ========================================================
# Indra Hotel CMS - Homelab K3s Build & Deploy Script
# ========================================================

set -e

IMAGE_NAME="hotel-cms:latest"
NAMESPACE="hotel-cms"

echo "========================================================"
echo " 🛠️  Building Local Docker Image: ${IMAGE_NAME}"
echo "========================================================"
docker build -t ${IMAGE_NAME} .

echo "========================================================"
echo " 🚚 Importing Image into K3s Containerd Engine"
echo "========================================================"

if command -v k3s &> /dev/null; then
    echo "Importing image into K3s..."
    docker save ${IMAGE_NAME} | sudo k3s ctr images import -
elif command -v ctr &> /dev/null; then
    echo "Importing image into containerd k8s.io namespace..."
    docker save ${IMAGE_NAME} | sudo ctr -n k8s.io images import -
else
    echo "⚠️  Neither k3s nor ctr binary found directly in PATH. Skipping ctr import step (assuming shared Docker socket or microk8s/minikube)."
fi

echo "========================================================"
echo " 🚀 Applying K3s Kubernetes Manifests"
echo "========================================================"
kubectl apply -f k8s/

echo "========================================================"
echo " ⏳ Waiting for MySQL StatefulSet to become ready..."
echo "========================================================"
kubectl rollout status statefulset/mysql -n ${NAMESPACE} --timeout=120s || true

echo "========================================================"
echo " ⏳ Waiting for App Deployment to complete rollout..."
echo "========================================================"
kubectl rollout status deployment/hotel-cms-app -n ${NAMESPACE} --timeout=120s || true

echo "========================================================"
echo " ✅ Deployment Status summary:"
echo "========================================================"
kubectl get pods,svc,pvc,ingress -n ${NAMESPACE}

echo ""
echo "🎉 Deployment complete! Ensure Cloudflare Tunnel points to port 8000 on localhost."
