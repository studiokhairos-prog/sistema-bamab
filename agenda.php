<?php
require __DIR__.'/layout.php';
$pdo=db();
$today=date('Y-m-d');
$rows=$pdo->query("SELECT * FROM agenda WHERE published=1 ORDER BY event_date ASC, CASE WHEN event_time='' THEN '23:59' ELSE event_time END ASC, id ASC")->fetchAll();
$upcoming=array_values(array_filter($rows,fn($r)=>($r['event_date']??'') >= $today));
$next=$upcoming[0]??null;
$types=[];foreach($upcoming as $r){$tp=trim((string)($r['event_type']??''));if($tp!=='')$types[$tp]=true;}
$monthParam=(string)($_GET['month']??'');
if(!preg_match('/^\\d{4}-\\d{2}$/',$monthParam)) $monthParam=$next?substr((string)$next['event_date'],0,7):date('Y-m');
$monthStart=DateTime::createFromFormat('!Y-m-d',$monthParam.'-01')?:new DateTime('first day of this month');
$prev=(clone $monthStart)->modify('-1 month')->format('Y-m');$nextMonth=(clone $monthStart)->modify('+1 month')->format('Y-m');
$monthNames=[1=>'JANEIRO',2=>'FEVEREIRO',3=>'MARÇO',4=>'ABRIL',5=>'MAIO',6=>'JUNHO',7=>'JULHO',8=>'AGOSTO',9=>'SETEMBRO',10=>'OUTUBRO',11=>'NOVEMBRO',12=>'DEZEMBRO'];
$weekdayNames=[1=>'SEGUNDA',2=>'TERÇA',3=>'QUARTA',4=>'QUINTA',5=>'SEXTA',6=>'SÁBADO',7=>'DOMINGO'];
$weekdayLong=[1=>'SEGUNDA-FEIRA',2=>'TERÇA-FEIRA',3=>'QUARTA-FEIRA',4=>'QUINTA-FEIRA',5=>'SEXTA-FEIRA',6=>'SÁBADO',7=>'DOMINGO'];
$eventsByDate=[];foreach($rows as $r)$eventsByDate[$r['event_date']][]=$r;
$upcomingByDate=[];foreach($upcoming as $r)$upcomingByDate[$r['event_date']][]=$r;
function agenda_type_class(string $type): string {return 'type-'.preg_replace('/[^a-z0-9]+/','-',strtolower(iconv('UTF-8','ASCII//TRANSLIT',$type)?:$type));}
function agenda_type_icon(string $type): string {$u=mb_strtoupper($type);if(str_contains($u,'REUNI'))return '♟';if(str_contains($u,'CAMPEON'))return '★';if(str_contains($u,'APRESENT'))return '✦';if(str_contains($u,'ENSAIO'))return '♫';return '◆';}
function agenda_event_timestamp(array $r): int {
    $time=trim((string)($r['event_time']??''));
    $value=(string)$r['event_date'].' '.($time!==''?$time.':00':'00:00:00');
    $ts=strtotime($value); return $ts!==false?$ts:0;
}
function agenda_relative_label(string $date,string $today): string {
    $a=new DateTime($today);$b=new DateTime($date);$days=(int)$a->diff($b)->format('%r%a');
    if($days===0)return 'HOJE';if($days===1)return 'AMANHÃ';if($days>1)return 'EM '.$days.' DIAS';return 'REALIZADO';
}
$todayCount=count($upcomingByDate[$today]??[]);
$weekEnd=date('Y-m-d',strtotime('+6 days'));
$weekCount=count(array_filter($upcoming,fn($r)=>$r['event_date']>=$today&&$r['event_date']<=$weekEnd));
$currentMonth=date('Y-m');
$monthCount=count(array_filter($upcoming,fn($r)=>substr((string)$r['event_date'],0,7)===$currentMonth));
site_header('Agenda');
?>
<main id="conteudo-principal" class="agenda-interactive-page agenda-pro-page">
 <section class="agenda-hero-official agenda-pro-hero">
  <div class="agenda-hero-inner"><span>COMPROMISSOS OFICIAIS · TEMPO REAL</span><h1>AGENDA INTERATIVA BAMAB</h1><p>Ensaios, reuniões, apresentações, campeonatos e compromissos da Banda Amaral Brasil.</p></div>
  <div class="agenda-live-clock" aria-live="polite" data-server-now="<?= (int) round(microtime(true) * 1000) ?>"><div class="clock-crest"><img src="<?=e(setting('logo_path','assets/brasao_bamab_2026.png'))?>" alt="Brasão BAMAB"></div><div><span>HORÁRIO DE BRASÍLIA</span><strong id="agendaClock">--:--:--</strong><small id="agendaClockDate">Sincronizando data...</small><em id="agendaClockSync" class="clock-sync-status">● SINCRONIZANDO...</em></div></div>
 </section>

 <section class="agenda-stats-strip">
  <div><span>HOJE</span><strong><?=$todayCount?></strong><small>compromisso(s)</small></div>
  <div><span>PRÓXIMOS 7 DIAS</span><strong><?=$weekCount?></strong><small>compromisso(s)</small></div>
  <div><span>ESTE MÊS</span><strong><?=$monthCount?></strong><small>compromisso(s)</small></div>
  <div><span>PRÓXIMOS</span><strong><?=count($upcoming)?></strong><small>na agenda</small></div>
 </section>

 <div class="agenda-interactive-shell">
  <aside class="agenda-side">
   <?php if($next):$nd=new DateTime($next['event_date']);$nextTs=agenda_event_timestamp($next);?>
   <section class="agenda-next-mini agenda-next-pro"><div class="agenda-mini-label">PRÓXIMO COMPROMISSO</div><img src="<?=e(setting('logo_path','assets/brasao_bamab_2026.png'))?>" alt="Brasão BAMAB"><div class="agenda-relative-chip"><?=e(agenda_relative_label($next['event_date'],$today))?></div><h2><?=e($next['title'])?></h2><strong><?=e($weekdayLong[(int)$nd->format('N')])?> · <?=e($nd->format('d/m/Y'))?></strong><span>◷ <?=e($next['event_time']?:'Horário a definir')?><?= $next['end_time']?' às '.e($next['end_time']):''?></span><span>⌖ <?=e($next['location']?:'Local a definir')?></span><a href="#evento-<?=$next['id']?>">VER DETALHES →</a></section>
   <section class="agenda-countdown-panel" data-countdown-panel data-target="<?=$nextTs*1000?>" data-has-time="<?=$next['event_time']!==''?'1':'0'?>"><span>CONTAGEM REGRESSIVA</span><div class="countdown-grid"><div><strong data-cd-days>--</strong><small>DIAS</small></div><div><strong data-cd-hours>--</strong><small>HORAS</small></div><div><strong data-cd-minutes>--</strong><small>MIN</small></div><div><strong data-cd-seconds>--</strong><small>SEG</small></div></div><p data-cd-message>Calculando próximo compromisso...</p></section>
   <?php endif;?>

   <section class="agenda-calendar agenda-calendar-pro"><div class="calendar-title"><a href="?month=<?=e($prev)?>" aria-label="Mês anterior">‹</a><strong><?=e($monthNames[(int)$monthStart->format('n')].' '.$monthStart->format('Y'))?></strong><a href="?month=<?=e($nextMonth)?>" aria-label="Próximo mês">›</a></div><div class="calendar-week"><b>SEG</b><b>TER</b><b>QUA</b><b>QUI</b><b>SEX</b><b>SÁB</b><b>DOM</b></div><div class="calendar-days">
   <?php $firstDow=(int)$monthStart->format('N');for($i=1;$i<$firstDow;$i++):?><span class="calendar-empty"></span><?php endfor;?>
   <?php $days=(int)$monthStart->format('t');for($d=1;$d<=$days;$d++):$ds=$monthStart->format('Y-m-').str_pad((string)$d,2,'0',STR_PAD_LEFT);$has=!empty($eventsByDate[$ds]);$hasUpcoming=!empty($upcomingByDate[$ds]);$isToday=$ds===$today;$isPast=$ds<$today;?>
      <?php if($hasUpcoming):?><button type="button" class="<?=$has?'has-event ':''?><?=$isToday?'is-today ':''?>" data-calendar-date="<?=e($ds)?>" title="Filtrar eventos de <?=e(date('d/m/Y',strtotime($ds)))?>"><span><?=$d?></span><i><?=count($upcomingByDate[$ds])?></i></button><?php else:?><span class="calendar-static <?=$has?'has-event ':''?><?=$isToday?'is-today ':''?><?=$isPast?'is-past':''?>"><span><?=$d?></span><?php if($has):?><i><?=count($eventsByDate[$ds])?></i><?php endif;?></span><?php endif;?>
   <?php endfor;?></div><div class="calendar-legend"><span><i class="today-dot"></i> Hoje</span><span><i class="event-dot"></i> Com evento</span></div></section>

   <section class="agenda-side-actions"><button type="button" onclick="window.print()">▤ IMPRIMIR AGENDA</button><a href="agenda.php?month=<?=e(date('Y-m'))?>">◎ VOLTAR AO MÊS ATUAL</a></section>
  </aside>

  <section class="agenda-main-column">
   <div class="agenda-controls agenda-controls-pro"><button class="active" data-agenda-filter="ALL">TODOS</button><button data-agenda-range="TODAY">HOJE</button><button data-agenda-range="WEEK">7 DIAS</button><button data-agenda-range="MONTH">ESTE MÊS</button><?php foreach(array_keys($types) as $tp):?><button data-agenda-filter="<?=e($tp)?>"><?=e(agenda_type_icon($tp).' '.$tp)?></button><?php endforeach;?></div>
   <div class="agenda-selected-date" id="agendaSelectedDate" hidden><span>DATA SELECIONADA: <strong id="agendaSelectedDateText"></strong></span><button type="button" id="agendaClearDate">LIMPAR FILTRO</button></div>

   <?php if($next):$d=new DateTime($next['event_date']);?>
   <article class="agenda-featured-card agenda-featured-pro <?=e(agenda_type_class($next['event_type']))?>" id="evento-<?=$next['id']?>" data-agenda-card data-date="<?=e($next['event_date'])?>" data-type="<?=e($next['event_type'])?>" data-event-ts="<?=agenda_event_timestamp($next)*1000?>"><div class="agenda-featured-ribbon">PRÓXIMO EVENTO</div><?php if((int)($next['featured']??0)===1):?><div class="agenda-featured-star">★ DESTAQUE</div><?php endif;?><div class="agenda-featured-logo"><img src="<?=e(setting('logo_path','assets/brasao_bamab_2026.png'))?>" alt="Brasão BAMAB"></div><div class="agenda-featured-date"><strong><?=e($d->format('d'))?></strong><span><?=e($weekdayNames[(int)$d->format('N')])?></span><small><?=e($monthNames[(int)$d->format('n')].' · '.$d->format('Y'))?></small></div><div class="agenda-featured-details"><div class="agenda-card-badges"><span class="agenda-event-type"><?=e(agenda_type_icon($next['event_type']))?> <?=e($next['event_type'])?></span><span class="agenda-status-chip"><?=e(agenda_relative_label($next['event_date'],$today))?></span></div><h2><?=e($next['title'])?></h2><p><b>◷</b> <?=e($next['event_time']?:'Horário a definir')?><?= $next['end_time']?' às '.e($next['end_time']):''?></p><p><b>⌖</b> <?=e($next['location']?:'Local a definir')?></p><?php if($next['area']):?><p><b>♟</b> <?=e($next['area'])?></p><?php endif;?><?php if($next['description']):?><div class="agenda-featured-description"><?=nl2br(e($next['description']))?></div><?php endif;?><div class="agenda-card-actions"><a href="agenda_ics.php?id=<?=$next['id']?>">＋ ADICIONAR AO CALENDÁRIO</a><button type="button" data-share-event data-share-title="<?=e($next['title'])?>" data-share-date="<?=e($d->format('d/m/Y'))?>">↗ COMPARTILHAR</button></div></div></article>
   <?php endif;?>

   <?php if($todayCount>0):?><div class="agenda-today-alert"><span>● ACONTECE HOJE</span><strong><?=$todayCount?> compromisso(s) programado(s) para hoje.</strong><button type="button" data-jump-today>VER EVENTOS DE HOJE</button></div><?php endif;?>

   <div class="agenda-section-title"><span>▣</span><h2>PRÓXIMOS COMPROMISSOS</h2><small>Agenda atualizada automaticamente pela coordenação.</small></div>
   <div class="agenda-art-grid" id="agendaCards">
    <?php foreach($upcoming as $r): if($next && (int)$r['id']===(int)$next['id']) continue;$d=new DateTime($r['event_date']);?>
    <article class="agenda-art-card agenda-art-pro <?=e(agenda_type_class($r['event_type']))?>" id="evento-<?=$r['id']?>" data-agenda-card data-date="<?=e($r['event_date'])?>" data-type="<?=e($r['event_type'])?>" data-event-ts="<?=agenda_event_timestamp($r)*1000?>"><div class="agenda-art-top"><img src="<?=e(setting('logo_path','assets/brasao_bamab_2026.png'))?>" alt="Brasão"><span><?=e($r['event_type']?:'EVENTO')?></span><?php if((int)($r['featured']??0)===1):?><b>★</b><?php endif;?></div><div class="agenda-art-body"><div class="agenda-date-block"><small><?=e($weekdayNames[(int)$d->format('N')])?></small><strong><?=e($d->format('d'))?></strong><b><?=e($monthNames[(int)$d->format('n')])?></b><em><?=e($d->format('Y'))?></em><mark><?=e(agenda_relative_label($r['event_date'],$today))?></mark></div><div class="agenda-card-info"><h3><?=e($r['title'])?></h3><p>◷ <?=e($r['event_time']?:'Horário a definir')?><?= $r['end_time']?' às '.e($r['end_time']):''?></p><p>⌖ <?=e($r['location']?:'Local a definir')?></p><?php if($r['area']):?><p>♟ <?=e($r['area'])?></p><?php endif;?><div><?=nl2br(e($r['description']))?></div><div class="agenda-card-actions compact"><a href="agenda_ics.php?id=<?=$r['id']?>">＋ CALENDÁRIO</a><button type="button" data-share-event data-share-title="<?=e($r['title'])?>" data-share-date="<?=e($d->format('d/m/Y'))?>">↗ COMPARTILHAR</button></div></div></div><footer><span><?=e(setting('site_subtitle','BANDA AMARAL BRASIL'))?></span><i><?=e(agenda_type_icon($r['event_type']))?></i></footer></article>
    <?php endforeach;?>
   </div>
   <?php if(!$upcoming):?><div class="agenda-empty"><img src="<?=e(setting('logo_path','assets/brasao_bamab_2026.png'))?>" alt="Brasão"><h2>Nenhum compromisso publicado</h2><p>A agenda será atualizada assim que a coordenação publicar um novo evento.</p></div><?php endif;?>
   <div class="agenda-no-filter" id="agendaNoFilter" hidden>Nenhum compromisso encontrado neste filtro.</div>
  </section>
 </div>
