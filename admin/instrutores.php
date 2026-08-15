<?php
require __DIR__.'/_layout.php';$u=require_admin();$pdo=db();$err='';
if(!is_general_admin($u)){http_response_code(403);exit('Somente o Admin Geral pode administrar instrutores.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{csrf_check();$a=$_POST['action']??'';
  if($a==='create'||$a==='update'){
   $id=(int)($_POST['id']??0);$name=trim((string)($_POST['full_name']??''));$preferred=trim((string)($_POST['preferred_name']??''));$role=$_POST['role']??'INSTRUTOR';
   $birth=trim((string)($_POST['birth_date']??''));$cpf=normalize_cpf((string)($_POST['cpf']??''));$phone=trim((string)($_POST['phone']??''));$email=trim((string)($_POST['email']??''));$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
   $areas=array_values(array_intersect(enrollment_instruments(),array_map('strval',$_POST['areas']??[])));
   if(mb_strlen($name)<3)throw new RuntimeException('Informe o nome completo.');
   if(!in_array($role,['INSTRUTOR','AUXILIAR'],true))throw new RuntimeException('Função inválida.');
   if(birth_age($birth)<18)throw new RuntimeException('Instrutor/Auxiliar precisa ter 18 anos ou mais.');
   if(!cpf_valid($cpf))throw new RuntimeException('Informe um CPF válido.');
   if(mb_strlen($username)<4)throw new RuntimeException('O login precisa ter pelo menos 4 caracteres.');
   if(!$areas)throw new RuntimeException('Selecione pelo menos uma ala de responsabilidade.');
   $photo='';
   if(!empty($_FILES['photo']['name']))$photo=upload_file($_FILES['photo'],'image');
   $pdo->beginTransaction();
   if($a==='create'){
    if(strlen($password)<6)throw new RuntimeException('A senha inicial precisa ter pelo menos 6 caracteres.');
    $pdo->prepare("INSERT INTO instructors(full_name,preferred_name,role,birth_date,cpf,phone,email,photo_path,username,password_hash,active,created_at,updated_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,1,?,?,?)")
      ->execute([$name,$preferred,$role,$birth,$cpf,$phone,$email,$photo,$username,password_hash($password,PASSWORD_DEFAULT),now_iso(),now_iso(),(int)$u['id']]);
    $id=(int)$pdo->lastInsertId();$pdo->prepare("UPDATE instructors SET instructor_code=? WHERE id=?")->execute([instructor_code_for_id($id),$id]);ensure_instructor_qr_token($id);
   }else{
    $old=instructor_record($id);if(!$old)throw new RuntimeException('Instrutor não encontrado.');
    if($photo==='')$photo=$old['photo_path']; elseif($old['photo_path'])delete_local_media($old['photo_path']);
    $pdo->prepare("UPDATE instructors SET full_name=?,preferred_name=?,role=?,birth_date=?,cpf=?,phone=?,email=?,photo_path=?,username=?,updated_at=? WHERE id=?")
      ->execute([$name,$preferred,$role,$birth,$cpf,$phone,$email,$photo,$username,now_iso(),$id]);
    if($password!==''){
      if(strlen($password)<6)throw new RuntimeException('A nova senha precisa ter pelo menos 6 caracteres.');
      $pdo->prepare("UPDATE instructors SET password_hash=?,updated_at=? WHERE id=?")->execute([password_hash($password,PASSWORD_DEFAULT),now_iso(),$id]);
    }
    $pdo->prepare("DELETE FROM instructor_areas WHERE instructor_id=?")->execute([$id]);
   }
   $ins=$pdo->prepare("INSERT INTO instructor_areas(instructor_id,area) VALUES(?,?)");foreach($areas as $area)$ins->execute([$id,$area]);
   $pdo->commit();go('instrutores.php','Cadastro interno salvo.');
  }
  if($a==='toggle'){$id=(int)$_POST['id'];$active=(int)$_POST['active']===1?1:0;$pdo->prepare("UPDATE instructors SET active=?,updated_at=? WHERE id=?")->execute([$active,now_iso(),$id]);go('instrutores.php',$active?'Acesso reativado.':'Acesso desativado.');}
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$err=$e->getMessage();}
}
$editId=(int)($_GET['edit']??0);$edit=$editId?instructor_record($editId):null;$editAreas=$edit?instructor_areas($editId):[];
$rows=$pdo->query("SELECT * FROM instructors ORDER BY active DESC,role,full_name COLLATE NOCASE")->fetchAll();
admin_header('Instrutores e Auxiliares');$msg=flash();?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="panel"><div class="panel-heading-flex"><div><span>ÁREA SOMENTE INTERNA</span><h2>Cadastro de Instrutores e Auxiliares</h2><p>Estes usuários não aparecem no site público. Cada cadastro recebe código BAMAB automático, login próprio e acesso somente às alas atribuídas pelo Admin Geral. O instrutor pode entrar usando nome, apelido, usuário ou código.</p></div><a class="small-button" href="../instrutor/login.php" target="_blank" rel="noopener noreferrer">ABRIR LOGIN INTERNO</a></div></section>
<section class="panel"><h2><?=$edit?'Editar cadastro':'Novo cadastro interno'?></h2><form method="post" enctype="multipart/form-data" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="<?=$edit?'update':'create'?>"><input type="hidden" name="id" value="<?=$editId?>">
<div class="grid2"><label>Nome completo<input name="full_name" value="<?=e($edit['full_name']??'')?>" required></label><label>Nome social / apelido<input name="preferred_name" value="<?=e($edit['preferred_name']??'')?>"></label><label>Função<select name="role"><option value="INSTRUTOR" <?=($edit['role']??'')==='INSTRUTOR'?'selected':''?>>INSTRUTOR</option><option value="AUXILIAR" <?=($edit['role']??'')==='AUXILIAR'?'selected':''?>>AUXILIAR</option></select></label><label>Data de nascimento (18+)<input type="date" name="birth_date" value="<?=e($edit['birth_date']??'')?>" required></label><label>CPF<input name="cpf" value="<?=e($edit['cpf']??'')?>" required></label><label>Telefone / WhatsApp<input name="phone" value="<?=e($edit['phone']??'')?>"></label><label>E-mail<input type="email" name="email" value="<?=e($edit['email']??'')?>"></label><label>Foto<input type="file" name="photo" accept="image/*"></label><label>Login<input name="username" autocomplete="off" value="<?=e($edit['username']??'')?>" required></label><label><?=$edit?'Nova senha (deixe vazio para manter)':'Senha inicial'?><input type="password" name="password" autocomplete="new-password" <?=$edit?'':'required'?>></label></div>
<h3>Alas sob responsabilidade</h3><div class="instructor-area-grid"><?php foreach(enrollment_instruments() as $area):?><label class="check area-check"><input type="checkbox" name="areas[]" value="<?=e($area)?>" <?=in_array($area,$editAreas,true)?'checked':''?>> <?=e($area)?></label><?php endforeach;?></div><button class="primary"><?=$edit?'SALVAR ALTERAÇÕES':'CADASTRAR INSTRUTOR / AUXILIAR'?></button><?php if($edit):?><a class="small-button" href="instrutores.php">CANCELAR EDIÇÃO</a><?php endif;?></form></section>
<section class="panel"><h2>Equipe de instrução cadastrada</h2><div class="table-wrap"><table class="admin-table"><thead><tr><th>Foto</th><th>Nome</th><th>Função</th><th>Alas</th><th>Código</th><th>Login</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $r):$areas=instructor_areas((int)$r['id']);?><tr><td><?php if($r['photo_path']):?><img class="admin-thumb-3x4" src="../<?=e($r['photo_path'])?>"><?php endif;?></td><td><strong><?=e($r['full_name'])?></strong><?php if($r['preferred_name']):?><small class="preferred-list-name">CHAMAR: <?=e($r['preferred_name'])?></small><?php endif;?></td><td><?=e($r['role'])?></td><td><?=e(implode(' • ',$areas))?></td><td><strong><?=e($r['instructor_code']?:instructor_code_for_id((int)$r['id']))?></strong></td><td><?=e($r['username'])?></td><td><span class="<?=$r['active']?'card-active':'card-inactive'?>"><?=$r['active']?'ATIVO':'INATIVO'?></span></td><td><a class="small-button" href="?edit=<?=$r['id']?>">EDITAR</a> <a class="small-button" href="cracha_instrutor.php?id=<?=$r['id']?>" target="_blank" rel="noopener noreferrer">CRACHÁ / QR</a><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="active" value="<?=$r['active']?0:1?>"><button class="<?=$r['active']?'danger-soft':'activate-btn'?>"><?=$r['active']?'DESATIVAR':'REATIVAR'?></button></form></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="8" class="empty-cell">Nenhum instrutor cadastrado.</td></tr><?php endif;?></tbody></table></div></section>
<?php admin_footer();?>
