<?php
require __DIR__.'/_layout.php';$u=require_admin();$pdo=db();
$id=(int)($_GET['id']??$_POST['id']??0);
$st=$pdo->prepare("SELECT e.*,p.name period_name,p.start_date period_start,p.end_date period_end FROM enrollments e LEFT JOIN enrollment_periods p ON p.id=e.period_id WHERE e.id=?");$st->execute([$id]);$r=$st->fetch();
if(!$r){http_response_code(404);exit('Matrícula não encontrada.');}
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();
 try{
  $action=$_POST['action']??'save';
  if($action==='save'){
   $status=(string)($_POST['status']??'PENDENTE');if(!array_key_exists($status,enrollment_statuses())||$status==='EXCLUIDA')$status='PENDENTE';
   $notes=trim((string)($_POST['admin_notes']??''));
   $pdo->prepare("UPDATE enrollments SET status=?,admin_notes=?,updated_at=? WHERE id=?")->execute([$status,$notes,now_iso(),$id]);
   if($status==='APROVADA'){
       issue_or_activate_card($id,(int)$u['id']);
       go('matricula_ver.php?id='.$id,'Aluno APROVADO. Carteirinha e crachá foram ativados automaticamente.');
   }
   if(enrollment_card($id)) deactivate_card($id,'Documento desativado porque a matrícula está com status '.enrollment_status_label($status).'.');
   go('matricula_ver.php?id='.$id,'Matrícula atualizada. Documentos de identificação permanecem inativos até a aprovação.');
  }
  if($action==='photo'){
   if(empty($_FILES['photo']['name'])) throw new RuntimeException('Selecione a nova foto.');
   $new=upload_file($_FILES['photo'],'image');$old=$r['photo_path'];
   $pdo->prepare("UPDATE enrollments SET photo_path=?,updated_at=? WHERE id=?")->execute([$new,now_iso(),$id]);delete_local_media((string)$old);
   go('matricula_ver.php?id='.$id,'Foto 3x4 atualizada.');
  }
  if($action==='guardian_update'){
   if((int)$r['is_minor']!==1) throw new RuntimeException('Esta matrícula não é de menor.');
   $gname=trim((string)($_POST['guardian_name']??''));$gbirth=trim((string)($_POST['guardian_birth_date']??''));$gcpf=trim((string)($_POST['guardian_cpf']??''));
   $gphone=trim((string)($_POST['guardian_phone']??''));$gemail=trim((string)($_POST['guardian_email']??''));$grel=trim((string)($_POST['guardian_relationship']??''));
   $gaddr=trim((string)($_POST['guardian_address']??''));$gneigh=trim((string)($_POST['guardian_neighborhood']??''));$gcity=trim((string)($_POST['guardian_city']??''));
   if(mb_strlen($gname)<3||birth_age($gbirth)<18||!cpf_valid($gcpf)||mb_strlen($gphone)<8||!filter_var($gemail,FILTER_VALIDATE_EMAIL)||$grel===''||$gaddr===''||$gcity==='') throw new RuntimeException('Revise os dados do responsável: nome, nascimento 18+, CPF válido, telefone, e-mail, vínculo e endereço são obrigatórios.');
   $photo=$r['guardian_photo_path'];
   if(!empty($_FILES['guardian_photo']['name'])){$new=upload_file($_FILES['guardian_photo'],'image');delete_local_media((string)$photo);$photo=$new;}
   $pdo->prepare("UPDATE enrollments SET guardian_name=?,guardian_birth_date=?,guardian_cpf=?,guardian_phone=?,guardian_email=?,guardian_relationship=?,guardian_address=?,guardian_neighborhood=?,guardian_city=?,guardian_photo_path=?,updated_at=? WHERE id=?")
       ->execute([$gname,$gbirth,normalize_cpf($gcpf),$gphone,$gemail,$grel,$gaddr,$gneigh,$gcity,$photo,now_iso(),$id]);
   if($r['status']==='APROVADA') issue_or_activate_guardian_card($id,(int)$u['id']);
   go('matricula_ver.php?id='.$id,'Dados do responsável atualizados.');
  }
  if($action==='education_work_update'){
   $studying=(int)($_POST['currently_studying']??0)===1;
   $network=trim((string)($_POST['school_network']??''));
   $school=trim((string)($_POST['school_name']??''));
   $works=(int)($_POST['works_currently']??0)===1;
   $employer=trim((string)($_POST['employer_name']??''));
   $needs=$works&&((int)($_POST['needs_work_declaration']??0)===1);
   if($studying&&(!array_key_exists($network,school_networks())||mb_strlen($school)<3)) throw new RuntimeException('Para aluno que estuda, informe a rede e o nome da escola.');
   if($needs&&mb_strlen($employer)<2) throw new RuntimeException('Informe a empresa/empregador para a declaração de trabalho.');
   $pdo->prepare("UPDATE enrollments SET currently_studying=?,school_network=?,school_name=?,works_currently=?,employer_name=?,needs_work_declaration=?,updated_at=? WHERE id=?")
     ->execute([$studying?1:0,$studying?$network:'',$studying?$school:'',$works?1:0,$works?$employer:'',$needs?1:0,now_iso(),$id]);
   go('matricula_ver.php?id='.$id,'Situação escolar/profissional atualizada.');
  }
  if($action==='issue_card'){
   if(!is_general_admin($u)) throw new RuntimeException('Somente o Admin Geral pode emitir carteirinhas.');
   issue_or_activate_card($id,(int)$u['id']);go('matricula_ver.php?id='.$id,'Carteirinha emitida/ativada.');
  }
  if($action==='deactivate_card'){
   if(!is_general_admin($u)) throw new RuntimeException('Somente o Admin Geral pode desativar carteirinhas.');
   deactivate_card($id,trim((string)($_POST['reason']??'Desativada pelo Admin Geral.')));go('matricula_ver.php?id='.$id,'Carteirinha desativada.');
  }
  if($action==='delete'){
   if(!is_general_admin($u)) throw new RuntimeException('Somente o Admin Geral pode excluir matrículas.');
   deactivate_card($id,'Inscrição excluída/cancelada.');
   $pdo->prepare("UPDATE enrollments SET status='EXCLUIDA',deleted_at=?,deleted_by=?,updated_at=? WHERE id=?")->execute([now_iso(),(int)$u['id'],now_iso(),$id]);
   go('matriculas.php?period_id='.(int)$r['period_id'],'Inscrição excluída da lista e carteirinha desativada.');
  }
 }catch(Throwable $e){$err=$e->getMessage();}
}
$st=$pdo->prepare("SELECT e.*,p.name period_name,p.start_date period_start,p.end_date period_end FROM enrollments e LEFT JOIN enrollment_periods p ON p.id=e.period_id WHERE e.id=?");$st->execute([$id]);$r=$st->fetch();
$card=enrollment_card($id);$age=birth_age($r['birth_date']);admin_header('Matrícula '.($r['registration_number']?:$r['protocol']));$msg=flash();
?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<div class="detail-toolbar"><a class="small-button" href="matriculas.php?period_id=<?=(int)$r['period_id']?>">← VOLTAR</a><a class="small-button" href="ficha_matricula.php?id=<?=$id?>" target="_blank" rel="noopener noreferrer">IMPRIMIR FICHA A4</a><?php if($card):?><a class="small-button card-print-btn" href="carteirinha.php?id=<?=$id?>" target="_blank" rel="noopener noreferrer">CARTEIRINHA</a><?php endif;?><a class="small-button" href="reconhecimentos.php?kind=ALUNO&q=<?=urlencode($r['registration_number']?:$r['student_name'])?>&year=<?=date('Y')?>">CERTIFICADO / DIPLOMA</a></div>