</main>
<script>
(()=>{
 const cards=[...document.querySelectorAll('[data-agenda-card]')],buttons=[...document.querySelectorAll('[data-agenda-filter],[data-agenda-range]')],empty=document.getElementById('agendaNoFilter');
 const today='<?=date('Y-m-d')?>';let calendarDate='';
 const selectedBox=document.getElementById('agendaSelectedDate'),selectedText=document.getElementById('agendaSelectedDateText');
 function isoAddDays(date,days){const d=new Date(date+'T12:00:00');d.setDate(d.getDate()+days);return d.toISOString().slice(0,10)}
 function brDate(iso){const [y,m,d]=iso.split('-');return d+'/'+m+'/'+y}
 function filterCards({type='ALL',range='',date=''}){let shown=0;cards.forEach(c=>{let ok=true;if(type&&type!=='ALL')ok=c.dataset.type===type;if(range==='TODAY')ok=ok&&c.dataset.date===today;if(range==='WEEK')ok=ok&&c.dataset.date>=today&&c.dataset.date<=isoAddDays(today,6);if(range==='MONTH')ok=ok&&c.dataset.date.slice(0,7)===today.slice(0,7);if(date)ok=ok&&c.dataset.date===date;c.hidden=!ok;if(ok)shown++;});if(empty)empty.hidden=shown>0;}
 function apply(btn){calendarDate='';if(selectedBox)selectedBox.hidden=true;buttons.forEach(b=>b.classList.remove('active'));btn.classList.add('active');filterCards({type:btn.dataset.agendaFilter||'ALL',range:btn.dataset.agendaRange||''});}
 buttons.forEach(b=>b.addEventListener('click',()=>apply(b)));
 document.querySelectorAll('[data-calendar-date]').forEach(b=>b.addEventListener('click',()=>{calendarDate=b.dataset.calendarDate;buttons.forEach(x=>x.classList.remove('active'));filterCards({date:calendarDate});if(selectedText)selectedText.textContent=brDate(calendarDate);if(selectedBox)selectedBox.hidden=false;document.querySelector('.agenda-main-column')?.scrollIntoView({behavior:'smooth',block:'start'});}));
 document.getElementById('agendaClearDate')?.addEventListener('click',()=>{calendarDate='';if(selectedBox)selectedBox.hidden=true;const all=document.querySelector('[data-agenda-filter="ALL"]');if(all)apply(all);});
 document.querySelector('[data-jump-today]')?.addEventListener('click',()=>{const b=document.querySelector('[data-agenda-range="TODAY"]');if(b){apply(b);document.querySelector('.agenda-main-column')?.scrollIntoView({behavior:'smooth',block:'start'});}});

 const clockBox=document.querySelector('.agenda-live-clock'),clock=document.getElementById('agendaClock'),clockDate=document.getElementById('agendaClockDate'),clockSync=document.getElementById('agendaClockSync');
 let serverEpochMs=Number(clockBox?.dataset.serverNow||Date.now()),syncPerf=performance.now(),syncOk=false;
 function officialNowMs(){return serverEpochMs+(performance.now()-syncPerf);}
 function brasiliaParts(ms=officialNowMs()){const parts=new Intl.DateTimeFormat('pt-BR',{timeZone:'America/Sao_Paulo',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false,weekday:'long'}).formatToParts(new Date(ms));const o={};parts.forEach(p=>o[p.type]=p.value);return o;}
 function updateClock(){const p=brasiliaParts();if(clock)clock.textContent=`${p.hour}:${p.minute}:${p.second}`;if(clockDate)clockDate.textContent=`${p.weekday}, ${p.day}/${p.month}/${p.year}`;}
 async function syncOfficialClock(){try{const started=performance.now();const res=await fetch('agenda_hora.php?_='+Date.now(),{cache:'no-store',headers:{'Accept':'application/json'}});if(!res.ok)throw new Error('HTTP '+res.status);const data=await res.json();const ended=performance.now();if(!data||!Number.isFinite(Number(data.timestamp_ms)))throw new Error('Resposta inválida');const halfTrip=(ended-started)/2;serverEpochMs=Number(data.timestamp_ms)+halfTrip;syncPerf=ended;syncOk=true;if(clockSync){clockSync.textContent='● SINCRONIZADO · BRASÍLIA';clockSync.classList.add('is-synced');clockSync.classList.remove('is-offline');}}catch(err){if(clockSync){clockSync.textContent=syncOk?'● ÚLTIMA SINCRONIZAÇÃO MANTIDA':'● HORA DO SERVIDOR';clockSync.classList.toggle('is-offline',!syncOk);}}}
 updateClock();syncOfficialClock();setInterval(updateClock,1000);setInterval(syncOfficialClock,60000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)syncOfficialClock();});

 const cd=document.querySelector('[data-countdown-panel]');
 function updateCountdown(){if(!cd)return;const target=Number(cd.dataset.target||0);const hasTime=cd.dataset.hasTime==='1';let diff=target-officialNowMs();const msg=cd.querySelector('[data-cd-message]');if(diff<=0){cd.querySelector('[data-cd-days]').textContent='00';cd.querySelector('[data-cd-hours]').textContent='00';cd.querySelector('[data-cd-minutes]').textContent='00';cd.querySelector('[data-cd-seconds]').textContent='00';if(msg)msg.textContent='O compromisso já chegou. Confira os detalhes acima.';return;}const days=Math.floor(diff/86400000);diff%=86400000;const hours=Math.floor(diff/3600000);diff%=3600000;const minutes=Math.floor(diff/60000);diff%=60000;const seconds=Math.floor(diff/1000);cd.querySelector('[data-cd-days]').textContent=String(days).padStart(2,'0');cd.querySelector('[data-cd-hours]').textContent=String(hours).padStart(2,'0');cd.querySelector('[data-cd-minutes]').textContent=String(minutes).padStart(2,'0');cd.querySelector('[data-cd-seconds]').textContent=String(seconds).padStart(2,'0');if(msg)msg.textContent=hasTime?'Tempo restante para o início do compromisso.':'Data programada; horário ainda não definido.';}
 updateCountdown();setInterval(updateCountdown,1000);

 document.querySelectorAll('[data-share-event]').forEach(btn=>btn.addEventListener('click',async()=>{const title=btn.dataset.shareTitle||'Evento BAMAB',date=btn.dataset.shareDate||'';const text=`${title} — ${date} · Agenda BAMAB`;try{if(navigator.share){await navigator.share({title:'Agenda BAMAB',text,url:location.href});}else{await navigator.clipboard.writeText(text+' '+location.href);const old=btn.textContent;btn.textContent='✓ LINK COPIADO';setTimeout(()=>btn.textContent=old,1800);}}catch(e){}}));
})();
</script>
<?php site_footer();?>
