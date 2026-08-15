<?php
require __DIR__.'/../config.php';
require __DIR__.'/../report_helpers.php';
$u=require_admin();
$pdo=db();
$id=(int)($_GET['event_id']??0);
$st=$pdo->prepare("SELECT * FROM attendance_events WHERE id=?");$st->execute([$id]);$event=$st->fetch();
if(!$event){http_response_code(404);exit('Evento não encontrado.');}
$areas=attendance_event_areas($id);if(!$areas)$areas=enrollment_instruments();
$st=$pdo->prepare("SELECT ac.*,i.full_name instructor_name,i.preferred_name instructor_preferred,a.display_name admin_name FROM attendance_checks ac LEFT JOIN instructors i ON i.id=ac.instructor_id LEFT JOIN admins a ON a.id=ac.admin_id WHERE ac.event_id=? AND ac.person_type='ALUNO'");
$st->execute([$id]);$checks=[];foreach($st->fetchAll() as $c)$checks[$c['person_id'].':'.$c['check_type']]=$c;
$export=(string)($_GET['export']??'');
if($export==='csv'){
    header('Content-Type:text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="presenca_por_ala_bamab_'.date('Y-m-d').'.csv"');echo "\xEF\xBB\xBF";
    $o=fopen('php://output','w');fputcsv($o,['Ala','Nome','Nome de uso','Matrícula','Entrada','Responsável Entrada','Saída','Responsável Saída','Situação'],';');
    foreach($areas as $area){
        $students=attendance_expected_students_for_area($event,$area);
        foreach($students as $s){
            $en=$checks[$s['id'].':ENTRADA']??null;$ex=$checks[$s['id'].':SAIDA']??null;$status=$en&&$ex?'PRESENÇA COMPLETA':($en?'SAÍDA PENDENTE':'AUSENTE');
            fputcsv($o,[$area,$s['student_name'],$s['preferred_name'],$s['registration_number'],$en?date('H:i:s',strtotime($en['scanned_at'])):'',$en?($en['instructor_preferred']?:($en['instructor_name']?:($en['admin_name']?:'ADMIN'))):'',$ex?date('H:i:s',strtotime($ex['scanned_at'])):'',$ex?($ex['instructor_preferred']?:($ex['instructor_name']?:($ex['admin_name']?:'ADMIN'))):'',$status],';');
        }
    }
    fclose($o);exit;
}
if($export==='xls'){
    bamab_report_excel_headers('presenca_por_ala_bamab_'.date('Y-m-d').'.xls');
    $logo=bamab_report_logo_data_uri();
    echo '<!doctype html><html><head><meta charset="utf-8">'.bamab_report_excel_style_block().'</head><body><div class="sheet-wrap">';
    echo '<div class="sheet-head">'.($logo?'<img src="'.$logo.'" alt="Brasão">':'').'<div><h1>RELATÓRIO DE PRESENÇA POR ALA</h1><p>'.e(report_location_line()).' · '.e($event['title']).' · '.e(date('d/m/Y',strtotime($event['event_date']))).'</p></div></div>';
    foreach($areas as $area){
        $students=attendance_expected_students_for_area($event,$area);$complete=$pending=$absent=0;
        foreach($students as $s){$en=$checks[$s['id'].':ENTRADA']??null;$ex=$checks[$s['id'].':SAIDA']??null;if($en&&$ex)$complete++;elseif($en)$pending++;else$absent++;}
        echo '<div class="sheet-block"><h2>'.e($area).'</h2><div class="sheet-meta"><div><strong>'.$complete.'</strong><small>Presença completa</small></div><div><strong>'.$pending.'</strong><small>Saída pendente</small></div><div><strong>'.$absent.'</strong><small>Ausentes</small></div></div>';
        echo '<table class="sheet-table"><thead><tr><th>Nome</th><th>Matrícula</th><th>Entrada</th><th>Saída</th><th>Situação</th></tr></thead><tbody>';
        if($students){foreach($students as $s){$en=$checks[$s['id'].':ENTRADA']??null;$ex=$checks[$s['id'].':SAIDA']??null;$status=$en&&$ex?'PRESENÇA COMPLETA':($en?'SAÍDA PENDENTE':'AUSENTE');echo '<tr><td>'.e($s['preferred_name']?:$s['student_name']).'</td><td>'.e($s['registration_number']).'</td><td>'.($en?e(date('H:i:s',strtotime($en['scanned_at']))):'—').'</td><td>'.($ex?e(date('H:i:s',strtotime($ex['scanned_at']))):'—').'</td><td>'.e($status).'</td></tr>';}} else echo '<tr><td colspan="5">Nenhum aluno ativo nesta ala.</td></tr>';
        echo '</tbody></table></div>';
    }
    echo '<table class="sheet-sign"><tr><td><div class="line"></div><strong>COORDENADOR GERAL</strong></td><td><div class="line"></div><strong>INSTRUTOR RESPONSÁVEL</strong></td><td><div class="line"></div><strong>SECRETÁRIO(A)</strong></td></tr></table><div class="footer-note">Relatório gerado em '.date('d/m/Y H:i').'.</div></div></body></html>';
    exit;
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatório de Presença</title><link rel="stylesheet" href="../assets/report_a4.css"><?=bamab_report_style_block('../')?></head><body><div class="a4-toolbar no-print"><a href="presenca.php?event_id=<?=$id?>">← VOLTAR</a><button onclick="window.print()">IMPRIMIR / PDF A4</button><a href="?event_id=<?=$id?>&export=xls">EXCEL</a><a href="?event_id=<?=$id?>&export=csv">CSV</a></div><main class="a4-document"><header class="a4-header"><img src="<?=e(bamab_report_logo_web('../'))?>" alt="Brasão"><div class="a4-location"><?=e(report_location_line())?></div></header><section class="a4-title"><h1>RELATÓRIO DE PRESENÇA POR ALA</h1><p><?=e($event['title'])?> · <?=e(date('d/m/Y',strtotime($event['event_date'])))?> · Presença completa = ENTRADA + SAÍDA</p></section>
<?php foreach($areas as $area):$students=attendance_expected_students_for_area($event,$area);$complete=$pending=$absent=0;foreach($students as $s){$en=$checks[$s['id'].':ENTRADA']??null;$ex=$checks[$s['id'].':SAIDA']??null;if($en&&$ex)$complete++;elseif($en)$pending++;else$absent++;}?><section class="a4-section"><h2><?=e($area)?></h2><div class="a4-counts"><div><strong><?=$complete?></strong><span>Presença completa</span></div><div><strong><?=$pending?></strong><span>Saída pendente</span></div><div><strong><?=$absent?></strong><span>Ausentes</span></div></div><table class="a4-table"><thead><tr><th>Nome</th><th>Matrícula</th><th>Entrada</th><th>Saída</th><th>Situação</th></tr></thead><tbody><?php foreach($students as $s):$en=$checks[$s['id'].':ENTRADA']??null;$ex=$checks[$s['id'].':SAIDA']??null;$status=$en&&$ex?'PRESENÇA COMPLETA':($en?'SAÍDA PENDENTE':'AUSENTE');?><tr><td><?=e($s['preferred_name']?:$s['student_name'])?></td><td><?=e($s['registration_number'])?></td><td><?=$en?e(date('H:i:s',strtotime($en['scanned_at']))):'—'?></td><td><?=$ex?e(date('H:i:s',strtotime($ex['scanned_at']))):'—'?></td><td><?=e($status)?></td></tr><?php endforeach;?><?php if(!$students):?><tr><td colspan="5">Nenhum aluno ativo nesta ala.</td></tr><?php endif;?></tbody></table></section><?php endforeach;?>
<section class="a4-signatures"><div class="a4-signature"><div class="line"></div><strong>COORDENADOR GERAL</strong></div><div class="a4-signature"><div class="line"></div><strong>INSTRUTOR RESPONSÁVEL</strong></div><div class="a4-signature"><div class="line"></div><strong>SECRETÁRIO(A)</strong></div></section><footer class="a4-footer">Relatório gerado em <?=date('d/m/Y H:i')?>.</footer></main></body></html>
