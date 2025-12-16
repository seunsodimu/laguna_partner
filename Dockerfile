FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    cron \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure Apache DocumentRoot for public directory
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Configure PHP limits and logging
RUN echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/php.ini && \
    echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/php.ini && \
    echo "error_log = /var/www/html/logs/php-errors.log" >> /usr/local/etc/php/conf.d/php.ini && \
    echo "log_errors = On" >> /usr/local/etc/php/conf.d/php.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/logs

# Setup cron jobs
COPY docker/crontab /etc/cron.d/laguna-cron
RUN chmod 0644 /etc/cron.d/laguna-cron

# Create startup script
RUN echo '#!/bin/bash\n\
cron\n\
apache2-foreground' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

# Expose port 80
EXPOSE 82

# Start Apache and cron
CMD ["/usr/local/bin/start.sh"]