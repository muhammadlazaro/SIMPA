#!/bin/bash
set -euo pipefail

APP_NAME="simpa"
APP_DIR="/var/www/${APP_NAME}"
REPO_URL="https://github.com/muhammadlazaro/SIMPA.git"
BRANCH="${BRANCH:-master}"
DOMAIN="${DOMAIN:-simpa.plutolab.my.id}"
API_BASE_URL="${API_BASE_URL:-https://${DOMAIN}}"

DB_DATABASE="${DB_DATABASE:-smpa_db}"
DB_USERNAME="${DB_USERNAME:-simpa}"
DB_PASSWORD="${DB_PASSWORD:-simpa_secret}"
RUN_SEED="${RUN_SEED:-false}"

PHP_VERSION="8.2"
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
PHP_FPM_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"

echo "=========================================="
echo "Starting Deployment for SIMPA Project..."
echo "Branch: ${BRANCH}"
echo "App dir: ${APP_DIR}"
echo "Domain: ${DOMAIN}"
echo "=========================================="

echo "1/7 Installing system dependencies..."
sudo apt update
sudo apt install -y nginx git curl unzip software-properties-common mysql-server
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

if ! command -v composer >/dev/null 2>&1; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

if ! command -v node >/dev/null 2>&1; then
    echo "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt install -y nodejs
fi

echo "2/7 Ensuring MySQL database exists..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\`;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

echo "3/7 Syncing repository..."
sudo mkdir -p "$(dirname "${APP_DIR}")"
sudo chown -R "$USER:$USER" "$(dirname "${APP_DIR}")"

if [ -d "${APP_DIR}/.git" ]; then
    cd "${APP_DIR}"
    git fetch origin "${BRANCH}"
    git checkout "${BRANCH}"
    git pull --ff-only origin "${BRANCH}"
elif [ -d "${APP_DIR}" ] && [ "$(find "${APP_DIR}" -mindepth 1 -maxdepth 1 | wc -l)" -gt 0 ]; then
    echo "ERROR: ${APP_DIR} exists but is not a git repository."
    echo "Move it away or convert it to a git checkout before deploying."
    exit 1
else
    git clone --branch "${BRANCH}" "${REPO_URL}" "${APP_DIR}"
    cd "${APP_DIR}"
fi

echo "4/7 Setting up backend..."
cd "${APP_DIR}/backend"

if [ ! -f .env ]; then
    cp .env.example .env
fi

set_env_value() {
    local key="$1"
    local value="$2"

    if grep -qE "^#?${key}=" .env; then
        sed -i "s|^#\\?${key}=.*|${key}=${value}|g" .env
    else
        printf "\n%s=%s\n" "${key}" "${value}" >> .env
    fi
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
set_env_value "DB_CONNECTION" "mysql"
set_env_value "DB_HOST" "127.0.0.1"
set_env_value "DB_PORT" "3306"
set_env_value "DB_DATABASE" "${DB_DATABASE}"
set_env_value "DB_USERNAME" "${DB_USERNAME}"
set_env_value "DB_PASSWORD" "${DB_PASSWORD}"

if ! grep -q '^APP_KEY=base64:' .env; then
    GENERATED_APP_KEY="$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")"
    set_env_value "APP_KEY" "${GENERATED_APP_KEY}"
fi

composer install --optimize-autoloader --no-dev

php artisan migrate --force

if [ "${RUN_SEED}" = "true" ]; then
    php artisan db:seed --force
fi

mkdir -p resources/views
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

echo "5/7 Building frontend..."
cd "${APP_DIR}/frontend"
cat > .env.production <<EOF
VITE_API_BASE_URL=${API_BASE_URL}
VITE_APP_NAME="Sistem Informasi Manajemen Pengembangan Aplikasi"
VITE_DEBUG=false
VITE_API_TIMEOUT=30000
EOF

if [ -f package-lock.json ]; then
    npm ci
else
    npm install
fi
npm run build

echo "6/7 Configuring Nginx..."
sudo tee /etc/nginx/sites-available/simpa > /dev/null <<EOF
server {
    listen 80;
    server_name ${DOMAIN};

    root ${APP_DIR}/frontend/dist;
    index index.html;

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

if [ -f "/etc/nginx/sites-enabled/default" ]; then
    sudo rm -f /etc/nginx/sites-enabled/default
fi
sudo ln -sf /etc/nginx/sites-available/simpa /etc/nginx/sites-enabled/simpa
sudo nginx -t

echo "7/7 Restarting services..."
sudo systemctl restart "${PHP_FPM_SERVICE}"
sudo systemctl reload nginx

echo "=========================================="
echo "Deployment completed successfully."
echo "Access the app at: https://${DOMAIN}"
echo "=========================================="
