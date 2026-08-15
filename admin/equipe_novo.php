<?php
require __DIR__.'/_layout.php';
$u=require_admin();
if(!is_general_admin($u)){http_response_code(403);exit('Somente o Admin Geral pode cadastrar membros da equipe.');}
$pdo=db();$roles=team_roles(true);$errors=[];
$v=['full_name'=>'','preferred_name'=>'','birth_date'=>'','cpf'=>'','phone'=>'','email'=>'','address'=>'','city'=>'','role_id'=>'','experience'=>'','guardian_name'=>'','guardian_phone'=>'','guardian_relationship'=>'','emergency_name'=>'','emergency_phone'=>'','image_authorization'=>'','signer_name'=>''];

if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();foreach(array_keys($v) as $k)$v[$k]=trim((string)($_POST[$k]??''));
 if(mb_strlen($v['full_name'])<3)$errors[]='Informe o nome completo.';
 $age=birth_age($v['birth_date']);if($age<0||$age>100)$errors[]='Informe uma data de nascimento válida.';$minor=$age>=0&&$age<18;
 $role=team_role((int)$v['role_id']);if(!$role||(int)$role['active']!==1)$errors[]='Escolha uma função interna ativa.';
 if($v['phone']==='')$errors[]='Informe um telefone/WhatsApp.';
 if($v['city']==='')$errors[]='Informe a cidade.';
 if($minor){if(mb_strlen($v['guardian_name'])<3)$errors[]='Para menor de 18 anos, informe o responsável legal.';if(mb_strlen($v['guardian_phone'])<8)$errors[]='Informe o telefone do responsável.';if($v['guardian_relationship']==='')$errors[]='Informe o vínculo do responsável.';}
 if($v['image_authorization']!=='1'&&$v['image_authorization']!=='0')$errors[]='Registre a escolha sobre imagem e voz.';
 if(empty($_POST['name_respect_ack']))$errors[]='Confirme a regra de respeito ao nome social/apelido.';
 if(empty($_POST['commitment_ack']))$errors[]='Confirme que o termo de compromisso foi apresentado e aceito.';
 if(empty($_POST['privacy_ack']))$errors[]='Confirme ciência do aviso de privacidade.';
 if(mb_strlen($v['signer_name'])<3)$errors[]='Informe o nome de quem declarou/assinou a ficha.';
 if($minor&&normalized_name($v['signer_name'])!==normalized_name($v['guardian_name']))$errors[]='Para menor de idade, o responsável legal deve constar como declarante.';
 if(empty($_FILES['photo_3x4']['name']))$errors[]='Envie a foto 3x4 para o crachá.';
 $photo='';
 if(!$errors){try{
  $photo=upload_file($_FILES['photo_3x4'],'image');$now=now_iso();$qr=bin2hex(random_bytes(16));$public=bin2hex(random_bytes(24));$temp='TEMP-'.strtoupper(bin2hex(random_bytes(5)));
  $status=isset($_POST['approve_now'])?'APROVADO':'PENDENTE';$approved=$status==='APROVADO'?$now:null;
  $pdo->beginTransaction();
  $st=$pdo->prepare("INSERT INTO team_members(application_number,qr_token,public_token,created_at,updated_at,status,role_id,full_name,preferred_name,name_respect_ack,birth_date,cpf,phone,email,address,city,experience,photo_path,is_minor,guardian_name,guardian_phone,guardian_relationship,emergency_name,emergency_phone,image_authorization,commitment_ack,privacy_ack,signer_name,signed_at,terms_version,term_commitment_snapshot,term_image_snapshot,term_privacy_snapshot,term_name_respect_snapshot,approved_at,admin_notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $st->execute([$temp,$qr,$public,$now,$now,$status,(int)$v['role_id'],$v['full_name'],$v['preferred_name'],1,$v['birth_date'],normalize_cpf($v['cpf']),$v['phone'],$v['email'],$v['address'],$v['city'],$v['experience'],$photo,$minor?1:0,$v['guardian_name'],$v['guardian_phone'],$v['guardian_relationship'],$v['emergency_name'],$v['emergency_phone'],(int)$v['image_authorization'],1,1,$v['signer_name'],$now,setting('team_terms_version','2026.1'),setting('team_term_commitment'),setting('team_term_image'),setting('team_term_privacy'),setting('term_name_respect'),$approved,trim((string)($_POST['admin_notes']??''))]);
  $id=(int)$pdo->lastInsertId();$num=team_application_number($id);$pdo->prepare("UPDATE team_members SET application_number=? WHERE id=?")->execute([$num,$id]);$pdo->commit();
  go('equipe_ver.php?id='.$id,'Membro da equipe cadastrado internamente pelo Admin Geral.');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($photo)delete_local_media($photo);$errors[]='Não foi possível salvar: '.$e->getMessage();}}
}
admin_header('Novo Cadastro Interno — Equipe BAMAB');
?>
<?php if($errors):?><div class="alert error"><strong>Revise a ficha:</strong><ul><?php foreach($errors as $x):?><li><?=e($x)?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="internal-registration-warning"><strong>CADASTRO INTERNO — SOMENTE ADMIN GERAL</strong><p>Esta ficha não é pública. Use-a para cadastrar Coordenação, Coreógrafo, Mó, Baliza, Mídia, Secretário, Produção, Coordenador, Coordenador Geral e outras funções internas criadas pelo Admin.</p></div>
<form method="post" enctype="multipart/form-data" class="panel form-stack internal-team-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<h2>1. Identificação</h2><div class="grid2">
<label>Nome completo *<input name="full_name" value="<?=e($v['full_name'])?>" required></label>
<label>Nome social / apelido que deseja usar <input name="preferred_name" value="<?=e($v['preferred_name'])?>" placeholder="Opcional — somente como a pessoa deseja ser chamada"></label>
</div>
<div class="name-respect-admin-form"><strong>REGRA DE RESPEITO AO NOME / APELIDO</strong><p><?=e(setting('term_name_respect'))?></p><label class="check"><input type="checkbox" name="name_respect_ack" required> Confirmo que não será utilizado apelido diferente do escolhido pela própria pessoa.</label></div>
<div class="grid2">
<label>Data de nascimento *<input type="date" id="team_birth" name="birth_date" value="<?=e($v['birth_date'])?>" required max="<?=date('Y-m-d')?>"></label>
<label>Foto 3x4 para o crachá *<input type="file" name="photo_3x4" accept="image/jpeg,image/png,image/webp" required></label>
<label>CPF<input name="cpf" value="<?=e($v['cpf'])?>"></label><label>Telefone / WhatsApp *<input name="phone" value="<?=e($v['phone'])?>" required></label>
<label>E-mail<input type="email" name="email" value="<?=e($v['email'])?>"></label><label>Cidade *<input name="city" value="<?=e($v['city'])?>" required></label>
<label class="span2">Endereço<input name="address" value="<?=e($v['address'])?>"></label></div>

