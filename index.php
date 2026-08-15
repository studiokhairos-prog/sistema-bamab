<?php
require __DIR__.'/layout.php';
site_header();
$logo=setting('logo_path','assets/brasao_bamab_2026.png');
$hero=setting('hero_image_path','assets/hero_banda_inicial.jpg');
$sponsors=[];
if(setting('sponsor_widget_enabled','1')==='1'){
  try{$sponsors=db()->query("SELECT * FROM sponsors WHERE active=1 AND logo_path<>'' ORDER BY sort_order ASC,id ASC")->fetchAll();}catch(Throwable $e){$sponsors=[];}
}
$institutional=[];
if(setting('institutional_widget_enabled','1')==='1'){
  try{$institutional=db()->query("SELECT * FROM institutional_supporters WHERE active=1 AND logo_path<>'' ORDER BY sort_order ASC,id ASC")->fetchAll();}catch(Throwable $e){$institutional=[];}
}
$institutionalPosition=in_array(setting('institutional_widget_position','left'),['left','right'],true)?setting('institutional_widget_position','left'):'left';
$institutionalWidth=max(210,min(340,(int)setting('institutional_widget_width','248')));
$homeContacts=[];if(setting('home_contact_enabled','1')==='1'){try{$homeContacts=array_slice(active_contact_channels('OFICIAL'),0,3);}catch(Throwable $e){$homeContacts=[];}}
?>
<main id="conteudo-principal" class="home-shell">
<section class="hero-v21">
  <div class="hero-crest"><div class="gold-swoosh"></div><img src="<?=e($logo)?>" alt="Brasão BAMAB"></div>
  <div class="hero-copy">
    <h1><?=e(setting('hero_title','BAMAB'))?></h1>
    <h2><?=e(setting('site_subtitle','BANDA AMARAL BRASIL'))?></h2>
    <div class="ornament"><span></span><b>✣</b><span></span></div>
    <h3><?=e(setting('welcome_title','SEJAM BEM-VINDOS!'))?></h3>
    <p><?=e(setting('hero_text'))?></p>
    <div class="hero-actions"><a class="gold-btn" href="historia.php">CONHEÇA NOSSA HISTÓRIA <b>›</b></a><a class="outline-btn" href="galeria.php">VER GALERIA <b>▣</b></a></div>
  </div>
  <div class="hero-photo" style="background-image:linear-gradient(90deg,#0b0b0b 0%,transparent 18%),url('<?=e($hero)?>')"><span class="hero-photo-shade"></span></div>
</section>

<section class="announcement-strip">
  <div class="announce-icon">✎</div>
  <div class="announce-intro"><h3><?=e(setting('announcement_heading','ESPAÇO EDITÁVEL DO ADMIN'))?></h3><p><?=e(setting('announcement_intro'))?></p></div>
  <div class="announce-divider"></div>
  <div class="announce-message"><h4><?=e(setting('announcement_title','COMUNICADO IMPORTANTE'))?></h4><p><?=e(setting('announcement_text'))?></p></div>
  <?php if(admin_user()):?><a class="announce-edit" href="admin/configuracoes.php">⚙ <span>EDITAR CONTEÚDO</span></a><?php endif;?>
</section>

<section class="enrollment-home-strip">
  <div class="enrollment-home-icon">✎</div>
  <div class="enrollment-home-copy">
    <span><?=setting('enrollment_open','1')==='1'?'INSCRIÇÕES ABERTAS':'INSCRIÇÕES TEMPORARIAMENTE FECHADAS'?></span>
    <h3><?=e(setting('enrollment_title','MATRÍCULAS BAMAB'))?> — <strong>BANDA AMARAL BRASIL</strong></h3>
    <p>Lira • Pratos • Bumbo • Caixa Tenor • Quintom • Corpo Coreógrafo • Porta Bandeira • Porta-Estandarte • Guarda de Honra</p>
  </div>
  <div class="home-enrollment-actions"><a href="matriculas.php" class="enrollment-home-btn"><?=active_enrollment_period(true)?'FAZER MATRÍCULA':'VER MATRÍCULAS'?> <b>›</b></a></div>
