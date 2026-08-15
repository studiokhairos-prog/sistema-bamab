<?php
require __DIR__.'/layout.php';
$pdo=db();$period=active_enrollment_period(true);$periodConfigured=active_enrollment_period(false);$errors=[];
$values=[
 'student_name'=>'','preferred_name'=>'','birth_date'=>'','student_cpf'=>'','student_phone'=>'','student_email'=>'',
 'address'=>'','neighborhood'=>'','city'=>'','instrument'=>'','experience'=>'',
 'currently_studying'=>'0','school_network'=>'','school_name'=>'','works_currently'=>'0','employer_name'=>'','needs_work_declaration'=>'0',
 'guardian_name'=>'','guardian_birth_date'=>'','guardian_cpf'=>'','guardian_phone'=>'','guardian_email'=>'','guardian_relationship'=>'',
 'guardian_address'=>'','guardian_neighborhood'=>'','guardian_city'=>'',
 'emergency_name'=>'','emergency_phone'=>'','signer_name'=>'','image_authorization'=>''
];
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();$period=active_enrollment_period(true);
 foreach(array_keys($values) as $k)$values[$k]=trim((string)($_POST[$k]??''));
 if(!$period)$errors[]='As matrículas estão fechadas ou fora do período definido pela Coordenação.';
 if(mb_strlen($values['student_name'])<3)$errors[]='Informe o nome completo do participante.';
 $age=birth_age($values['birth_date']);if($age<0||$age>100)$errors[]='Informe uma data de nascimento válida.';$isMinor=$age>=0&&$age<18;
 if($values['student_cpf']!==''&&!cpf_valid($values['student_cpf']))$errors[]='O CPF do participante é inválido.';
 if($values['address']==='')$errors[]='Informe o endereço do participante.';
 if($values['city']==='')$errors[]='Informe a cidade.';
 if(!in_array($values['instrument'],enrollment_instruments(),true))$errors[]='Selecione um instrumento/ala.';
 $currentlyStudying=$values['currently_studying']==='1';
 $worksCurrently=$values['works_currently']==='1';
 $needsWorkDeclaration=$worksCurrently&&$values['needs_work_declaration']==='1';
 if($currentlyStudying){
   if(!array_key_exists($values['school_network'],school_networks()))$errors[]='Selecione a rede de ensino do aluno.';
   if(mb_strlen($values['school_name'])<3)$errors[]='Informe o nome da escola onde o aluno estuda.';
 }
 if($needsWorkDeclaration&&mb_strlen($values['employer_name'])<2)$errors[]='Informe o nome da empresa/empregador para gerar a declaração de trabalho.';
 if($isMinor){
   if(mb_strlen($values['guardian_name'])<3)$errors[]='Para menor de 18 anos, informe o nome completo do pai/mãe/responsável.';
   $guardianAge=birth_age($values['guardian_birth_date']);
   if($guardianAge<18)$errors[]='O pai/mãe/responsável precisa ter 18 anos ou mais, comprovado pela data de nascimento informada.';
   if(!cpf_valid($values['guardian_cpf']))$errors[]='Informe um CPF válido do pai/mãe/responsável.';
   if(mb_strlen($values['guardian_phone'])<8)$errors[]='Informe o telefone do pai/mãe/responsável.';
   if($values['guardian_email']===''||!filter_var($values['guardian_email'],FILTER_VALIDATE_EMAIL))$errors[]='Informe um e-mail válido do pai/mãe/responsável.';
   if($values['guardian_relationship']==='')$errors[]='Informe o vínculo do responsável.';
   if($values['guardian_address']===''||$values['guardian_city']==='')$errors[]='Informe endereço e cidade do responsável.';
 }
 if($values['image_authorization']!=='1'&&$values['image_authorization']!=='0')$errors[]='Escolha se autoriza ou não o uso de imagem e voz.';
 foreach(['participation_authorization'=>'autorização de participação','instrument_commitment'=>'responsabilidade por instrumentos','uniform_commitment'=>'compromisso de uniformes/camisas','privacy_ack'=>'aviso de privacidade','name_respect_ack'=>'regra de respeito ao nome/apelido'] as $k=>$label) if(empty($_POST[$k]))$errors[]='É necessário aceitar/confirmar: '.$label.'.';
 if(mb_strlen($values['signer_name'])<3)$errors[]='Informe o nome completo de quem declara e assina a matrícula.';
 if($isMinor&&normalized_name($values['signer_name'])!==normalized_name($values['guardian_name']))$errors[]='Para menor de 18 anos, a declaração deve ser feita pelo responsável legal informado.';
 $hasStudentPhoto=!empty($_FILES['photo_3x4']['name'])||trim((string)($_POST['student_photo_camera']??''))!=='';
 if(!$hasStudentPhoto)$errors[]='A foto 3x4 do aluno é obrigatória no ato da inscrição. Escolha da galeria ou tire pela câmera.';
 if($isMinor){
   $hasGuardianPhoto=!empty($_FILES['guardian_photo']['name'])||trim((string)($_POST['guardian_photo_camera']??''))!=='';
   if(!$hasGuardianPhoto)$errors[]='A foto 3x4 do responsável/acompanhante é obrigatória para gerar seus documentos.';
 }
 $photo='';$guardianPhoto='';
 if(!$errors){try{
   $photo=receive_required_photo('photo_3x4','student_photo_camera','aluno');
   if($isMinor)$guardianPhoto=receive_required_photo('guardian_photo','guardian_photo_camera','responsavel');
   $protocol=enrollment_protocol();$token=enrollment_public_token();$qrToken=bin2hex(random_bytes(16));$guardianQr=$isMinor?bin2hex(random_bytes(16)):'';$now=now_iso();$termsVersion=setting('enrollment_terms_version','2026.1');
   $pdo->beginTransaction();
   $st=$pdo->prepare("INSERT INTO enrollments(
    protocol,public_token,created_at,updated_at,status,period_id,registration_number,photo_path,
    student_name,preferred_name,name_respect_ack,qr_token,birth_date,student_cpf,student_phone,student_email,address,neighborhood,city,instrument,experience,is_minor,
    guardian_name,guardian_birth_date,guardian_cpf,guardian_phone,guardian_email,guardian_relationship,guardian_address,guardian_neighborhood,guardian_city,guardian_photo_path,guardian_qr_token,
    emergency_name,emergency_phone,image_authorization,participation_authorization,instrument_commitment,uniform_commitment,privacy_ack,
    currently_studying,school_network,school_name,works_currently,employer_name,needs_work_declaration,
    signer_name,signed_at,terms_version,term_participation_snapshot,term_image_snapshot,term_instrument_snapshot,term_uniform_snapshot,term_privacy_snapshot,term_name_respect_snapshot
   ) VALUES(".implode(',',array_fill(0,55,'?')).")");
   $st->execute([
    $protocol,$token,$now,$now,'PENDENTE',(int)$period['id'],'',$photo,
    $values['student_name'],$values['preferred_name'],1,$qrToken,$values['birth_date'],normalize_cpf($values['student_cpf']),$values['student_phone'],$values['student_email'],$values['address'],$values['neighborhood'],$values['city'],$values['instrument'],$values['experience'],$isMinor?1:0,
    $values['guardian_name'],$values['guardian_birth_date'],normalize_cpf($values['guardian_cpf']),$values['guardian_phone'],$values['guardian_email'],$values['guardian_relationship'],$values['guardian_address'],$values['guardian_neighborhood'],$values['guardian_city'],$guardianPhoto,$guardianQr,
    $values['emergency_name'],$values['emergency_phone'],(int)$values['image_authorization'],1,1,1,1,
    $currentlyStudying?1:0,$currentlyStudying?$values['school_network']:'',$currentlyStudying?$values['school_name']:'',
    $worksCurrently?1:0,$worksCurrently?$values['employer_name']:'',$needsWorkDeclaration?1:0,
    $values['signer_name'],$now,$termsVersion,setting('term_participation'),setting('term_image'),setting('term_instrument'),setting('term_uniform'),setting('term_privacy'),setting('term_name_respect')
   ]);
   $id=(int)$pdo->lastInsertId();$year=substr((string)$period['start_date'],0,4)?:date('Y');$registration=registration_number_from_id($id,$year);
   $pdo->prepare("UPDATE enrollments SET registration_number=? WHERE id=?")->execute([$registration,$id]);$pdo->commit();
   header('Location: matricula_confirmacao.php?t='.urlencode($token));exit;
 }catch(Throwable $e){
   if($pdo->inTransaction())$pdo->rollBack();if($photo)delete_local_media($photo);if($guardianPhoto)delete_local_media($guardianPhoto);
   $errors[]='Não foi possível concluir a matrícula: '.$e->getMessage();
 }}
}
site_header('Matrículas');$open=$period!==null;
?>
<main id="conteudo-principal" class="content-page enrollment-page">
<div class="page-banner enrollment-banner"><span>INSCRIÇÕES BAMAB</span><h1><?=e(setting('enrollment_title','MATRÍCULAS BAMAB'))?></h1><p><?=e(setting('enrollment_intro'))?></p><?php if($period):?><div class="period-public-badge"><strong><?=e($period['name'])?></strong><span><?=e(date('d/m/Y',strtotime($period['start_date'])))?> até <?=e(date('d/m/Y',strtotime($period['end_date'])))?></span></div><?php endif;?></div>
<?php if(!$open):?><div class="enrollment-closed"><strong>MATRÍCULAS FECHADAS</strong><p><?= $periodConfigured?'Período cadastrado: '.e($periodConfigured['name']).'.':'A Coordenação ainda não ativou um período.'?></p></div>
<?php else:?>
<?php if($errors):?><div class="form-errors"><strong>Revise a matrícula:</strong><ul><?php foreach($errors as $er):?><li><?=e($er)?></li><?php endforeach;?></ul></div><?php endif;?>
<form method="post" enctype="multipart/form-data" class="enrollment-form minor-wizard" id="enrollmentForm">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="student_photo_camera" id="student_photo_camera">
<input type="hidden" name="guardian_photo_camera" id="guardian_photo_camera">

