<?php
require __DIR__.'/../config.php';require __DIR__.'/_doc_badge_template.php';$u=require_admin();$id=(int)($_GET['id']??0);
$st=db()->prepare("SELECT * FROM enrollments WHERE id=? AND deleted_at IS NULL");$st->execute([$id]);$r=$st->fetch();
if(!$r){http_response_code(404);exit('Aluno não encontrado.');}
$card=enrollment_card($id);if($r['status']!=='APROVADA'||!$card||$card['status']!=='ATIVA')exit('Crachá indisponível até a aprovação do aluno.');
$nameMode=setting('doc_student_card_name_mode','first_last_surname');
$studentDisplayName=bamab_student_card_display_name((string)$r['student_name'],(string)($r['preferred_name']??''),(string)$nameMode);
render_bamab_badge([
 'title'=>'Crachá do aluno',
 'front_title'=>setting('doc_student_badge_title'),
 'logo'=>bamab_doc_logo('../'),
 'photo'=>bamab_doc_photo((string)($r['photo_path']??''),'../'),
 'name_label'=>setting('doc_student_name_label','NOME'),
 'name'=>$studentDisplayName,
 'student_name_emphasis'=>true,
 'preferred'=>$r['preferred_name'],
 'role_label'=>setting('doc_role_label'),
 'role'=>$r['instrument'],
 'number_label'=>setting('doc_number_label'),
 'number'=>$r['registration_number']?:$r['protocol'],
 'valid'=>bamab_doc_date($card['valid_until']??''),
 'qr'=>'../qr.php?kind=student&id='.$id,
 'back_info'=>[
   'Contato'=>$r['student_phone'],
   'Emergência'=>$r['emergency_phone'],
   'Responsável'=>(int)$r['is_minor']===1?$r['guardian_name']:'',
   'Telefone responsável'=>(int)$r['is_minor']===1?$r['guardian_phone']:''
 ],
 'back_rules'=>setting('badge_back_rules')
]);
