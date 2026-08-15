<?php
require_once __DIR__.'/config.php';
function nav_active(string $file): string { return basename($_SERVER['PHP_SELF']??'')===$file?'active':''; }
function site_header(string $title=''): void {
    $name=setting('site_name','BAMAB'); $logo=setting('logo_path','assets/brasao_bamab_2026.png');
    $gold=valid_hex_color(setting('secondary_color','#c99a34'),'#c99a34');
    $black=valid_hex_color(setting('dark_color','#050505'),'#050505');
    $silver=valid_hex_color(setting('light_color','#ededed'),'#ededed');
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="<?=e($black)?>"><meta name="description" content="BAMAB — Banda Amaral Brasil">
<title><?=e($title?$title.' — '.$name:$name.' — Banda Amaral Brasil')?></title>
<link rel="stylesheet" href="<?=e(app_asset('assets/site.css'))?>">
<style>:root{--gold:<?=e($gold)?>;--black:<?=e($black)?>;--silver:<?=e($silver)?>;--primary:#090909;--secondary:<?=e($gold)?>;--dark:<?=e($black)?>;--light:<?=e($silver)?>}</style>
</head><body>
<a class="skip-link" href="#conteudo-principal">Ir para o conteúdo principal</a>
<header class="site-header">
  <a class="brand" href="index.php" aria-label="BAMAB — Página inicial">
    <img src="<?=e($logo)?>" alt="Brasão oficial BAMAB 2026">
    <div class="brand-copy"><strong><?=e($name)?></strong><small><?=e(setting('site_subtitle','BANDA AMARAL BRASIL'))?></small></div>
  </a>
  <button class="menu-btn" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="mainNav">☰</button>
  <nav class="main-nav" id="mainNav" aria-label="Navegação principal">
    <a class="<?=nav_active('index.php')?>" href="index.php"><i>⌂</i> INÍCIO</a>
    <a class="<?=nav_active('historia.php')?>" href="historia.php"><i>▤</i> HISTÓRIA</a>
    <a class="<?=nav_active('agenda.php')?>" href="agenda.php"><i>▣</i> AGENDA</a>
    <a class="<?=nav_active('galeria.php')?>" href="galeria.php"><i>▣</i> GALERIA</a>
    <a class="<?=nav_active('campeonatos.php')?>" href="campeonatos.php"><i>★</i> CAMPEONATOS</a>
    <a class="<?=nav_active('integrantes.php')?>" href="integrantes.php"><i>●</i> INTEGRANTES</a>
    <a class="<?=nav_active('coordenacao.php')?>" href="coordenacao.php"><i>♟</i> COORDENAÇÃO</a>
    <a class="<?=nav_active('matriculas.php')?>" href="matriculas.php"><i>✎</i> MATRÍCULAS</a>
    <a class="<?=nav_active('contato.php')?>" href="contato.php"><i>✉</i> CONTATO</a>
  </nav>
  <details class="site-login-menu">
    <summary><span>▣</span> ENTRAR <b>▾</b></summary>
    <div class="site-login-options">
      <a class="login-admin" href="admin/"><i>◆</i><span><strong>ENTRAR COMO ADMIN</strong><small>Painel administrativo</small></span></a>
      <a class="login-instructor" href="instrutor/login.php"><i>♫</i><span><strong>ENTRAR COMO INSTRUTOR</strong><small>Professor / Auxiliar</small></span></a>
      <div class="site-login-recovery"><strong>ESQUECI MINHA SENHA</strong><div><a href="admin/esqueci_senha.php">ADMIN</a><a href="instrutor/esqueci_senha.php">INSTRUTOR</a></div><small>Redefinir com nome, nascimento e CPF.</small></div>
    </div>
  </details>
</header>
<?php }
function social_circle(string $url,string $symbol,string $label): void {
    $url=safe_external_url($url);
    if(!$url)return;
    ?><a class="social-circle" aria-label="<?=e($label)?>" href="<?=e($url)?>" target="_blank" rel="noopener noreferrer"><?=e($symbol)?></a><?php
}
function site_footer(): void { ?>
<section class="motto-strip"><span>✣</span><?=e(setting('motto','DISCIPLINA NA ROTINA, HARMONIA NA ALMA, EXCELÊNCIA NA APRESENTAÇÃO.'))?><span>✣</span></section>
<footer class="site-footer">
  <div class="footer-socials"><?php social_circle(setting('facebook_url'),'f','Facebook'); social_circle(setting('instagram_url'),'◎','Instagram'); social_circle(setting('youtube_url'),'▶','YouTube'); $footerWhatsApp=null;try{$footerWhatsApp=first_whatsapp_channel();}catch(Throwable $e){} if($footerWhatsApp):$footerWaLink=contact_channel_link($footerWhatsApp);if($footerWaLink):?><a class="social-circle whatsapp-circle" href="<?=e($footerWaLink)?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp BAMAB">W</a><?php endif;endif;$footerEmail=trim(setting('contact_email')); if($footerEmail&&filter_var($footerEmail,FILTER_VALIDATE_EMAIL)):?><a class="social-circle" href="mailto:<?=e($footerEmail)?>" aria-label="E-mail">✉</a><?php endif;?></div>
  <div class="footer-brand"><strong>BAMAB - BANDA AMARAL BRASIL</strong><span><?=e(setting('footer_text'))?></span></div>
  <div class="copyright">© <?=date('Y')?> BAMAB - Todos os direitos reservados.</div>
</footer>
<script src="<?=e(app_asset('assets/site.js'))?>"></script><script async src="https://www.instagram.com/embed.js"></script>
</body></html><?php }
?>
