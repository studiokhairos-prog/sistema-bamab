<?php
require __DIR__.'/_layout.php';
$u=require_admin();$pdo=db();$err='';$ok='';
if(!is_general_admin($u)){http_response_code(403);exit('Somente o Admin Geral pode usar o Acesso Master.');}
if(isset($_GET['return'])){unset($_SESSION['instructor_id'],$_SESSION['instructor_flash']);instructor_master_clear();$ok='Você voltou ao Painel Admin. O acesso de instrutor foi encerrado.';}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  csrf_check();$action=(string)($_POST['action']??'');
  if($action==='set_master'){
   $current=(string)($_POST['current_password']??'');$new=(string)($_POST['master_password']??'');$confirm=(string)($_POST['master_confirm']??'');
   $st=$pdo->prepare("SELECT password_hash FROM admins WHERE id=?");$st->execute([(int)$u['id']]);$hash=(string)($st->fetchColumn()?:'');
   if(!password_verify($current,$hash))throw new RuntimeException('Sua senha atual de Admin está incorreta.');
   if(strlen($new)<10)throw new RuntimeException('A senha Master precisa ter pelo menos 10 caracteres.');
   if($new!==$confirm)throw new RuntimeException('A confirmação da senha Master não confere.');
   set_setting('instructor_master_password_hash',password_hash($new,PASSWORD_DEFAULT));recovery_clear('instructor_master_access');
   $ok='Senha Master definida com segurança. Ela não é exibida nem armazenada em texto aberto.';
  }
  if($action==='disable_master'){
   $current=(string)($_POST['current_password']??'');$st=$pdo->prepare("SELECT password_hash FROM admins WHERE id=?");$st->execute([(int)$u['id']]);
   if(!password_verify($current,(string)($st->fetchColumn()?:'')))throw new RuntimeException('Sua senha atual de Admin está incorreta.');
   set_setting('instructor_master_password_hash','');$ok='Acesso Master desativado.';
  }
  if($action==='access'){
   if(!instructor_master_configured())throw new RuntimeException('Defina a senha Master antes de usar este recurso.');
   if(recovery_locked('instructor_master_access'))throw new RuntimeException('Muitas tentativas incorretas. Aguarde 15 minutos.');
   $id=(int)($_POST['instructor_id']??0);$master=(string)($_POST['master_password']??'');$identifier=trim((string)($_POST['identifier_used']??''));
   $r=instructor_record($id);if(!$r||(int)$r['active']!==1)throw new RuntimeException('Instrutor ativo não encontrado.');
   if(!instructor_master_verify($master)){recovery_register_failure('instructor_master_access');throw new RuntimeException('Senha Master incorreta.');}
   recovery_clear('instructor_master_access');session_regenerate_id(true);
   $_SESSION['instructor_id']=$id;$_SESSION['instructor_master_admin_id']=(int)$u['id'];$_SESSION['instructor_master_started_at']=now_iso();$_SESSION['instructor_master_identifier']=$identifier;
   $pdo->prepare("INSERT INTO instructor_master_access_logs(admin_id,instructor_id,identifier_used,client_ip,accessed_at) VALUES(?,?,?,?,?)")
      ->execute([(int)$u['id'],$id,$identifier,(string)($_SERVER['REMOTE_ADDR']??''),now_iso()]);
   header('Location: ../instrutor/index.php?master=1');exit;
  }
 }catch(Throwable $e){$err=$e->getMessage();}
}
$q=trim((string)($_GET['q']??''));$results=$q!==''?instructor_search($q):[];
$logs=$pdo->query("SELECT l.*,a.display_name admin_name,i.full_name,i.preferred_name,i.instructor_code FROM instructor_master_access_logs l JOIN admins a ON a.id=l.admin_id JOIN instructors i ON i.id=l.instructor_id ORDER BY l.id DESC LIMIT 20")->fetchAll();
admin_header('Acesso Master — Instrutores');$msg=flash();
?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($ok):?><div class="alert ok"><?=e($ok)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="panel master-access-hero"><span>ADMIN GERAL · ACESSO CONTROLADO</span><h2>Acessar a área de qualquer Instrutor/Auxiliar</h2><p>Localize pelo <strong>nome, apelido, usuário ou código BAMAB</strong>. A senha Master é exigida antes de entrar e cada acesso fica registrado.</p><div class="master-status <?=instructor_master_configured()?'on':'off'?>"><?=instructor_master_configured()?'● SENHA MASTER CONFIGURADA':'○ SENHA MASTER AINDA NÃO CONFIGURADA'?></div></section>
<section class="panel"><h2><?=instructor_master_configured()?'Alterar senha Master':'Criar senha Master'?></h2><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="set_master"><div class="grid3"><label>Sua senha atual de Admin<input type="password" name="current_password" required autocomplete="current-password"></label><label>Nova senha Master<input type="password" name="master_password" required minlength="10" autocomplete="new-password"></label><label>Confirmar senha Master<input type="password" name="master_confirm" required minlength="10" autocomplete="new-password"></label></div><button class="primary"><?=instructor_master_configured()?'ALTERAR SENHA MASTER':'CRIAR SENHA MASTER'?></button></form><?php if(instructor_master_configured()):?><form method="post" class="inline-danger-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="disable_master"><label>Sua senha atual de Admin<input type="password" name="current_password" required></label><button class="danger-soft">DESATIVAR ACESSO MASTER</button></form><?php endif;?></section>
<section class="panel"><h2>Localizar Instrutor</h2><form method="get" class="master-search"><input name="q" value="<?=e($q)?>" placeholder="Nome, apelido, usuário ou BAMAB-I-000001" required><button class="primary">PESQUISAR</button></form><?php if($q!==''&&!$results):?><div class="empty-cell">Nenhum instrutor ativo encontrado.</div><?php endif;?><?php if($results):?><div class="master-result-grid"><?php foreach($results as $r):?><article><div><span><?=e($r['role'])?></span><h3><?=e(instructor_display_name($r))?></h3><p><?=e($r['full_name'])?></p><strong><?=e($r['instructor_code'])?></strong><small>Usuário: <?=e($r['username'])?> · Alas: <?=e(implode(' • ',instructor_areas((int)$r['id'])))?></small></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="access"><input type="hidden" name="instructor_id" value="<?=$r['id']?>"><input type="hidden" name="identifier_used" value="<?=e($q)?>"><label>Senha Master<input type="password" name="master_password" required <?=instructor_master_configured()?'':'disabled'?>></label><button class="primary" <?=instructor_master_configured()?'':'disabled'?>>ACESSAR COMO ESTE INSTRUTOR</button></form></article><?php endforeach;?></div><?php endif;?></section>
<section class="panel"><h2>Últimos acessos Master</h2><div class="table-wrap"><table class="admin-table"><thead><tr><th>Data</th><th>Admin</th><th>Instrutor</th><th>Código</th><th>Busca usada</th></tr></thead><tbody><?php foreach($logs as $l):?><tr><td><?=e(date('d/m/Y H:i',strtotime($l['accessed_at'])))?></td><td><?=e($l['admin_name'])?></td><td><?=e($l['preferred_name']?:$l['full_name'])?></td><td><?=e($l['instructor_code'])?></td><td><?=e($l['identifier_used'])?></td></tr><?php endforeach;?><?php if(!$logs):?><tr><td colspan="5" class="empty-cell">Nenhum acesso Master registrado.</td></tr><?php endif;?></tbody></table></div></section>
<?php admin_footer();?>
