<?php
require __DIR__.'/_layout.php';
$u=require_admin();
$pdo=db();
$err='';
$categories=['PATROCINADOR','APOIADOR','IDEALIZADOR','PARCEIRO'];

function sponsor_hex(string $value,string $fallback='#ffffff'): string {
    $value=trim($value);
    return preg_match('/^#[0-9a-fA-F]{6}$/',$value) ? strtolower($value) : $fallback;
}
function sponsor_url(string $value): string {
    $value=trim($value);
    if($value==='') return '';
    if(!filter_var($value,FILTER_VALIDATE_URL)) throw new RuntimeException('Informe um link válido, começando com http:// ou https://.');
    $scheme=strtolower((string)parse_url($value,PHP_URL_SCHEME));
    if(!in_array($scheme,['http','https'],true)) throw new RuntimeException('O link deve usar http:// ou https://.');
    return $value;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        csrf_check();
        $action=(string)($_POST['action']??'save');
        if($action==='settings'){
            set_setting('sponsor_widget_enabled',isset($_POST['sponsor_widget_enabled'])?'1':'0');
            set_setting('sponsor_widget_title',trim((string)($_POST['sponsor_widget_title']??'PARCEIROS DA BAMAB')));
            set_setting('sponsor_widget_subtitle',trim((string)($_POST['sponsor_widget_subtitle']??'')));
            set_setting('sponsor_widget_show_name',isset($_POST['sponsor_widget_show_name'])?'1':'0');
            go('patrocinadores.php','Configurações do espaço de parceiros atualizadas.');
        }
        if($action==='toggle'){
            $id=(int)($_POST['id']??0);
            $st=$pdo->prepare("UPDATE sponsors SET active=CASE WHEN active=1 THEN 0 ELSE 1 END,updated_at=? WHERE id=?");
            $st->execute([now_iso(),$id]);
            go('patrocinadores.php','Visibilidade do parceiro atualizada.');
        }
        if($action==='delete'){
            $id=(int)($_POST['id']??0);
            $st=$pdo->prepare("SELECT logo_path FROM sponsors WHERE id=?");$st->execute([$id]);$row=$st->fetch();
            if($row){
                $pdo->prepare("DELETE FROM sponsors WHERE id=?")->execute([$id]);
                delete_local_media((string)$row['logo_path']);
            }
            go('patrocinadores.php','Parceiro removido.');
        }

        $id=(int)($_POST['id']??0);
        $name=trim((string)($_POST['name']??''));
        $category=trim((string)($_POST['category']??'PATROCINADOR'));
        $website=sponsor_url((string)($_POST['website_url']??''));
        $description=trim((string)($_POST['description']??''));
        $duration=max(3,min(60,(int)($_POST['duration_seconds']??6)));
        $sort=max(0,min(9999,(int)($_POST['sort_order']??100)));
        $scale=max(55,min(120,(int)($_POST['logo_scale']??90)));
        $bg=sponsor_hex((string)($_POST['background_color']??'#ffffff'));
        $active=isset($_POST['active'])?1:0;
        if(mb_strlen($name)<2) throw new RuntimeException('Informe o nome da empresa, parceiro ou idealizador.');
        if(mb_strlen($name)>120) throw new RuntimeException('O nome deve ter no máximo 120 caracteres.');
        if(!in_array($category,$categories,true)) $category='PATROCINADOR';
        if(mb_strlen($description)>220) throw new RuntimeException('A descrição deve ter no máximo 220 caracteres.');

        $old=null;
        if($id>0){$st=$pdo->prepare("SELECT * FROM sponsors WHERE id=?");$st->execute([$id]);$old=$st->fetch()?:null;if(!$old)throw new RuntimeException('Parceiro não encontrado.');}
        $logo=$old['logo_path']??'';
        $oldLogoToDelete='';
        if(!empty($_FILES['logo']['name'])){
            $newLogo=upload_file($_FILES['logo'],'image');
            if($logo!=='') $oldLogoToDelete=(string)$logo;
            $logo=$newLogo;
        }
        if($logo==='') throw new RuntimeException('Envie a logo do parceiro.');

        if($id>0){
            $st=$pdo->prepare("UPDATE sponsors SET name=?,category=?,logo_path=?,website_url=?,description=?,duration_seconds=?,sort_order=?,logo_scale=?,background_color=?,active=?,updated_at=? WHERE id=?");
            $st->execute([$name,$category,$logo,$website,$description,$duration,$sort,$scale,$bg,$active,now_iso(),$id]);
            if($oldLogoToDelete!=='') delete_local_media($oldLogoToDelete);
            go('patrocinadores.php','Parceiro atualizado. A alteração já aparece na página principal.');
        }
        $st=$pdo->prepare("INSERT INTO sponsors(name,category,logo_path,website_url,description,duration_seconds,sort_order,logo_scale,background_color,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->execute([$name,$category,$logo,$website,$description,$duration,$sort,$scale,$bg,$active,now_iso(),now_iso()]);
        go('patrocinadores.php','Parceiro cadastrado e incluído no espaço da página principal.');
    }catch(Throwable $e){$err=$e->getMessage();}
}

