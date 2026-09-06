#!/bin/sh
set -eu

export APP_ENV=prod
export APP_DEBUG=0

cd /var/www/webroot/ROOT

# Install dependencies (composer post-install-cmd triggers importmap:install,
# which downloads vendor JS into assets/vendor/)
composer install --no-dev --optimize-autoloader

# Setup ssh keys (one-time, run manually if needed)
# mkdir config/jwt
# openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
# openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

# Clear cache and build frontend assets
php bin/console cache:clear
php bin/console assets:install
php bin/console sass:build
php bin/console asset-map:compile
