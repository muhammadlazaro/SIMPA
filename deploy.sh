#!/bin/bash
set -euo pipefail

generate_secret() {
    od -An -N24 -tx1 /dev/urandom | tr -d ' \n'
}

APP_NAME="simpa"
APP_DIR="/var/www/${APP_NAME}"
REPO_URL="https://github.com/muhammadlazaro/SIMPA.git"
BRANCH="${BRANCH:-master}"
DOMAIN="${DOMAIN:-simpa.plutolab.my.id}"
API_BASE_URL="${API_BASE_URL:-https://${DOMAIN}}"

DB_DATABASE="${DB_DATABASE:-smpa_db}"
DB_USERNAME="${DB_USERNAME:-simpa}"
DB_PASSWORD="${DB_PASSWORD:-$(generate_secret)}"
DB_RUNTIME_USERNAME="${DB_RUNTIME_USERNAME:-${DB_USERNAME}}"
DB_RUNTIME_PASSWORD="${DB_RUNTIME_PASSWORD:-${DB_PASSWORD}}"
DB_BACKUP_USERNAME="${DB_BACKUP_USERNAME:-${DB_USERNAME}_backup}"
DB_BACKUP_PASSWORD="${DB_BACKUP_PASSWORD:-$(generate_secret)}"
DB_SOCKET="${DB_SOCKET:-/var/run/mysqld/mysqld.sock}"
MYSQL_ATTR_SSL_CA="${MYSQL_ATTR_SSL_CA:-}"
RUN_SEED="${RUN_SEED:-false}"

PHP_VERSION="8.2"
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
PHP_FPM_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"
PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT:-256M}"
PHP_POST_MAX_SIZE="${PHP_POST_MAX_SIZE:-12M}"
PHP_UPLOAD_MAX_FILESIZE="${PHP_UPLOAD_MAX_FILESIZE:-10M}"
PHP_MAX_EXECUTION_TIME="${PHP_MAX_EXECUTION_TIME:-60}"
PHP_MAX_INPUT_TIME="${PHP_MAX_INPUT_TIME:-60}"

CLIENT_MAX_BODY_SIZE="${CLIENT_MAX_BODY_SIZE:-12M}"
ENABLE_ORIGIN_TLS="${ENABLE_ORIGIN_TLS:-false}"
TLS_CERT_PATH="${TLS_CERT_PATH:-/etc/ssl/simpa/fullchain.pem}"
TLS_KEY_PATH="${TLS_KEY_PATH:-/etc/ssl/simpa/privkey.pem}"

LOG_STACK="${LOG_STACK:-daily}"
LOG_LEVEL="${LOG_LEVEL:-info}"
LOG_DAILY_DAYS="${LOG_DAILY_DAYS:-14}"

ENABLE_DB_BACKUP="${ENABLE_DB_BACKUP:-true}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/simpa/mysql}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
BACKUP_ENCRYPTION_PASSPHRASE="${BACKUP_ENCRYPTION_PASSPHRASE:-}"

sql_string() {
    printf "%s" "$1" | sed "s/'/''/g"
}

sql_identifier() {
    printf "%s" "$1" | sed 's/`/``/g'
}

check_mysql_login() {
    local username="$1"
    local password="$2"
    local database="$3"
    local mysql_args=()

    if [[ -S "${DB_SOCKET}" ]]; then
        mysql_args+=(--socket="${DB_SOCKET}")
    else
        mysql_args+=(--host=127.0.0.1 --port=3306)
    fi

    MYSQL_PWD="${password}" mysql "${mysql_args[@]}" \
        --user="${username}" \
        --database="${database}" \
        --execute="SELECT 1;" >/dev/null
}

echo "=========================================="
echo "Starting Deployment for SIMPA Project..."
echo "Branch: ${BRANCH}"
echo "App dir: ${APP_DIR}"
echo "Domain: ${DOMAIN}"
echo "=========================================="

echo "1/7 Installing system dependencies..."
sudo apt update
sudo apt install -y nginx git curl unzip software-properties-common mysql-server openssl
sudo add-apt-repository ppa:ondrej/php -y || true
sudo apt update
sudo apt install -y \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-bcmath"

