<?php
require __DIR__.'/../config.php';require __DIR__.'/../admin/_doc_badge_template.php';$u=require_instructor();$id=(int)$u['id'];$r=instructor_record($id);if(!$r){http_response_code(404);exit('Cadastro não encontrado.');}
$areas=instructor_areas($id);ensure_instructor_qr_token($id);$r=instructor_record($id)?:$r;
render_bamab_badge([
 'title'=>'Minha Credencial QR BAMAB',
 'front_title'=>'CRACHÁ OFICIAL BAMAB — '.($r['role']==='AUXILIAR'?'AUXILIAR':'INSTRUTOR'),
 'logo'=>bamab_doc_logo('../'),
 'photo'=>bamab_doc_photo((string)($r['photo_path']??''),'../'),
 'name_label'=>'NOME',
 'name'=>bamab_student_card_display_name((string)$r['full_name'],(string)($r['preferred_name']??''),'first_last_surname'),
 'preferred'=>$r['preferred_name'],
 'role_label'=>'FUNÇÃO / ALAS',
 'role'=>$r['role'].' · '.($areas?implode(' • ',$areas):'SEM ALA'),
 'number_label'=>'CÓDIGO DO INSTRUTOR',
 'number'=>$r['instructor_code']?:instructor_code_for_id($id),
 'valid'=>'ENQUANTO ATIVO',
 'qr'=>'../qr.php?kind=instructor&id='.$id,
 'back_info'=>['Telefone'=>$r['phone'],'E-mail'=>$r['email'],'Alas'=>implode(' • ',$areas)],
 'back_rules'=>'QR pessoal e intransferível para controle de presença. Apresente na ENTRADA e novamente na SAÍDA de ensaios, reuniões, apresentações e atividades oficiais.'
]);
