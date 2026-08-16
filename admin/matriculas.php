<?php
require __DIR__.'/_layout.php';
$u=require_admin();$pdo=db();$err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        $action=$_POST['action']??'';
        if($action==='create_period'){
            $name=trim((string)($_POST['name']??''));$start=$_POST['start_date']??'';$end=$_POST['end_date']??'';
            if($name===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)) throw new RuntimeException('Preencha nome, início e fim do período.');
            if($end<$start) throw new RuntimeException('A data final não pode ser anterior à inicial.');
            $active=isset($_POST['active'])?1:0;
            if($active)$pdo->exec("UPDATE enrollment_periods SET active=0,closed_at='".now_iso()."' WHERE active=1");
            $pdo->prepare("INSERT INTO enrollment_periods(name,start_date,end_date,active,created_at) VALUES(?,?,?,?,?)")->execute([$name,$start,$end,$active,now_iso()]);
            go('matriculas.php','Período de matrículas criado.');
        }
        if($action==='update_period'){
            $id=(int)$_POST['period_id'];$name=trim((string)$_POST['name']);$start=$_POST['start_date'];$end=$_POST['end_date'];
            if($name===''||$end<$start) throw new RuntimeException('Revise as datas do período.');
            $pdo->prepare("UPDATE enrollment_periods SET name=?,start_date=?,end_date=? WHERE id=?")->execute([$name,$start,$end,$id]);
            go('matriculas.php?period_id='.$id,'Período atualizado.');
        }
        if($action==='activate_period'){
            $id=(int)$_POST['period_id'];$pdo->exec("UPDATE enrollment_periods SET active=0 WHERE active=1");
            $pdo->prepare("UPDATE enrollment_periods SET active=1,closed_at=NULL WHERE id=?")->execute([$id]);
            set_setting('enrollment_open','1');go('matriculas.php?period_id='.$id,'Período ativado.');
        }
        if($action==='close_period'){
            $id=(int)$_POST['period_id'];$pdo->prepare("UPDATE enrollment_periods SET active=0,closed_at=? WHERE id=?")->execute([now_iso(),$id]);
            set_setting('enrollment_open','0');go('matriculas.php?period_id='.$id,'Período encerrado. O relatório final já está disponível.');
        }
        if($action==='delete_period'){
            if(!is_general_admin($u)) throw new RuntimeException('Somente o Admin Geral pode excluir períodos de inscrição.');
            $id=(int)($_POST['period_id']??0);
            $period=enrollment_period($id);
            if(!$period) throw new RuntimeException('Período não encontrado.');
            $st=$pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE period_id=?");$st->execute([$id]);
            $totalPeriod=(int)$st->fetchColumn();
            if($totalPeriod>0) throw new RuntimeException('Este período possui '.$totalPeriod.' matrícula(s) e não pode ser excluído para evitar perda de dados. Corrija as datas/nome ou encerre o período.');
            $wasActive=(int)$period['active']===1;
            $pdo->prepare("DELETE FROM enrollment_periods WHERE id=?")->execute([$id]);
            if($wasActive) set_setting('enrollment_open','0');
            go('matriculas.php','Período excluído com sucesso.');
        }
        if($action==='settings'){
            foreach(['enrollment_title','enrollment_intro','enrollment_terms_version','term_participation','term_image','term_instrument','term_uniform','term_privacy'] as $k) set_setting($k,trim((string)($_POST[$k]??'')));
            go('matriculas.php','Textos e termos atualizados.');
        }
    }catch(Throwable $e){$err=$e->getMessage();}
}

