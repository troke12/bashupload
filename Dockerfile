FROM webdevops/php-nginx:8.2

# Set document root
ENV WEB_DOCUMENT_ROOT=/app/web

# Configure PHP for large file uploads
ENV PHP_UPLOAD_MAX_FILESIZE=2G
ENV PHP_POST_MAX_SIZE=2G
ENV PHP_MAX_EXECUTION_TIME=1800
ENV PHP_MEMORY_LIMIT=256M

# Configure Nginx for large file uploads
ENV SERVICE_NGINX_CLIENT_MAX_BODY_SIZE=2G

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app/

# Create storage directory and set permissions
RUN mkdir -p /var/files \
    && chown -R application:application /var/files \
    && chmod 755 /var/files \
    && chown -R application:application /app

# Configure nginx vhost for /files location
RUN printf 'location /files {\n\troot /var;\n\tinternal;\n}\n' > /opt/docker/etc/nginx/vhost.common.d/20-files.conf

# Configure PHP-FPM timeout in nginx
RUN echo "fastcgi_read_timeout 1800;" >> /opt/docker/etc/nginx/vhost.common.d/10-php.conf

# Configure PHP settings via php.ini
RUN echo "max_input_time = 1800" >> /opt/docker/etc/php/php.ini

# Setup cron job for cleaning expired files (runs every hour as per README.md)
# Run as application user to have proper access to /var/files
RUN echo "0 * * * * cd /app && php tasks/clean.php" | crontab -u application -

# Expose port 80
EXPOSE 80
