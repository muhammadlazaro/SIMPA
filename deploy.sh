#!/bin/bash
set -e

echo "=========================================="
echo "🚀 Starting Deployment for SIMPA Project..."
echo "=========================================="

echo "1/6 Installing Dependencies (Nginx, PHP, MySQL, Node.js)..."
sudo apt update
sudo apt install -y nginx git curl unzip software-properties-common
sudo add-apt-repository ppa:ondrej/php -y || true
sudo apt update
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-bcmath
sudo apt install -y mysql-server

if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

if ! command -v node &> /dev/null; then
    echo "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt install -y nodejs
fi

echo "2/6 Configuring MySQL Database..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS smpa_db;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'simpa'@'localhost' IDENTIFIED BY 'simpa_secret';"
sudo mysql -e "GRANT ALL PRIVILEGES ON smpa_db.* TO 'simpa'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

echo "3/6 Cloning Repository..."
cd /var/www
if [ -d "simpa" ]; then
    sudo rm -rf simpa
fi
sudo git clone https://github.com/muhammadlazaro/SIMPA.git simpa
sudo chown -R $USER:$USER /var/www/simpa
cd simpa

echo "4/6 Setting up Backend (Laravel)..."
cd backend
composer install --optimize-autoloader --no-dev
cp .env.example .env
sed -i 's/DB_DATABASE=laravel/DB_DATABASE=smpa_db/g' .env
sed -i 's/DB_USERNAME=root/DB_USERNAME=simpa/g' .env
sed -i 's/DB_PASSWORD=/DB_PASSWORD=simpa_secret/g' .env
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
cd ..

echo "5/6 Setting up Frontend (Vue.js)..."
cd frontend
cp .env.production.example .env.production
sed -i 's|VITE_API_URL=.*|VITE_API_URL=http://simpa.plutolab.my.id/api|g' .env.production
npm install
npm run build
cd ..

echo "6/6 Configuring Nginx Web Server..."
sudo tee /etc/nginx/sites-available/simpa > /dev/null <<EOF
server {
    listen 80;
    server_name simpa.plutolab.my.id;

    # Frontend Vue
    location / {
        root /var/www/simpa/frontend/dist;
        try_files \$uri \$uri/ /index.html;
    }

    # Backend Laravel API
    location /api {
        alias /var/www/simpa/backend/public;
        try_files \$uri \$uri/ @laravel;
    }

    location @laravel {
        rewrite ^/api/(.*)$ /api/index.php?/\$1 last;
    }

    location ~ ^/api/index\.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/simpa/backend/public/index.php;
    }
}
EOF

if [ -f "/etc/nginx/sites-enabled/default" ]; then
    sudo rm /etc/nginx/sites-enabled/default
fi
sudo ln -sf /etc/nginx/sites-available/simpa /etc/nginx/sites-enabled/simpa
sudo nginx -t
sudo systemctl restart nginx

echo "=========================================="
echo "✅ Deployment Completed Successfully!"
echo "🌐 Access the app at: http://simpa.plutolab.my.id"
echo "=========================================="