<section class="form-section gate-section">
 <div class="form-section-title"><span>01</span><div><h2>Identificação e idade do aluno</h2><p>A idade é verificada primeiro. Se o aluno tiver menos de 18 anos, o restante da matrícula fica bloqueado até a validação do responsável.</p></div></div>
 <div class="form-grid two">
  <label>Nome completo *<input name="student_name" value="<?=e($values['student_name'])?>" required></label>
  <label>Nome social / apelido que deseja usar<input name="preferred_name" value="<?=e($values['preferred_name'])?>" placeholder="Opcional — somente como deseja ser chamado(a)"></label>
  <label>Data de nascimento *<input type="date" id="birth_date" name="birth_date" value="<?=e($values['birth_date'])?>" required max="<?=date('Y-m-d')?>"></label>
  <label>CPF do aluno<input id="student_cpf" name="student_cpf" value="<?=e($values['student_cpf'])?>" inputmode="numeric" placeholder="Se informado, será validado"></label>
 </div>
 <div class="name-respect-notice"><strong>RESPEITO AO NOME / APELIDO</strong><p><?=e(setting('term_name_respect'))?></p><label><input type="checkbox" name="name_respect_ack" value="1" required> Confirmo que o aluno deve ser chamado somente pelo nome ou apelido/nome social que ele próprio escolheu.</label></div>

 <div class="camera-choice">
  <div><h3>Foto 3x4 do aluno — obrigatória</h3><p>Você pode escolher uma imagem da galeria ou tirar uma foto agora.</p></div>
  <input type="file" id="studentPhotoFile" name="photo_3x4" accept="image/jpeg,image/png,image/webp">
  <button type="button" class="camera-btn" data-camera="student">📷 TIRAR FOTO PELA CÂMERA</button>
  <div class="photo-preview"><img id="studentPreview" alt=""><span id="studentPreviewText">Nenhuma foto selecionada.</span></div>
 </div>
 <div class="age-status" id="ageStatus">Informe a data de nascimento para continuar.</div>
