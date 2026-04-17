#!/bin/sh

# Install dependencies (composer post-install-cmd triggers importmap:install,
# which downloads vendor JS into assets/vendor/)
cd /var/www/webroot/ROOT && composer install --no-dev --optimize-autoloader

# Setup ssh keys
# mkdir /var/www/webroot/ROOT/config/jwt
# openssl genpkey -out  /var/www/webroot/ROOT/config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
# openssl pkey -in config/jwt/private.pem -out /var/www/webroot/ROOT/config/jwt/public.pem -pubout

# Clear cache and build frontend assets
php /var/www/webroot/ROOT/bin/console cache:clear --env=prod
php /var/www/webroot/ROOT/bin/console sass:build --env=prod
php /var/www/webroot/ROOT/bin/console asset-map:compile --env=prod
php /var/www/webroot/ROOT/bin/console assets:install
