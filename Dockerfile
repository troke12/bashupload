FROM php:8.2-fpm

# Install system dependencies and nginx
RUN apt-get update && apt-get install -y \
    nginx \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    mime-support \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/bashupload

# Copy application files
COPY . /var/www/bashupload/

# Create storage directory and set permissions
RUN mkdir -p /var/files \
    && chown -R www-data:www-data /var/files \
    && chmod 755 /var/files \
    && chown -R www-data:www-data /var/www/bashupload

# Configure PHP for file uploads
RUN echo "upload_max_filesize = 2G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 2G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 1800" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 1800" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Create PHP-FPM socket directory
RUN mkdir -p /var/run/php

# Configure nginx
RUN rm -f /etc/nginx/sites-enabled/default \
    && printf 'server {\n\tserver_name _;\n\tlisten 80;\n\n\troot /var/www/bashupload/web;\n\tindex index.php;\n\n\tlocation / {\n\t\ttry_files $uri /index.php?$args;\n\t}\n\n\tlocation /files {\n\t\troot /var;\n\t\tinternal;\n\t}\n\n\tlocation = /index.php {\n\t\tfastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\t\tinclude fastcgi_params;\n\t\tfastcgi_pass unix:/var/run/php/php-fpm.sock;\n\t\tfastcgi_read_timeout 1800;\n\t}\n}\n' > /etc/nginx/sites-available/bashupload \
    && ln -sf /etc/nginx/sites-available/bashupload /etc/nginx/sites-enabled/bashupload \
    && sed -i '/^http {/a\    client_max_body_size 2G;' /etc/nginx/nginx.conf

# Expose port 80
EXPOSE 80

# Create startup script
RUN echo '#!/bin/bash\n\
set -e\n\
php-fpm -D\n\
nginx -g "daemon off;"' > /start.sh \
    && chmod +x /start.sh

# Start nginx and php-fpm
CMD ["/start.sh"]

