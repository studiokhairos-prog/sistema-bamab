<?php
require __DIR__.'/_layout.php';
$u=require_admin();
$pdo=db();
$err='';
$categories=['PREFEITURA','SECRETARIA','ÓRGÃO PÚBLICO','APOIO INSTITUCIONAL','REALIZAÇÃO','PARCEIRO INSTITUCIONAL'];

function institutional_hex(string $value,string $fallback='#ffffff'): string {
    $value=trim($value);
    return preg_match('/^#[0-9a-fA-F]{6}$/',$value) ? strtolower($value) : $fallback;
}
function institutional_url(string $value): string {
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
            $position=(string)($_POST['institutional_widget_position']??'left');
            if(!in_array($position,['left','right'],true)) $position='left';
            $width=max(210,min(340,(int)($_POST['institutional_widget_width']??248)));
            set_setting('institutional_widget_enabled',isset($_POST['institutional_widget_enabled'])?'1':'0');
            set_setting('institutional_widget_title',trim((string)($_POST['institutional_widget_title']??'APOIO INSTITUCIONAL')));
            set_setting('institutional_widget_subtitle',trim((string)($_POST['institutional_widget_subtitle']??'PREFEITURA • SECRETARIAS • ÓRGÃOS PARCEIROS')));
            set_setting('institutional_widget_show_name',isset($_POST['institutional_widget_show_name'])?'1':'0');
            set_setting('institutional_widget_position',$position);
            set_setting('institutional_widget_width',(string)$width);
            go('apoio_institucional.php','Configurações do apoio institucional atualizadas.');
        }
        if($action==='toggle'){
            $id=(int)($_POST['id']??0);
            $st=$pdo->prepare("UPDATE institutional_supporters SET active=CASE WHEN active=1 THEN 0 ELSE 1 END,updated_at=? WHERE id=?");
            $st->execute([now_iso(),$id]);
            go('apoio_institucional.php','Visibilidade da instituição atualizada.');
        }
        if($action==='delete'){
            $id=(int)($_POST['id']??0);
            $st=$pdo->prepare("SELECT logo_path FROM institutional_supporters WHERE id=?");$st->execute([$id]);$row=$st->fetch();
            if($row){$pdo->prepare("DELETE FROM institutional_supporters WHERE id=?")->execute([$id]);delete_local_media((string)$row['logo_path']);}
            go('apoio_institucional.php','Instituição removida.');
        }

        $id=(int)($_POST['id']??0);
        $name=trim((string)($_POST['name']??''));
        $category=trim((string)($_POST['category']??'PREFEITURA'));
        $website=institutional_url((string)($_POST['website_url']??''));
        $description=trim((string)($_POST['description']??''));
        $duration=max(3,min(60,(int)($_POST['duration_seconds']??7)));
        $sort=max(0,min(9999,(int)($_POST['sort_order']??100)));
        $scale=max(55,min(120,(int)($_POST['logo_scale']??90)));
        $bg=institutional_hex((string)($_POST['background_color']??'#ffffff'));
        $active=isset($_POST['active'])?1:0;
        if(mb_strlen($name)<2) throw new RuntimeException('Informe o nome da Prefeitura, Secretaria ou instituição.');
        if(mb_strlen($name)>120) throw new RuntimeException('O nome deve ter no máximo 120 caracteres.');
        if(!in_array($category,$categories,true)) $category='APOIO INSTITUCIONAL';
        if(mb_strlen($description)>220) throw new RuntimeException('A descrição deve ter no máximo 220 caracteres.');

        $old=null;
        if($id>0){$st=$pdo->prepare("SELECT * FROM institutional_supporters WHERE id=?");$st->execute([$id]);$old=$st->fetch()?:null;if(!$old)throw new RuntimeException('Instituição não encontrada.');}
        $logo=$old['logo_path']??'';$oldLogoToDelete='';
        if(!empty($_FILES['logo']['name'])){$newLogo=upload_file($_FILES['logo'],'image');if($logo!=='')$oldLogoToDelete=(string)$logo;$logo=$newLogo;}
        if($logo==='') throw new RuntimeException('Envie a logo da instituição.');

        if($id>0){
            $st=$pdo->prepare("UPDATE institutional_supporters SET name=?,category=?,logo_path=?,website_url=?,description=?,duration_seconds=?,sort_order=?,logo_scale=?,background_color=?,active=?,updated_at=? WHERE id=?");
            $st->execute([$name,$category,$logo,$website,$description,$duration,$sort,$scale,$bg,$active,now_iso(),$id]);
            if($oldLogoToDelete!=='') delete_local_media($oldLogoToDelete);
            go('apoio_institucional.php','Instituição atualizada. A alteração já aparece na página principal.');
        }
        $st=$pdo->prepare("INSERT INTO institutional_supporters(name,category,logo_path,website_url,description,duration_seconds,sort_order,logo_scale,background_color,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->execute([$name,$category,$logo,$website,$description,$duration,$sort,$scale,$bg,$active,now_iso(),now_iso()]);
        go('apoio_institucional.php','Instituição cadastrada no espaço de apoio institucional.');
    }catch(Throwable $e){$err=$e->getMessage();}
}