</section>

<section class="feature-grid">
  <article class="feature-card"><div class="feature-head"><span class="feature-icon">▤</span><h3>COMO A BANDA<br>COMEÇOU</h3></div><p>A trajetória da BAMAB desde sua fundação, marcada por sonhos, desafios e muita música.</p><a href="historia.php">SAIBA MAIS <b>›</b></a></article>
  <article class="feature-card"><div class="feature-head"><span class="feature-icon">★</span><h3>VITÓRIAS E<br>CAMPEONATOS</h3></div><p>Nossas conquistas que orgulham nossa história e representam nossa cidade.</p><a href="campeonatos.php">VER CONQUISTAS <b>›</b></a></article>
  <article class="feature-card"><div class="feature-head"><span class="feature-icon">▣</span><h3>GALERIA DE<br>FOTOS E VÍDEOS</h3></div><p>Momentos inesquecíveis registrados em imagens e vídeos da Banda Amaral Brasil.</p><a href="galeria.php">ACESSAR GALERIA <b>›</b></a></article>
  <article class="feature-card"><div class="feature-head"><span class="feature-icon">●</span><h3>BIOGRAFIA DOS<br>INTEGRANTES</h3></div><p>Conheça os músicos que fazem parte da nossa banda e da nossa família.</p><a href="integrantes.php">CONHECER INTEGRANTES <b>›</b></a></article>
  <article class="feature-card"><div class="feature-head"><span class="feature-icon">◆</span><h3>COORDENAÇÃO</h3></div><p>Equipe responsável por guiar, organizar e apoiar a BAMAB em sua trajetória.</p><a href="coordenacao.php">CONHECER EQUIPE <b>›</b></a></article>
</section>
<?php if($homeContacts):?>
<section class="home-contact-strip" aria-label="Contatos oficiais BAMAB"><div class="home-contact-heading"><span>FALE COM A BAMAB</span><h2><?=e(setting('home_contact_title','CONTATOS OFICIAIS'))?></h2><p>Escolha um dos canais oficiais abaixo.</p></div><div class="home-contact-links"><?php foreach($homeContacts as $hc):$hlink=contact_channel_link($hc);?><article><span><?=e($hc['contact_type'])?></span><strong><?=e($hc['label'])?></strong><small><?=e($hc['contact_value'])?></small><?php if($hlink):?><a href="<?=e($hlink)?>" <?=str_starts_with($hlink,'http')?'target="_blank" rel="noopener noreferrer"':''?>>ABRIR <b>›</b></a><?php endif;?></article><?php endforeach;?></div><a class="home-contact-all" href="contato.php">VER TODOS OS CONTATOS</a></section>
<?php endif;?>
</main>
<?php if($institutional || $sponsors):?>
<section class="home-support-zone institutional-position-<?=e($institutionalPosition)?>" aria-label="Apoios e parceiros da BAMAB">
<?php if($institutional):?>
<aside class="sponsor-float institutional-float" style="--widget-width:<?=$institutionalWidth?>px" data-rotating-logos aria-label="Apoio institucional: Prefeitura, Secretarias e órgãos parceiros">
  <div class="sponsor-float-head institutional-head">
    <img src="<?=e($logo)?>" alt="Brasão BAMAB">
    <div><strong><?=e(setting('institutional_widget_title','APOIO INSTITUCIONAL'))?></strong><small><?=e(setting('institutional_widget_subtitle','PREFEITURA • SECRETARIAS • ÓRGÃOS PARCEIROS'))?></small></div>
  </div>
  <div class="sponsor-stage" aria-live="polite">
    <?php foreach($institutional as $i=>$sp):$duration=max(3,min(60,(int)$sp['duration_seconds']));$scale=max(55,min(120,(int)$sp['logo_scale']));$website=safe_external_url((string)$sp['website_url']);?>
    <article class="sponsor-slide <?=$i===0?'is-active':''?>" data-duration="<?=$duration*1000?>" aria-hidden="<?=$i===0?'false':'true'?>">
      <?php if($website):?><a class="sponsor-logo-link" href="<?=e($website)?>" target="_blank" rel="noopener noreferrer" aria-label="Visitar <?=e($sp['name'])?>"><?php else:?><div class="sponsor-logo-link"><?php endif;?>
        <div class="sponsor-logo-frame" style="--sponsor-bg:<?=e(valid_hex_color((string)($sp['background_color']??''),'#ffffff'))?>;--sponsor-scale:<?=$scale?>%"><img src="<?=e($sp['logo_path'])?>" alt="Logo <?=e($sp['name'])?>"></div>
      <?php if($website):?></a><?php else:?></div><?php endif;?>
      <div class="sponsor-slide-copy"><span><?=e($sp['category'])?></span><?php if(setting('institutional_widget_show_name','1')==='1'):?><strong><?=e($sp['name'])?></strong><?php endif;?><?php if($sp['description']):?><small><?=e($sp['description'])?></small><?php endif;?></div>
    </article>
    <?php endforeach;?>
  </div>
  <div class="sponsor-float-foot"><div class="sponsor-progress"><i></i></div><div class="sponsor-dots" aria-label="Navegação do apoio institucional"><?php foreach($institutional as $i=>$sp):?><button type="button" class="<?=$i===0?'is-active':''?>" data-sponsor-go="<?=$i?>" aria-label="Mostrar <?=e($sp['name'])?>"></button><?php endforeach;?></div><small><b data-sponsor-current>1</b>/<?=count($institutional)?></small></div>
