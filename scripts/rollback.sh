#!/usr/bin/env bash
# ========================================================
# Indra Hotel CMS - Homelab K3s Rollback Utility
# ========================================================

set -e

NAMESPACE="hotel-cms"
DEPLOYMENT="hotel-cms-app"
REVISION="${1:-}"

echo "========================================================"
echo " ⏪ Indra Hotel CMS - Rollback Tool"
echo "========================================================"

if ! command -v kubectl &> /dev/null || ! kubectl get namespace "${NAMESPACE}" &> /dev/null; then
    echo "❌ Error: Kubernetes cluster or namespace '${NAMESPACE}' not found."
    exit 1
fi

echo "📜 Current Rollout History for Deployment [${DEPLOYMENT}]:"
kubectl rollout history deployment/${DEPLOYMENT} -n ${NAMESPACE}

if [ -n "${REVISION}" ]; then
    echo ""
    echo "🔄 Rolling back deployment [${DEPLOYMENT}] to revision: ${REVISION}..."
    kubectl rollout undo deployment/${DEPLOYMENT} -n ${NAMESPACE} --to-revision=${REVISION}
else
    echo ""
    echo "🔄 Rolling back deployment [${DEPLOYMENT}] to previous revision..."
    kubectl rollout undo deployment/${DEPLOYMENT} -n ${NAMESPACE}
fi

echo ""
echo "⏳ Waiting for rollback to complete..."
kubectl rollout status deployment/${DEPLOYMENT} -n ${NAMESPACE} --timeout=120s

echo ""
echo "✅ Rollback Complete! Current Pod Status:"
kubectl get pods -n ${NAMESPACE} -l app=${DEPLOYMENT}
