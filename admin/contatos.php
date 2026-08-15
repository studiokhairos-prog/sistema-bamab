<?php
require __DIR__.'/_layout.php';
$u=require_admin();
if(!is_general_admin($u)){http_response_code(403);exit('Somente o Admin Geral pode editar os contatos.');}
$pdo=db();$err='';$editId=(int)($_GET['edit']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        $action=(string)($_POST['action']??'save');
        if($action==='settings'){
            foreach(['contact_section_title','contact_section_subtitle','contact_whatsapp_button_text','home_contact_title'] as $k){set_setting($k,trim((string)($_POST[$k]??'')));}
            set_setting('home_contact_enabled',isset($_POST['home_contact_enabled'])?'1':'0');
            go('contatos.php','Apresentação dos contatos atualizada.');
        }
        if($action==='delete'){
            $id=(int)($_POST['id']??0);$pdo->prepare("DELETE FROM contact_channels WHERE id=?")->execute([$id]);
            go('contatos.php','Canal de contato excluído.');
        }
        if($action==='toggle'){
            $id=(int)($_POST['id']??0);$pdo->prepare("UPDATE contact_channels SET active=CASE active WHEN 1 THEN 0 ELSE 1 END,updated_at=? WHERE id=?")->execute([now_iso(),$id]);
            go('contatos.php','Visibilidade do canal atualizada.');
        }
        $id=(int)($_POST['id']??0);
        $label=trim((string)($_POST['label']??''));
        $type=(string)($_POST['contact_type']??'TELEFONE');
        $value=trim((string)($_POST['contact_value']??''));
        $url=trim((string)($_POST['link_url']??''));
        $message=trim((string)($_POST['whatsapp_message']??''));
        $level=(string)($_POST['channel_level']??'OFICIAL');
        $desc=trim((string)($_POST['description']??''));
        $order=max(0,min(9999,(int)($_POST['sort_order']??100)));
        $active=isset($_POST['active'])?1:0;
        if(mb_strlen($label)<2)throw new RuntimeException('Informe um nome para o contato.');
        if(!array_key_exists($type,contact_channel_types()))throw new RuntimeException('Tipo de contato inválido.');
        if(!array_key_exists($level,contact_channel_levels()))throw new RuntimeException('Classificação inválida.');
        if($type==='EMAIL' && !filter_var($value,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Informe um e-mail válido.');
        if($type==='TELEFONE' && strlen(normalize_phone_digits($value))<10)throw new RuntimeException('Informe o telefone com DDD.');
        if($type==='WHATSAPP' && strlen(normalize_phone_digits($value))<10 && safe_external_url($url)==='')throw new RuntimeException('Informe o WhatsApp com DDD ou um link direto válido.');
        if(!in_array($type,['WHATSAPP','TELEFONE','EMAIL'],true) && $url!=='' && safe_external_url($url)==='')throw new RuntimeException('Informe um link válido iniciando com http:// ou https://.');
        if($type==='WHATSAPP' && $url!=='' && safe_external_url($url)==='')throw new RuntimeException('O link direto do WhatsApp é inválido. Deixe vazio para o sistema gerar automaticamente.');
        $now=now_iso();
        if($id>0){
            $pdo->prepare("UPDATE contact_channels SET label=?,contact_type=?,contact_value=?,link_url=?,whatsapp_message=?,channel_level=?,description=?,sort_order=?,active=?,updated_at=? WHERE id=?")
                ->execute([$label,$type,$value,$url,$message,$level,$desc,$order,$active,$now,$id]);
            go('contatos.php','Canal de contato atualizado.');
        }
        $pdo->prepare("INSERT INTO contact_channels(label,contact_type,contact_value,link_url,whatsapp_message,channel_level,description,sort_order,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$label,$type,$value,$url,$message,$level,$desc,$order,$active,$now,$now]);
        go('contatos.php','Novo canal de contato cadastrado.');
    }catch(Throwable $e){$err=$e->getMessage();}
}
$edit=null;if($editId>0){$st=$pdo->prepare("SELECT * FROM contact_channels WHERE id=?");$st->execute([$editId]);$edit=$st->fetch()?:null;}
$rows=$pdo->query("SELECT * FROM contact_channels ORDER BY CASE channel_level WHEN 'OFICIAL' THEN 0 ELSE 1 END,sort_order,id")->fetchAll();
admin_header('Contatos e WhatsApp');$msg=flash();
?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="panel contact-admin-intro"><div class="panel-heading-flex"><div><span>CONTATOS DINÂMICOS BAMAB</span><h2>Oficial, secundários e WhatsApp</h2><p>Cadastre quantos contatos precisar. O sistema separa o canal oficial dos secundários e cria automaticamente o link do WhatsApp a partir do número com DDD.</p></div><a class="small-button" target="_blank" rel="noopener noreferrer" href="../contato.php">VER PÁGINA PÚBLICA</a></div></section>

<form class="panel form-stack" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="settings">
<h2>Apresentação pública</h2><div class="grid2"><label>Título da página<input name="contact_section_title" value="<?=e(setting('contact_section_title','FALE COM A BAMAB'))?>" maxlength="120"></label><label>Título na página inicial<input name="home_contact_title" value="<?=e(setting('home_contact_title','CONTATOS OFICIAIS'))?>" maxlength="100"></label></div>
<label>Descrição<textarea name="contact_section_subtitle" rows="3" maxlength="600"><?=e(setting('contact_section_subtitle'))?></textarea></label>
<div class="grid2"><label>Texto do botão WhatsApp<input name="contact_whatsapp_button_text" value="<?=e(setting('contact_whatsapp_button_text','CHAMAR NO WHATSAPP'))?>" maxlength="80"></label><label class="checkline"><input type="checkbox" name="home_contact_enabled" <?=setting('home_contact_enabled','1')==='1'?'checked':''?>> Mostrar contatos oficiais também na página principal</label></div>
<button class="primary">SALVAR APRESENTAÇÃO</button></form>

<form class="panel form-stack" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$edit?(int)$edit['id']:0?>">
<h2><?=$edit?'Editar contato':'Adicionar contato'?></h2>
<div class="grid2"><label>Nome / identificação<input name="label" value="<?=e($edit['label']??'')?>" placeholder="Ex.: WhatsApp Oficial BAMAB" required maxlength="100"></label><label>Classificação<select name="channel_level"><?php foreach(contact_channel_levels() as $k=>$v):?><option value="<?=e($k)?>" <?=($edit['channel_level']??'OFICIAL')===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label><label>Tipo<select name="contact_type" id="contactType"><?php foreach(contact_channel_types() as $k=>$v):?><option value="<?=e($k)?>" <?=($edit['contact_type']??'WHATSAPP')===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label><label>Número / e-mail / identificação<input name="contact_value" value="<?=e($edit['contact_value']??'')?>" placeholder="Ex.: (98) 99999-9999"></label><label>Link externo opcional<input type="url" name="link_url" value="<?=e($edit['link_url']??'')?>" placeholder="https://..."><small>Para WhatsApp, pode deixar vazio: o sistema gera o link pelo número.</small></label><label>Ordem<input type="number" min="0" max="9999" name="sort_order" value="<?=e((string)($edit['sort_order']??100))?>"></label></div>
<label>Mensagem inicial do WhatsApp<textarea name="whatsapp_message" rows="2" maxlength="500" placeholder="Olá! Gostaria de falar com a BAMAB."><?=e($edit['whatsapp_message']??'')?></textarea></label>
<label>Descrição / finalidade<textarea name="description" rows="3" maxlength="500" placeholder="Ex.: Informações gerais, convites e apresentações."><?=e($edit['description']??'')?></textarea></label>
<label class="checkline"><input type="checkbox" name="active" <?=!$edit||(int)$edit['active']===1?'checked':''?>> Contato ativo e visível</label>
<div class="form-actions"><button class="primary"><?=$edit?'SALVAR ALTERAÇÕES':'CADASTRAR CONTATO'?></button><?php if($edit):?><a class="small-button" href="contatos.php">CANCELAR EDIÇÃO</a><?php endif;?></div></form>

<section class="panel"><div class="panel-heading-flex"><div><h2>Contatos cadastrados</h2><p>O selo OFICIAL fica em maior destaque. Os demais aparecem como canais secundários.</p></div><strong><?=count($rows)?> canal(is)</strong></div>
<div class="contact-admin-list"><?php if(!$rows):?><p>Nenhum canal dinâmico cadastrado. Cadastre o WhatsApp oficial e os contatos secundários acima.</p><?php endif;?><?php foreach($rows as $c):$link=contact_channel_link($c);?><article class="contact-admin-card <?=((int)$c['active']===1)?'':'is-inactive'?>"><div><span><?=e($c['channel_level'])?> · <?=e($c['contact_type'])?></span><h3><?=e($c['label'])?></h3><strong><?=e($c['contact_value']?:'—')?></strong><p><?=e($c['description']?:'Sem descrição.')?></p><?php if($link):?><a target="_blank" rel="noopener noreferrer" href="<?=e($link)?>">TESTAR LINK ↗</a><?php endif;?></div><div class="contact-admin-actions"><a class="small-button" href="contatos.php?edit=<?=(int)$c['id']?>">EDITAR</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><button class="small-button"><?=((int)$c['active']===1)?'OCULTAR':'ATIVAR'?></button></form><form method="post" onsubmit="return confirm('Excluir este contato?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><button class="danger-soft">EXCLUIR</button></form></div></article><?php endforeach;?></div></section>
<?php admin_footer();?>
