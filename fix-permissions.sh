#!/bin/bash
# Fix permissions for existing files in /var/files
# This allows files to be deleted from host (Windows volume mount)

if [ -d "/var/files" ]; then
  # Fix directory permissions
  chmod -R 777 /var/files
  
  # Fix file permissions (666 = rw-rw-rw-)
  find /var/files -type f -exec chmod 666 {} \;
  
  # Fix directory permissions (777 = rwxrwxrwx)
  find /var/files -type d -exec chmod 777 {} \;
  
  echo "Permissions fixed for /var/files"
else
  echo "Directory /var/files not found"
  exit 1
fi

