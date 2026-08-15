<?php
require __DIR__.'/_layout.php';
require_admin();
$root=dirname(__DIR__);
$checks=[
 ['PHP 8+',version_compare(PHP_VERSION,'8.0.0','>='),PHP_VERSION],
 ['Extensão PDO',extension_loaded('pdo'),extension_loaded('pdo')?'Ativa':'Inativa'],
 ['PDO SQLite',extension_loaded('pdo_sqlite'),extension_loaded('pdo_sqlite')?'Ativo':'Inativo'],
 ['SQLite3',extension_loaded('sqlite3'),extension_loaded('sqlite3')?'Ativo':'Inativo'],
 ['Pasta data gravável',is_dir($root.'/data')&&is_writable($root.'/data'),is_writable($root.'/data')?'Sim':'Não'],
 ['Pasta uploads gravável',is_dir($root.'/uploads')&&is_writable($root.'/uploads'),is_writable($root.'/uploads')?'Sim':'Não'],
 ['HTTPS',(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'Ativo':'Não — normal no XAMPP local'],
];
$criticalOk=true;foreach($checks as $i=>$c){if($i<=5&&!$c[1])$criticalOk=false;}
admin_header('Diagnóstico do Sistema','diagnostic-page');
?>
<section class="panel"><div class="panel-heading-flex"><div><span>BAMAB v<?=e(APP_VERSION)?></span><h2>Verificação do ambiente</h2><p>Esta tela mostra apenas informações necessárias para saber se o servidor está pronto. Ela é protegida pelo login do Admin.</p></div><strong class="<?=$criticalOk?'status-ok':'status-bad'?>"><?=$criticalOk?'AMBIENTE PRINCIPAL OK':'VERIFIQUE OS ITENS EM VERMELHO'?></strong></div></section>
<section class="panel"><div class="records diagnostic-list"><?php foreach($checks as $c):?><article><div><span><?=e($c[0])?></span><h3 class="<?=$c[1]?'status-ok':'status-bad'?>"><?=$c[1]?'OK':'ATENÇÃO'?></h3><p><?=e($c[2])?></p></div></article><?php endforeach;?></div></section>
<section class="panel"><h2>Atalhos de verificação</h2><div class="agenda-form-actions"><a class="small-button" href="teste_dispositivos.php">TESTAR EM CELULAR / TABLET</a><a class="small-button" href="../index.php" target="_blank" rel="noopener noreferrer">ABRIR SITE</a><a class="small-button" href="documentos.php">EDITOR DE DOCUMENTOS</a><a class="small-button" href="presenca.php">PRESENÇA POR ALA</a></div><p><small>Para a BAMAB, <strong>PDO SQLite</strong> é obrigatório porque o banco de dados local usa SQLite.</small></p></section>
<?php admin_footer();?>
