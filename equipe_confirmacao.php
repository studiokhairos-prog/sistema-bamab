<?php
require __DIR__.'/config.php';
http_response_code(404);
header('Location: index.php');
exit;