</section>

<section class="form-section guardian-gate" id="guardianGate" hidden>
 <div class="form-section-title"><span>02</span><div><h2>Pai, mãe ou responsável legal</h2><p><strong>Etapa obrigatória para menores.</strong> O responsável também precisa ter 18 anos ou mais e CPF válido.</p></div></div>
 <div class="minor-lock-warning">🔒 A matrícula do menor permanece bloqueada até todos os dados abaixo serem preenchidos e validados.</div>
 <div class="form-grid two">
  <label>Nome completo do responsável *<input id="guardian_name" name="guardian_name" value="<?=e($values['guardian_name'])?>"></label>
  <label>Data de nascimento do responsável *<input type="date" id="guardian_birth_date" name="guardian_birth_date" value="<?=e($values['guardian_birth_date'])?>" max="<?=date('Y-m-d')?>"></label>
  <label>CPF do responsável *<input id="guardian_cpf" name="guardian_cpf" value="<?=e($values['guardian_cpf'])?>" inputmode="numeric" placeholder="000.000.000-00"></label>
  <label>Vínculo *<select id="guardian_relationship" name="guardian_relationship"><option value="">Selecione</option><?php foreach(['MÃE','PAI','AVÓ/AVÔ','TUTOR(A) LEGAL','OUTRO RESPONSÁVEL LEGAL'] as $x):?><option <?=$values['guardian_relationship']===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></label>
  <label>Telefone / WhatsApp *<input id="guardian_phone" name="guardian_phone" value="<?=e($values['guardian_phone'])?>"></label>
  <label>E-mail *<input type="email" id="guardian_email" name="guardian_email" value="<?=e($values['guardian_email'])?>"></label>
  <label class="span2">Endereço completo *<input id="guardian_address" name="guardian_address" value="<?=e($values['guardian_address'])?>"></label>
  <label>Bairro<input name="guardian_neighborhood" value="<?=e($values['guardian_neighborhood'])?>"></label>
  <label>Cidade *<input id="guardian_city" name="guardian_city" value="<?=e($values['guardian_city'])?>"></label>
 </div>
 <div class="camera-choice guardian-photo-box">
  <div><h3>Foto 3x4 do responsável/acompanhante — obrigatória</h3><p>Será usada na carteirinha e no crachá de acompanhante.</p></div>
  <input type="file" id="guardianPhotoFile" name="guardian_photo" accept="image/jpeg,image/png,image/webp">
  <button type="button" class="camera-btn" data-camera="guardian">📷 TIRAR FOTO PELA CÂMERA</button>
  <div class="photo-preview"><img id="guardianPreview" alt=""><span id="guardianPreviewText">Nenhuma foto selecionada.</span></div>
 </div>
 <button type="button" class="guardian-validate-btn" id="validateGuardian">VALIDAR RESPONSÁVEL E LIBERAR MATRÍCULA</button>
 <div class="guardian-validation-result" id="guardianValidationResult"></div>
