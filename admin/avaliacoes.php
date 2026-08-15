<?php
require __DIR__.'/_layout.php';
$u=require_admin();if(!is_general_admin($u)){http_response_code(403);exit('Somente o Admin Geral pode acessar os relatórios de avaliação.');}
$pdo=db();
$q=trim((string)($_GET['q']??''));$year=(int)($_GET['year']??date('Y'));if($year<2000||$year>2100)$year=(int)date('Y');
$sql="SELECT t.*,i.full_name,i.preferred_name,i.instructor_code,
      (SELECT GROUP_CONCAT(a.area,' • ') FROM evaluation_test_areas a WHERE a.test_id=t.id) areas,
      (SELECT COUNT(DISTINCT s.student_id) FROM evaluation_submissions s WHERE s.test_id=t.id AND s.status='CONCLUIDA') students_count,
      (SELECT ROUND(AVG(s.final_score),1) FROM evaluation_submissions s WHERE s.test_id=t.id AND s.status='CONCLUIDA') class_average
      FROM evaluation_tests t
      LEFT JOIN instructors i ON i.id=t.owner_instructor_id
      WHERE t.evaluation_year=? AND (t.report_sent=1 OR (t.owner_instructor_id IS NULL AND t.status='ENCERRADO'))";
$params=[$year];
if($q!==''){$like='%'.$q.'%';$sql.=" AND (t.title LIKE ? OR i.full_name LIKE ? OR i.preferred_name LIKE ? OR i.instructor_code LIKE ? OR EXISTS(SELECT 1 FROM evaluation_test_areas ax WHERE ax.test_id=t.id AND ax.area LIKE ?))";$params=array_merge($params,[$like,$like,$like,$like,$like]);}
$sql.=" ORDER BY COALESCE(NULLIF(t.submitted_to_admin_at,''),t.updated_at) DESC,t.id DESC";$st=$pdo->prepare($sql);$st->execute($params);$tests=$st->fetchAll();
admin_header('Relatórios Finais de Avaliação');$msg=flash();?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?>
<section class="panel evaluation-admin-intro evaluation-admin-final-only"><span>ADMIN · SOMENTE RESULTADOS FINAIS</span><h2>Avaliações Musicais BAMAB</h2><p>Os testes são iniciados e conduzidos pelos próprios instrutores em suas alas. Aqui chegam somente os <strong>relatórios finalizados</strong>, com nome do aluno, matrícula/código, instrumento/ala e nota final. Os 10 critérios detalhados permanecem na área do instrutor responsável.</p><div class="evaluation-scale-admin"><b>REGULAR <em>5</em></b><b>BOM <em>8</em></b><b>ÓTIMO <em>9</em></b><b>EXCELENTE <em>10</em></b></div></section>
<form class="panel evaluation-final-filter" method="get"><div class="grid4"><label>Ano<input type="number" name="year" min="2000" max="2100" value="<?=$year?>"></label><label class="span3">Buscar relatório<input name="q" value="<?=e($q)?>" placeholder="Instrutor, código, ala ou nome do teste"></label><button class="primary">FILTRAR</button></div></form>
<section class="panel"><div class="panel-heading-flex"><div><h2>Relatórios recebidos — <?=$year?></h2><p><?=count($tests)?> relatório(s) final(is) disponível(is).</p></div></div><div class="evaluation-final-report-list">
<?php foreach($tests as $t):$iname=trim((string)($t['preferred_name']??''))!==''?$t['preferred_name']:($t['full_name']??'');$sent=(string)($t['submitted_to_admin_at']??'');?><article><div class="eval-report-main"><span><?=e($t['areas']?:'ALA NÃO INFORMADA')?> · <?=e(date('d/m/Y',strtotime($t['test_date'])))?></span><h3><?=e($t['title'])?></h3><p><strong>Instrutor:</strong> <?=e($iname?:'REGISTRO LEGADO')?><?php if(!empty($t['instructor_code'])):?> · <?=e($t['instructor_code'])?><?php endif;?></p><small><?= (int)$t['students_count']?> aluno(s) avaliado(s)<?php if($sent!==''):?> · enviado em <?=e(date('d/m/Y H:i',strtotime($sent)))?><?php endif;?></small></div><div class="eval-report-kpi"><span>MÉDIA DO RELATÓRIO</span><strong><?= $t['class_average']!==null?e(evaluation_score_text((float)$t['class_average'])):'—'?></strong></div><a class="primary small-button" href="avaliacao_relatorio.php?test_id=<?=(int)$t['id']?>">ABRIR RELATÓRIO FINAL</a></article><?php endforeach;?>
<?php if(!$tests):?><div class="empty-cell">Nenhum relatório final foi enviado pelos instrutores neste período.</div><?php endif;?></div></section>
<?php admin_footer();?>