$editId=(int)($_GET['edit']??0);$edit=null;
if($editId){$st=$pdo->prepare("SELECT * FROM sponsors WHERE id=?");$st->execute([$editId]);$edit=$st->fetch()?:null;}
$rows=$pdo->query("SELECT * FROM sponsors ORDER BY sort_order ASC,id ASC")->fetchAll();
$activeCount=0;$cycle=0;foreach($rows as $r){if((int)$r['active']===1){$activeCount++;$cycle+=(int)$r['duration_seconds'];}}
admin_header('Patrocinadores, Apoiadores e Idealizadores');$msg=flash();
?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<div class="panel" style="margin-bottom:12px"><a class="small-button" href="reconhecimentos.php?kind=PARCEIRO">CERTIFICADOS DE PARCEIROS</a></div>
<section class="panel" style="border-left:4px solid var(--gold,#c99a34);margin-bottom:16px"><strong style="color:var(--gold,#c99a34)">ESPAÇO EMBUTIDO — NÃO FLUTUA</strong><p style="margin:.45rem 0 0">O espaço de parceiros fica <b>dentro da parte inferior direita da página principal</b> e não acompanha a rolagem. Ele pode ser desligado pela chave abaixo sem apagar nenhum cadastro. O tempo, tamanho, ordem e visibilidade de cada logo continuam sendo controlados individualmente.</p></section>

<section class="panel sponsor-admin-summary">
  <div class="panel-heading-flex">
    <div><span>ESPAÇO INSTITUCIONAL DA PÁGINA PRINCIPAL</span><h2>Divulgação de quem fortalece a BAMAB</h2><p>As logos aparecem em rotação automática no espaço inferior direito da página, sem flutuar sobre o conteúdo. Cada parceiro pode ter um tempo diferente de exibição.</p></div>
    <a class="small-button" href="../index.php" target="_blank" rel="noopener noreferrer">VER NA PÁGINA PRINCIPAL</a>
  </div>
  <div class="sponsor-admin-stats"><div><strong><?=count($rows)?></strong><span>CADASTRADOS</span></div><div><strong><?=$activeCount?></strong><span>ATIVOS</span></div><div><strong><?=$cycle?>s</strong><span>VOLTA COMPLETA</span></div><div><strong><?=setting('sponsor_widget_enabled','1')==='1'?'ATIVO':'DESATIVADO'?></strong><span>ESPAÇO PÚBLICO</span></div></div>
</section>

<form class="panel form-stack sponsor-global-form" method="post">
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="settings">
  <h2>Configuração geral do espaço</h2>
  <div class="grid2">
    <label>Título do espaço<input name="sponsor_widget_title" value="<?=e(setting('sponsor_widget_title','PARCEIROS DA BAMAB'))?>" maxlength="80"></label>
    <label>Subtítulo<input name="sponsor_widget_subtitle" value="<?=e(setting('sponsor_widget_subtitle','PATROCINADORES • APOIADORES • IDEALIZADORES'))?>" maxlength="120"></label>
  </div>
  <div class="sponsor-switches"><label class="check"><input type="checkbox" name="sponsor_widget_enabled" <?=setting('sponsor_widget_enabled','1')==='1'?'checked':''?>> Exibir o espaço na página principal</label><label class="check"><input type="checkbox" name="sponsor_widget_show_name" <?=setting('sponsor_widget_show_name','1')==='1'?'checked':''?>> Mostrar nome do parceiro abaixo da logo</label></div>
  <button class="primary">SALVAR CONFIGURAÇÃO GERAL</button>
</form>

