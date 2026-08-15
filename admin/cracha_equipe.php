<?php
require __DIR__.'/../config.php';require __DIR__.'/_doc_badge_template.php';$u=require_admin();$id=(int)($_GET['id']??0);$r=team_member($id);
if(!$r||$r['deleted_at']){http_response_code(404);exit('Membro não encontrado.');}
if($r['status']!=='APROVADO'||$r['badge_status']!=='ATIVO')exit('Crachá indisponível até a aprovação/ativação.');
$nameMode=setting('doc_team_card_name_mode','first_last_surname');
$teamDisplayName=bamab_student_card_display_name((string)$r['full_name'],(string)($r['preferred_name']??''),(string)$nameMode);
render_bamab_badge([
 'title'=>'Crachá da equipe',
 'front_title'=>setting('doc_team_badge_title'),
 'logo'=>bamab_doc_logo('../'),
 'photo'=>bamab_doc_photo((string)($r['photo_path']??''),'../'),
 'name_label'=>setting('doc_student_name_label','NOME'),
 'name'=>$teamDisplayName,
 'preferred'=>$r['preferred_name'],
 'role_label'=>setting('doc_team_role_label'),
 'role'=>$r['role_name'],
 'number_label'=>setting('doc_team_number_label'),
 'number'=>$r['application_number'],
 'valid'=>bamab_doc_date($r['badge_valid_until']??''),
 'qr'=>'../qr.php?kind=team&id='.$id,
 'back_info'=>[
   'Telefone'=>$r['phone'],
   'Emergência'=>$r['emergency_phone'],
   'Responsável'=>(int)$r['is_minor']===1?$r['guardian_name']:'',
   'Telefone responsável'=>(int)$r['is_minor']===1?$r['guardian_phone']:''
 ],
 'back_rules'=>setting('team_card_back_notice')
]);
