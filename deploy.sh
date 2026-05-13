#!/bin/bash
# Script de deploy para el VPS
# Uso: bash deploy.sh

set -e

echo "=== Deploy Restaurante Backend ==="

# 1. Pull latest code
echo "→ Pulling latest code..."
git pull origin main

# 2. Rebuild and restart containers
echo "→ Rebuilding Docker containers..."
docker compose build --no-cache app
docker compose up -d

# 3. Run migrations in all tenant DBs
echo "→ Running central migrations..."
docker compose exec app php artisan migrate --path=database/migrations --force

echo "→ Running tenant migrations..."
docker compose exec app php artisan tenants:migrate --force

# 4. Clear caches
echo "→ Clearing caches..."
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache

echo ""
echo "✓ Deploy completado!"
echo "  Backend disponible en puerto 8100"
