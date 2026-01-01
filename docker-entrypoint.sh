#!/bin/bash
set -e

DOMAIN="${SSL_DOMAIN:-vendor.lagunatools.com}"
EMAIL="${SSL_EMAIL:-admin@lagunatools.com}"

# Generate self-signed cert if Let's Encrypt cert doesn't exist
if [ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
    echo "Generating self-signed certificate for $DOMAIN..."
    mkdir -p /etc/ssl/private /etc/ssl/certs
    
    openssl req -x509 -nodes -days 365 \
      -newkey rsa:2048 \
      -keyout /etc/ssl/private/selfsigned.key \
      -out /etc/ssl/certs/selfsigned.crt \
      -subj "/CN=$DOMAIN"
    
    # Update Apache config to use self-signed temporarily
    sed -i "s|SSLCertificateFile.*|SSLCertificateFile /etc/ssl/certs/selfsigned.crt|g" /etc/apache2/sites-enabled/default-ssl.conf
    sed -i "s|SSLCertificateKeyFile.*|SSLCertificateKeyFile /etc/ssl/private/selfsigned.key|g" /etc/apache2/sites-enabled/default-ssl.conf
fi

exec "$@"