$editId=(int)($_GET['edit']??0);$edit=null;
if($editId){$st=$pdo->prepare("SELECT * FROM institutional_supporters WHERE id=?");$st->execute([$editId]);$edit=$st->fetch()?:null;}
$rows=$pdo->query("SELECT * FROM institutional_supporters ORDER BY sort_order ASC,id ASC")->fetchAll();
$activeCount=0;$cycle=0;foreach($rows as $r){if((int)$r['active']===1){$activeCount++;$cycle+=(int)$r['duration_seconds'];}}
admin_header('Prefeitura, Secretarias e Apoio Institucional');$msg=flash();
?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<div class="panel" style="margin-bottom:12px"><a class="small-button" href="reconhecimentos.php?kind=INSTITUCIONAL">CERTIFICADOS INSTITUCIONAIS</a></div>
<section class="panel institutional-admin-note" style="margin-bottom:16px"><strong style="color:var(--site-gold,#c99a34)">ESPAÇO EMBUTIDO — NÃO FLUTUA</strong><p style="margin:.45rem 0 0">Este quadro fica dentro da página principal e <b>não acompanha a rolagem</b>. Você escolhe abaixo se ele fica na parte inferior à esquerda ou à direita. É um espaço separado dos patrocinadores comerciais.</p></section>

<section class="panel sponsor-admin-summary">
  <div class="panel-heading-flex"><div><span>APOIO INSTITUCIONAL DA BAMAB</span><h2>Prefeitura, Secretarias e órgãos que apoiam a Banda</h2><p>Cadastre as logos oficiais e controle individualmente tempo, ordem, tamanho e visibilidade.</p></div><a class="small-button" href="../index.php" target="_blank" rel="noopener noreferrer">VER NA PÁGINA PRINCIPAL</a></div>
  <div class="sponsor-admin-stats"><div><strong><?=count($rows)?></strong><span>CADASTRADOS</span></div><div><strong><?=$activeCount?></strong><span>ATIVOS</span></div><div><strong><?=$cycle?>s</strong><span>VOLTA COMPLETA</span></div><div><strong><?=setting('institutional_widget_enabled','1')==='1'?'ATIVO':'DESATIVADO'?></strong><span>ESPAÇO PÚBLICO</span></div></div>
</section>

<form class="panel form-stack sponsor-global-form" method="post">
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="settings">
  <h2>Configuração geral do espaço institucional</h2>
  <div class="grid2">
    <label>Título do espaço<input name="institutional_widget_title" value="<?=e(setting('institutional_widget_title','APOIO INSTITUCIONAL'))?>" maxlength="80"></label>
    <label>Subtítulo<input name="institutional_widget_subtitle" value="<?=e(setting('institutional_widget_subtitle','PREFEITURA • SECRETARIAS • ÓRGÃOS PARCEIROS'))?>" maxlength="120"></label>
    <label>Largura do quadro (px)<input type="number" name="institutional_widget_width" min="210" max="340" value="<?=e(setting('institutional_widget_width','248'))?>"><small>Faixa segura: 210 a 340 px.</small></label>
  </div>
  <div><strong style="display:block;margin-bottom:8px;color:var(--site-gold,#c99a34)">POSIÇÃO NA PARTE INFERIOR DA PÁGINA</strong><div class="institutional-position-grid"><label class="institutional-position-option"><input type="radio" name="institutional_widget_position" value="left" <?=setting('institutional_widget_position','left')==='left'?'checked':''?>> ESQUERDA</label><label class="institutional-position-option"><input type="radio" name="institutional_widget_position" value="right" <?=setting('institutional_widget_position','left')==='right'?'checked':''?>> DIREITA</label></div></div>
  <div class="sponsor-switches"><label class="check"><input type="checkbox" name="institutional_widget_enabled" <?=setting('institutional_widget_enabled','1')==='1'?'checked':''?>> Exibir o espaço institucional na página principal</label><label class="check"><input type="checkbox" name="institutional_widget_show_name" <?=setting('institutional_widget_show_name','1')==='1'?'checked':''?>> Mostrar nome da instituição abaixo da logo</label></div>
  <button class="primary">SALVAR CONFIGURAÇÃO GERAL</button>
</form>

