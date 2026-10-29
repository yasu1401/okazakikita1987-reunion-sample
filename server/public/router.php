<?php
// PHP built-in development server only. Serve static files from public/ only.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path!=='/'&&!str_contains($path,'..')&&preg_match('~^/(style\.css|home-hero\.css|form-assist\.js|menu\.js|assets/[a-zA-Z0-9._-]+)$~D',$path)&&is_file(__DIR__.$path))return false;
require __DIR__.'/index.php';
