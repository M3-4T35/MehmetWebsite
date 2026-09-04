#!/usr/bin/env bash
set -e

# ==============================================================================
# MehmetWebsite - Full Deployment & Database Setup Script
# ==============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "[-] Please run this script with sudo: sudo ./setup_and_deploy.sh"
  exit 1
fi

PROJECT_DIR="/var/www/MehmetWebsite"
cd "$PROJECT_DIR"

echo "[1/7] Installing required server packages (PHP 8.2+, Apache, PostgreSQL, Composer)..."
apt-get update
apt-get install -y \
  apache2 \
  libapache2-mod-php \
  php-cli \
  php-intl \
  php-pgsql \
  php-mbstring \
  php-xml \
  php-curl \
  php-zip \
  unzip \
  composer \
  postgresql \
  postgresql-contrib \
  certbot \
  python3-certbot-apache

echo "[+] Starting PostgreSQL service..."
systemctl enable --now postgresql

echo "[2/7] Configuring PostgreSQL Database..."
DB_NAME="mehmet_db"
DB_USER="mehmet_user"

# Prompt for DB password or generate one
if [ -z "$DB_PASS" ]; then
  DB_PASS=$(openssl rand -hex 16)
  echo "[+] Generated random DB password for $DB_USER: $DB_PASS"
fi

# Create Postgres User if not exists
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname = '$DB_USER'" | grep -q 1 || \
sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';"

# Update password if user already exists
sudo -u postgres psql -c "ALTER USER $DB_USER WITH PASSWORD '$DB_PASS';"

# Create Database if not exists
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'" | grep -q 1 || \
sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"

# Grant permissions (including PostgreSQL 15+ schema public permissions)
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"
sudo -u postgres psql -d "$DB_NAME" -c "GRANT ALL ON SCHEMA public TO $DB_USER;"
sudo -u postgres psql -d "$DB_NAME" -c "ALTER SCHEMA public OWNER TO $DB_USER;"

echo "[3/7] Setting up .env.local..."
if [ ! -f .env.local ]; then
  cp .env .env.local
  APP_SECRET=$(openssl rand -hex 32)
  sed -i "s|^APP_ENV=.*|APP_ENV=prod|" .env.local
  sed -i "s|^APP_DEBUG=.*|APP_DEBUG=0|" .env.local
  sed -i "s|^APP_SECRET=.*|APP_SECRET=$APP_SECRET|" .env.local
  sed -i "s|^DATABASE_URL=.*|DATABASE_URL=\"postgresql://$DB_USER:$DB_PASS@127.0.0.1:5432/$DB_NAME?serverVersion=16&charset=utf8\"|" .env.local
  echo "[+] Created .env.local with production configuration."
else
  echo "[!] .env.local already exists. Updating DATABASE_URL..."
  sed -i "s|^DATABASE_URL=.*|DATABASE_URL=\"postgresql://$DB_USER:$DB_PASS@127.0.0.1:5432/$DB_NAME?serverVersion=16&charset=utf8\"|" .env.local
fi

echo "[4/7] Installing Composer PHP dependencies..."
# Run as the directory owner to avoid running composer as root
OWNER=$(stat -c '%U' "$PROJECT_DIR")
sudo -u "$OWNER" composer install --no-dev --optimize-autoloader

echo "[5/7] Running database migrations..."
sudo -u "$OWNER" php bin/console doctrine:migrations:migrate --no-interaction

echo "[6/7] Creating/Updating Admin User..."
echo "You can now set your admin email and password for the website login."
sudo -u "$OWNER" php bin/console app:create-user --admin

echo "[7/7] Configuring Apache, Permissions, and Cache..."
mkdir -p public/uploads/media public/uploads/cv var/cache var/log
chown -R www-data:www-data public/uploads var/cache var/log
chmod -R 775 public/uploads var

# Create Apache VirtualHost config
APACHE_CONF="/etc/apache2/sites-available/mehmetates.conf"
cat << 'APACHE_EOF' > "$APACHE_CONF"
<VirtualHost *:80>
    ServerName mehmetates.fr
    ServerAlias www.mehmetates.fr

    DocumentRoot /var/www/MehmetWebsite/public

    <Directory /var/www/MehmetWebsite/public>
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    # Security: do not execute or list uploaded files
    <Directory /var/www/MehmetWebsite/public/uploads>
        Options -Indexes -ExecCGI
        SetHandler none
        <FilesMatch "\.(?i:php|phtml|phar|php3|php4|php5|php7|phps|cgi|pl|py|sh)$">
            Require all denied
        </FilesMatch>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/mehmet_error.log
    CustomLog ${APACHE_LOG_DIR}/mehmet_access.log combined

    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
    </IfModule>
</VirtualHost>
APACHE_EOF

# Enable rewrite / headers if needed and enable site
a2enmod rewrite headers || true
a2ensite mehmetates.conf
systemctl reload apache2

# Cache warmup
sudo -u "$OWNER" php bin/console cache:warmup --env=prod

echo ""
echo "============================================================"
echo "🎉 DEPLOYMENT COMPLETE!"
echo "Database: $DB_NAME"
echo "Database User: $DB_USER"
echo ""
echo "Next step for HTTPS (recommended):"
echo "  sudo certbot --apache -d mehmetates.fr -d www.mehmetates.fr"
echo "============================================================"
