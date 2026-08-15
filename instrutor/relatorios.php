<?php
require __DIR__.'/_layout.php';$u=require_instructor();$pdo=db();$areas=instructor_areas((int)$u['id']);
$rows=[];
if($areas){
 $marks=implode(',',array_fill(0,count($areas),'?'));
 $st=$pdo->prepare("SELECT DISTINCT ae.* FROM attendance_events ae JOIN attendance_event_areas aea ON aea.event_id=ae.id WHERE aea.area IN ($marks) ORDER BY ae.event_date DESC,ae.id DESC LIMIT 100");
 $st->execute($areas);$rows=$st->fetchAll();
}
instructor_header('Relatórios de Ensaios');?>
<section class="internal-event-info"><span>HISTÓRICO DO PROFESSOR</span><h2>Relatórios de ensaios</h2><p>Abra um ensaio para ver, por ala, quem marcou presença completa e quem não compareceu.</p></section>
<section class="professor-report-list"><?php foreach($rows as $r):$eventAreas=array_values(array_intersect($areas,attendance_event_areas((int)$r['id'])));?><article><div><span><?=e(date('d/m/Y',strtotime($r['event_date'])))?></span><h3><?=e($r['title'])?></h3><small><?=e(implode(' • ',$eventAreas))?></small></div><div><?php foreach($eventAreas as $a):?><a href="relatorio.php?event_id=<?=$r['id']?>&area=<?=urlencode($a)?>"><?=e($a)?></a><a href="relatorio.php?event_id=<?=$r['id']?>&area=<?=urlencode($a)?>&export=xls">EXCEL</a><?php endforeach;?></div></article><?php endforeach;?><?php if(!$rows):?><p>Nenhum relatório disponível ainda.</p><?php endif;?></section>
<?php instructor_footer();?>
