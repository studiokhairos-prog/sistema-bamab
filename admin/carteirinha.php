<?php
require __DIR__.'/../config.php';require __DIR__.'/_doc_card_template.php';$u=require_admin();$id=(int)($_GET['id']??0);$pdo=db();
$st=$pdo->prepare("SELECT e.*,p.name period_name FROM enrollments e LEFT JOIN enrollment_periods p ON p.id=e.period_id WHERE e.id=?");$st->execute([$id]);$r=$st->fetch();
if(!$r){http_response_code(404);exit('Matrícula não encontrada.');}
$card=enrollment_card($id);if(!$card){exit('Carteirinha ainda não foi ativada.');}
$nameMode=setting('doc_student_card_name_mode','first_last_surname');
$display=bamab_student_card_display_name((string)$r['student_name'],(string)($r['preferred_name']??''),(string)$nameMode);
render_bamab_card([
 'page_title'=>'Carteirinha '.($r['registration_number']?:$r['protocol']),
 'front_title'=>setting('doc_student_card_title','CARTEIRINHA DO ALUNO'),
 'logo'=>bamab_doc_logo('../'),'photo'=>bamab_doc_photo((string)($r['photo_path']??''),'../'),
 'name'=>$display,
 'student_name_emphasis'=>true,'name_label'=>setting('doc_student_name_label','NOME'),'preferred'=>$r['preferred_name']??'','preferred_label'=>setting('doc_preferred_name_label','NOME SOCIAL / APELIDO'),
 'fields'=>[
   ['label'=>setting('doc_number_label','MATRÍCULA'),'value'=>$r['registration_number']?:$r['protocol']],
   ['label'=>setting('doc_role_label','INSTRUMENTO / ALA'),'value'=>$r['instrument']],
   ['label'=>setting('doc_validity_label','VALIDADE'),'value'=>bamab_doc_date($card['valid_until']??'')],
 ],
 'valid'=>bamab_doc_date($card['valid_until']??''),'qr'=>'../qr.php?kind=student&id='.$id,'active'=>(($card['status']??'')==='ATIVA'),
 'back_notice'=>setting('card_back_notice','Documento pessoal e intransferível.'),
 'signatures'=>['ASSINATURA DO ALUNO','ASSINATURA DO RESPONSÁVEL']
]);