echo "Configuring PHP runtime limits..."
sudo tee "/etc/php/${PHP_VERSION}/fpm/conf.d/99-simpa-limits.ini" > /dev/null <<EOF
memory_limit=${PHP_MEMORY_LIMIT}
post_max_size=${PHP_POST_MAX_SIZE}
upload_max_filesize=${PHP_UPLOAD_MAX_FILESIZE}
max_execution_time=${PHP_MAX_EXECUTION_TIME}
max_input_time=${PHP_MAX_INPUT_TIME}
EOF
sudo tee "/etc/php/${PHP_VERSION}/cli/conf.d/99-simpa-limits.ini" > /dev/null <<EOF
memory_limit=${PHP_MEMORY_LIMIT}
post_max_size=${PHP_POST_MAX_SIZE}
upload_max_filesize=${PHP_UPLOAD_MAX_FILESIZE}
max_execution_time=${PHP_MAX_EXECUTION_TIME}
max_input_time=${PHP_MAX_INPUT_TIME}
EOF

if ! command -v composer >/dev/null 2>&1; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

if ! command -v node >/dev/null 2>&1; then
    echo "Installing Node.js..."
    curl --proto '=https' --tlsv1.2 -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt install -y nodejs
fi

echo "2/7 Ensuring MySQL database exists..."
DB_DATABASE_SQL="$(sql_identifier "${DB_DATABASE}")"
DB_RUNTIME_USERNAME_SQL="$(sql_string "${DB_RUNTIME_USERNAME}")"
DB_RUNTIME_PASSWORD_SQL="$(sql_string "${DB_RUNTIME_PASSWORD}")"
DB_BACKUP_USERNAME_SQL="$(sql_string "${DB_BACKUP_USERNAME}")"
DB_BACKUP_PASSWORD_SQL="$(sql_string "${DB_BACKUP_PASSWORD}")"

sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE_SQL}\`;

CREATE USER IF NOT EXISTS '${DB_RUNTIME_USERNAME_SQL}'@'localhost' IDENTIFIED BY '${DB_RUNTIME_PASSWORD_SQL}';
ALTER USER '${DB_RUNTIME_USERNAME_SQL}'@'localhost' IDENTIFIED BY '${DB_RUNTIME_PASSWORD_SQL}';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${DB_RUNTIME_USERNAME_SQL}'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, SHOW VIEW, TRIGGER, EVENT ON \`${DB_DATABASE_SQL}\`.* TO '${DB_RUNTIME_USERNAME_SQL}'@'localhost';

CREATE USER IF NOT EXISTS '${DB_BACKUP_USERNAME_SQL}'@'localhost' IDENTIFIED BY '${DB_BACKUP_PASSWORD_SQL}';
ALTER USER '${DB_BACKUP_USERNAME_SQL}'@'localhost' IDENTIFIED BY '${DB_BACKUP_PASSWORD_SQL}';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${DB_BACKUP_USERNAME_SQL}'@'localhost';
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES ON \`${DB_DATABASE_SQL}\`.* TO '${DB_BACKUP_USERNAME_SQL}'@'localhost';

FLUSH PRIVILEGES;
SQL

grant_runtime_migration_privileges() {
    sudo mysql <<SQL
GRANT ALL PRIVILEGES ON \`${DB_DATABASE_SQL}\`.* TO '${DB_RUNTIME_USERNAME_SQL}'@'localhost';
FLUSH PRIVILEGES;
SQL
}

restore_runtime_app_privileges() {
    sudo mysql <<SQL
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${DB_RUNTIME_USERNAME_SQL}'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, SHOW VIEW, TRIGGER, EVENT ON \`${DB_DATABASE_SQL}\`.* TO '${DB_RUNTIME_USERNAME_SQL}'@'localhost';
FLUSH PRIVILEGES;
SQL
}

if ! check_mysql_login "${DB_RUNTIME_USERNAME}" "${DB_RUNTIME_PASSWORD}" "${DB_DATABASE}"; then
    echo "ERROR: MySQL runtime user cannot connect after provisioning."
    echo "User: ${DB_RUNTIME_USERNAME}@localhost"
    echo "Check MySQL root access and DB_PASSWORD."
    exit 1
fi

echo "3/7 Syncing repository..."
sudo mkdir -p "$(dirname "${APP_DIR}")"
sudo chown -R "$USER:$USER" "$(dirname "${APP_DIR}")"

if [[ -d "${APP_DIR}/.git" ]]; then
    cd "${APP_DIR}"
    git fetch origin "${BRANCH}"
    git checkout "${BRANCH}"
    git pull --ff-only origin "${BRANCH}"
elif [[ -d "${APP_DIR}" ]] && [[ "$(find "${APP_DIR}" -mindepth 1 -maxdepth 1 | wc -l)" -gt 0 ]]; then
    echo "ERROR: ${APP_DIR} exists but is not a git repository."
    echo "Move it away or convert it to a git checkout before deploying."
    exit 1
else
    git clone --branch "${BRANCH}" "${REPO_URL}" "${APP_DIR}"
    cd "${APP_DIR}"
fi

echo "4/7 Setting up backend..."
cd "${APP_DIR}/backend"

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

set_env_value() {
    local key="$1"
    local value="$2"
    local escaped_value

    escaped_value="$(printf "%s" "${value}" | sed -e 's/[\/&|\\]/\\&/g')"

    if grep -qE "^#?${key}=" .env; then
        sed -i "s|^#\\?${key}=.*|${key}=${escaped_value}|g" .env
    else
        printf "\n%s=%s\n" "${key}" "${value}" >> .env
    fi
}

restore_runtime_db_env() {
    set_env_value "DB_USERNAME" "${DB_RUNTIME_USERNAME}"
    set_env_value "DB_PASSWORD" "${DB_RUNTIME_PASSWORD}"
}

set_env_value "APP_NAME" "\"Sistem Informasi Manajemen Pengembangan Aplikasi\""
set_env_value "APP_ENV" "production"
set_env_value "APP_DEBUG" "false"
set_env_value "APP_URL" "https://${DOMAIN}"
set_env_value "FRONTEND_URL" "https://${DOMAIN}"
set_env_value "CORS_ALLOWED_ORIGINS" "https://${DOMAIN}"
set_env_value "SANCTUM_STATEFUL_DOMAINS" "${DOMAIN}"
set_env_value "SESSION_SECURE_COOKIE" "true"
set_env_value "SESSION_HTTP_ONLY" "true"
set_env_value "SESSION_SAME_SITE" "lax"
set_env_value "LOG_CHANNEL" "stack"
set_env_value "LOG_STACK" "${LOG_STACK}"
set_env_value "LOG_LEVEL" "${LOG_LEVEL}"
set_env_value "LOG_DAILY_DAYS" "${LOG_DAILY_DAYS}"
set_env_value "DB_CONNECTION" "mysql"
if [[ -S "${DB_SOCKET}" ]]; then
    set_env_value "DB_HOST" "localhost"
    set_env_value "DB_SOCKET" "${DB_SOCKET}"
else
    set_env_value "DB_HOST" "127.0.0.1"
    set_env_value "DB_SOCKET" ""
fi
set_env_value "DB_PORT" "3306"
set_env_value "DB_DATABASE" "${DB_DATABASE}"
set_env_value "DB_USERNAME" "${DB_RUNTIME_USERNAME}"
set_env_value "DB_PASSWORD" "${DB_RUNTIME_PASSWORD}"

if [[ -n "${MYSQL_ATTR_SSL_CA}" ]]; then
    set_env_value "MYSQL_ATTR_SSL_CA" "${MYSQL_ATTR_SSL_CA}"
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    GENERATED_APP_KEY="$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")"
    set_env_value "APP_KEY" "${GENERATED_APP_KEY}"
fi

composer install --optimize-autoloader --no-dev

php artisan config:clear || true
if ! check_mysql_login "${DB_RUNTIME_USERNAME}" "${DB_RUNTIME_PASSWORD}" "${DB_DATABASE}"; then
    echo "ERROR: MySQL runtime user cannot connect before running migrations."
    restore_runtime_db_env
    exit 1
fi

grant_runtime_migration_privileges
if ! php artisan migrate --force; then
    echo "ERROR: Laravel migrations failed."
    echo "Restoring runtime database credentials and least-privilege grants."
    restore_runtime_app_privileges
    restore_runtime_db_env
    php artisan config:clear || true
    exit 1
fi
restore_runtime_app_privileges

if [[ "${RUN_SEED}" = "true" ]]; then
    php artisan db:seed --force
fi

restore_runtime_db_env

mkdir -p resources/views
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 770 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 660 {} \;

echo "5/7 Building frontend..."
cd "${APP_DIR}/frontend"
cat > .env.production <<EOF
VITE_API_BASE_URL=${API_BASE_URL}
VITE_APP_NAME="Sistem Informasi Manajemen Pengembangan Aplikasi"
VITE_DEBUG=false
VITE_API_TIMEOUT=30000
EOF

if [[ -f package-lock.json ]]; then
    npm ci --ignore-scripts
else
    npm install --ignore-scripts
fi
npm run build

echo "6/7 Configuring Nginx..."
if [[ "${ENABLE_ORIGIN_TLS}" = "true" ]]; then
    if ! sudo test -r "${TLS_CERT_PATH}" || ! sudo test -r "${TLS_KEY_PATH}"; then
        echo "ERROR: ENABLE_ORIGIN_TLS=true but TLS cert/key is not readable."
        echo "Expected cert: ${TLS_CERT_PATH}"
        echo "Expected key : ${TLS_KEY_PATH}"
        exit 1
    fi

    sudo tee /etc/nginx/sites-available/simpa > /dev/null <<EOF
server {
    listen 80;
    server_name ${DOMAIN};

    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${DOMAIN};

    ssl_certificate ${TLS_CERT_PATH};
    ssl_certificate_key ${TLS_KEY_PATH};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;

    root ${APP_DIR}/frontend/dist;
    index index.html;
    autoindex off;
    client_max_body_size ${CLIENT_MAX_BODY_SIZE};

    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https://${DOMAIN}; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    add_header Cross-Origin-Opener-Policy "same-origin" always;
    add_header Cross-Origin-Resource-Policy "same-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location /storage/ {
        alias ${APP_DIR}/backend/public/storage/;
    }

    location ^~ /api/ {
        root ${APP_DIR}/backend/public;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /api {
        root ${APP_DIR}/backend/public;
        try_files \$uri /index.php?\$query_string;
    }

    location = /health {
        root ${APP_DIR}/backend/public;
        try_files \$uri /index.php?\$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_pass unix:${PHP_FPM_SOCKET};
        fastcgi_param SCRIPT_FILENAME ${APP_DIR}/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT ${APP_DIR}/backend/public;
    }

    location ~ /\. {
        deny all;
    }
}
EOF
else
    sudo tee /etc/nginx/sites-available/simpa > /dev/null <<EOF
server {
    listen 80;
    server_name ${DOMAIN};

    root ${APP_DIR}/frontend/dist;
    index index.html;
    autoindex off;
    client_max_body_size ${CLIENT_MAX_BODY_SIZE};

    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https://${DOMAIN}; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    add_header Cross-Origin-Opener-Policy "same-origin" always;
    add_header Cross-Origin-Resource-Policy "same-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location /storage/ {
        alias ${APP_DIR}/backend/public/storage/;
    }

    location ^~ /api/ {
        root ${APP_DIR}/backend/public;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /api {
        root ${APP_DIR}/backend/public;
        try_files \$uri /index.php?\$query_string;
    }

    location = /health {
        root ${APP_DIR}/backend/public;
        try_files \$uri /index.php?\$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_pass unix:${PHP_FPM_SOCKET};
        fastcgi_param SCRIPT_FILENAME ${APP_DIR}/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT ${APP_DIR}/backend/public;
    }

    location ~ /\. {
        deny all;
    }
}
EOF
fi

if [[ -f "/etc/nginx/sites-enabled/default" ]]; then
    sudo rm -f /etc/nginx/sites-enabled/default
fi
sudo ln -sf /etc/nginx/sites-available/simpa /etc/nginx/sites-enabled/simpa
sudo nginx -t

if [[ "${ENABLE_DB_BACKUP}" = "true" ]]; then
    echo "Configuring MySQL backup timer..."
    sudo install -m 0750 -o root -g root "${APP_DIR}/ops/mysql-backup.sh" /usr/local/sbin/simpa-mysql-backup
    sudo install -d -m 0700 -o root -g root "${BACKUP_DIR}"

    sudo tee /etc/simpa-backup.env > /dev/null <<EOF
DB_HOST=localhost
DB_PORT=3306
DB_SOCKET=${DB_SOCKET}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_BACKUP_USERNAME}
DB_PASSWORD=${DB_BACKUP_PASSWORD}
BACKUP_DIR=${BACKUP_DIR}
BACKUP_RETENTION_DAYS=${BACKUP_RETENTION_DAYS}
BACKUP_ENCRYPTION_PASSPHRASE=${BACKUP_ENCRYPTION_PASSPHRASE}
EOF
    sudo chmod 600 /etc/simpa-backup.env

    sudo tee /etc/systemd/system/simpa-db-backup.service > /dev/null <<EOF
[Unit]
Description=SIMPA MySQL backup

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/simpa-mysql-backup /etc/simpa-backup.env
EOF

    sudo tee /etc/systemd/system/simpa-db-backup.timer > /dev/null <<EOF
[Unit]
Description=Run SIMPA MySQL backup daily

[Timer]
OnCalendar=*-*-* 02:15:00
Persistent=true
Unit=simpa-db-backup.service

[Install]
WantedBy=timers.target
EOF

    sudo systemctl daemon-reload
    sudo systemctl enable --now simpa-db-backup.timer
fi

echo "7/7 Restarting services..."
sudo systemctl restart "${PHP_FPM_SERVICE}"
sudo systemctl reload nginx

echo "=========================================="
echo "Deployment completed successfully."
echo "Access the app at: https://${DOMAIN}"
echo "=========================================="
