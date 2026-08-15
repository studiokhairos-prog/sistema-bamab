<?php
require __DIR__.'/../config.php';require __DIR__.'/_doc_card_template.php';$u=require_admin();$id=(int)($_GET['id']??0);$pdo=db();
$st=$pdo->prepare("SELECT * FROM enrollments WHERE id=? AND deleted_at IS NULL");$st->execute([$id]);$r=$st->fetch();$card=$r?guardian_card($id):null;
if(!$r||!$card||(int)$r['is_minor']!==1){http_response_code(404);exit('Documento do acompanhante não disponível.');}
$notice=setting('companion_card_back_notice','Documento vinculado à matrícula do aluno menor. Uso pessoal do responsável/acompanhante cadastrado. Em caso de cancelamento ou desativação da matrícula do menor, este documento também é desativado automaticamente.');
render_bamab_card([
 'page_title'=>'Carteirinha do Acompanhante','front_title'=>setting('doc_companion_card_title','CARTEIRINHA DO ACOMPANHANTE'),
 'logo'=>bamab_doc_logo('../'),'photo'=>bamab_doc_photo((string)($r['guardian_photo_path']??''),'../'),
 'name'=>$r['guardian_name']??'','name_label'=>'NOME DO ACOMPANHANTE','preferred'=>'','preferred_label'=>'',
 'fields'=>[
   ['label'=>setting('doc_companion_number_label','Nº ACOMPANHANTE'),'value'=>$card['companion_number']??''],
   ['label'=>setting('doc_companion_role_label','VÍNCULO'),'value'=>$r['guardian_relationship']??'RESPONSÁVEL'],
   ['label'=>'ALUNO VINCULADO','value'=>$r['student_name']??''],
   ['label'=>setting('doc_validity_label','VALIDADE'),'value'=>bamab_doc_date($card['valid_until']??'')],
 ],
 'valid'=>bamab_doc_date($card['valid_until']??''),'qr'=>'../qr.php?kind=guardian&id='.$id,'active'=>(($card['status']??'')==='ATIVA'),
 'back_notice'=>$notice.' Matrícula vinculada: '.($r['registration_number']??'').' — Aluno: '.($r['student_name']??'').'.',
 'signatures'=>['RESPONSÁVEL / ACOMPANHANTE']
]);
