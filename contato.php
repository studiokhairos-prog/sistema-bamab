<?php
require __DIR__.'/layout.php';
$channels=[];try{$channels=active_contact_channels();}catch(Throwable $e){$channels=[];}
$official=array_values(array_filter($channels,fn($c)=>($c['channel_level']??'')==='OFICIAL'));
$secondary=array_values(array_filter($channels,fn($c)=>($c['channel_level']??'')==='SECUNDARIO'));
$instagram=safe_external_url(setting('instagram_url'));$whatsapp=safe_external_url(setting('whatsapp_url'));$email=trim(setting('contact_email'));$address=trim(setting('address'));
site_header('Contato');
function bamab_contact_icon(string $type): string {return ['WHATSAPP'=>'◉','TELEFONE'=>'☎','EMAIL'=>'✉','INSTAGRAM'=>'◎','FACEBOOK'=>'f','YOUTUBE'=>'▶','SITE'=>'⌂','OUTRO'=>'◆'][$type]??'◆';}
function bamab_contact_card(array $c,bool $official=false): void {$link=contact_channel_link($c);$isWa=($c['contact_type']??'')==='WHATSAPP';?>
<article class="dynamic-contact-card <?=$official?'contact-official':''?> <?=$isWa?'contact-whatsapp':''?>"><div class="contact-channel-icon"><?=e(bamab_contact_icon((string)$c['contact_type']))?></div><div class="contact-channel-copy"><span><?=$official?'CANAL OFICIAL':'CANAL SECUNDÁRIO'?> · <?=e((string)$c['contact_type'])?></span><h2><?=e((string)$c['label'])?></h2><?php if($c['contact_value']):?><strong><?=e((string)$c['contact_value'])?></strong><?php endif;?><?php if($c['description']):?><p><?=e((string)$c['description'])?></p><?php endif;?></div><?php if($link):?><a class="contact-channel-action" href="<?=e($link)?>" <?=str_starts_with($link,'http')?'target="_blank" rel="noopener noreferrer"':''?>><?=e($isWa?setting('contact_whatsapp_button_text','CHAMAR NO WHATSAPP'):'ABRIR CONTATO')?> <b>›</b></a><?php endif;?></article>
<?php }
?>
<main id="conteudo-principal" class="content-page contact-dynamic-page"><header class="page-banner"><span>CONTATO BAMAB</span><h1><?=e(setting('contact_section_title','FALE COM A BAMAB'))?></h1><p><?=e(setting('contact_section_subtitle','Canais oficiais e secundários de atendimento, informações e convites.'))?></p></header>
<?php if($channels):?>
<section class="contact-channel-section"><div class="contact-section-title"><span>PRINCIPAIS CANAIS</span><h2>Contatos oficiais</h2><p>Use os canais destacados abaixo para informações, convites, apresentações, campeonatos e assuntos institucionais.</p></div><div class="contact-official-grid"><?php foreach($official as $c)bamab_contact_card($c,true);?></div></section>
<?php if($secondary):?><section class="contact-channel-section secondary-contacts"><div class="contact-section-title"><span>OUTRAS FORMAS DE FALAR</span><h2>Contatos secundários</h2></div><div class="contact-secondary-grid"><?php foreach($secondary as $c)bamab_contact_card($c,false);?></div></section><?php endif;?>
<?php else:?>
<section class="contact-panel"><img src="<?=e(setting('logo_path','assets/brasao_bamab_2026.png'))?>" alt="Brasão BAMAB"><div><?php if($instagram):?><a href="<?=e($instagram)?>" target="_blank" rel="noopener noreferrer">Instagram oficial</a><?php endif;?><?php if($email&&filter_var($email,FILTER_VALIDATE_EMAIL)):?><a href="mailto:<?=e($email)?>"><?=e($email)?></a><?php endif;?><?php if($whatsapp):?><a href="<?=e($whatsapp)?>" target="_blank" rel="noopener noreferrer">WhatsApp oficial</a><?php endif;?><?php if($address):?><p><?=e($address)?></p><?php endif;?><?php if(!$instagram&&!$whatsapp&&!$email&&!$address):?><p class="empty-public">Os canais oficiais de contato serão publicados pela Coordenação.</p><?php endif;?></div></section>
<?php endif;?>
<?php if($address):?><section class="contact-address"><span>ENDEREÇO / REFERÊNCIA</span><strong><?=e($address)?></strong></section><?php endif;?>
</main><?php site_footer();?>
