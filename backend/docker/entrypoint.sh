#!/bin/sh
set -e

# Symfony requires a .env file to exist (Docker env vars are used instead)
if [ ! -f .env ]; then
    touch .env
fi

# Install composer dependencies if vendor is empty
if [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generate JWT keys if missing
if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT keys..."
    mkdir -p config/jwt
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

# Create var directories
mkdir -p var/cache var/log var/share
chown -R www-data:www-data var

# Run migrations (non-fatal: fix migration errors manually if needed)
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || \
    echo "[WARNING] Migrations failed — run 'make migrate' manually to investigate."

exec "$@"
