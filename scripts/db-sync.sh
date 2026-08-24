#!/usr/bin/env bash
# ========================================================
# Indra Hotel CMS - Database Sync & Migration Utility
# ========================================================

set -e

NAMESPACE="hotel-cms"
MYSQL_ROOT_PASS_K3S="mysql_root_k3s_secure_pass_2026"
MYSQL_ROOT_PASS_COMPOSE="root_secure_password_2026"
DB_NAME="hotel_website"

ACTION="${1:-migrate}"

echo "========================================================"
echo " 🗄️ Database Command: [${ACTION}]"
echo "========================================================"

detect_environment() {
    if command -v kubectl &> /dev/null && kubectl get namespace "${NAMESPACE}" &> /dev/null; then
        echo "k3s"
    elif command -v docker &> /dev/null && docker ps --format '{{.Names}}' | grep -q "hotel_cms_db"; then
        echo "docker-compose"
    else
        echo "local"
    fi
}

ENV_TYPE=$(detect_environment)
echo "🔍 Detected target environment: [${ENV_TYPE}]"

case "${ACTION}" in
    migrate|update)
        echo "🚀 Running schema updates & dynamic migrations..."
        if [ "${ENV_TYPE}" = "k3s" ]; then
            echo "Importing schema.sql into K3s MySQL..."
            kubectl exec -i -n ${NAMESPACE} statefulset/mysql -- mysql -u root -p"${MYSQL_ROOT_PASS_K3S}" ${DB_NAME} < schema.sql || true
            echo "Running PHP migration installer on app pod..."
            kubectl exec -n ${NAMESPACE} deployment/hotel-cms-app -- php install.php || true
        elif [ "${ENV_TYPE}" = "docker-compose" ]; then
            echo "Importing schema.sql into Docker Compose MySQL..."
            docker exec -i hotel_cms_db mysql -u root -p"${MYSQL_ROOT_PASS_COMPOSE}" ${DB_NAME} < schema.sql || true
            echo "Running PHP migration installer on Docker container..."
            docker exec hotel_cms_app php install.php || true
        else
            echo "Running local PHP migration installer..."
            php install.php
        fi
        echo "✅ Database migration complete."
        ;;

    create|init)
        echo "🛠️ Creating and initializing database schema & seed data..."
        if [ "${ENV_TYPE}" = "k3s" ]; then
            echo "Applying schema.sql and seed.sql to K3s MySQL..."
            kubectl exec -i -n ${NAMESPACE} statefulset/mysql -- mysql -u root -p"${MYSQL_ROOT_PASS_K3S}" ${DB_NAME} < schema.sql
            kubectl exec -i -n ${NAMESPACE} statefulset/mysql -- mysql -u root -p"${MYSQL_ROOT_PASS_K3S}" ${DB_NAME} < seed.sql
            kubectl exec -n ${NAMESPACE} deployment/hotel-cms-app -- php install.php || true
        elif [ "${ENV_TYPE}" = "docker-compose" ]; then
            echo "Applying schema.sql and seed.sql to Docker Compose MySQL..."
            docker exec -i hotel_cms_db mysql -u root -p"${MYSQL_ROOT_PASS_COMPOSE}" ${DB_NAME} < schema.sql
            docker exec -i hotel_cms_db mysql -u root -p"${MYSQL_ROOT_PASS_COMPOSE}" ${DB_NAME} < seed.sql
            docker exec hotel_cms_app php install.php || true
        else
            echo "Running local PHP installer..."
            php install.php
        fi
        echo "✅ Database created & seeded successfully."
        ;;

    reset|recreate)
        echo "⚠️  WARNING: Resetting database (dropping and recreating schema & seed)..."
        if [ "${ENV_TYPE}" = "k3s" ]; then
            kubectl exec -i -n ${NAMESPACE} statefulset/mysql -- mysql -u root -p"${MYSQL_ROOT_PASS_K3S}" -e "DROP DATABASE IF EXISTS ${DB_NAME}; CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
            kubectl exec -i -n ${NAMESPACE} statefulset/mysql -- mysql -u root -p"${MYSQL_ROOT_PASS_K3S}" ${DB_NAME} < schema.sql
            kubectl exec -i -n ${NAMESPACE} statefulset/mysql -- mysql -u root -p"${MYSQL_ROOT_PASS_K3S}" ${DB_NAME} < seed.sql
            kubectl exec -n ${NAMESPACE} deployment/hotel-cms-app -- php install.php || true
        elif [ "${ENV_TYPE}" = "docker-compose" ]; then
            docker exec -i hotel_cms_db mysql -u root -p"${MYSQL_ROOT_PASS_COMPOSE}" -e "DROP DATABASE IF EXISTS ${DB_NAME}; CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
            docker exec -i hotel_cms_db mysql -u root -p"${MYSQL_ROOT_PASS_COMPOSE}" ${DB_NAME} < schema.sql
            docker exec -i hotel_cms_db mysql -u root -p"${MYSQL_ROOT_PASS_COMPOSE}" ${DB_NAME} < seed.sql
            docker exec hotel_cms_app php install.php || true
        else
            if [ -f "database.sqlite" ]; then
                rm -f database.sqlite
            fi
            php install.php
        fi
        echo "🎉 Database reset complete!"
        ;;

    *)
        echo "Usage: $0 {migrate|create|reset}"
        exit 1
        ;;
esac
