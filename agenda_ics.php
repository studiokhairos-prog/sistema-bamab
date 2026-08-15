<?php
require __DIR__.'/config.php';
$id=(int)($_GET['id']??0);
$st=db()->prepare("SELECT * FROM agenda WHERE id=? AND published=1");$st->execute([$id]);$r=$st->fetch();
if(!$r){http_response_code(404);exit('Compromisso não encontrado.');}
function ics_escape(string $v): string {return str_replace(["\\",";",",","\r","\n"],["\\\\","\\;","\\,",'',"\\n"],$v);}
$date=preg_replace('/[^0-9]/','',(string)$r['event_date']);
$start=trim((string)$r['event_time']);$end=trim((string)$r['end_time']);
$lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//BAMAB//Agenda Interativa//PT-BR','CALSCALE:GREGORIAN','METHOD:PUBLISH','BEGIN:VEVENT'];
$lines[]='UID:bamab-agenda-'.$r['id'].'@bamab.local';
$lines[]='DTSTAMP:'.gmdate('Ymd\\THis\\Z');
if($start!==''){
    $lines[]='DTSTART;TZID=America/Sao_Paulo:'.$date.'T'.str_replace(':','',$start).'00';
    if($end!=='')$lines[]='DTEND;TZID=America/Sao_Paulo:'.$date.'T'.str_replace(':','',$end).'00';
}else{
    $lines[]='DTSTART;VALUE=DATE:'.$date;
    $tomorrow=date('Ymd',strtotime($r['event_date'].' +1 day'));$lines[]='DTEND;VALUE=DATE:'.$tomorrow;
}
$lines[]='SUMMARY:'.ics_escape((string)$r['title']);
if(trim((string)$r['location'])!=='')$lines[]='LOCATION:'.ics_escape((string)$r['location']);
$desc=trim((string)$r['description']);if(trim((string)$r['area'])!=='')$desc.=($desc!==''?"\n":'').'Ala / responsável: '.$r['area'];if(trim((string)$r['event_type'])!=='')$desc.=($desc!==''?"\n":'').'Tipo: '.$r['event_type'];
if($desc!=='')$lines[]='DESCRIPTION:'.ics_escape($desc);
$lines[]='END:VEVENT';$lines[]='END:VCALENDAR';
header('Content-Type: text/calendar; charset=UTF-8');header('Content-Disposition: attachment; filename="BAMAB_'.preg_replace('/[^A-Za-z0-9_-]+/','_',iconv('UTF-8','ASCII//TRANSLIT',(string)$r['title'])?:'evento').'.ics"');
echo implode("\r\n",$lines)."\r\n";