$periods=$pdo->query("SELECT p.*,(SELECT COUNT(*) FROM enrollments e WHERE e.period_id=p.id AND e.deleted_at IS NULL) total FROM enrollment_periods p ORDER BY p.id DESC")->fetchAll();
$active=active_enrollment_period(false);
$selectedId=(int)($_GET['period_id']??($active['id']??($periods[0]['id']??0)));
$selected=enrollment_period($selectedId);
$q=trim((string)($_GET['q']??''));$status=trim((string)($_GET['status']??''));$instrument=trim((string)($_GET['instrument']??''));
$where=['deleted_at IS NULL'];$params=[];
if($selected){$where[]='period_id=?';$params[]=$selectedId;}
if($q!==''){$where[]='(student_name LIKE ? OR registration_number LIKE ? OR protocol LIKE ? OR guardian_name LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like,$like);}
if($status!==''&&array_key_exists($status,enrollment_statuses())){$where[]='status=?';$params[]=$status;}
if($instrument!==''&&in_array($instrument,enrollment_instruments(),true)){$where[]='instrument=?';$params[]=$instrument;}
$st=$pdo->prepare("SELECT * FROM enrollments WHERE ".implode(' AND ',$where)." ORDER BY student_name COLLATE NOCASE LIMIT 500");$st->execute($params);$rows=$st->fetchAll();

$counts=$selected?enrollment_period_counts($selectedId):array_fill_keys(enrollment_instruments(),0);
$total=$selected?array_sum($counts):0;
$pending=$selected?enrollment_count("period_id=? AND status='PENDENTE' AND deleted_at IS NULL",[$selectedId]):0;
$approved=$selected?enrollment_count("period_id=? AND status='APROVADA' AND deleted_at IS NULL",[$selectedId]):0;
$cardsActive=$selected?enrollment_count("period_id=? AND deleted_at IS NULL AND id IN (SELECT enrollment_id FROM student_cards WHERE status='ATIVA')",[$selectedId]):0;

admin_header('Matrículas por Período');$msg=flash();
?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<section class="panel period-manager">
 <div class="panel-heading-flex"><div><h2>Períodos de matrícula</h2><p>Somente um período fica ativo por vez. As inscrições fecham automaticamente fora das datas definidas.</p><p class="period-delete-help"><strong>Período criado por engano?</strong> O Admin Geral pode usar EXCLUIR enquanto ainda não houver nenhuma matrícula vinculada. Períodos com inscrições ficam protegidos contra exclusão acidental.</p></div><?php if($active):?><span class="period-live">ATIVO: <?=e($active['name'])?></span><?php else:?><span class="period-closed-admin">SEM PERÍODO ATIVO</span><?php endif;?></div>
 <form method="post" class="period-create-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_period">
   <label>Nome do período<input name="name" required placeholder="Ex.: Matrículas 2027 — 1º semestre"></label>
   <label>Início<input type="date" name="start_date" required></label><label>Fim<input type="date" name="end_date" required></label>
   <label class="check"><input type="checkbox" name="active"> Ativar agora</label><button class="primary">CRIAR PERÍODO</button>
 </form>
 <div class="period-list"><?php foreach($periods as $p):?>
  <article class="<?=$selectedId==(int)$p['id']?'selected-period':''?>">
   <div><strong><?=e($p['name'])?></strong><small><?=e(date('d/m/Y',strtotime($p['start_date'])))?> a <?=e(date('d/m/Y',strtotime($p['end_date'])))?> · <?=$p['total']?> inscrição(ões)</small></div>
   <span class="period-state state-<?=strtolower(period_status_label($p))?>"><?=e(period_status_label($p))?></span>
   <a class="small-button" href="matriculas.php?period_id=<?=$p['id']?>">ABRIR</a>
   <?php if((int)$p['active']===1):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="close_period"><input type="hidden" name="period_id" value="<?=$p['id']?>"><button class="danger-soft">ENCERRAR</button></form>
   <?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="activate_period"><input type="hidden" name="period_id" value="<?=$p['id']?>"><button class="activate-btn">ATIVAR</button></form><?php endif;?>
   <?php if(is_general_admin($u)):?>
     <?php if((int)$p['total']===0):?>
       <form method="post" class="delete-period-form" onsubmit="return confirm('ATENÇÃO: excluir definitivamente o período <?=e(addslashes($p['name']))?>? Esta ação não pode ser desfeita.');">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="action" value="delete_period">
        <input type="hidden" name="period_id" value="<?=$p['id']?>">
        <button class="delete-period-btn" title="Excluir período criado por engano">EXCLUIR</button>
       </form>
     <?php else:?>
       <button class="delete-period-btn disabled" type="button" disabled title="Este período possui matrículas e não pode ser excluído">EXCLUIR</button>
     <?php endif;?>
   <?php endif;?>
  </article>
 <?php endforeach;?><?php if(!$periods):?><div class="empty-public">Crie o primeiro período para liberar as matrículas.</div><?php endif;?></div>
</section>

<?php if($selected):?>
<section class="panel selected-period-admin">
 <div class="panel-heading-flex"><div><span>PERÍODO SELECIONADO</span><h2><?=e($selected['name'])?></h2><p><?=e(date('d/m/Y',strtotime($selected['start_date'])))?> a <?=e(date('d/m/Y',strtotime($selected['end_date'])))?> · <?=e(period_status_label($selected))?></p></div>
 <div class="period-actions"><a class="admin-action-link" href="relatorio_matriculas.php?period_id=<?=$selectedId?>">▤ RELATÓRIO GERAL</a><a class="admin-action-link" href="relatorio_matriculas.php?period_id=<?=$selectedId?>&export=xls">⇩ EXCEL</a><a class="admin-action-link" href="relatorio_matriculas.php?period_id=<?=$selectedId?>&export=csv">⇩ CSV</a></div></div>
 <form method="post" class="period-edit-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_period"><input type="hidden" name="period_id" value="<?=$selectedId?>">
  <label>Nome<input name="name" value="<?=e($selected['name'])?>" required></label><label>Início<input type="date" name="start_date" value="<?=e($selected['start_date'])?>" required></label><label>Fim<input type="date" name="end_date" value="<?=e($selected['end_date'])?>" required></label><button class="primary">SALVAR DATAS</button>
 </form>
</section>

<div class="kpis enrollment-kpis"><article><span>Total do período</span><strong><?=$total?></strong></article><article><span>Pendentes</span><strong><?=$pending?></strong></article><article><span>Aprovadas</span><strong><?=$approved?></strong></article><article><span>Carteirinhas ativas</span><strong><?=$cardsActive?></strong></article></div>

<section class="panel">
 <div class="panel-heading-flex"><div><h2>Inscritos por ala / vaga escolhida</h2><p>Contagem atualizada diretamente das inscrições deste período.</p></div></div>
 <div class="ala-count-grid"><?php foreach($counts as $ala=>$n):?><a href="matriculas.php?period_id=<?=$selectedId?>&instrument=<?=urlencode($ala)?>#lista"><span><?=e($ala)?></span><strong><?=$n?></strong><small>inscrito(s)</small></a><?php endforeach;?></div>
</section>

<section class="panel" id="lista">
 <div class="panel-heading-flex"><div><h2>Lista de inscrições</h2><p>Ordenada alfabeticamente. Novas inscrições públicas são aprovadas automaticamente; o Admin pode editar os dados e alterar o status a qualquer momento.</p></div><a class="admin-action-link" href="relatorio_matriculas.php?period_id=<?=$selectedId?>">IMPRIMIR RELATÓRIO</a></div>
 <form method="get" class="filter-row"><input type="hidden" name="period_id" value="<?=$selectedId?>">
  <label>Pesquisar<input name="q" value="<?=e($q)?>" placeholder="Nome ou matrícula"></label>
  <label>Status<select name="status"><option value="">Todos</option><?php foreach(enrollment_statuses() as $k=>$v):if($k==='EXCLUIDA')continue;?><option value="<?=e($k)?>" <?=$status===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label>
  <label>Ala<select name="instrument"><option value="">Todas</option><?php foreach(enrollment_instruments() as $i):?><option <?=$instrument===$i?'selected':''?>><?=e($i)?></option><?php endforeach;?></select></label>
  <button class="primary">FILTRAR</button>
 </form>
 <div class="table-wrap"><table class="admin-table"><thead><tr><th>Nº matrícula</th><th>Foto</th><th>Nome</th><th>Ala</th><th>Responsável</th><th>Status</th><th>Carteirinha</th><th></th></tr></thead><tbody>
 <?php foreach($rows as $r):$card=enrollment_card((int)$r['id']);?><tr>
  <td><strong><?=e($r['registration_number']?:$r['protocol'])?></strong></td>
  <td><?php if($r['photo_path']):?><img class="admin-thumb-3x4" src="../<?=e($r['photo_path'])?>" alt=""><?php else:?>-<?php endif;?></td>
  <td><?=e($r['student_name'])?><?php if($r['preferred_name']):?><small class="preferred-list-name">CHAMAR: <?=e($r['preferred_name'])?></small><?php endif;?><?php if((int)$r['is_minor']===1):?><small class="minor-tag">MENOR</small><?php endif;?></td>
  <td><?=e($r['instrument'])?></td><td><?=e($r['guardian_name']?:'-')?></td>
  <td><span class="status-pill status-<?=e(strtolower($r['status']))?>"><?=e(enrollment_status_label($r['status']))?></span></td>
  <td><?=($card&&$card['status']==='ATIVA')?'<span class="card-active">ATIVA</span>':'<span class="card-inactive">NÃO ATIVA</span>'?></td>
  <td><a class="small-button" href="matricula_ver.php?id=<?=$r['id']?>">ABRIR</a></td>
 </tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="8" class="empty-cell">Nenhuma matrícula encontrada.</td></tr><?php endif;?></tbody></table></div>
</section>

<section class="panel ala-rosters">
 <h2>Listas rápidas por ala</h2><p>Estas listas se atualizam automaticamente a cada nova matrícula.</p>
 <div class="roster-grid">
 <?php foreach(enrollment_instruments() as $ala):$st=$pdo->prepare("SELECT registration_number,student_name,status FROM enrollments WHERE period_id=? AND instrument=? AND deleted_at IS NULL ORDER BY student_name COLLATE NOCASE");$st->execute([$selectedId,$ala]);$rr=$st->fetchAll();?>
  <article><h3><?=e($ala)?> <span><?=count($rr)?></span></h3><?php foreach($rr as $x):?><div><strong><?=e($x['student_name'])?></strong><small><?=e($x['registration_number'])?> · <?=e(enrollment_status_label($x['status']))?></small></div><?php endforeach;?><?php if(!$rr):?><em>Sem inscritos.</em><?php endif;?></article>
 <?php endforeach;?>
 </div>
</section>
<?php endif;?>

<section class="panel">
 <h2>Textos e termos da matrícula</h2><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="settings">
 <div class="grid2"><label>Título<input name="enrollment_title" value="<?=e(setting('enrollment_title'))?>"></label><label>Versão dos termos<input name="enrollment_terms_version" value="<?=e(setting('enrollment_terms_version','2026.1'))?>"></label></div>
 <label>Apresentação<textarea name="enrollment_intro" rows="3"><?=e(setting('enrollment_intro'))?></textarea></label>
 <label>Termo — participação<textarea name="term_participation" rows="5"><?=e(setting('term_participation'))?></textarea></label>
 <label>Termo — imagem e voz<textarea name="term_image" rows="6"><?=e(setting('term_image'))?></textarea></label>
 <label>Termo — instrumentos<textarea name="term_instrument" rows="6"><?=e(setting('term_instrument'))?></textarea></label>
 <label>Termo — uniformes e camisas<textarea name="term_uniform" rows="6"><?=e(setting('term_uniform'))?></textarea></label>
 <label>Aviso de privacidade<textarea name="term_privacy" rows="6"><?=e(setting('term_privacy'))?></textarea></label><button class="primary">SALVAR TEXTOS E TERMOS</button></form>
</section>
<?php admin_footer();?>