<form class="panel form-stack sponsor-edit-form" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$editId?>">
  <h2><?=$edit?'Editar parceiro':'Cadastrar novo parceiro'?></h2>
  <div class="grid2">
    <label>Nome / Empresa<input name="name" required maxlength="120" value="<?=e($edit['name']??'')?>" placeholder="Ex.: Empresa Exemplo"></label>
    <label>Categoria<select name="category"><?php foreach($categories as $cat):?><option value="<?=e($cat)?>" <?=($edit['category']??'PATROCINADOR')===$cat?'selected':''?>><?=e($cat)?></option><?php endforeach;?></select></label>
    <label>Logo <?=!$edit?'<strong>(obrigatória)</strong>':''?><input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" <?=$edit?'':'required'?>><small>PNG com fundo transparente costuma ficar melhor.</small></label>
    <label>Link da empresa / rede social<input type="url" name="website_url" value="<?=e($edit['website_url']??'')?>" placeholder="https://..."><small>Ao clicar na logo, o visitante será direcionado para este endereço.</small></label>
    <label>Tempo desta logo na tela (segundos)<input type="number" name="duration_seconds" min="3" max="60" value="<?=e((string)($edit['duration_seconds']??6))?>"><small>Use mais segundos para dar maior destaque a um patrocinador.</small></label>
    <label>Ordem de exibição<input type="number" name="sort_order" min="0" max="9999" value="<?=e((string)($edit['sort_order']??100))?>"><small>Menor número aparece primeiro.</small></label>
    <label>Tamanho da logo dentro do quadro (%)<input type="range" name="logo_scale" min="55" max="120" value="<?=e((string)($edit['logo_scale']??90))?>" oninput="this.nextElementSibling.textContent=this.value+'%'"><small><?=e((string)($edit['logo_scale']??90))?>%</small></label>
    <label>Cor de fundo da área da logo<input type="color" name="background_color" value="<?=e($edit['background_color']??'#ffffff')?>"><small>Útil para logos escuras ou claras.</small></label>
  </div>
  <label>Texto curto opcional<textarea name="description" rows="2" maxlength="220" placeholder="Ex.: Apoio oficial às atividades culturais da BAMAB."><?=e($edit['description']??'')?></textarea></label>
  <label class="check"><input type="checkbox" name="active" <?=!$edit||(int)($edit['active']??1)===1?'checked':''?>> Ativo e disponível para aparecer no site</label>
  <?php if($edit && $edit['logo_path']):?><div class="sponsor-edit-preview" style="--sponsor-bg:<?=e($edit['background_color'])?>;--sponsor-scale:<?=e((string)$edit['logo_scale'])?>%"><span>PRÉVIA DA LOGO ATUAL</span><div><img src="../<?=e($edit['logo_path'])?>" alt="Logo atual"></div></div><?php endif;?>
  <div class="agenda-form-actions"><button class="primary"><?=$edit?'SALVAR ALTERAÇÕES':'CADASTRAR PARCEIRO'?></button><?php if($edit):?><a class="small-button" href="patrocinadores.php">CANCELAR EDIÇÃO</a><?php endif;?></div>
</form>

<section class="panel">
  <div class="panel-heading-flex"><div><h2>Logos cadastradas</h2><p>O tempo de cada cartão é independente. O sistema passa para a próxima logo automaticamente.</p></div></div>
  <div class="sponsor-admin-list">
  <?php foreach($rows as $r):?>
    <article class="sponsor-admin-card <?=$r['active']?'is-active':'is-inactive'?>">
      <div class="sponsor-admin-logo" style="background:<?=e($r['background_color'])?>"><img src="../<?=e($r['logo_path'])?>" alt="<?=e($r['name'])?>" style="max-width:<?=e((string)$r['logo_scale'])?>%;max-height:<?=e((string)$r['logo_scale'])?>%"></div>
      <div class="sponsor-admin-info"><span><?=e($r['category'])?></span><h3><?=e($r['name'])?></h3><?php if($r['description']):?><p><?=e($r['description'])?></p><?php endif;?><div><b><?=e((string)$r['duration_seconds'])?> segundos</b><small>Ordem <?=e((string)$r['sort_order'])?></small><small><?=$r['active']?'ATIVO':'OCULTO'?></small></div><?php if($r['website_url']):?><a href="<?=e($r['website_url'])?>" target="_blank" rel="noopener noreferrer">ABRIR LINK ↗</a><?php endif;?></div>
      <div class="sponsor-admin-actions"><a class="small-button" href="?edit=<?=$r['id']?>">EDITAR</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="small-button" type="submit"><?=$r['active']?'OCULTAR':'ATIVAR'?></button></form><form method="post" onsubmit="return confirm('Excluir este parceiro e a logo enviada?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="danger" type="submit">EXCLUIR</button></form></div>
    </article>
  <?php endforeach;?>
  <?php if(!$rows):?><div class="empty-cell">Nenhum patrocinador, apoiador ou idealizador cadastrado ainda.</div><?php endif;?>
  </div>
</section>
<?php admin_footer();?>
