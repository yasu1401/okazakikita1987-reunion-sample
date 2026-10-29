<?php
// Copy to ignored config.local.php. Live credentials must never enter Git.
return [
 'mode'=>'development',
 'dsn'=>'sqlite:'.__DIR__.'/var/reunion.sqlite',
 'db_user'=>'', 'db_password'=>'',
 'storage'=>__DIR__.'/var',
 'base_url'=>'http://127.0.0.1:8790',
 // On Sakura: mode=live; mysql:host=...;dbname=...;charset=utf8mb4
 // Put this folder OUTSIDE www/, and serve ONLY public/ via the document root.
];