</section>

<fieldset id="remainingEnrollment" class="remaining-enrollment" disabled>
 <div class="locked-overlay" id="lockedOverlay"><strong>🔒 CONTINUAÇÃO BLOQUEADA</strong><span>Informe a data de nascimento do aluno. Se for menor, valide primeiro o responsável legal.</span></div>

 <section class="form-section">
  <div class="form-section-title"><span>03</span><div><h2>Dados complementares do aluno</h2></div></div>
  <div class="form-grid two">
   <label>Telefone / WhatsApp<input name="student_phone" value="<?=e($values['student_phone'])?>"></label>
   <label>E-mail<input type="email" name="student_email" value="<?=e($values['student_email'])?>"></label>
   <label>Cidade *<input name="city" value="<?=e($values['city'])?>" required></label>
   <label>Bairro<input name="neighborhood" value="<?=e($values['neighborhood'])?>"></label>
   <label class="span2">Endereço completo *<input name="address" value="<?=e($values['address'])?>" required></label>
   <label>Contato de emergência<input name="emergency_name" value="<?=e($values['emergency_name'])?>"></label>
   <label>Telefone de emergência<input name="emergency_phone" value="<?=e($values['emergency_phone'])?>"></label>
  </div>
 </section>

 <section class="form-section education-work-section">
  <div class="form-section-title"><span>04</span><div><h2>Estudo e trabalho</h2><p>Essas informações permitem gerar automaticamente as declarações necessárias.</p></div></div>

  <div class="study-work-card">
   <h3>Você ainda estuda?</h3>
   <div class="image-choice compact-choice">
    <label><input type="radio" name="currently_studying" value="1" <?=$values['currently_studying']==='1'?'checked':''?> required><span>SIM, AINDA ESTUDO</span></label>
    <label><input type="radio" name="currently_studying" value="0" <?=$values['currently_studying']!=='1'?'checked':''?> required><span>NÃO ESTUDO ATUALMENTE</span></label>
   </div>
   <div id="schoolFields" class="conditional-fields">
    <div class="form-grid two">
     <label>Rede de ensino *<select name="school_network" id="school_network"><option value="">SELECIONE</option><?php foreach(school_networks() as $k=>$label):?><option value="<?=e($k)?>" <?=$values['school_network']===$k?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
     <label>Nome da escola *<input name="school_name" id="school_name" value="<?=e($values['school_name'])?>" placeholder="Nome da instituição de ensino"></label>
    </div>
    <p class="declaration-info">Ao concluir a matrícula, o sistema prepara a <strong>Declaração de Compromisso e Participação Cultural</strong> para apresentação à escola, quando necessária.</p>
   </div>
  </div>

  <div class="study-work-card">
   <h3>Você trabalha atualmente?</h3>
   <div class="image-choice compact-choice">
    <label><input type="radio" name="works_currently" value="1" <?=$values['works_currently']==='1'?'checked':''?> required><span>SIM, TRABALHO</span></label>
    <label><input type="radio" name="works_currently" value="0" <?=$values['works_currently']!=='1'?'checked':''?> required><span>NÃO TRABALHO ATUALMENTE</span></label>
   </div>
   <div id="workFields" class="conditional-fields">
    <label class="full-label">Empresa / empregador<input name="employer_name" id="employer_name" value="<?=e($values['employer_name'])?>" placeholder="Informe para personalizar a declaração"></label>
    <p><strong>Precisa de declaração para apresentar no trabalho?</strong></p>
    <div class="image-choice compact-choice">
     <label><input type="radio" name="needs_work_declaration" value="1" <?=$values['needs_work_declaration']==='1'?'checked':''?>><span>SIM, PRECISO</span></label>
     <label><input type="radio" name="needs_work_declaration" value="0" <?=$values['needs_work_declaration']!=='1'?'checked':''?>><span>NÃO PRECISO</span></label>
    </div>
   </div>
  </div>
 </section>

 <section class="form-section">
  <div class="form-section-title"><span>05</span><div><h2>Instrumento ou ala</h2></div></div>
  <div class="instrument-grid"><?php foreach(enrollment_instruments() as $inst):?><label class="instrument-option"><input type="radio" name="instrument" value="<?=e($inst)?>" <?=$values['instrument']===$inst?'checked':''?> required><span><?=e($inst)?></span></label><?php endforeach;?></div>
  <label class="full-label">Experiência / observações<textarea name="experience" rows="4"><?=e($values['experience'])?></textarea></label>
 </section>

 <section class="form-section terms-section">
  <div class="form-section-title"><span>06</span><div><h2>Autorizações e termos</h2></div></div>
  <article class="term-card"><h3>Autorização de participação</h3><p><?=nl2br(e(setting('term_participation')))?></p><label class="accept-line"><input type="checkbox" name="participation_authorization" required> LI E ACEITO.</label></article>
  <article class="term-card"><h3>Uso de imagem e voz</h3><p><?=nl2br(e(setting('term_image')))?></p><div class="image-choice"><label><input type="radio" name="image_authorization" value="1" <?=$values['image_authorization']==='1'?'checked':''?> required><span>AUTORIZO</span></label><label><input type="radio" name="image_authorization" value="0" <?=$values['image_authorization']==='0'?'checked':''?> required><span>NÃO AUTORIZO</span></label></div></article>
  <article class="term-card"><h3>Responsabilidade com instrumentos</h3><p><?=nl2br(e(setting('term_instrument')))?></p><label class="accept-line"><input type="checkbox" name="instrument_commitment" required> LI E ACEITO.</label></article>
  <article class="term-card"><h3>Uniformes e camisas</h3><p><?=nl2br(e(setting('term_uniform')))?></p><label class="accept-line"><input type="checkbox" name="uniform_commitment" required> LI E ESTOU CIENTE.</label></article>
  <article class="term-card"><h3>Privacidade</h3><p><?=nl2br(e(setting('term_privacy')))?></p><label class="accept-line"><input type="checkbox" name="privacy_ack" required> LI E ESTOU CIENTE.</label></article>
 </section>

 <section class="form-section signature-section">
  <div class="form-section-title"><span>07</span><div><h2>Declaração eletrônica</h2><p>Quando o aluno for menor, deve ser o mesmo responsável validado acima.</p></div></div>
  <label class="full-label">Nome completo de quem conclui a matrícula *<input name="signer_name" value="<?=e($values['signer_name'])?>" required></label>
 </section>

 <div class="form-submit-row"><div><strong><?=e($period['name'])?></strong><small>Após o envio, a Coordenação analisará a matrícula.</small></div><button class="enrollment-submit">ENVIAR MATRÍCULA <span>›</span></button></div>
