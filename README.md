# 🏨 Indra Hotel CMS

Indra Hotel CMS is a modern, high-performance Content Management System & Booking Engine tailored for boutique hotels and luxury resorts. Built on PHP 8.2 with Apache, it features a clean dynamic routing system, multi-language internationalization (i18n), customizable themes, media asset management, and flexible database support for both **MySQL** and **SQLite**.

---

## 📋 Table of Contents

- [Features](#-features)
- [Project Architecture](#-project-architecture)
- [🛠️ Development Guide](#️-development-guide)
  - [Prerequisites](#prerequisites)
  - [Option A: PHP Built-in Server (Fast Dev)](#option-a-php-built-in-server-fast-dev)
  - [Option B: Docker Compose Dev Environment](#option-b-docker-compose-dev-environment)
  - [Default Admin Credentials](#default-admin-credentials)
- [🚀 Production Deployment Guide (K3s & Docker)](#-production-deployment-guide-k3s--docker)
  - [Architecture Overview](#architecture-overview)
  - [Prerequisites](#prerequisites-1)
  - [One-Command K3s Deployment](#one-command-k3s-deployment)
  - [Cloudflare Tunnel Integration](#cloudflare-tunnel-integration)
  - [Kubernetes Manifest Structure](#kubernetes-manifest-structure)
- [🔒 Security & Production Hardening](#-security--production-hardening)
- [🛠️ Maintenance & Useful Commands](#️-maintenance--useful-commands)

---

## ✨ Features

- **Guest Booking Engine**: Real-time room availability, promo code discounts, and booking management.
- **Admin Management Portal**: Manage rooms, room types, amenities, bookings, dining & wellness, location spots, and custom pages.
- **Theme & Page Builder**: Customizable responsive themes (`royal-khmer`, `minimal-sanctuary`, `coastal-breeze`, etc.) and dynamic drag-and-drop page builder.
- **Multi-Language (i18n)**: Built-in translation manager for multi-language content.
- **Media Asset Manager**: Chunked file upload manager with WebP/PNG/JPEG compression.
- **Auto Database Initialization**: Automatically creates and migrates database schemas on startup.

---

## 🏗️ Project Architecture

```
├── admin/                  # Admin portal dashboard & controllers
├── api/                    # Application API endpoints (JSON / POST handlers)
├── assets/                 # Frontend CSS, JavaScript, icons, and static images
├── config.php              # Global configuration & environment variable loader
├── database.sqlite         # Local fallback SQLite database
├── Dockerfile              # Production PHP 8.2 Apache Dockerfile
├── docker-compose.yml      # Multi-container Docker Compose file
├── health.php              # Health probe endpoint for Kubernetes / Docker
├── includes/               # Core application modules (db.php, auth.php, helpers.php)
├── index.php               # Front controller & main entry point
├── k8s/                    # Production Kubernetes / K3s manifests
│   ├── 00-namespace.yaml
│   ├── 01-config-secret.yaml
│   ├── 02-pvc.yaml
│   ├── 03-mysql.yaml
│   ├── 04-app-deployment.yaml
│   └── 05-ingress-service.yaml
├── Makefile                # DevOps automation commands
├── router.php              # Router script for PHP CLI web server
├── schema.sql              # Base database schema
├── scripts/                # Deployment scripts (build-and-deploy.sh)
└── uploads/                # User media uploads directory (volume mounted in prod)
```

---

## 🛠️ Development Guide

### Prerequisites
- **PHP**: 8.1 or higher (extensions: `pdo`, `pdo_sqlite`, `pdo_mysql`, `gd`, `zip`, `mbstring`)
- **Database**: SQLite (default for dev) or MySQL 8.0
- **Docker & Docker Compose** (Optional for containerized dev)

---

### Option A: PHP Built-in Server (Fast Dev)

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd hotel_website_project_cms
   ```

2. **Start the local server**:
   ```bash
   php -S localhost:8000 router.php
   ```

3. **Open in browser**:
   Navigate to [http://localhost:8000](http://localhost:8000). The database schema will automatically initialize using `database.sqlite`.

---

### Option B: Docker Compose Dev Environment

Run the full production-like stack locally using Docker Compose (PHP Apache container + MySQL database container):

1. **Start the containers**:
   ```bash
   make up
   # or
   docker-compose up -d --build
   ```

2. **Access the application**:
   - Web Application: [http://localhost:8000](http://localhost:8000)
   - MySQL Database: `localhost:3306`

3. **Stop containers**:
   ```bash
   make down
   ```

---

### Default Admin Credentials

When the database is initialized for the first time, default admin credentials are generated:

- **Login URL**: [http://localhost:8000/admin/login.php](http://localhost:8000/admin/login.php)
- **Email**: `admin@hotel.com`
- **Password**: `admin123456`

*(Be sure to update password in the Admin Settings upon first login).*

---

## 🚀 Production Deployment Guide (K3s & Docker)

### Architecture Overview

```
 [ Internet ] 
      │ (HTTPS SSL)
      ▼
 [ Cloudflare Tunnel (cloudflared) ] (on homelab host)
      │
      ▼ (HTTP localhost:8000)
 [ K3s Traefik Ingress / NodePort 8000 ]
      │
  ┌───┴─────────────────────────┐
  │                             │
  ▼                             ▼
[ App Pod 1 ]                [ App Pod 2 ]  (Stateless PHP 8.2 Apache)
  │                             │
  ├─────────────┬───────────────┤
  ▼             ▼               ▼
[ Uploads PVC ] [ MySQL DB ] [ Health Probes ]
```

---

### Prerequisites

1. **Homelab Server**: Fedora Server (or any Linux distribution) with K3s Kubernetes installed.
2. **Cloudflare Tunnel**: `cloudflared` installed and running on the host, configured to route `hotel.kong41.com` to `http://localhost:8000`.
3. **Docker**: Installed on the host server to build container images (`sudo dnf install -y docker`).

---

### 🎩 Fedora Server Specific Setup (SELinux & Firewalld)

Fedora Server comes with **SELinux** (Enforcing mode) and **firewalld** enabled by default. Follow these steps to ensure smooth operation of K3s, Docker, and Cloudflare Tunnel:

#### 1. Install K3s with SELinux Support
Before installing K3s on Fedora Server, install the K3s SELinux policy package:
```bash
# Install SELinux container dependencies
sudo dnf install -y container-selinux selinux-policy-base

# Install K3s SELinux policy RPM
sudo dnf install -y https://github.com/k3s-io/k3s-selinux/releases/download/v1.6.Stable.1/k3s-selinux-1.6-1.el8.noarch.rpm

# Install K3s
curl -sfL https://get.k3s.io | sh -
```

#### 2. Configure Firewalld Rules (Restrict Port 8000 to Localhost Only)
Block LAN network access (`192.168.x.x` / `10.x.x.x`) to port `8000` while allowing `127.0.0.1` (localhost for Cloudflare Tunnel) and trusting K3s container network interfaces:

```bash
# 1. Remove open public port 8000 if previously added
sudo firewall-cmd --permanent --remove-port=8000/tcp

# 2. Allow port 8000 ONLY for local loopback (127.0.0.1 - Cloudflare Tunnel)
sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="127.0.0.1" port port="8000" protocol="tcp" accept'

# 3. Block/Drop all LAN attempts to port 8000
sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" port port="8000" protocol="tcp" drop'

# 4. Trust K3s CNI interfaces for pod-to-pod communication
sudo firewall-cmd --zone=trusted --add-interface=cni0 --permanent
sudo firewall-cmd --zone=trusted --add-interface=flannel.1 --permanent

# 5. Reload firewalld
sudo firewall-cmd --reload
```

#### 3. Enable & Start Docker Service on Fedora
```bash
sudo dnf install -y docker
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

---

### One-Command K3s Deployment

To build the Docker image locally and deploy all Kubernetes resources to your K3s cluster:

```bash
make k3s-deploy
```

*Or run the deploy script directly:*

```bash
./scripts/build-and-deploy.sh
```

#### What happens during deployment:
1. **Local Build**: Builds Docker image `hotel-cms:latest` locally (no external container registry required).
2. **K3s Import**: Imports the image directly into K3s containerd storage (`k3s ctr images import`).
3. **Manifest Apply**: Applies all manifests in `k8s/` (`Namespace`, `ConfigMap`, `Secret`, `PVC`, `StatefulSet`, `Deployment`, `Service`, `Ingress`).
4. **Health Rollout**: Waits for MySQL and web pods to complete rolling update.

---

### Cloudflare Tunnel Integration

Your Cloudflare Tunnel forwards ingress traffic for `hotel.kong41.com` to `localhost:8000`.

The K3s `NodePort` Service exposes the application on host port `8000`:
- **Ingress URL**: `https://hotel.kong41.com`
- **Internal Cluster Port**: `30800` (NodePort mapped to host port `8000`)

---

### Kubernetes Manifest Structure

All manifests are located in the `k8s/` directory:

| Manifest | Resource Type | Description |
| :--- | :--- | :--- |
| `00-namespace.yaml` | `Namespace` | Creates isolated `hotel-cms` namespace. |
| `01-config-secret.yaml` | `ConfigMap` / `Secret` | Sets `DB_DRIVER=mysql`, `DB_HOST=mysql-service`, and database credentials. |
| `02-pvc.yaml` | `PersistentVolumeClaim` | Storage for media uploads (`uploads-pvc` - 10Gi) and MySQL database (`mysql-pvc` - 20Gi). |
| `03-mysql.yaml` | `StatefulSet` / `Service` | MySQL 8.0 instance with persistent volume mount and health probes. |
| `04-app-deployment.yaml` | `Deployment` | Stateless PHP web app scaled to 2 replicas (`replicas: 2`) with Liveness & Readiness checks (`/health.php`). |
| `05-ingress-service.yaml` | `Service` / `Ingress` | Exposes NodePort and Traefik Ingress routes for `hotel.kong41.com`. |

---

## 🔒 Security & Production Hardening

- **OPcache Enabled**: Fast opcode caching configured in `Dockerfile` for high performance.
- **Apache Security Headers**: `X-Frame-Options`, `X-Content-Type-Options`, and `ServerTokens Prod` enforced.
- **Stateless App Pods**: Web servers store zero state locally; all user media files are mounted via `uploads-pvc`.
- **Health Check Endpoint**: [`/health.php`](file:///Users/kong41/Desktop/hotel_website_project_cms/health.php) returns HTTP status 200 JSON when PHP and MySQL database connections are healthy.
- **Sensitive Files Denied**: Direct HTTP access to `.sqlite`, `.sql`, `.env`, `.ini`, `.log`, and `.git` is blocked via `.htaccess` and Apache rules.

---

## 🛠️ Maintenance & Deployment Commands

| Command | Description |
| :--- | :--- |
| `make deploy` / `make k3s-deploy` | Rebuild Docker image with unique tag & deploy to K3s cluster. |
| `make rollback` | Instant rollback to the previous deployment revision. |
| `make history` | View deployment rollout revision history. |
| `make up` | Start local Docker Compose stack & auto-migrate database. |
| `make down` | Stop local Docker Compose stack. |
| `make db-create` | Initialize database schema and seed data. |
| `make db-migrate` | Apply new database tables & column changes without losing data. |
| `make db-reset` | Drop & recreate fresh database with `schema.sql` and `seed.sql`. |
| `make deploy-all` | Rebuild code + apply database migrations + deploy to K3s in one command. |
| `make restart` | Force rolling restart of app pods without rebuilding image. |
| `make status` | Check status of K3s pods, services, PVCs, and ingress. |
| `make logs` | Tail live logs from K3s application pods. |
| `make k3s-delete` | Remove all application resources from K3s cluster. |

---

## 🚀 Standard Deployment Guide (Step-by-Step)

Follow these steps whenever you modify PHP source code, assets, database schemas, or configuration:

### Step 1: Commit & Pull Code Changes
On your local machine, commit and push your changes:
```bash
git add .
git commit -m "Your update description"
git push origin main
```

On your live production server, pull the latest updates:
```bash
git pull origin main
```

### Step 2: Run Standard Deployment
Execute the single deployment command:
```bash
make deploy
```
*(Or specify a custom version tag: `./scripts/build-and-deploy.sh v1.0.2`)*

#### What happens during standard deployment:
1. **Unique Image Tagging**: Generates a unique version tag (e.g. `hotel-cms:v20260824112350`).
2. **Local Container Build**: Builds the Docker image without cache.
3. **Containerd Import**: Imports the image into K3s containerd storage (`k8s.io` namespace).
4. **Kubernetes Image Update**: Runs `kubectl set image deployment/hotel-cms-app app=hotel-cms:<tag>`, forcing Kubernetes to replace old pods with brand-new ones.
5. **Database Auto-Migration**: Runs `db-sync.sh migrate` and `install.php` to apply any new database tables/columns without wiping existing data.

### Step 3: Verify Deployment Status
Check that all pods are running and healthy:
```bash
make status
```

---

## ⏪ Rollback Guide (Step-by-Step)

If an issue occurs after deployment and you need to revert to a previous working version:

### Option 1: Quick Rollback to Previous Version (1 Command)
To instantly undo the last deployment and restore the previous pods:

```bash
make rollback
# (or ./scripts/rollback.sh)
```

---

### Option 2: Rollback to a Specific Revision Number

1. **View Deployment Revision History**:
   ```bash
   make history
   # or: kubectl rollout history deployment/hotel-cms-app -n hotel-cms
   ```

2. **Rollback to Desired Revision (e.g., Revision 2)**:
   ```bash
   ./scripts/rollback.sh 2
   # or: kubectl rollout undo deployment/hotel-cms-app -n hotel-cms --to-revision=2
   ```

3. **Verify Rollback Completion**:
   ```bash
   make status
   ```

---

## 📄 License

This project is proprietary software created for Indra Hotel Phnom Penh Co., Ltd. All rights reserved.