<section class="panel enrollment-admin-detail">
 <div class="detail-header"><div><span>NÚMERO DE MATRÍCULA</span><h2><?=e($r['registration_number']?:$r['protocol'])?></h2><p>Protocolo <?=e($r['protocol'])?> · <?=e($r['period_name']?:'Período legado')?> · recebida em <?=e(date('d/m/Y H:i',strtotime($r['created_at'])))?></p></div><span class="status-pill status-<?=e(strtolower($r['status']))?>"><?=e(enrollment_status_label($r['status']))?></span></div>
 <div class="student-photo-detail"><?php if($r['photo_path']):?><img src="../<?=e($r['photo_path'])?>" alt="Foto 3x4 de <?=e($r['student_name'])?>"><?php else:?><div>SEM FOTO</div><?php endif;?>
  <form method="post" enctype="multipart/form-data" class="photo-update-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="photo"><label>Atualizar foto 3x4<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label><button class="small-button">SALVAR FOTO</button></form>
 </div>
 <div class="detail-grid">
  <div><small>Participante</small><strong><?=e($r['student_name'])?></strong></div><div><small>Nome social / apelido autorizado</small><strong><?=e($r['preferred_name']?:'Somente o nome da pessoa')?></strong></div><div><small>Nascimento / idade</small><strong><?=e(date('d/m/Y',strtotime($r['birth_date'])))?> — <?=$age>=0?$age.' anos':'-'?></strong></div>
  <div><small>CPF participante</small><strong><?=e($r['student_cpf']?:'-')?></strong></div><div><small>Telefone</small><strong><?=e($r['student_phone']?:'-')?></strong></div>
  <div><small>E-mail</small><strong><?=e($r['student_email']?:'-')?></strong></div><div><small>Instrumento / ala</small><strong><?=e($r['instrument'])?></strong></div>
  <div class="span2"><small>Endereço</small><strong><?=e($r['address'].' — '.$r['neighborhood'].' — '.$r['city'])?></strong></div><div class="span2"><small>Experiência / observações</small><strong><?=e($r['experience']?:'-')?></strong></div>
 </div>
