#!/usr/bin/env bash
#
# Cloud Agent / Linux development bootstrap for Unitec ERP.
#
# Idempotent: safe to run repeatedly. Prepares the PHP/Laravel app to run
# end-to-end against a local MariaDB instance. System packages (PHP 8.4,
# Composer, MariaDB, Node) are expected to already be present on the image;
# this script only performs repository-derived setup after checkout.
#
# Notes for this repository:
#   * The `unitec_empresas` table has 240+ columns. Under utf8mb4 a fresh
#     `migrate` transiently exceeds MySQL's hard 65,535-byte row limit before
#     the later `shrink_empresas_varchar_to_text` migration runs, so the dev
#     connection uses utf8mb3 (3 bytes/char) which keeps every migration under
#     the limit. Long-lived production databases on utf8mb4 are unaffected.
#   * APP_DEBUG is left off because the custom Filament login Blade view trips
#     Livewire's dev-only "multiple root elements" strict check when debug is
#     on. This matches the shipped production template (.env.mysql.example).

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="unitec_erp"
DB_USER="root"
DB_PASS="rua@2050bc"
DB_CHARSET="utf8mb3"
DB_COLLATION="utf8mb3_unicode_ci"

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

# ---------------------------------------------------------------------------
# 1. MariaDB: ensure the server is running and the dev database/user exist.
# ---------------------------------------------------------------------------
# skip-name-resolve keeps TCP clients from being reverse-resolved to
# "localhost" (which would match the socket-only root@localhost account and
# reject the password). Matches the repository's bundled tools/mysql/my.ini.
MARIADB_CNF="/etc/mysql/mariadb.conf.d/99-unitec-erp.cnf"
if [ ! -f "$MARIADB_CNF" ]; then
    log "Applying MariaDB skip-name-resolve config"
    printf '[mysqld]\nskip-name-resolve\n' | sudo tee "$MARIADB_CNF" >/dev/null
    sudo service mariadb restart || true
fi

log "Ensuring MariaDB is running"
if ! sudo mysqladmin ping >/dev/null 2>&1; then
    sudo service mariadb start
    for _ in $(seq 1 30); do
        sudo mysqladmin ping >/dev/null 2>&1 && break
        sleep 1
    done
fi

log "Ensuring database '${DB_NAME}' and root TCP access"
# root@localhost keeps its default unix_socket auth (used here via `sudo mysql`).
# The application connects over TCP as root@127.0.0.1 with a password.
sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET ${DB_CHARSET} COLLATE ${DB_COLLATION};
CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON *.* TO '${DB_USER}'@'${DB_HOST}' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------------------
# 2. PHP dependencies.
# ---------------------------------------------------------------------------
log "Installing Composer dependencies"
composer install --no-interaction --no-progress

# ---------------------------------------------------------------------------
# 3. Environment file.
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
    log "Creating .env from .env.example"
    cp .env.example .env
fi

set_env() {
    local key="$1" value="$2"
    if grep -qE "^${key}=" .env; then
        # Use a non-/ delimiter because values may contain slashes.
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

log "Configuring .env for local MariaDB (utf8mb3)"
set_env DB_CONNECTION mysql
set_env DB_HOST "$DB_HOST"
set_env DB_PORT "$DB_PORT"
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASS"
set_env DB_CHARSET "$DB_CHARSET"
set_env DB_COLLATION "$DB_COLLATION"
set_env APP_DEBUG false

if ! grep -qE '^APP_KEY=base64:' .env; then
    log "Generating application key"
    php artisan key:generate --force
fi

# ---------------------------------------------------------------------------
# 4. Database schema + baseline data.
# ---------------------------------------------------------------------------
log "Running migrations"
php artisan migrate --force

# Seed the standard install (user USUARIO + fiscal tables) only once.
if [ "$(php artisan tinker --execute='echo \App\Models\User::query()->count();' 2>/dev/null | tail -n1 | tr -dc '0-9')" = "0" ]; then
    log "Seeding initial data"
    php artisan db:seed --force
else
    log "Users already present; skipping seed"
fi

# ---------------------------------------------------------------------------
# 5. Front-end assets.
# ---------------------------------------------------------------------------
log "Installing npm packages"
npm install --no-audit --no-fund

log "Building front-end assets"
npm run build

log "Bootstrap complete"