<h2>2. Função interna</h2><div class="internal-role-grid"><?php foreach($roles as $role):?><label class="internal-role-option"><input type="radio" name="role_id" value="<?=$role['id']?>" <?=((string)$v['role_id']===(string)$role['id'])?'checked':''?> required><span><strong><?=e($role['name'])?></strong><small><?=e($role['category'])?></small></span></label><?php endforeach;?></div>
<label>Experiência / habilidades / observações<textarea name="experience" rows="5"><?=e($v['experience'])?></textarea></label>

<section class="admin-minor-section" id="teamGuardian"><h2>3. Responsável legal — quando menor de 18 anos</h2><div class="grid2">
<label>Nome do responsável<input data-team-guardian name="guardian_name" value="<?=e($v['guardian_name'])?>"></label><label>Telefone do responsável<input data-team-guardian name="guardian_phone" value="<?=e($v['guardian_phone'])?>"></label>
<label>Vínculo<select data-team-guardian name="guardian_relationship"><option value="">Selecione</option><?php foreach(['MÃE','PAI','AVÓ/AVÔ','TUTOR(A) LEGAL','OUTRO RESPONSÁVEL LEGAL'] as $x):?><option <?=$v['guardian_relationship']===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></label>
<label>Contato de emergência<input name="emergency_name" value="<?=e($v['emergency_name'])?>"></label><label>Telefone de emergência<input name="emergency_phone" value="<?=e($v['emergency_phone'])?>"></label></div></section>

<h2>4. Termos registrados internamente</h2>
<article class="admin-term-card"><h3>Compromisso com a função</h3><p><?=nl2br(e(setting('team_term_commitment')))?></p><label class="check"><input type="checkbox" name="commitment_ack" required> O termo foi apresentado e aceito.</label></article>
<article class="admin-term-card"><h3>Imagem e voz</h3><p><?=nl2br(e(setting('team_term_image')))?></p><div class="image-choice-admin"><label><input type="radio" name="image_authorization" value="1" <?=$v['image_authorization']==='1'?'checked':''?> required> AUTORIZA</label><label><input type="radio" name="image_authorization" value="0" <?=$v['image_authorization']==='0'?'checked':''?> required> NÃO AUTORIZA</label></div></article>
<article class="admin-term-card"><h3>Privacidade</h3><p><?=nl2br(e(setting('team_term_privacy')))?></p><label class="check"><input type="checkbox" name="privacy_ack" required> Houve ciência do aviso de privacidade.</label></article>

<h2>5. Declaração / controle administrativo</h2><div class="grid2"><label>Nome de quem declarou/assinou a ficha *<input name="signer_name" value="<?=e($v['signer_name'])?>" required></label><label class="check approve-now"><input type="checkbox" name="approve_now"> Cadastrar já como APROVADO</label></div><label>Observações internas<textarea name="admin_notes" rows="4"></textarea></label>
<button class="primary internal-save-button">CADASTRAR MEMBRO DA EQUIPE</button>
</form>
<script>(function(){const b=document.getElementById('team_birth'),fs=[...document.querySelectorAll('[data-team-guardian]')],box=document.getElementById('teamGuardian');function age(v){if(!v)return null;const d=new Date(v+'T00:00:00'),n=new Date();let a=n.getFullYear()-d.getFullYear(),m=n.getMonth()-d.getMonth();if(m<0||(m===0&&n.getDate()<d.getDate()))a--;return a}function up(){const a=age(b.value),mi=a!==null&&a<18;box.classList.toggle('minor-required-admin',mi);fs.forEach(x=>x.required=mi)}b.addEventListener('change',up);up()})();</script>
<?php admin_footer();?>
