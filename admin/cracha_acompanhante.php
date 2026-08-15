<?php
require __DIR__.'/../config.php';require __DIR__.'/_doc_badge_template.php';$u=require_admin();$id=(int)($_GET['id']??0);
$st=db()->prepare("SELECT * FROM enrollments WHERE id=? AND deleted_at IS NULL");$st->execute([$id]);$r=$st->fetch();$card=$r?guardian_card($id):null;
if(!$r||!$card||(int)$r['is_minor']!==1||$card['status']!=='ATIVA'){http_response_code(404);exit('Crachá do acompanhante não disponível.');}
render_bamab_badge([
 'title'=>'Crachá de acompanhante',
 'front_title'=>setting('doc_companion_badge_title'),
 'logo'=>bamab_doc_logo('../'),
 'photo'=>bamab_doc_photo((string)($r['guardian_photo_path']??''),'../'),
 'name_label'=>setting('doc_student_name_label','NOME'),
 'name'=>$r['guardian_name'],
 'preferred'=>'',
 'role_label'=>setting('doc_companion_role_label'),
 'role'=>$r['guardian_relationship'].' — ACOMPANHANTE',
 'number_label'=>setting('doc_companion_number_label'),
 'number'=>$card['companion_number'],
 'valid'=>bamab_doc_date($card['valid_until']??''),
 'qr'=>'../qr.php?kind=guardian&id='.$id,
 'back_info_title'=>'DADOS DO ACOMPANHANTE',
 'back_info'=>['Telefone'=>$r['guardian_phone'],'E-mail'=>$r['guardian_email'],'Aluno vinculado'=>$r['student_name'],'Matrícula do aluno'=>$r['registration_number']],
 'back_rules'=>'Crachá de acompanhante vinculado ao aluno menor cadastrado. Uso pessoal e intransferível. O cancelamento, exclusão ou desativação da matrícula do menor desativa também este crachá.'
]);