</section>

<section class="panel name-respect-admin"><h2>Nome de uso autorizado</h2><p><?=e(setting('term_name_respect'))?></p><strong>NO SISTEMA: <?=e(display_person_name($r['student_name'],$r['preferred_name']))?></strong></section>
<?php if((int)$r['is_minor']===1):$gcard=guardian_card($id);?><section class="panel enrollment-admin-detail guardian-admin-panel"><div class="panel-heading-flex"><div><h2>Responsável legal / acompanhante</h2><p>O responsável deve ter 18 anos ou mais, CPF válido e foto 3x4 para seus documentos.</p></div><?php if($gcard):?><span class="<?=$gcard['status']==='ATIVA'?'card-active':'card-inactive'?>"><?=e($gcard['status'])?></span><?php endif;?></div>
<form method="post" enctype="multipart/form-data" class="form-stack guardian-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="guardian_update">
<div class="guardian-admin-photo"><?php if($r['guardian_photo_path']):?><img src="../<?=e($r['guardian_photo_path'])?>" alt="Foto responsável"><?php else:?><div>SEM FOTO</div><?php endif;?><label>Foto 3x4<input type="file" name="guardian_photo" accept="image/*"></label></div>
<div class="grid2"><label>Nome completo<input name="guardian_name" value="<?=e($r['guardian_name'])?>" required></label><label>Nascimento (18+)<input type="date" name="guardian_birth_date" value="<?=e($r['guardian_birth_date'])?>" required></label><label>CPF<input name="guardian_cpf" value="<?=e($r['guardian_cpf'])?>" required></label><label>Vínculo<input name="guardian_relationship" value="<?=e($r['guardian_relationship'])?>" required></label><label>Telefone<input name="guardian_phone" value="<?=e($r['guardian_phone'])?>" required></label><label>E-mail<input type="email" name="guardian_email" value="<?=e($r['guardian_email'])?>" required></label><label class="span2">Endereço<input name="guardian_address" value="<?=e($r['guardian_address'])?>" required></label><label>Bairro<input name="guardian_neighborhood" value="<?=e($r['guardian_neighborhood'])?>"></label><label>Cidade<input name="guardian_city" value="<?=e($r['guardian_city'])?>" required></label></div><button class="primary">SALVAR RESPONSÁVEL</button></form>
<?php if($gcard):?><div class="companion-document-actions"><a class="small-button card-print-btn" target="_blank" rel="noopener noreferrer" href="carteirinha_acompanhante.php?id=<?=$id?>">CARTEIRINHA DO ACOMPANHANTE</a><a class="small-button card-print-btn" target="_blank" rel="noopener noreferrer" href="cracha_acompanhante.php?id=<?=$id?>">CRACHÁ DO ACOMPANHANTE</a><div><small>Número</small><strong><?=e($gcard['companion_number'])?></strong></div><div><small>Validade</small><strong><?=e(date('d/m/Y',strtotime($gcard['valid_until'])))?></strong></div></div><?php endif;?>
</section><?php endif;?>

