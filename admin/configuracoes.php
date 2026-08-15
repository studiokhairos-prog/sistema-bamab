<?php
require __DIR__.'/_layout.php';
$u=require_admin();$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        $textKeys=['site_name','site_subtitle','hero_title','welcome_title','hero_text','history_title','history_text','announcement_heading','announcement_intro','announcement_title','announcement_text','motto','address','report_city','report_state','footer_text','enrollment_thanks_title','enrollment_thanks_text','card_back_notice','badge_back_rules'];
        foreach($textKeys as $k) set_setting($k,trim((string)($_POST[$k]??'')));

        foreach(['instagram_url','youtube_url','facebook_url','whatsapp_url'] as $k){
            $raw=trim((string)($_POST[$k]??''));
            if($raw!=='' && safe_external_url($raw)==='') throw new RuntimeException('Informe links válidos usando http:// ou https://.');
            if($k==='instagram_url' && $raw!=='' && !instagram_url_valid($raw)) throw new RuntimeException('O endereço do Instagram deve ser um link oficial do instagram.com.');
            set_setting($k,$raw);
        }
        $email=trim((string)($_POST['contact_email']??''));
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail de contato válido.');
        set_setting('contact_email',$email);

        foreach(['primary_color'=>'#090909','secondary_color'=>'#c99a34','dark_color'=>'#050505','light_color'=>'#ededed'] as $k=>$fallback){
            $raw=trim((string)($_POST[$k]??$fallback));
            if(!preg_match('/^#[0-9a-fA-F]{6}$/',$raw)) throw new RuntimeException('Uma das cores informadas é inválida.');
            set_setting($k,strtolower($raw));
        }

        if(!empty($_FILES['logo']['name'])){
            $old=setting('logo_path');$new=upload_file($_FILES['logo'],'image');set_setting('logo_path',$new);
            if($old && !str_starts_with($old,'assets/')) delete_local_media($old);
        }
        if(!empty($_FILES['hero']['name'])){
            $old=setting('hero_image_path');$new=upload_file($_FILES['hero'],'image');set_setting('hero_image_path',$new);
            if($old && !str_starts_with($old,'assets/')) delete_local_media($old);
        }
        go('configuracoes.php','Conteúdo e identidade visual atualizados.');
    }catch(Throwable $e){$err=$e->getMessage();}
}
admin_header('Editar Site');$msg=flash();
?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<form class="panel form-stack" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<h2>Identidade oficial</h2><div class="identity-preview"><?php if(setting('logo_path')):?><img src="../<?=e(setting('logo_path'))?>" alt="Brasão"><?php endif;?><div><strong><?=e(setting('site_name'))?></strong><span><?=e(setting('site_subtitle'))?></span><p>Identidade visual oficial usada no site, painel e documentos.</p></div></div>
<div class="grid2"><label>Nome principal<input name="site_name" value="<?=e(setting('site_name'))?>" maxlength="80"></label><label>Nome completo<input name="site_subtitle" value="<?=e(setting('site_subtitle'))?>" maxlength="140"></label><label>Substituir brasão<input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"></label><label>Substituir foto principal da banda<input type="file" name="hero" accept="image/png,image/jpeg,image/webp,image/gif"></label></div>
<h2>Topo da página</h2><div class="grid2"><label>Título<input name="hero_title" value="<?=e(setting('hero_title'))?>" maxlength="80"></label><label>Saudação<input name="welcome_title" value="<?=e(setting('welcome_title'))?>" maxlength="100"></label></div><label>Texto de apresentação<textarea name="hero_text" rows="5" maxlength="1200"><?=e(setting('hero_text'))?></textarea></label>
<h2>Espaço editável / comunicado</h2><div class="grid2"><label>Título da área<input name="announcement_heading" value="<?=e(setting('announcement_heading'))?>" maxlength="100"></label><label>Título do comunicado<input name="announcement_title" value="<?=e(setting('announcement_title'))?>" maxlength="100"></label></div><label>Descrição da área<textarea name="announcement_intro" rows="3" maxlength="700"><?=e(setting('announcement_intro'))?></textarea></label><label>Comunicado importante<textarea name="announcement_text" rows="4" maxlength="1000"><?=e(setting('announcement_text'))?></textarea></label>
<h2>História</h2><label>Título<input name="history_title" value="<?=e(setting('history_title'))?>" maxlength="120"></label><label>Como a banda começou<textarea name="history_text" rows="10" maxlength="12000"><?=e(setting('history_text'))?></textarea></label>

