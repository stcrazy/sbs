<?php
// Local preview only. Never deploy PHP's built-in development server.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/sbs/api/')){require dirname(__DIR__).'/api/index.php';return true;}
$relative=preg_replace('~^/sbs/~','',$path);$root=realpath(dirname(__DIR__));$file=realpath($root.'/'.$relative);
if($path==='/sbs/'||$path==='/sbs'){$file=$root.'/index.html';}
$file=$file?realpath($file):false;
if(!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||!is_file($file)||!preg_match('~\.(?:html|js|css|svg|png|webmanifest)$~',$file)){http_response_code(404);return true;}
header('Content-Type: '.match(pathinfo($file,PATHINFO_EXTENSION)){'html'=>'text/html; charset=utf-8','js'=>'application/javascript','css'=>'text/css','svg'=>'image/svg+xml','png'=>'image/png','webmanifest'=>'application/manifest+json',default=>'application/octet-stream'});readfile($file);return true;
