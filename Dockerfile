FROM php:8.2-apache

# Install required PHP extensions for MySQL connection
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy your application files into the Apache web directory
COPY . /var/www/html/

# Set ownership permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Expose HTTP port 80
EXPOSE 80