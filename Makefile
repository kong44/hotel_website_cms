# ========================================================
# Indra Hotel CMS - DevOps Makefile
# ========================================================

.PHONY: help build up down k3s-deploy k3s-delete status logs clean

help:
	@echo "Available commands:"
	@echo "  make build       - Build local Docker image (hotel-cms:latest)"
	@echo "  make up          - Start local Docker Compose environment"
	@echo "  make down        - Stop local Docker Compose environment"
	@echo "  make k3s-deploy  - Build image and deploy manifests to K3s cluster"
	@echo "  make k3s-delete  - Delete K3s resources for hotel-cms"
	@echo "  make status      - Show status of K3s resources"
	@echo "  make logs        - Tail logs from K3s app pods"

build:
	docker build --no-cache -t hotel-cms:latest .

up:
	docker-compose up -d --build

down:
	docker-compose down

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

db-init:
	kubectl exec -i -n hotel-cms statefulset/mysql -- mysql -u root -pmysql_root_k3s_secure_pass_2026 hotel_website < schema.sql
	kubectl exec -i -n hotel-cms statefulset/mysql -- mysql -u root -pmysql_root_k3s_secure_pass_2026 hotel_website < seed.sql
