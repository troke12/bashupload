FROM webdevops/php-nginx:8.2

# HTTP Configuration - Application runs on HTTP only (FORCE_SSL = false)
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

# Copy fix-permissions script first
COPY fix-permissions.sh /usr/local/bin/fix-permissions.sh
RUN chmod +x /usr/local/bin/fix-permissions.sh

# Copy application files
COPY . /app/

# Create storage directory and set permissions
# Use 777 for directory to allow host to delete files (Windows volume mount)
RUN mkdir -p /var/files/tmp \
    && chown -R application:application /var/files \
    && chmod 777 /var/files \
    && chmod 777 /var/files/tmp \
    && chown -R application:application /app

# Configure nginx based on setup/nginx.conf
# This configuration follows the structure from setup/nginx.conf
# 
# From setup/nginx.conf:
# - server_name: handled by webdevops default (_)
# - listen 80: handled by webdevops default
# - root: set via WEB_DOCUMENT_ROOT=/app/web
# - location /: handled by webdevops default (try_files $uri /index.php?$args)
# - location /files: configured below (internal file access)
# - location = /index.php: handled by webdevops default with fastcgi timeout

# Location /files for internal file access (based on STORAGE in config.php)
# This matches setup/nginx.conf lines 19-23
RUN printf '# Location /files - internal file access\n' > /opt/docker/etc/nginx/vhost.common.d/20-files.conf \
    && printf '# Be sure to update this if you have updated "STORAGE" param in config.php\n' >> /opt/docker/etc/nginx/vhost.common.d/20-files.conf \
    && printf 'location /files {\n\troot /var;\n\tinternal;\n}\n' >> /opt/docker/etc/nginx/vhost.common.d/20-files.conf

# Configure PHP-FPM timeout (based on setup/nginx.conf line 28)
# Webdevops handles fastcgi_pass automatically, we just need to set timeout
RUN echo "fastcgi_read_timeout 1800;" >> /opt/docker/etc/nginx/vhost.common.d/10-php.conf

# Configure PHP settings via php.ini
RUN echo "max_input_time = 1800" >> /opt/docker/etc/php/php.ini

# Setup cron job for cleaning expired files (runs every hour as per README.md)
# Run as application user to have proper access to /var/files
RUN echo "0 * * * * cd /app && php tasks/clean.php" | crontab -u application -

# Expose port 80 (HTTP only)
# FORCE_SSL is set to false in config.php, so no SSL/HTTPS redirect will occur
# Application is accessible via HTTP only: http://localhost:8000
EXPOSE 80
