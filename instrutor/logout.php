<?php
require __DIR__.'/../config.php';
$master=instructor_master_session();
unset($_SESSION['instructor_id'],$_SESSION['instructor_flash']);
instructor_master_clear();
header('Location: '.($master?'../admin/acesso_master_instrutores.php?return=1':'login.php'));exit;
