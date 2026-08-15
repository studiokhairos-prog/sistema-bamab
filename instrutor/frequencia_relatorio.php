<?php
require __DIR__.'/../config.php';
require __DIR__.'/../report_helpers.php';
$u=require_instructor();
$area=trim((string)($_GET['area']??''));
if(!instructor_has_area((int)$u['id'],$area)){http_response_code(403);exit('Ala não autorizada.');}
$rows=instructor_frequency_for_area((int)$u['id'],$area);
$export=(string)($_GET['export']??'');
if($export==='xls'){
    bamab_report_excel_headers('frequencia_'.preg_replace('/[^A-Za-z0-9_-]+/','_', $area).'_'.date('Y-m-d').'.xls');
    $logo=bamab_report_logo_data_uri();
    echo '<!doctype html><html><head><meta charset="utf-8">'.bamab_report_excel_style_block().'</head><body><div class="sheet-wrap">';
    echo '<div class="sheet-head">'.($logo?'<img src="'.$logo.'" alt="Brasão">':'').'<div><h1>RELATÓRIO DE FREQUÊNCIA DOS ALUNOS</h1><p>'.e(report_location_line()).' · '.e($area).' · Atualizado em '.date('d/m/Y').'</p></div></div>';
    echo '<div class="sheet-block"><table class="sheet-table"><thead><tr><th>Aluno</th><th>Matrícula</th><th>Ensaios</th><th>Completas</th><th>Saída pendente</th><th>Ausências</th><th>Frequência</th></tr></thead><tbody>';
    if($rows){foreach($rows as $r){echo '<tr><td>'.e($r['preferred']?:$r['name']).'</td><td>'.e($r['number']).'</td><td>'.$r['total'].'</td><td>'.$r['complete'].'</td><td>'.$r['entry_only'].'</td><td>'.$r['absent'].'</td><td>'.e(number_format((float)$r['percentage'],1,',','.')).'%</td></tr>';}} else echo '<tr><td colspan="7">Nenhum aluno ativo nesta ala.</td></tr>';
    echo '</tbody></table></div><table class="sheet-sign"><tr><td><div class="line"></div><strong>COORDENADOR GERAL</strong></td><td><div class="line"></div><strong>INSTRUTOR RESPONSÁVEL</strong><br>'.e(instructor_display_name($u)).'</td><td><div class="line"></div><strong>SECRETÁRIO(A)</strong></td></tr></table><div class="footer-note">Relatório gerado em '.date('d/m/Y H:i').'.</div></div></body></html>';
    exit;
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatório de Frequência</title><link rel="stylesheet" href="../assets/report_a4.css"><?=bamab_report_style_block('../')?></head><body><div class="a4-toolbar no-print"><a href="frequencia.php?area=<?=urlencode($area)?>">← VOLTAR</a><button onclick="window.print()">IMPRIMIR / PDF A4</button><a href="?area=<?=urlencode($area)?>&export=xls">EXCEL</a></div><main class="a4-document"><header class="a4-header"><img src="<?=e(bamab_report_logo_web('../'))?>" alt="Brasão"><div class="a4-location"><?=e(report_location_line())?></div></header><section class="a4-title"><h1>RELATÓRIO DE FREQUÊNCIA DOS ALUNOS</h1><p><?=e($area)?> · Atualizado em <?=date('d/m/Y')?></p></section><table class="a4-table"><thead><tr><th>Aluno</th><th>Matrícula</th><th>Ensaios</th><th>Completas</th><th>Saída pendente</th><th>Ausências</th><th>Frequência</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['preferred']?:$r['name'])?></td><td><?=e($r['number'])?></td><td><?=$r['total']?></td><td><?=$r['complete']?></td><td><?=$r['entry_only']?></td><td><?=$r['absent']?></td><td><?=e(number_format((float)$r['percentage'],1,',','.'))?>%</td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="7">Nenhum aluno ativo nesta ala.</td></tr><?php endif;?></tbody></table><section class="a4-signatures"><div class="a4-signature"><div class="line"></div><strong>COORDENADOR GERAL</strong></div><div class="a4-signature"><div class="line"></div><strong>INSTRUTOR RESPONSÁVEL</strong><span><?=e(instructor_display_name($u))?></span></div><div class="a4-signature"><div class="line"></div><strong>SECRETÁRIO(A)</strong></div></section><footer class="a4-footer">Relatório gerado em <?=date('d/m/Y H:i')?>.</footer></main></body></html>
