<?php
require __DIR__.'/_layout.php';
$u=require_admin();$err='';$result=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        if(empty($_FILES['instagram_zip']['tmp_name'])) throw new RuntimeException('Selecione o ZIP exportado pelo Instagram.');
        if((int)($_FILES['instagram_zip']['size']??0)>1200*1024*1024) throw new RuntimeException('O ZIP deve ter no máximo 1,2 GB nesta importação.');
        $result=instagram_export_import($_FILES['instagram_zip']['tmp_name']);
        if($result['logo_candidate'] && !setting('logo_path')){
            set_setting('logo_path',$result['logo_candidate']);
            try{apply_palette_from_logo($result['logo_candidate']);}catch(Throwable $ignored){}
        }
    }catch(Throwable $e){$err=$e->getMessage();}
}
admin_header('Importar todo o Instagram');
?>
<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<?php if($result):?><div class="alert ok"><strong>Importação concluída.</strong> <?=e((string)$result['images'])?> foto(s) e <?=e((string)$result['videos'])?> vídeo(s) importados.<?php if($result['logo_candidate']):?> Uma imagem de perfil foi detectada como possível brasão.<?php endif;?></div><?php endif;?>

<section class="panel form-stack">
<h2>Importar fotos e vídeos oficiais da conta</h2>
<p>Este importador recebe o arquivo ZIP gerado pelo próprio Instagram/Meta e adiciona automaticamente todas as imagens e vídeos encontrados à galeria BAMAB.</p>
<form method="post" enctype="multipart/form-data" class="form-stack">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label>Arquivo ZIP da exportação do Instagram<input type="file" name="instagram_zip" accept=".zip,application/zip" required></label>
<button class="primary">IMPORTAR TODO O CONTEÚDO DO ZIP</button>
</form>
</section>

<section class="panel">
<h2>Como obter o arquivo oficial</h2>
<ol class="steps-admin">
<li>No Instagram, abra <strong>Configurações / Centro de Contas</strong>.</li>
<li>Entre em <strong>Suas informações e permissões</strong>.</li>
<li>Escolha <strong>Exportar suas informações</strong>.</li>
<li>Selecione o perfil <strong>@bamab_slp</strong>.</li>
<li>Escolha exportar para o dispositivo, período completo e qualidade alta das mídias.</li>
<li>Quando o Instagram liberar o arquivo, baixe o ZIP e envie aqui.</li>
</ol>
</section>

<div class="panel">
<h2>Brasão e cores</h2>
<p>Se o ZIP trouxer uma imagem identificada como foto de perfil e ainda não houver brasão cadastrado, o sistema tenta usá-la como brasão e aplicar automaticamente as cores predominantes. Você também pode enviar o brasão manualmente em <a href="configuracoes.php">Configurações do Site</a>.</p>
</div>
<?php admin_footer();?>
