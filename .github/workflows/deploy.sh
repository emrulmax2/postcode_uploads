#!/bin/sh
# Change to the project directory
cd /home/postcodeslccac/postcode_uploads

# Pull the latest changes from the git repository
git pull origin main

# Install/update composer dependencies
composer install --no-interaction

export PATH=/opt/cpanel/ea-nodejs22/bin/:$PATH
# Install NPM dependencies and build assets
npm install
npm run build
rm -rf node_modules

# Ensure public/build is available under the public_html directory
mkdir -p /home/postcodeslccac/public_html
if [ ! -L /home/postcodeslccac/public_html/build ]; then
	ln -s /home/postcodeslccac/postcode_uploads/public/build /home/postcodeslccac/public_html/build
fi
if [ ! -L /home/postcodeslccac/public_html/storage ]; then
	ln -s /home/postcodeslccac/postcode_uploads/public/storage /home/postcodeslccac/public_html/storage
fi

# Run database migrations
php artisan migrate --force

# Clear caches
php artisan cache:clear

# Clear and cache routes
php artisan route:cache

# Clear and cache config
php artisan config:cache

# Clear and cache views
php artisan view:cache

#Clear all
php artisan optimize:clear
