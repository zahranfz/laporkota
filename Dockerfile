FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli

# Enable Apache rewrite module
RUN a2enmod rewrite

# Configure PHP settings for file uploads
RUN { \
        echo 'upload_max_filesize = 10M'; \
        echo 'post_max_size = 12M'; \
        echo 'memory_limit = 256M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# Redirect web root to /aduan-kota/
RUN echo "<?php header('Location: /aduan-kota/'); exit; ?>" > /var/www/html/index.php

# Create uploads directory and ensure correct permissions
RUN mkdir -p /var/www/html/aduan-kota/uploads \
    && chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html/aduan-kota
