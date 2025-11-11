<?php

# Clean expired and outdated files from storage

require __DIR__ . '/../config.php';

# Logging function that outputs to both stderr and stdout for docker logs
function log_message($message) {
  $timestamp = date('Y-m-d H:i:s');
  $log_msg = "[{$timestamp}] Clean task: {$message}\n";
  # Output to stderr (visible in docker logs)
  fwrite(STDERR, $log_msg);
  # Also use error_log for system logging
  error_log("Clean task: " . $message);
}

# Get storage path
$storage_path = realpath(STORAGE);
if ( !$storage_path || !is_dir($storage_path) ) {
  log_message("Storage path not found: " . STORAGE);
  exit(1);
}

log_message("Starting cleanup process...");
log_message("Storage path: {$storage_path}");
log_message("EXPIRE_DAYS: " . EXPIRE_DAYS . ", MAX_DOWNLOADS: " . MAX_DOWNLOADS);

$deleted_count = 0;
$error_count = 0;

# Function to safely delete file with permission fix
function safe_delete_file($file_path) {
  global $deleted_count, $error_count;
  
  if ( !is_file($file_path) ) {
    return false;
  }
  
  # Fix permission before delete (666 = rw-rw-rw-)
  @chmod($file_path, 0666);
  
  # Try to delete
  if ( @unlink($file_path) ) {
    $deleted_count++;
    return true;
  } else {
    $error_count++;
    log_message("Failed to delete file: " . basename($file_path));
    return false;
  }
}

# Function to get all files in directory recursively
function get_files_recursive($dir, $exclude_patterns = []) {
  $files = [];
  if ( !is_dir($dir) ) {
    return $files;
  }
  
  $items = @scandir($dir);
  if ( $items === false ) {
    return $files;
  }
  
  foreach ( $items as $item ) {
    if ( $item === '.' || $item === '..' ) continue;
    
    $full_path = $dir . '/' . $item;
    
    # Check exclude patterns
    $excluded = false;
    foreach ( $exclude_patterns as $pattern ) {
      if ( fnmatch($pattern, $item) || fnmatch($pattern, $full_path) ) {
        $excluded = true;
        break;
      }
    }
    if ( $excluded ) continue;
    
    if ( is_file($full_path) ) {
      $files[] = $full_path;
    } elseif ( is_dir($full_path) ) {
      $files = array_merge($files, get_files_recursive($full_path, $exclude_patterns));
    }
  }
  
  return $files;
}

# 1. Delete expired files (older than EXPIRE_DAYS)
# Exclude .delete files and tmp directory
$all_files = get_files_recursive($storage_path, ['tmp/*']);
$expired_time = time() - (EXPIRE_DAYS * 24 * 60 * 60);
$expired_files_found = 0;

foreach ( $all_files as $file ) {
  # Skip .delete files and files in tmp directory
  if ( strpos($file, '.delete') !== false || strpos($file, '/tmp/') !== false ) {
    continue;
  }
  
  # Check if file is older than EXPIRE_DAYS
  $file_mtime = @filemtime($file);
  if ( $file_mtime !== false ) {
    $file_age_days = (time() - $file_mtime) / (24 * 60 * 60);
    if ( $file_mtime < $expired_time ) {
      safe_delete_file($file);
      log_message("Deleted expired file: " . basename($file) . " (age: " . round($file_age_days, 2) . " days)");
    } else {
      $expired_files_found++;
    }
  }
}

log_message("Found {$expired_files_found} files not yet expired");

# 2. Delete files that reached MAX_DOWNLOADS
# Find all .delete metafiles
$delete_metafiles = get_files_recursive($storage_path, ['tmp/*']);
$min_age = time() - (60 * 60); // 60 minutes ago
$max_downloads_files_found = 0;
$max_downloads_files_processed = 0;

foreach ( $delete_metafiles as $metafile ) {
  # Only process .delete files
  if ( strpos($metafile, '.delete') === false ) {
    continue;
  }
  
  # Read download count first
  $download_count = 0;
  $content = @file_get_contents($metafile);
  if ( $content !== false ) {
    $download_count = max(intval(trim($content)), 1);
  }
  
  # Check if reached max downloads
  if ( $download_count >= MAX_DOWNLOADS ) {
    $max_downloads_files_found++;
    
    # Check if metafile is at least 60 minutes old (to ensure download is complete)
    $metafile_mtime = @filemtime($metafile);
    $metafile_age_minutes = $metafile_mtime !== false ? (time() - $metafile_mtime) / 60 : 0;
    
    if ( $metafile_mtime !== false && $metafile_mtime <= $min_age ) {
      $actual_file = str_replace('.delete', '', $metafile);
      
      # Delete metafile
      safe_delete_file($metafile);
      
      # Delete actual file
      if ( is_file($actual_file) ) {
        safe_delete_file($actual_file);
        log_message("Deleted file (reached max downloads): " . basename($actual_file) . " (downloads: {$download_count}, age: " . round($metafile_age_minutes, 1) . " min)");
        $max_downloads_files_processed++;
      }
    } else {
      log_message("File reached max downloads but too new to delete: " . basename($metafile) . " (downloads: {$download_count}, age: " . round($metafile_age_minutes, 1) . " min, need 60 min)");
    }
  }
}

log_message("Found {$max_downloads_files_found} files that reached MAX_DOWNLOADS, processed {$max_downloads_files_processed}");

# Log summary
log_message("Completed. Deleted: {$deleted_count} files, Errors: {$error_count}");
