<?php
require __DIR__.'/layout.php';
$token=trim((string)($_GET['t']??''));
$st=db()->prepare("SELECT e.*,p.name period_name,p.start_date period_start,p.end_date period_end FROM enrollments e LEFT JOIN enrollment_periods p ON p.id=e.period_id WHERE e.public_token=?");
$st->execute([$token]);$r=$st->fetch();
if(!$r){http_response_code(404);site_header('Matrícula não encontrada');?><main id="conteudo-principal" class="content-page"><div class="empty-public">Matrícula não encontrada.</div></main><?php site_footer();exit;}
function mask_cpf(string $cpf): string {$d=normalize_cpf($cpf);return strlen($d)===11?'***.***.***-'.substr($d,-2):'';}
$logo=setting('logo_path','assets/brasao_bamab_2026.png');
site_header('Matrícula enviada');
?>
<main id="conteudo-principal" class="content-page receipt-page">
<section class="official-receipt">
  <div class="receipt-3d-frame"></div>
  <header class="receipt-head">
    <img src="<?=e($logo)?>" alt="Brasão BAMAB">
    <div><span>BANDA AMARAL BRASIL</span><h1>COMPROVANTE DE MATRÍCULA</h1><small><?=e($r['period_name']?:'Período de matrícula BAMAB')?></small></div>
  </header>
  <section class="receipt-thanks">
    <strong><?=e(setting('enrollment_thanks_title'))?></strong>
    <p><?=e(setting('enrollment_thanks_text'))?></p>
  </section>
  <div class="receipt-number"><small>NÚMERO DE MATRÍCULA</small><strong><?=e($r['registration_number']?:$r['protocol'])?></strong><span>Protocolo <?=e($r['protocol'])?> · <?=e(date('d/m/Y H:i',strtotime($r['created_at'])))?></span></div>
  <div class="receipt-grid">
    <div><small>Nome do aluno</small><strong><?=e($r['student_name'])?></strong></div>
    <div><small>Nome social / apelido autorizado</small><strong><?=e($r['preferred_name']?:'—')?></strong></div>
    <div><small>Instrumento / ala</small><strong><?=e($r['instrument'])?></strong></div>
    <div><small>Status</small><strong><?=e(enrollment_status_label($r['status']))?></strong></div>
    <div><small>Data de nascimento</small><strong><?=e(date('d/m/Y',strtotime($r['birth_date'])))?></strong></div>
    <div><small>Período</small><strong><?php if($r['period_start']):?><?=e(date('d/m/Y',strtotime($r['period_start'])))?> a <?=e(date('d/m/Y',strtotime($r['period_end'])))?><?php else:?>—<?php endif;?></strong></div>
    <?php if((int)$r['is_minor']===1):?><div><small>Responsável legal</small><strong><?=e($r['guardian_name'])?></strong></div><div><small>CPF do responsável</small><strong><?=e(mask_cpf($r['guardian_cpf']))?></strong></div><?php endif;?>
    <div><small>Situação escolar</small><strong><?=(int)$r['currently_studying']===1?e(school_network_label($r['school_network'])):'NÃO ESTUDA ATUALMENTE'?></strong></div>
    <div><small>Situação profissional</small><strong><?=(int)$r['works_currently']===1?'TRABALHA ATUALMENTE':'NÃO TRABALHA ATUALMENTE'?></strong></div>
  </div>
  <section class="receipt-terms">
    <h2>Registros da inscrição</h2>
    <div><span>✓</span> Matrícula <b>APROVADA AUTOMATICAMENTE</b> e disponível para conferência da Administração BAMAB.</div>
    <div><span>✓</span> Participação autorizada e termos registrados.</div>
    <div><span>✓</span> Instrumentos, patrimônio, uniforme e camisas: ciência/compromisso registrados.</div>
    <div><span>✓</span> Uso de imagem e voz: <b><?=(int)$r['image_authorization']===1?'AUTORIZADO':'NÃO AUTORIZADO'?></b>.</div>
    <div><span>✓</span> Regra de respeito ao nome/apelido escolhida e registrada.</div>
    <?php if((int)$r['currently_studying']===1):?><div><span>✓</span> Declaração de compromisso e participação cultural para escola: <b>PREPARADA NO SISTEMA</b>.</div><?php endif;?>
    <?php if((int)$r['works_currently']===1&&(int)$r['needs_work_declaration']===1):?><div><span>✓</span> Declaração de participação para apresentação no trabalho: <b>PREPARADA NO SISTEMA</b>.</div><?php endif;?>
  </section>
  <footer class="receipt-footer">
    <div><span>DISCIPLINA</span><b>★</b><span>RESPEITO</span><b>★</b><span>UNIÃO</span><b>★</b><span>EXCELÊNCIA</span></div>
    <p>Obrigado por fazer parte da nossa história.</p>
  </footer>
