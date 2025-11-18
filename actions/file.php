<?php

# Download file handler



# file stats
$uri_path = trim($uri ?? '', '/');
$file_parts = $uri_path === '' ? [] : explode('/', $uri_path);

# Invalid request (e.g., /index.php, /logo.png) should return 404 without PHP warnings
if ( count($file_parts) < 2 || $file_parts[0] === '' || $file_parts[1] === '' ) {
  header('HTTP/1.0 404 Not Found');
  exit;
}

$file_id = $file_parts[0];
$file_name = implode('/', array_slice($file_parts, 1)); # support edge-case names containing slashes
$file = [
  'id' => $file_id,
  'name' => $file_name,
  'path' => '/' . $file_id . '-' . $file_name,
  'extension' => strtolower(pathinfo($file_name, PATHINFO_EXTENSION))
];

$file_path = STORAGE . '/' . md5($file['path']);
$file['size'] = is_file($file_path) ? filesize($file_path) : 0;


# download stats
$download_mark_file = $file_path . '.delete';
if ( is_file($download_mark_file) ) {
  $downloads = (int)file_get_contents($download_mark_file);
}
else {
  $downloads = 0;
}

# Check if file has reached max downloads limit
$max_downloads_reached = ($downloads >= MAX_DOWNLOADS);

# title for rendering info
$title = htmlspecialchars($file['name']) . ' / download from bashupload.com';


# render
if ( !isset($_GET['download']) && $renderer == 'html' ) {
  $sorry = !$file['size'] || $max_downloads_reached;
}

# direct download - only if file exists and hasn't reached max downloads
else if ( $file['size'] && !$max_downloads_reached )
{
  # Increment download counter before serving file
  file_put_contents($download_mark_file, $downloads + 1);
  # Set permissions so host can delete (666 = rw-rw-rw-)
  chmod($download_mark_file, 0666);
  
	header('Content-type: ' . system_extension_mime_type($file['name']));
	header('Content-Disposition: attachment; filename=' . $file['name']); 
	header('Content-Transfer-Encoding: binary');
	header('Content-Length: ' . $file['size']);
	header('X-Accel-Redirect: /files/' . md5($file['path']));
	readfile($file_path);
	exit;
}

# max downloads reached or file not found
else if ( $max_downloads_reached )
{
	header('HTTP/1.0 403 Forbidden');
	if ( $renderer == 'txt' ) {
		echo "File has reached maximum download limit (" . MAX_DOWNLOADS . " downloads).\n";
	}
	exit;
}

# no file found
else
{
	header('HTTP/1.0 404 Not Found');
	exit;
}