<section class="panel education-work-admin"><div class="panel-heading-flex"><div><h2>Estudo, trabalho e declarações</h2><p>As declarações são montadas automaticamente com os dados desta matrícula e prontas para impressão em A4.</p></div><div class="declaration-buttons"><?php if((int)$r['currently_studying']===1):?><a class="small-button card-print-btn" target="_blank" rel="noopener noreferrer" href="declaracao_estudante.php?id=<?=$id?>">DECLARAÇÃO PARA ESCOLA</a><?php endif;?><?php if((int)$r['works_currently']===1&&(int)$r['needs_work_declaration']===1):?><a class="small-button card-print-btn" target="_blank" rel="noopener noreferrer" href="declaracao_trabalho.php?id=<?=$id?>">DECLARAÇÃO PARA TRABALHO</a><?php endif;?></div></div>
<form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="education_work_update">
<div class="grid2"><label>Aluno ainda estuda?<select name="currently_studying"><option value="0" <?=(int)$r['currently_studying']!==1?'selected':''?>>NÃO</option><option value="1" <?=(int)$r['currently_studying']===1?'selected':''?>>SIM</option></select></label><label>Rede de ensino<select name="school_network"><option value="">NÃO SE APLICA</option><?php foreach(school_networks() as $k=>$label):?><option value="<?=e($k)?>" <?=$r['school_network']===$k?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label>Nome da escola<input name="school_name" value="<?=e($r['school_name'])?>"></label><label>Trabalha atualmente?<select name="works_currently"><option value="0" <?=(int)$r['works_currently']!==1?'selected':''?>>NÃO</option><option value="1" <?=(int)$r['works_currently']===1?'selected':''?>>SIM</option></select></label><label>Empresa / empregador<input name="employer_name" value="<?=e($r['employer_name'])?>"></label><label>Precisa declaração para trabalho?<select name="needs_work_declaration"><option value="0" <?=(int)$r['needs_work_declaration']!==1?'selected':''?>>NÃO</option><option value="1" <?=(int)$r['needs_work_declaration']===1?'selected':''?>>SIM</option></select></label></div><button class="primary">SALVAR ESTUDO / TRABALHO</button></form></section>

