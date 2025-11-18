<?php

# Upload file(s) handler

$uploads = $uploads ?? [];
$error = $error ?? null;
$rewrite_id = isset($_POST['rewrite_id']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_POST['rewrite_id']) : null;
$storage_dir = STORAGE;
$tmp_dir = rtrim($storage_dir, '/') . '/tmp';

# Ensure storage directory exists and is writable
if ( !is_dir($storage_dir) && !@mkdir($storage_dir, 0777, true) ) {
  $error = 'Storage directory is not accessible.';
  return;
}
@chmod($storage_dir, 0777);
if ( !is_writable($storage_dir) ) {
  $error = 'Storage directory is not writable.';
  return;
}

# Ensure tmp directory exists for streamed uploads
if ( !is_dir($tmp_dir) && !@mkdir($tmp_dir, 0777, true) ) {
  $error = 'Temporary upload directory is not accessible.';
  return;
}
@chmod($tmp_dir, 0777);
if ( !is_writable($tmp_dir) ) {
  $error = 'Temporary upload directory is not writable.';
  return;
}

# Normalize uploaded files array
$incoming_files = [];
if ( !empty($_FILES) ) {
  foreach ( $_FILES as $key => $file ) {
    if ( is_array($file['tmp_name']) ) {
      foreach ( $file['tmp_name'] as $index => $tmp_name ) {
        if ( empty($tmp_name) ) continue;
        $incoming_files[] = [
          'tmp_name' => $tmp_name,
          'name' => $file['name'][$index] ?? uniqid(),
          'upload_name' => $key
        ];
      }
    }
    else if ( !empty($file['tmp_name']) ) {
      $incoming_files[] = [
        'tmp_name' => $file['tmp_name'],
        'name' => $file['name'] ?? uniqid(),
        'upload_name' => $key
      ];
    }
  }
}

# Handle raw input (curl -T file)
if ( $f = fopen('php://input', 'r') ) {
  $name = trim($uri ?? '', '/');
  if ( !$name ) $name = uniqid();

  $tmp = @tempnam($tmp_dir, 'upload');
  if ( $tmp !== false ) {
    $ftmp = fopen($tmp, 'w');
    while ( !feof($f) ) {
      $chunk = fread($f, 8192);
      if ($chunk === false) break;
      fwrite($ftmp, $chunk);
    }
    fclose($ftmp);
    if ( @filesize($tmp) ) {
      $incoming_files[] = [
        'tmp_name' => $tmp,
        'name' => $name,
        'upload_name' => count($incoming_files)
      ];
    }
    else {
      @unlink($tmp);
    }
  }
  else {
    $error = 'Unable to create temporary file for upload.';
  }
  fclose($f);
}

# Move files to storage directory
$id = gen_id();
foreach ( $incoming_files as $index => $file )
{
  if ( empty($file['tmp_name']) ) continue;

  # make file name safe
	$file_name = str_replace(['/', '-'], '_', trim($file['name'], '/'));
	
	# if the file name is too long, let's just replace it with random short ID
	if ( strpos($file_name, ' ') || strlen($file_name) > 15 ) {
		$file_name = gen_id() . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
	}

  # move file to a final location
	$destination = $storage_dir . '/' . md5('/' . $id . '-' . $file_name);
	if ( !@rename($file['tmp_name'], $destination) ) {
    if ( !@copy($file['tmp_name'], $destination) || !@unlink($file['tmp_name']) ) {
      $error = 'Failed to move uploaded file. Please check storage permissions.';
      continue;
    }
  }

	# Set permissions so nginx can read and host can delete (666 = rw-rw-rw-)
	@chmod($destination, 0666);
  $size = @filesize($destination);
  if ( $size === false ) $size = 0;

  # register this uploaded file data
	$uploads[] = [
		'id' => ($rewrite_id ?: $id),
		'name' => $file_name,
		'path' => $destination,
		'size' => $size,
		'upload_name' => $file['upload_name'] ?? $index,
		'is_rewritten' => $rewrite_id ? true : false
	];
}

if ( empty($uploads) && !$error ) {
  $error = 'No files were uploaded.';
}