<?php
require __DIR__.'/../config.php';require __DIR__.'/_doc_card_template.php';$u=require_admin();$id=(int)($_GET['id']??0);$r=team_member($id);
if(!$r||$r['deleted_at']){http_response_code(404);exit('Membro não encontrado.');}
if($r['status']!=='APROVADO'||$r['badge_status']!=='ATIVO')exit('Carteirinha indisponível até a aprovação/ativação.');
$nameMode=setting('doc_team_card_name_mode','first_last_surname');
$display=bamab_student_card_display_name((string)$r['full_name'],(string)($r['preferred_name']??''),(string)$nameMode);
render_bamab_card([
 'page_title'=>'Carteirinha da Equipe','front_title'=>setting('doc_team_card_title','CARTEIRINHA DA EQUIPE'),
 'logo'=>bamab_doc_logo('../'),'photo'=>bamab_doc_photo((string)($r['photo_path']??''),'../'),
 'name'=>$display,'name_label'=>setting('doc_student_name_label','NOME'),'preferred'=>$r['preferred_name']??'','preferred_label'=>setting('doc_preferred_name_label','NOME SOCIAL / APELIDO'),
 'fields'=>[
   ['label'=>setting('doc_team_number_label','Nº EQUIPE'),'value'=>$r['application_number']??''],
   ['label'=>setting('doc_team_role_label','FUNÇÃO'),'value'=>$r['role_name']??''],
   ['label'=>setting('doc_validity_label','VALIDADE'),'value'=>bamab_doc_date($r['badge_valid_until']??'')],
 ],
 'valid'=>bamab_doc_date($r['badge_valid_until']??''),'qr'=>'../qr.php?kind=team&id='.$id,'active'=>true,
 'back_notice'=>setting('team_card_back_notice','Documento institucional e intransferível.'),
 'signatures'=>['ASSINATURA DO MEMBRO','ADMIN / COORDENAÇÃO']
]);
