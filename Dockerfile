FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY beauty-cabin/ /var/www/html/

# Allow Apache to serve the application
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Expose Apache
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]