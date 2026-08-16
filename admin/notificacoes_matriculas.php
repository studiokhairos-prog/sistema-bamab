<?php
declare(strict_types=1);
require __DIR__.'/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$u=admin_user();
if(!$u){
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Sessão administrativa encerrada.'],JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo=db();
$adminId=(int)$u['id'];

function notification_json(array $data,int $status=200): never {
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function unread_notification_count(PDO $pdo,int $adminId): int {
    $st=$pdo->prepare("SELECT COUNT(*)
        FROM admin_notifications n
        JOIN enrollments e ON e.id=n.enrollment_id
        LEFT JOIN admin_notification_reads r ON r.notification_id=n.id AND r.admin_id=?
        WHERE n.type='NOVA_MATRICULA' AND r.notification_id IS NULL AND e.deleted_at IS NULL");
    $st->execute([$adminId]);
    return (int)$st->fetchColumn();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $csrf=(string)($_POST['csrf']??'');
    if($csrf===''||!hash_equals((string)($_SESSION['csrf']??''),$csrf)){
        notification_json(['ok'=>false,'error'=>'Solicitação inválida.'],403);
    }
    if((string)($_POST['action']??'')!=='mark_read'){
        notification_json(['ok'=>false,'error'=>'Ação inválida.'],400);
    }

    $rawIds=$_POST['ids']??[];
    if(!is_array($rawIds))$rawIds=[$rawIds];
    $ids=[];
    foreach(array_slice($rawIds,0,50) as $rawId){
        $id=filter_var($rawId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($id!==false)$ids[(int)$id]=true;
    }

    if($ids){
        $pdo->beginTransaction();
        try{
            $mark=$pdo->prepare("INSERT OR IGNORE INTO admin_notification_reads(notification_id,admin_id,read_at)
                SELECT id,?,? FROM admin_notifications WHERE id=? AND type='NOVA_MATRICULA'");
            $now=now_iso();
            foreach(array_keys($ids) as $id)$mark->execute([$adminId,$now,$id]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            notification_json(['ok'=>false,'error'=>'Não foi possível registrar a leitura.'],500);
        }
    }

    notification_json(['ok'=>true,'unread_count'=>unread_notification_count($pdo,$adminId)]);
}

if($_SERVER['REQUEST_METHOD']!=='GET'){
    notification_json(['ok'=>false,'error'=>'Método não permitido.'],405);
}

$st=$pdo->prepare("SELECT n.id,n.enrollment_id,n.title,n.message,n.created_at,
        e.student_name,e.registration_number,e.instrument
    FROM admin_notifications n
    JOIN enrollments e ON e.id=n.enrollment_id
    LEFT JOIN admin_notification_reads r ON r.notification_id=n.id AND r.admin_id=?
    WHERE n.type='NOVA_MATRICULA' AND r.notification_id IS NULL AND e.deleted_at IS NULL
    ORDER BY n.id ASC
    LIMIT 10");
$st->execute([$adminId]);
$notifications=[];
foreach($st->fetchAll() as $row){
    $notifications[]=[
        'id'=>(int)$row['id'],
        'enrollment_id'=>(int)$row['enrollment_id'],
        'title'=>(string)$row['title'],
        'message'=>(string)$row['message'],
        'student_name'=>(string)$row['student_name'],
        'registration_number'=>(string)$row['registration_number'],
        'instrument'=>(string)$row['instrument'],
        'created_at'=>(string)$row['created_at'],
        'url'=>'matricula_ver.php?id='.(int)$row['enrollment_id'],
    ];
}

notification_json([
    'ok'=>true,
    'notifications'=>$notifications,
    'unread_count'=>unread_notification_count($pdo,$adminId),
]);