</section>
<div class="confirmation-actions no-print"><button onclick="window.print()" class="print-btn">IMPRIMIR / SALVAR COMPROVANTE</button><a class="outline-btn" href="index.php">VOLTAR AO SITE</a></div>
</main>
<style>
.receipt-page{max-width:1050px}.official-receipt{position:relative;background:linear-gradient(135deg,#fffdf8,#f2eee5);color:#241b13;border:4px solid #111;border-radius:22px;padding:30px;overflow:hidden;box-shadow:0 18px 55px #0003}.official-receipt:before{content:"";position:absolute;inset:8px;border:2px solid #c79a35;border-radius:15px;pointer-events:none}.official-receipt:after{content:"";position:absolute;inset:0;background-image:url("<?=e($logo)?>");background-repeat:no-repeat;background-position:center 55%;background-size:54% auto;opacity:.055;pointer-events:none}.official-receipt>*{position:relative;z-index:1}.receipt-3d-frame{position:absolute;inset:0;background:linear-gradient(140deg,transparent 0 10%,#7d102015 10% 12%,transparent 12% 84%,#c99a3414 84%);pointer-events:none}.receipt-head{position:relative;display:flex;align-items:center;gap:24px;padding:5px 10px 22px;border-bottom:3px double #c99a34}.receipt-head img{width:150px;height:150px;object-fit:contain;filter:drop-shadow(0 10px 8px #0005)}.receipt-head span{color:#7d1020;font-weight:900;letter-spacing:.18em;font-size:12px}.receipt-head h1{font:700 42px Georgia,serif;color:#111;margin:5px 0;text-shadow:0 1px 0 #d7b45e}.receipt-head small{color:#735b32;font-weight:700}.receipt-thanks{position:relative;margin:22px auto;padding:18px 24px;max-width:800px;text-align:center;border:1px solid #c8a458;border-radius:14px;background:#fff9}.receipt-thanks strong{display:block;color:#7d1020;font:700 23px Georgia,serif}.receipt-thanks p{font-size:15px;line-height:1.65;margin-bottom:0}.receipt-number{position:relative;text-align:center;background:linear-gradient(#171717,#050505);border:3px solid #d2a640;border-radius:13px;color:#fff;padding:14px;margin:18px 0;box-shadow:inset 0 0 0 1px #654a18,0 6px 12px #0003}.receipt-number small,.receipt-number span{display:block}.receipt-number small{color:#d5b15c;font-weight:900}.receipt-number strong{display:block;color:#fff;font-size:30px;letter-spacing:.06em;margin:4px}.receipt-number span{font-size:11px;color:#ccc}.receipt-grid{position:relative;display:grid;grid-template-columns:1fr 1fr;gap:12px}.receipt-grid>div{background:#fff;border:1px solid #d5bd85;border-radius:10px;padding:12px}.receipt-grid small,.receipt-grid strong{display:block}.receipt-grid small{text-transform:uppercase;color:#8a6b2c;font-size:10px;font-weight:900}.receipt-grid strong{font-size:15px;margin-top:5px}.receipt-terms{position:relative;margin-top:18px;border:1px solid #d5bd85;border-radius:12px;padding:16px;background:#fff}.receipt-terms h2{margin:0 0 10px;color:#7d1020;font-size:17px}.receipt-terms div{padding:5px 0;font-size:13px}.receipt-terms span{color:#9b7629;font-weight:900}.receipt-footer{position:relative;text-align:center;margin:22px -30px -30px;padding:15px 20px;background:linear-gradient(90deg,#080808,#25180e,#080808);border-top:3px solid #c99a34;color:#d9b65f}.receipt-footer>div{display:flex;justify-content:center;gap:13px;flex-wrap:wrap;font-size:11px;font-weight:900;letter-spacing:.12em}.receipt-footer p{margin:9px 0 0;color:#eee;font:italic 15px Georgia,serif}@media(max-width:650px){.receipt-head{flex-direction:column;text-align:center}.receipt-grid{grid-template-columns:1fr}.receipt-head h1{font-size:30px}}@media print{@page{size:A4 portrait;margin:0}.site-header,.site-footer,.motto-strip,.no-print{display:none!important}html,body{width:210mm!important;margin:0!important;padding:0!important;background:#fff!important}.content-page{width:210mm!important;max-width:210mm!important;margin:0!important;padding:10mm!important}.official-receipt{width:190mm!important;min-height:277mm!important;margin:0!important;padding:10mm!important;box-shadow:none!important;border-radius:0!important;break-inside:avoid}.official-receipt:after{position:fixed!important;left:48mm!important;top:82mm!important;width:114mm!important;height:114mm!important;background-size:contain!important}.receipt-head img{width:32mm!important;height:32mm!important}.receipt-head h1{font-size:24pt!important}.receipt-page{margin:0!important}}
</style>
<?php site_footer(); ?>
