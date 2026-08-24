# ========================================================
# Indra Hotel CMS - DevOps & Deployment Makefile
# ========================================================

.PHONY: help build up down deploy k3s-deploy k3s-delete status logs restart db-create db-init db-migrate db-reset deploy-all

help:
	@echo "Available commands:"
	@echo "  --- CODE DEPLOYMENT ---"
	@echo "  make deploy       - Rebuild & deploy code changes to K3s cluster"
	@echo "  make up           - Build & start local Docker Compose environment"
	@echo "  make down         - Stop local Docker Compose environment"
	@echo "  make restart      - Restart app pods in K3s without rebuilding image"
	@echo ""
	@echo "  --- DATABASE MANAGEMENT ---"
	@echo "  make db-create    - Create/Initialize database schema & seed data"
	@echo "  make db-migrate   - Apply new tables & schema migrations without dropping data"
	@echo "  make db-reset     - Drop & recreate fresh database with schema and seed data"
	@echo ""
	@echo "  --- COMBINED COMMANDS ---"
	@echo "  make deploy-all   - Rebuild code + apply database migrations + deploy to K3s"
	@echo ""
	@echo "  --- UTILITIES ---"
	@echo "  make status       - Show status of K3s resources"
	@echo "  make logs         - Tail logs from K3s app pods"
	@echo "  make k3s-delete   - Delete K3s resources for hotel-cms"

build:
	docker build --no-cache -t hotel-cms:latest .

up:
	docker-compose up -d --build
	./scripts/db-sync.sh migrate

down:
	docker-compose down

deploy: k3s-deploy

k3s-deploy:
	./scripts/build-and-deploy.sh

k3s-delete:
	kubectl delete -f k8s/

status:
	kubectl get pods,svc,pvc,ingress -n hotel-cms

logs:
	kubectl logs -f -l app=hotel-cms-app -n hotel-cms --tail=100

restart:
	kubectl rollout restart deployment/hotel-cms-app -n hotel-cms

db-create:
	./scripts/db-sync.sh create

db-init: db-create

db-migrate:
	./scripts/db-sync.sh migrate

db-reset:
	./scripts/db-sync.sh reset

deploy-all:
	./scripts/build-and-deploy.sh
