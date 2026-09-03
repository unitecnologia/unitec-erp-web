#!/usr/bin/env bash
#
# Cloud Agent / Linux per-boot startup for Unitec ERP.
#
# Brings up MariaDB (its data directory is part of the environment snapshot)
# and then runs the Laravel dev server in the foreground so the agent can
# reach the ERP at http://127.0.0.1:8000/admin.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

if ! sudo mysqladmin ping >/dev/null 2>&1; then
    sudo service mariadb start
    for _ in $(seq 1 30); do
        sudo mysqladmin ping >/dev/null 2>&1 && break
        sleep 1
    done
fi

exec php artisan serve --host=0.0.0.0 --port=8000
