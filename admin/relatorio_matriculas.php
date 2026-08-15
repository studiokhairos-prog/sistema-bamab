<?php
require __DIR__.'/../config.php';
require __DIR__.'/../report_helpers.php';
$u=require_admin();
$pdo=db();
$periodId=(int)($_GET['period_id']??0);
$period=enrollment_period($periodId);
if(!$period){http_response_code(404);exit('Período não encontrado.');}
$st=$pdo->prepare("SELECT * FROM enrollments WHERE period_id=? AND deleted_at IS NULL ORDER BY instrument,student_name COLLATE NOCASE");
$st->execute([$periodId]);
$rows=$st->fetchAll();
$grouped=[];foreach(enrollment_instruments() as $i)$grouped[$i]=[];foreach($rows as $r)$grouped[$r['instrument']][]=$r;
$export=(string)($_GET['export']??'');
if($export==='csv'){
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_bamab_'.date('Y-m-d').'.csv"');
    echo "ï»¿";
    $out=fopen('php://output','w');
    fputcsv($out,['Ala/Instrumento','Número de matrícula','Nome','Status','Data da inscrição','Responsável','Telefone'],';');
    foreach($rows as $r)fputcsv($out,[$r['instrument'],$r['registration_number']?:$r['protocol'],$r['student_name'],enrollment_status_label($r['status']),$r['created_at'],$r['guardian_name'],$r['guardian_phone']?:$r['student_phone']],';');
    fclose($out);exit;
}
if($export==='xls'){
    bamab_report_excel_headers('relatorio_matriculas_bamab_'.date('Y-m-d').'.xls');
    $logo=bamab_report_logo_data_uri();
    echo '<!doctype html><html><head><meta charset="utf-8">'.bamab_report_excel_style_block().'</head><body><div class="sheet-wrap">';
    echo '<div class="sheet-head">'.($logo?'<img src="'.$logo.'" alt="Brasão">':'').'<div><h1>RELATÓRIO GERAL DE MATRÍCULAS</h1><p>'.e(report_location_line()).' · '.e($period['name']).' · '.e(date('d/m/Y',strtotime($period['start_date']))).' a '.e(date('d/m/Y',strtotime($period['end_date']))).' · '.count($rows).' inscrição(ões)</p></div></div>';
    foreach($grouped as $ala=>$lista){
        echo '<div class="sheet-block"><h2>'.e($ala).' — '.count($lista).' aluno(s)</h2><table class="sheet-table"><thead><tr><th>Ordem</th><th>Matrícula</th><th>Nome do inscrito</th><th>Status</th><th>Responsável</th><th>Telefone</th></tr></thead><tbody>';
        if($lista){
            foreach($lista as $idx=>$r){
                echo '<tr><td>'.($idx+1).'</td><td>'.e($r['registration_number']?:$r['protocol']).'</td><td>'.e($r['student_name']).'</td><td>'.e(enrollment_status_label($r['status'])).'</td><td>'.e($r['guardian_name']?:'—').'</td><td>'.e($r['guardian_phone']?:($r['student_phone']?:'—')).'</td></tr>';
            }
        }else echo '<tr><td colspan="6">Nenhuma inscrição nesta ala.</td></tr>';
        echo '</tbody></table></div>';
    }
    echo '<table class="sheet-sign"><tr><td><div class="line"></div><strong>COORDENADOR GERAL</strong></td><td><div class="line"></div><strong>INSTRUTOR RESPONSÁVEL</strong></td><td><div class="line"></div><strong>SECRETÁRIO(A)</strong></td></tr></table>';
    echo '<div class="footer-note">Relatório gerado em '.date('d/m/Y H:i').'.</div></div></body></html>';
    exit;
}
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatório Geral de Matrículas</title><link rel="stylesheet" href="../assets/report_a4.css"><?=bamab_report_style_block('../')?></head><body>
<div class="a4-toolbar no-print"><a href="matriculas.php?period_id=<?=$periodId?>">← VOLTAR</a><button onclick="window.print()">IMPRIMIR / PDF A4</button><a href="?period_id=<?=$periodId?>&export=xls">EXCEL</a><a href="?period_id=<?=$periodId?>&export=csv">CSV</a></div>
<main class="a4-document"><header class="a4-header"><img src="<?=e(bamab_report_logo_web('../'))?>" alt="Brasão"><div class="a4-location"><?=e(report_location_line())?></div></header><section class="a4-title"><h1>RELATÓRIO GERAL DE MATRÍCULAS</h1><p><?=e($period['name'])?> · <?=e(date('d/m/Y',strtotime($period['start_date'])))?> a <?=e(date('d/m/Y',strtotime($period['end_date'])))?> · <?=count($rows)?> inscrição(ões)</p></section>
<?php foreach($grouped as $ala=>$lista):?><section class="a4-section"><h2><?=e($ala)?> — <?=count($lista)?> aluno(s)</h2><table class="a4-table"><thead><tr><th>Ordem</th><th>Matrícula</th><th>Nome do inscrito</th><th>Status</th></tr></thead><tbody><?php foreach($lista as $idx=>$r):?><tr><td><?=$idx+1?></td><td><?=e($r['registration_number']?:$r['protocol'])?></td><td><?=e($r['student_name'])?></td><td><?=e(enrollment_status_label($r['status']))?></td></tr><?php endforeach;?><?php if(!$lista):?><tr><td colspan="4">Nenhuma inscrição nesta ala.</td></tr><?php endif;?></tbody></table></section><?php endforeach;?>
<section class="a4-signatures"><div class="a4-signature"><div class="line"></div><strong>COORDENADOR GERAL</strong></div><div class="a4-signature"><div class="line"></div><strong>INSTRUTOR RESPONSÁVEL</strong></div><div class="a4-signature"><div class="line"></div><strong>SECRETÁRIO(A)</strong></div></section><footer class="a4-footer">Relatório gerado em <?=date('d/m/Y H:i')?>.</footer></main></body></html>
