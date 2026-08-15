<?php
require_once __DIR__.'/../config.php';
function instructor_header(string $title): void {
$u=instructor_user();
header('X-Robots-Tag: noindex, nofollow',true);
$siteName=setting('site_name','BAMAB');
$siteSubtitle=setting('site_subtitle','BANDA AMARAL BRASIL');
$logo=setting('logo_path','assets/brasao_bamab_2026.png');
$primary=valid_hex_color(setting('primary_color','#090909'),'#090909');
$gold=valid_hex_color(setting('secondary_color','#c99a34'),'#c99a34');
$dark=valid_hex_color(setting('dark_color','#050505'),'#050505');
$light=valid_hex_color(setting('light_color','#ededed'),'#ededed');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="theme-color" content="<?=e($primary)?>"><title><?=e($title)?> — Área Interna BAMAB</title><link rel="stylesheet" href="../<?=e(app_asset('assets/admin.css'))?>"><link rel="stylesheet" href="../<?=e(app_asset('assets/instructor.css'))?>"><style>:root{--primary:<?=e($primary)?>;--gold:<?=e($gold)?>;--dark:<?=e($dark)?>;--light:<?=e($light)?>;--wine:<?=e($primary)?>;--site-shadow:rgba(0,0,0,.28)}</style></head><body class="instructor-body"><header class="instructor-top"><a class="instructor-brand" href="index.php"><img src="../<?=e($logo)?>" alt="Brasão oficial BAMAB"><div><strong><?=e($siteName)?></strong><small><?=e($siteSubtitle)?> · Área do Instrutor</small></div></a><?php if($u):?><nav><a href="index.php">CONTROLE</a><a href="avaliacoes.php">AVALIAÇÕES</a><a href="meu_qr.php">MEU QR</a><a href="frequencia.php">FREQUÊNCIA</a><a href="relatorios.php">RELATÓRIOS</a><a href="logout.php">SAIR</a></nav><?php endif;?></header><main class="instructor-main"><div class="instructor-page-title"><div><span>USO INTERNO BAMAB</span><h1><?=e($title)?></h1></div><?php if($u):?><small><?=e(instructor_display_name($u))?> · <?=e($u['role'])?> · <?=e($u['instructor_code']??'')?></small><?php endif;?></div><?php if($u&&instructor_master_session()):?><div class="master-session-banner"><strong>ACESSO MASTER DO ADMIN</strong><span>Você está visualizando a área deste instrutor com autorização administrativa.</span><a href="../admin/acesso_master_instrutores.php?return=1">VOLTAR AO ADMIN</a></div><?php endif;?>
<?php }
function instructor_footer(): void {?></main></body></html><?php }
function igo(string $url,string $msg=''): never {if($msg)$_SESSION['instructor_flash']=$msg;header('Location: '.$url);exit;}
function iflash(): string {$m=$_SESSION['instructor_flash']??'';unset($_SESSION['instructor_flash']);return $m;}
