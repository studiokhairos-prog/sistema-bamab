<?php
require __DIR__.'/config.php';
require __DIR__.'/qr_lib.php';
$kind=(string)($_GET['kind']??'');$id=(int)($_GET['id']??0);$payload='';
if($kind==='instructor'){
    $admin=admin_user();$iu=instructor_user();
    if(!$admin && (!$iu || (int)$iu['id']!==$id)){http_response_code(403);exit;}
    $r=instructor_record($id);if(!$r){http_response_code(404);exit;}
    $payload=instructor_qr_payload($r);
}else{
    require_admin();
    if($kind==='student'){
        $st=db()->prepare("SELECT * FROM enrollments WHERE id=?");$st->execute([$id]);$r=$st->fetch();
        if(!$r){http_response_code(404);exit;}
        $payload=student_qr_payload($r);
    }elseif($kind==='guardian'){
        $st=db()->prepare("SELECT * FROM enrollments WHERE id=?");$st->execute([$id]);$r=$st->fetch();
        if(!$r || (int)$r['is_minor']!==1){http_response_code(404);exit;}
        $payload=guardian_qr_payload($r);
    }elseif($kind==='team'){
        $r=team_member($id);if(!$r){http_response_code(404);exit;}
        $payload=team_qr_payload($r);
    }else{http_response_code(400);exit;}
}
header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: private, max-age=300');
echo bamab_qr_svg($payload,5,4);
