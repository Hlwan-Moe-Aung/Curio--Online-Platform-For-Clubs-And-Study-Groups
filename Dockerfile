# Use PHP with Apache
FROM php:8.2-apache

# Install system dependencies for Composer and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install mysqli zip && docker-php-ext-enable mysqli

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache rewriting (standard for most PHP apps)
RUN a2enmod rewrite

# Copy everything from your current local folder into the container
COPY . /var/www/html/

# Install PHP dependencies
WORKDIR /var/www/html
RUN composer install --no-interaction --optimize-autoloader

# Ensure the web server has permission to read the files
RUN chown -R www-data:www-data /var/www/html

# Tell the container to start Apache in the foreground
CMD ["apache2-foreground"]