</aside>
<?php endif;?>

<?php if($sponsors):?>
<aside class="sponsor-float sponsor-commercial" id="sponsorFloat" data-rotating-logos aria-label="Patrocinadores, apoiadores e idealizadores da BAMAB">
  <div class="sponsor-float-head">
    <img src="<?=e($logo)?>" alt="Brasão BAMAB">
    <div><strong><?=e(setting('sponsor_widget_title','PARCEIROS DA BAMAB'))?></strong><small><?=e(setting('sponsor_widget_subtitle','PATROCINADORES • APOIADORES • IDEALIZADORES'))?></small></div>
  </div>
  <div class="sponsor-stage" aria-live="polite">
    <?php foreach($sponsors as $i=>$sp):$duration=max(3,min(60,(int)$sp['duration_seconds']));$scale=max(55,min(120,(int)$sp['logo_scale']));$website=safe_external_url((string)$sp['website_url']);?>
    <article class="sponsor-slide <?=$i===0?'is-active':''?>" data-duration="<?=$duration*1000?>" aria-hidden="<?=$i===0?'false':'true'?>">
      <?php if($website):?><a class="sponsor-logo-link" href="<?=e($website)?>" target="_blank" rel="noopener noreferrer sponsored" aria-label="Visitar <?=e($sp['name'])?>"><?php else:?><div class="sponsor-logo-link"><?php endif;?>
        <div class="sponsor-logo-frame" style="--sponsor-bg:<?=e(valid_hex_color((string)($sp['background_color']??''),'#ffffff'))?>;--sponsor-scale:<?=$scale?>%"><img src="<?=e($sp['logo_path'])?>" alt="Logo <?=e($sp['name'])?>"></div>
      <?php if($website):?></a><?php else:?></div><?php endif;?>
      <div class="sponsor-slide-copy"><span><?=e($sp['category'])?></span><?php if(setting('sponsor_widget_show_name','1')==='1'):?><strong><?=e($sp['name'])?></strong><?php endif;?><?php if($sp['description']):?><small><?=e($sp['description'])?></small><?php endif;?></div>
    </article>
    <?php endforeach;?>
  </div>
  <div class="sponsor-float-foot"><div class="sponsor-progress"><i></i></div><div class="sponsor-dots" aria-label="Navegação dos parceiros"><?php foreach($sponsors as $i=>$sp):?><button type="button" class="<?=$i===0?'is-active':''?>" data-sponsor-go="<?=$i?>" aria-label="Mostrar <?=e($sp['name'])?>"></button><?php endforeach;?></div><small><b data-sponsor-current>1</b>/<?=count($sponsors)?></small></div>
</aside>
<?php endif;?>
</section>
<?php endif;?>
<?php site_footer(); ?>