<h2>Comprovante, carteirinha e crachá</h2>
<p>O brasão usado nestes documentos é o mesmo brasão oficial configurado acima.</p>
<label>Título de agradecimento do comprovante<input name="enrollment_thanks_title" value="<?=e(setting('enrollment_thanks_title'))?>" maxlength="140"></label>
<label>Mensagem de agradecimento do comprovante<textarea name="enrollment_thanks_text" rows="4" maxlength="1200"><?=e(setting('enrollment_thanks_text'))?></textarea></label>
<label>Informações do verso da carteirinha<textarea name="card_back_notice" rows="4" maxlength="1500"><?=e(setting('card_back_notice'))?></textarea></label>
<label>Regras do verso do crachá<textarea name="badge_back_rules" rows="4" maxlength="1800"><?=e(setting('badge_back_rules'))?></textarea></label>
<h2>Rodapé</h2><label>Frase principal<input name="motto" value="<?=e(setting('motto'))?>" maxlength="180"></label><label>Frase institucional<input name="footer_text" value="<?=e(setting('footer_text'))?>" maxlength="200"></label>
<h2>Redes e contato básico</h2><p>Estes campos continuam disponíveis por compatibilidade. Para cadastrar telefone oficial, telefones secundários, vários WhatsApps e demais canais, use o gerenciador avançado.</p><p><a class="small-button" href="contatos.php">ABRIR CONTATOS / WHATSAPP</a></p><div class="grid2"><label>Instagram<input type="url" name="instagram_url" value="<?=e(setting('instagram_url'))?>" placeholder="https://www.instagram.com/..."></label><label>YouTube<input type="url" name="youtube_url" value="<?=e(setting('youtube_url'))?>" placeholder="https://..."></label><label>Facebook<input type="url" name="facebook_url" value="<?=e(setting('facebook_url'))?>" placeholder="https://..."></label><label>WhatsApp<input type="url" name="whatsapp_url" value="<?=e(setting('whatsapp_url'))?>" placeholder="https://wa.me/..."></label><label>E-mail<input type="email" name="contact_email" value="<?=e(setting('contact_email'))?>"></label><label>Endereço<input name="address" value="<?=e(setting('address'))?>" maxlength="300"></label></div>
<h2>Relatórios e declarações A4</h2><p>Estes dados aparecem no cabeçalho impresso, junto ao brasão oficial.</p><div class="grid2"><label>Cidade<input name="report_city" value="<?=e(setting('report_city','Santa Luzia do Paruá'))?>" maxlength="100"></label><label>Estado<input name="report_state" value="<?=e(setting('report_state','Maranhão'))?>" maxlength="100"></label></div>
<h2>Cores</h2><div class="grid4"><label>Preto<input type="color" name="primary_color" value="<?=e(valid_hex_color(setting('primary_color','#090909'),'#090909'))?>"></label><label>Dourado<input type="color" name="secondary_color" value="<?=e(valid_hex_color(setting('secondary_color','#c99a34'),'#c99a34'))?>"></label><label>Fundo escuro<input type="color" name="dark_color" value="<?=e(valid_hex_color(setting('dark_color','#050505'),'#050505'))?>"></label><label>Prata / claro<input type="color" name="light_color" value="<?=e(valid_hex_color(setting('light_color','#ededed'),'#ededed'))?>"></label></div>
<button class="primary">SALVAR TODAS AS ALTERAÇÕES</button></form><?php admin_footer();?>
