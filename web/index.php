<?php



# Configure
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib.php';

# Ensure request scheme is available for CLI/FastCGI environments
if (empty($_SERVER['REQUEST_SCHEME'])) {
  $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
  $_SERVER['REQUEST_SCHEME'] = $is_https ? 'https' : 'http';
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$title = null;
$description = null;
$has_docs = false;
$doc = false;



# Route
$docs_handler = __DIR__ . '/../../bashupload-docs/index.php';
$action = 'default';

if ( in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT']) ) {
  
  $action = 'upload';
}
else {
  
  # load documentation, if we have the docs repo cloned
  if ( is_file($docs_handler) ) {
    $has_docs = true;
    $doc = include $docs_handler;
    
    if ( $doc ) {
      $action = 'docs';
    }
  }
  
  # everything else is a possible file to download
  if ( !$doc && ($uri != '/') ) {
    $action = 'file';
  }
}



# Execute routed handler
$accept_header = $_SERVER['HTTP_ACCEPT'] ?? 'text/html';
$accept = array_map('trim', explode(',', $accept_header));
$renderer = 'html';
if ( isset($_POST['json']) && $_POST['json'] == 'true' ) $renderer = 'json';
else if ( in_array('text/html', $accept, true) ) $renderer = 'html';
else $renderer = 'txt';

$action_handler = __DIR__ . "/../actions/{$action}.php";
if ( is_file($action_handler) ) {
  include $action_handler;
}



# Render
include __DIR__ . "/../render/{$renderer}.php";