<?php
require_once __DIR__.'/../config.php';
function admin_header(string $title,string $bodyClass=''): void {
$u=admin_user();
$logo=setting('logo_path','assets/brasao_bamab_2026.png');
$primary=valid_hex_color(setting('primary_color','#090909'),'#090909');
$gold=valid_hex_color(setting('secondary_color','#c99a34'),'#c99a34');
$dark=valid_hex_color(setting('dark_color','#050505'),'#050505');
$light=valid_hex_color(setting('light_color','#ededed'),'#ededed');
$siteName=setting('site_name','BAMAB');
$siteSubtitle=setting('site_subtitle','BANDA AMARAL BRASIL');
$bodyClasses=trim('admin-bamab-theme '.$bodyClass);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="theme-color" content="<?=e($primary)?>"><title><?=e($title)?> — Admin BAMAB</title><link rel="stylesheet" href="../<?=e(app_asset('assets/admin.css'))?>"><style>:root{--site-primary:<?=e($primary)?>;--site-gold:<?=e($gold)?>;--site-dark:<?=e($dark)?>;--site-light:<?=e($light)?>}</style></head><body class="<?=e($bodyClasses)?>">
<header class="admin-top"><a class="admin-brand" href="dashboard.php"><img src="../<?=e($logo)?>" alt="Brasão oficial BAMAB"><div><strong><?=e($siteName)?></strong><small><?=e($siteSubtitle)?> · Painel Administrativo</small></div></a>
<button class="admin-menu-btn" type="button" aria-label="Abrir menu administrativo" aria-expanded="false" aria-controls="adminNav">☰ <span>MENU</span></button>
<nav id="adminNav" aria-label="Menu administrativo"><a href="dashboard.php">Painel</a><a href="matriculas.php">Matrículas</a><a href="equipe.php">Equipe Interna/Crachás</a><a href="presenca.php">Presença por Ala</a><a href="presenca_leitor.php">Leitor Geral QR</a><a href="frequencia_geral.php">Frequência Geral</a><a href="instrutores.php">Instrutores/Auxiliares</a><a href="acesso_master_instrutores.php">Acesso Master</a><a href="avaliacoes.php">Relatórios Avaliativos</a><a href="documentos.php">Editor Documentos</a><a href="configuracoes.php">Site</a><a href="contatos.php">Contatos/WhatsApp</a><a href="reconhecimentos.php">Diplomas/Certificados</a><a href="minha_conta.php">Minha Conta</a><a href="conquistas.php">Conquistas</a><a href="pessoas.php">Equipe do Site</a><a href="galeria.php">Galeria</a><a href="instagram.php">Instagram</a><a href="instagram_export.php">Importar Instagram</a><a href="noticias.php">Notícias</a><a href="agenda.php">Agenda</a><a href="patrocinadores.php">Patrocinadores/Apoiadores</a><a href="apoio_institucional.php">Prefeitura/Secretarias</a><a href="teste_dispositivos.php">Teste Celular</a><a href="modo_gratis.php">Modo Grátis</a><a href="diagnostico.php">Diagnóstico</a><a href="../index.php" target="_blank" rel="noopener noreferrer">Ver site</a><a href="logout.php">Sair</a></nav></header>
<main class="admin-main"><div class="admin-title admin-title-branded"><div class="admin-page-brand"><img src="../<?=e($logo)?>" alt="Brasão BAMAB"><div><span><?=e($siteSubtitle)?></span><h1><?=e($title)?></h1><em>Administração interna BAMAB</em></div></div><?php if($u):?><small class="admin-user-chip"><?=e($u['display_name'])?> · <?=e(($u['role']??'ADMIN_GERAL')==='ADMIN_GERAL'?'ADMIN GERAL':$u['role'])?></small><?php endif;?></div>
<?php }
function admin_footer(): void {?></main><footer class="admin-brand-footer"><img src="../<?=e(setting('logo_path','assets/brasao_bamab_2026.png'))?>" alt="Brasão"><div><strong><?=e(setting('site_name','BAMAB'))?></strong><span><?=e(setting('site_subtitle','BANDA AMARAL BRASIL'))?> · Sistema Interno · v<?=e(APP_VERSION)?></span></div></footer><?php if(admin_user()):?>
<aside id="enrollmentNotificationApp" class="admin-enrollment-notification" data-endpoint="notificacoes_matriculas.php" data-csrf="<?=e(csrf_token())?>" role="dialog" aria-live="assertive" aria-labelledby="enrollmentNotificationTitle" hidden>
 <div class="admin-enrollment-notification__head"><div class="admin-enrollment-notification__icon" aria-hidden="true">✓</div><div><span>NOVA INSCRIÇÃO</span><h2 id="enrollmentNotificationTitle">Matrícula aprovada</h2></div><button type="button" data-notification-close aria-label="Fechar e marcar avisos como vistos">×</button></div>
 <p class="admin-enrollment-notification__summary" data-notification-summary></p>
 <div class="admin-enrollment-notification__list" data-notification-list></div>
 <button class="admin-enrollment-notification__read" type="button" data-notification-read>MARCAR COMO VISTA</button>
</aside>
<?php endif;?><script src="../<?=e(app_asset('assets/admin.js'))?>"></script></body></html><?php }
function flash(): string {$m=$_SESSION['flash']??'';unset($_SESSION['flash']);return $m;}
function go(string $url,string $msg=''): never {if($msg)$_SESSION['flash']=$msg;header('Location: '.$url);exit;}
