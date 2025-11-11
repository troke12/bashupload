<?php

# Clean expired and outdated files from storage

require __DIR__ . '/../config.php';

# Get storage path
$storage_path = realpath(STORAGE);
if ( !$storage_path || !is_dir($storage_path) ) {
  error_log("Clean task: Storage path not found: " . STORAGE);
  exit(1);
}

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
    error_log("Clean task: Failed to delete file: " . $file_path);
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
$all_files = get_files_recursive($storage_path, ['*.delete', 'tmp/*']);
$expired_time = time() - (EXPIRE_DAYS * 24 * 60 * 60);

foreach ( $all_files as $file ) {
  # Skip .delete files and files in tmp directory
  if ( strpos($file, '.delete') !== false || strpos($file, '/tmp/') !== false ) {
    continue;
  }
  
  # Check if file is older than EXPIRE_DAYS
  $file_mtime = @filemtime($file);
  if ( $file_mtime !== false && $file_mtime < $expired_time ) {
    safe_delete_file($file);
    error_log("Clean task: Deleted expired file: " . basename($file));
  }
}

# 2. Delete files that reached MAX_DOWNLOADS
# Find all .delete metafiles (at least 60 minutes old to ensure download is complete)
$delete_metafiles = get_files_recursive($storage_path, ['tmp/*']);
$min_age = time() - (60 * 60); // 60 minutes ago

foreach ( $delete_metafiles as $metafile ) {
  # Only process .delete files
  if ( strpos($metafile, '.delete') === false ) {
    continue;
  }
  
  # Check if metafile is at least 60 minutes old
  $metafile_mtime = @filemtime($metafile);
  if ( $metafile_mtime === false || $metafile_mtime > $min_age ) {
    continue;
  }
  
  # Read download count
  $download_count = 0;
  $content = @file_get_contents($metafile);
  if ( $content !== false ) {
    $download_count = max(intval(trim($content)), 1);
  }
  
  # If reached max downloads, delete both metafile and actual file
  if ( $download_count >= MAX_DOWNLOADS ) {
    $actual_file = str_replace('.delete', '', $metafile);
    
    # Delete metafile
    safe_delete_file($metafile);
    
    # Delete actual file
    if ( is_file($actual_file) ) {
      safe_delete_file($actual_file);
      error_log("Clean task: Deleted file (reached max downloads): " . basename($actual_file));
    }
  }
}

# Log summary
if ( $deleted_count > 0 || $error_count > 0 ) {
  error_log("Clean task: Completed. Deleted: {$deleted_count} files, Errors: {$error_count}");
}