<form class="panel form-stack sponsor-edit-form" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$editId?>">
  <h2><?=$edit?'Editar instituição':'Cadastrar Prefeitura, Secretaria ou instituição'?></h2>
  <div class="grid2">
    <label>Nome da instituição<input name="name" required maxlength="120" value="<?=e($edit['name']??'')?>" placeholder="Ex.: Prefeitura Municipal"></label>
    <label>Categoria<select name="category"><?php foreach($categories as $cat):?><option value="<?=e($cat)?>" <?=($edit['category']??'PREFEITURA')===$cat?'selected':''?>><?=e($cat)?></option><?php endforeach;?></select></label>
    <label>Logo <?=!$edit?'<strong>(obrigatória)</strong>':''?><input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" <?=$edit?'':'required'?>><small>PNG com fundo transparente costuma ficar melhor.</small></label>
    <label>Site / rede social oficial<input type="url" name="website_url" value="<?=e($edit['website_url']??'')?>" placeholder="https://..."><small>Opcional. Ao clicar na logo, o visitante abre este endereço.</small></label>
    <label>Tempo desta logo na tela (segundos)<input type="number" name="duration_seconds" min="3" max="60" value="<?=e((string)($edit['duration_seconds']??7))?>"><small>Cada instituição pode ter seu próprio tempo.</small></label>
    <label>Ordem de exibição<input type="number" name="sort_order" min="0" max="9999" value="<?=e((string)($edit['sort_order']??100))?>"><small>Menor número aparece primeiro.</small></label>
    <label>Tamanho da logo dentro do quadro (%)<input type="range" name="logo_scale" min="55" max="120" value="<?=e((string)($edit['logo_scale']??90))?>" oninput="this.nextElementSibling.textContent=this.value+'%'"><small><?=e((string)($edit['logo_scale']??90))?>%</small></label>
    <label>Cor de fundo da área da logo<input type="color" name="background_color" value="<?=e($edit['background_color']??'#ffffff')?>"><small>Ajuda a adaptar logos claras ou escuras.</small></label>
  </div>
  <label>Texto curto opcional<textarea name="description" rows="2" maxlength="220" placeholder="Ex.: Apoio institucional às atividades culturais da BAMAB."><?=e($edit['description']??'')?></textarea></label>
  <label class="check"><input type="checkbox" name="active" <?=!$edit||(int)($edit['active']??1)===1?'checked':''?>> Ativa e disponível para aparecer no site</label>
  <?php if($edit && $edit['logo_path']):?><div class="sponsor-edit-preview" style="--sponsor-bg:<?=e($edit['background_color'])?>;--sponsor-scale:<?=e((string)$edit['logo_scale'])?>%"><span>PRÉVIA DA LOGO ATUAL</span><div><img src="../<?=e($edit['logo_path'])?>" alt="Logo atual"></div></div><?php endif;?>
  <div class="agenda-form-actions"><button class="primary"><?=$edit?'SALVAR ALTERAÇÕES':'CADASTRAR INSTITUIÇÃO'?></button><?php if($edit):?><a class="small-button" href="apoio_institucional.php">CANCELAR EDIÇÃO</a><?php endif;?></div>
</form>

<section class="panel">
  <div class="panel-heading-flex"><div><h2>Logos institucionais cadastradas</h2><p>As logos passam automaticamente e respeitam o tempo configurado em cada cadastro.</p></div></div>
  <div class="sponsor-admin-list">
  <?php foreach($rows as $r):?>
    <article class="sponsor-admin-card <?=$r['active']?'is-active':'is-inactive'?>">
      <div class="sponsor-admin-logo" style="background:<?=e($r['background_color'])?>"><img src="../<?=e($r['logo_path'])?>" alt="<?=e($r['name'])?>" style="max-width:<?=e((string)$r['logo_scale'])?>%;max-height:<?=e((string)$r['logo_scale'])?>%"></div>
      <div class="sponsor-admin-info"><span><?=e($r['category'])?></span><h3><?=e($r['name'])?></h3><?php if($r['description']):?><p><?=e($r['description'])?></p><?php endif;?><div><b><?=e((string)$r['duration_seconds'])?> segundos</b><small>Ordem <?=e((string)$r['sort_order'])?></small><small><?=$r['active']?'ATIVO':'OCULTO'?></small></div><?php if($r['website_url']):?><a href="<?=e($r['website_url'])?>" target="_blank" rel="noopener noreferrer">ABRIR LINK ↗</a><?php endif;?></div>
      <div class="sponsor-admin-actions"><a class="small-button" href="?edit=<?=$r['id']?>">EDITAR</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="small-button" type="submit"><?=$r['active']?'OCULTAR':'ATIVAR'?></button></form><form method="post" onsubmit="return confirm('Excluir esta instituição e a logo enviada?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="danger" type="submit">EXCLUIR</button></form></div>
    </article>
  <?php endforeach;?>
  <?php if(!$rows):?><div class="empty-cell">Nenhuma Prefeitura, Secretaria ou instituição cadastrada ainda.</div><?php endif;?>
  </div>
</section>
<?php admin_footer();?>
