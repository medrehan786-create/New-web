# Use official PHP 8.2 with Apache image
FROM php:8.2-apache

# Install common PHP extensions you may need (pdo, pdo_mysql, etc.)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite (useful for pretty URLs)
RUN a2enmod rewrite

# Copy all application files into Apache's document root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Expose port 80 so you can access it from your host
EXPOSE 80

# Optional: set recommended PHP settings (memory, upload size, etc.)
# RUN echo "upload_max_filesize = 64M\npost_max_size = 64M" > /usr/local/etc/php/conf.d/uploads.ini

# Apache automatically starts as the container’s CMD from base image
