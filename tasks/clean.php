<?php

# Clean expired and outdated files from storage

require __DIR__ . '/../config.php';

# Get storage path
$storage_path = realpath(STORAGE);
if ( !$storage_path || !is_dir($storage_path) ) {
  error_log("Clean task: Storage path not found: " . STORAGE);
  exit(1);
}

# delete all expired files (older than EXPIRE_DAYS)
# Exclude .delete files and tmp directory
$expired_cmd = 'find ' . escapeshellarg($storage_path) . ' -type f ! -name "*.delete" ! -path "' . $storage_path . '/tmp/*" -mtime +' . intval(EXPIRE_DAYS) . ' -delete 2>&1';
exec($expired_cmd, $expired_output, $expired_code);
if ( $expired_code !== 0 && !empty($expired_output) ) {
  error_log("Clean task: Error deleting expired files: " . implode("\n", $expired_output));
}

# select all files with ".delete" metafile
# with at least one download was made (> 60 minutes ago to make sure downloading process is complete for big files)
$delete_files = [];
exec('find ' . escapeshellarg($storage_path) . ' -type f -name "*.delete" -mmin +60 2>&1', $delete_output, $delete_code);

if ( $delete_code === 0 && !empty($delete_output) ) {
  $delete_files = $delete_output;
}

# remove all files which were downloaded max times
foreach ( $delete_files as $file ) {
  $file = trim($file);
  if ( empty($file) || !is_file($file) ) continue;
  
  $download_count = 0;
  $content = @file_get_contents($file);
  if ( $content !== false ) {
    $download_count = max(intval($content), 1);
  }
  
  if ( $download_count >= MAX_DOWNLOADS ) {
    $actual_file = str_replace('.delete', '', $file);
    
    # Delete .delete metafile
    if ( is_file($file) ) {
      @unlink($file);
    }
    
    # Delete actual file
    if ( is_file($actual_file) ) {
      @unlink($actual_file);
    }
    
    error_log("Clean task: Deleted file (reached max downloads): " . basename($actual_file));
  }
}