<?php
require __DIR__.'/_layout.php';
require_admin();
require_once __DIR__.'/../qr_lib.php';

function bamab_lan_ip_candidates(): array {
    $raw=[];
    foreach([$_SERVER['SERVER_ADDR']??'', gethostbyname(gethostname())] as $v){
        $v=trim((string)$v);
        if($v!=='' && filter_var($v,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) $raw[]=$v;
    }
    $host=(string)($_SERVER['HTTP_HOST']??'');
    $hostOnly=preg_replace('/:\d+$/','',$host);
    if($hostOnly && filter_var($hostOnly,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) $raw[]=$hostOnly;
    $raw=array_values(array_unique($raw));
    usort($raw,static function($a,$b){
        $score=static function($ip){
            if(str_starts_with($ip,'127.')) return 9;
            if(str_starts_with($ip,'192.168.')||str_starts_with($ip,'10.')||preg_match('/^172\.(1[6-9]|2\d|3[01])\./',$ip)) return 0;
            return 5;
        };
        return $score($a)<=>$score($b);
    });
    return $raw;
}
function bamab_base_path(): string {
    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/BAMAB/admin/teste_dispositivos.php'));
    $adminPos=strrpos($script,'/admin/');
    $base=$adminPos!==false?substr($script,0,$adminPos+1):'/';
    return $base===''?'/':$base;
}
$base=bamab_base_path();
$port=(int)($_SERVER['SERVER_PORT']??80);
$portSuffix=($port!==80 && $port!==443)?':'.$port:'';
$candidates=bamab_lan_ip_candidates();
$lanIp='';
foreach($candidates as $ip){if(!str_starts_with($ip,'127.')){$lanIp=$ip;break;}}
$phoneUrl=$lanIp!==''?'http://'.$lanIp.$portSuffix.$base:'';
$localUrl='http://localhost'.$portSuffix.$base;
$qr=$phoneUrl!==''?bamab_qr_svg($phoneUrl,4,4):'';
admin_header('Teste em Celulares e Tablets','device-test-page');
?>
<section class="panel device-network-card">
  <div class="panel-heading-flex"><div><span>TESTE RESPONSIVO BAMAB</span><h2>Abra o site em celulares, tablets e outros computadores</h2><p>O computador com XAMPP e o aparelho de teste precisam estar conectados à <strong>mesma rede Wi‑Fi ou rede local</strong>.</p></div><a class="small-button" href="../index.php" target="_blank" rel="noopener noreferrer">ABRIR SITE NO COMPUTADOR</a></div>
  <?php if($phoneUrl):?>
  <div class="device-test-grid">
    <div><h3>LINK PARA O CELULAR</h3><p class="device-url" id="deviceLanUrl"><?=e($phoneUrl)?></p><div class="agenda-form-actions"><button class="small-button" type="button" id="copyLanUrl">COPIAR LINK</button><a class="small-button" href="<?=e($phoneUrl)?>" target="_blank" rel="noopener noreferrer">TESTAR LINK</a></div><p><small>Digite esse endereço no navegador do celular ou escaneie o QR Code.</small></p></div>
    <div class="device-qr" aria-label="QR Code do link local"><?=$qr?></div>
  </div>
  <?php else:?>
  <div class="alert error"><strong>Não consegui identificar automaticamente o IP da rede local.</strong><br>No Windows, abra o Prompt de Comando e execute <code>ipconfig</code>. Procure por <strong>Endereço IPv4</strong> da rede Wi‑Fi e abra no celular: <code>http://IP_DO_COMPUTADOR<?=e($base)?></code>.</div>
  <?php endif;?>
  <div class="device-local-note"><strong>Endereço local deste computador:</strong> <code><?=e($localUrl)?></code></div>
</section>

<section class="panel">
  <div class="panel-heading-flex"><div><span>SIMULADOR DE TELA</span><h2>Prévia rápida sem sair do Admin</h2><p>Use os tamanhos abaixo para verificar a página principal. O teste real no aparelho continua sendo o mais confiável.</p></div></div>
  <div class="device-size-buttons" role="group" aria-label="Larguras de teste">
    <button type="button" data-device-width="360">360 px</button><button type="button" data-device-width="390">390 px</button><button type="button" data-device-width="430">430 px</button><button type="button" data-device-width="768">Tablet</button><button type="button" data-device-width="1024">1024 px</button><button type="button" data-device-width="100%">Largura total</button>
  </div>
  <div class="device-frame-stage"><div class="device-frame-shell" id="deviceFrameShell" style="--device-width:390px"><iframe title="Prévia responsiva da página principal" src="../index.php" loading="lazy"></iframe></div></div>
</section>

<section class="panel">
  <h2>Checklist para o teste final</h2>
  <div class="records device-check-list"><article><div><span>1</span><h3>Página principal</h3><p>Confira menu, Agenda, patrocinadores, Prefeitura/Secretarias, notícias, imagens e rodapé.</p></div></article><article><div><span>2</span><h3>Matrícula</h3><p>Teste campos, câmera/foto, responsável de menor, termos e confirmação.</p></div></article><article><div><span>3</span><h3>Área do Instrutor</h3><p>Teste login, frequência, relatórios e leitura manual do número/QR.</p></div></article><article><div><span>4</span><h3>Impressão</h3><p>Carteirinhas e crachás devem ser impressos em escala 100%. Relatórios e fichas usam A4.</p></div></article></div>
  <div class="alert"><strong>Atenção ao leitor de câmera do QR:</strong> navegadores modernos podem bloquear a câmera em páginas HTTP abertas por IP local. O restante do site funciona pela rede local; para testar a câmera do QR no celular de forma completa, use uma publicação com <strong>HTTPS</strong> ou mantenha o lançamento manual disponível no sistema.</div>
</section>
<script>
(()=>{
  const shell=document.getElementById('deviceFrameShell');
  document.querySelectorAll('[data-device-width]').forEach(btn=>btn.addEventListener('click',()=>{
    const w=btn.dataset.deviceWidth;
    shell.style.setProperty('--device-width',w==='100%'?'100%':w+'px');
    document.querySelectorAll('[data-device-width]').forEach(b=>b.classList.toggle('is-active',b===btn));
  }));
  const copy=document.getElementById('copyLanUrl'), url=document.getElementById('deviceLanUrl');
  if(copy&&url) copy.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(url.textContent.trim());copy.textContent='LINK COPIADO';setTimeout(()=>copy.textContent='COPIAR LINK',1800);}catch(e){window.prompt('Copie o link:',url.textContent.trim());}});
})();
</script>
<?php admin_footer();?>