</fieldset>
</form>

<div class="camera-modal" id="cameraModal" hidden>
 <div class="camera-dialog"><button type="button" class="camera-close" id="cameraClose">×</button><h2>Tirar foto</h2><video id="cameraVideo" autoplay playsinline muted></video><canvas id="cameraCanvas" hidden></canvas><div class="camera-actions"><button type="button" id="cameraShot">CAPTURAR FOTO</button><button type="button" id="cameraCancel">CANCELAR</button></div><p id="cameraHelp">Autorize o navegador a usar a câmera.</p></div>
</div>

<script>
(()=>{
 const birth=document.getElementById('birth_date'), gate=document.getElementById('guardianGate'), remaining=document.getElementById('remainingEnrollment'), locked=document.getElementById('lockedOverlay'), ageStatus=document.getElementById('ageStatus'), form=document.getElementById('enrollmentForm');
 let isMinor=false, guardianValidated=false, stream=null, cameraTarget=null;
 function age(v){if(!v)return null;const b=new Date(v+'T00:00:00'),n=new Date();if(isNaN(b))return null;let a=n.getFullYear()-b.getFullYear(),m=n.getMonth()-b.getMonth();if(m<0||(m===0&&n.getDate()<b.getDate()))a--;return a}
 function cpfValid(v){const d=String(v).replace(/\D/g,'');if(d.length!==11||/^(\d)\1{10}$/.test(d))return false;for(let t=9;t<11;t++){let s=0;for(let i=0;i<t;i++)s+=Number(d[i])*((t+1)-i);let dig=(10*(s%11))%11;if(dig===10)dig=0;if(Number(d[t])!==dig)return false}return true}
 function hasPhoto(kind){const f=document.getElementById(kind+'PhotoFile'),h=document.getElementById(kind+'_photo_camera');return (f&&f.files&&f.files.length>0)||(h&&h.value.length>100)}
 function unlock(){remaining.disabled=false;remaining.classList.add('unlocked');locked.hidden=true}
 function lock(msg){remaining.disabled=true;remaining.classList.remove('unlocked');locked.hidden=false;if(msg)locked.querySelector('span').textContent=msg}
 function updateAge(){
   const a=age(birth.value);guardianValidated=false;
   if(a===null){gate.hidden=true;ageStatus.className='age-status';ageStatus.textContent='Informe a data de nascimento para continuar.';lock();return}
   if(a<0){gate.hidden=true;lock();return}
   isMinor=a<18;
   if(isMinor){gate.hidden=false;ageStatus.className='age-status minor';ageStatus.innerHTML='<strong>MENOR DE 18 ANOS ('+a+' anos)</strong> — responsável legal obrigatório antes de continuar.';lock('Complete e valide todos os dados do responsável legal.');}
   else{gate.hidden=true;ageStatus.className='age-status adult';ageStatus.innerHTML='<strong>MAIOR DE 18 ANOS ('+a+' anos)</strong> — matrícula liberada.';unlock()}
 }
 birth.addEventListener('change',updateAge);birth.addEventListener('input',updateAge);

 document.getElementById('validateGuardian').addEventListener('click',()=>{
   const result=document.getElementById('guardianValidationResult'),ga=age(document.getElementById('guardian_birth_date').value),cpf=document.getElementById('guardian_cpf').value;
   const required=['guardian_name','guardian_birth_date','guardian_cpf','guardian_phone','guardian_email','guardian_relationship','guardian_address','guardian_city'];
   const missing=required.filter(id=>!document.getElementById(id).value.trim());
   if(missing.length){guardianValidated=false;result.className='guardian-validation-result bad';result.textContent='Preencha todos os campos obrigatórios do responsável.';lock();return}
   if(ga===null||ga<18){guardianValidated=false;result.className='guardian-validation-result bad';result.textContent='Responsável não validado: precisa ter 18 anos ou mais.';lock();return}
   if(!cpfValid(cpf)){guardianValidated=false;result.className='guardian-validation-result bad';result.textContent='Responsável não validado: CPF inválido.';lock();return}
   if(!hasPhoto('guardian')){guardianValidated=false;result.className='guardian-validation-result bad';result.textContent='Adicione a foto 3x4 do responsável pela galeria ou câmera.';lock();return}
   guardianValidated=true;result.className='guardian-validation-result ok';result.innerHTML='<strong>✓ RESPONSÁVEL VALIDADO</strong> — continuação da matrícula liberada.';unlock();
 });

 function previewFile(input,img,text){input.addEventListener('change',()=>{const f=input.files?.[0];if(!f)return;img.src=URL.createObjectURL(f);img.hidden=false;text.hidden=true})}
 previewFile(document.getElementById('studentPhotoFile'),document.getElementById('studentPreview'),document.getElementById('studentPreviewText'));
 previewFile(document.getElementById('guardianPhotoFile'),document.getElementById('guardianPreview'),document.getElementById('guardianPreviewText'));

 const modal=document.getElementById('cameraModal'),video=document.getElementById('cameraVideo'),canvas=document.getElementById('cameraCanvas');
 async function openCamera(target){cameraTarget=target;modal.hidden=false;try{stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'user'},width:{ideal:720},height:{ideal:960}},audio:false});video.srcObject=stream}catch(e){document.getElementById('cameraHelp').textContent='Não foi possível abrir a câmera. Use a opção de galeria ou verifique a permissão do navegador.'}}
 function closeCamera(){if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;video.srcObject=null;modal.hidden=true}
 document.querySelectorAll('[data-camera]').forEach(b=>b.addEventListener('click',()=>openCamera(b.dataset.camera)));
 document.getElementById('cameraShot').addEventListener('click',()=>{if(!video.videoWidth)return;const targetRatio=3/4,w=720,h=960;canvas.width=w;canvas.height=h;const vw=video.videoWidth,vh=video.videoHeight,vr=vw/vh;let sx=0,sy=0,sw=vw,sh=vh;if(vr>targetRatio){sw=vh*targetRatio;sx=(vw-sw)/2}else{sh=vw/targetRatio;sy=(vh-sh)/2}canvas.getContext('2d').drawImage(video,sx,sy,sw,sh,0,0,w,h);const data=canvas.toDataURL('image/jpeg',.88);document.getElementById(cameraTarget+'_photo_camera').value=data;const img=document.getElementById(cameraTarget+'Preview'),txt=document.getElementById(cameraTarget+'PreviewText');img.src=data;img.hidden=false;txt.hidden=true;closeCamera()});
 document.getElementById('cameraClose').onclick=closeCamera;document.getElementById('cameraCancel').onclick=closeCamera;

 form.addEventListener('submit',e=>{const a=age(birth.value);if(a!==null&&a<18&&!guardianValidated){e.preventDefault();gate.scrollIntoView({behavior:'smooth'});alert('Antes de enviar a matrícula do menor, valide o responsável legal.');return}if(!hasPhoto('student')){e.preventDefault();alert('A foto 3x4 do aluno é obrigatória. Escolha da galeria ou tire pela câmera.')}});

 function updateStudyWork(){
   const study=document.querySelector('input[name="currently_studying"]:checked')?.value==='1';
   const work=document.querySelector('input[name="works_currently"]:checked')?.value==='1';
   const needWork=document.querySelector('input[name="needs_work_declaration"]:checked')?.value==='1';
   const sf=document.getElementById('schoolFields'),wf=document.getElementById('workFields');
   sf.hidden=!study;wf.hidden=!work;
   document.getElementById('school_network').required=study;
   document.getElementById('school_name').required=study;
   document.getElementById('employer_name').required=work&&needWork;
   document.querySelectorAll('input[name="needs_work_declaration"]').forEach(x=>x.disabled=!work);
 }
 document.querySelectorAll('input[name="currently_studying"],input[name="works_currently"],input[name="needs_work_declaration"]').forEach(x=>x.addEventListener('change',updateStudyWork));
 updateStudyWork();

 updateAge();
 <?php if($_SERVER['REQUEST_METHOD']==='POST'):?>
 if(isMinor){ setTimeout(()=>document.getElementById('validateGuardian').click(),100); }
 <?php endif;?>
})();
</script>
<?php endif;?></main><?php site_footer();?>
