#!/bin/sh

# Install dependencies
cd /var/www/webroot/ROOT && composer install

# Setup ssh keys
# mkdir /var/www/webroot/ROOT/config/jwt
# openssl genpkey -out  /var/www/webroot/ROOT/config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
# openssl pkey -in config/jwt/private.pem -out /var/www/webroot/ROOT/config/jwt/public.pem -pubout

# Clear ande setup
php /var/www/webroot/ROOT/bin/console cache:clear --env=prod
php /var/www/webroot/ROOT/bin/console assets:install