<section class="panel card-admin-panel"><div class="panel-heading-flex"><div><h2>Carteirinha do integrante</h2><p>Validade: 1 ano contado da data da inscrição. Ao marcar a matrícula como APROVADA, a carteirinha e o crachá são ativados automaticamente.</p></div><?php if($card):?><span class="<?=$card['status']==='ATIVA'?'card-active':'card-inactive'?>"><?=e($card['status'])?></span><?php endif;?></div>
 <?php if($card):?><div class="card-admin-data"><div><small>Emissão</small><strong><?=e(date('d/m/Y H:i',strtotime($card['issued_at'])))?></strong></div><div><small>Validade</small><strong><?=e(date('d/m/Y',strtotime($card['valid_until'])))?></strong></div><?php if($card['deactivation_reason']):?><div><small>Motivo da desativação</small><strong><?=e($card['deactivation_reason'])?></strong></div><?php endif;?></div><?php endif;?>
 <?php if(is_general_admin($u) && empty($r['deleted_at'])):?><div class="card-actions-admin">
  <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="issue_card"><button class="primary"><?=($card&&$card['status']==='ATIVA')?'RENOVAR DADOS / MANTER ATIVA':'EMITIR / ATIVAR CARTEIRINHA'?></button></form>
  <?php if($card&&$card['status']==='ATIVA'):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="deactivate_card"><input name="reason" placeholder="Motivo da desativação" required><button class="danger">DESATIVAR CARTEIRINHA</button></form><?php endif;?>
  <?php if($card):?><a class="small-button card-print-btn" href="carteirinha.php?id=<?=$id?>" target="_blank" rel="noopener noreferrer">CARTEIRINHA</a><?php endif;?><?php if($r['status']==='APROVADA'):?><a class="small-button card-print-btn" href="cracha_aluno.php?id=<?=$id?>" target="_blank" rel="noopener noreferrer">CRACHÁ DO ALUNO</a><?php endif;?>
 </div><?php endif;?>
</section>

<section class="panel"><h2>Análise da Coordenação</h2><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="save"><label>Status<select name="status"><?php foreach(enrollment_statuses() as $k=>$v):if($k==='EXCLUIDA')continue;?><option value="<?=e($k)?>" <?=$r['status']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label><label>Observações internas<textarea name="admin_notes" rows="6"><?=e($r['admin_notes'])?></textarea></label><button class="primary">SALVAR ANÁLISE</button></form></section>

<section class="panel recognition-person-panel"><div class="panel-heading-flex"><div><span>RECONHECIMENTO ANUAL</span><h2>Certificado ou Diploma</h2><p>Sem foto 3x4. O Admin pode emitir qualquer uma das duas opções para este aluno no ano atual ou em outro ano.</p></div><a class="small-button" href="reconhecimentos.php?kind=ALUNO&q=<?=urlencode($r['registration_number']?:$r['student_name'])?>&year=<?=date('Y')?>">ABRIR EMISSÃO</a></div></section>

<section class="panel danger-zone"><h2>Cancelamento / exclusão</h2><p>Ao excluir, a inscrição desaparece das listas normais e qualquer carteirinha emitida é desativada automaticamente.</p><?php if(is_general_admin($u)&&empty($r['deleted_at'])):?><form method="post" onsubmit="return confirm('Confirma a EXCLUSÃO desta inscrição? A carteirinha será desativada.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="delete"><button class="danger">EXCLUIR INSCRIÇÃO E DESATIVAR CARTEIRINHA</button></form><?php else:?><strong>Registro já excluído ou ação indisponível.</strong><?php endif;?></section>

<details class="panel terms-snapshots"><summary>Ver autorizações e termos registrados</summary><article><h3>Respeito ao nome/apelido</h3><p><?=nl2br(e($r['term_name_respect_snapshot']?:setting('term_name_respect')))?></p></article><article><h3>Participação</h3><p><?=nl2br(e($r['term_participation_snapshot']))?></p></article><article><h3>Imagem e voz</h3><p><?=nl2br(e($r['term_image_snapshot']))?></p></article><article><h3>Instrumentos</h3><p><?=nl2br(e($r['term_instrument_snapshot']))?></p></article><article><h3>Uniformes</h3><p><?=nl2br(e($r['term_uniform_snapshot']))?></p></article><article><h3>Privacidade</h3><p><?=nl2br(e($r['term_privacy_snapshot']))?></p></article></details>
<?php admin_footer();?>
