<?php
// Copy to ignored config.local.php. Live credentials must never enter Git.
return [
 'mode'=>'development',
 'dsn'=>'sqlite:'.__DIR__.'/var/reunion.sqlite',
 'db_user'=>'', 'db_password'=>'',
 'storage'=>__DIR__.'/var',
 'mail_enabled'=>false, // Enable only after sender setup and delivery verification.
 'mail_from'=>'reunion@example.test',
 'mail_from_name'=>'岡崎北高等学校1987年卒同窓会 幹事会',
 'base_url'=>'http://127.0.0.1:8790',
 // On Sakura: mode=live; mysql:host=...;dbname=...;charset=utf8mb4
 // Put this folder OUTSIDE www/, and serve ONLY public/ via the document root.
];
