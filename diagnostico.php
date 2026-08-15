<?php
require __DIR__.'/config.php';
if(!admin_user()){http_response_code(404);header('Content-Type: text/plain; charset=utf-8');exit('Página não encontrada.');}
header('Location: admin/diagnostico.php');
exit;
