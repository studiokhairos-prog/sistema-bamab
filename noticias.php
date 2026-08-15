<?php
require __DIR__.'/layout.php';
$rows=db()->query("SELECT * FROM posts WHERE published=1 ORDER BY created_at DESC,id DESC")->fetchAll();
site_header('Notícias');
?>
<main id="conteudo-principal" class="inner-page content-page"><header class="page-banner"><span>NOVIDADES</span><h1>Notícias</h1><p>Comunicados, histórias e novidades oficiais da Banda Amaral Brasil.</p></header><div class="news-grid"><?php foreach($rows as $n):$ts=strtotime((string)$n['created_at']);?><article class="news-card"><?php if($n['cover_path']):?><img src="<?=e($n['cover_path'])?>" alt="Capa da notícia <?=e($n['title'])?>"><?php endif;?><small><?=e($ts?date('d/m/Y',$ts):'')?></small><h3><?=e($n['title'])?></h3><?php if($n['summary']):?><p><?=e($n['summary'])?></p><?php endif;?><a href="noticia.php?id=<?=(int)$n['id']?>">Ler mais →</a></article><?php endforeach;?></div><?php if(!$rows):?><div class="empty-public">Nenhuma notícia foi publicada ainda.</div><?php endif;?></main><?php site_footer();?>
