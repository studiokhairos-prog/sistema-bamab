<?php
require __DIR__.'/_layout.php';
$u=require_admin();
if(($u['role']??'ADMIN_GERAL')!=='ADMIN_GERAL'){http_response_code(403);exit('Acesso restrito ao Admin Geral.');}
$err='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    if(($_POST['action']??'')==='backup'){
        if(!is_file(DB_PATH)){ $err='O banco ainda não foi criado.'; }
        else{
            try{ db()->exec('PRAGMA wal_checkpoint(FULL)'); }catch(Throwable $e){}
            clearstatcache(true,DB_PATH);
            $name='BAMAB_BACKUP_'.date('Y-m-d_H-i-s').'.sqlite';
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.$name.'"');
            header('Content-Length: '.(string)filesize(DB_PATH));
            header('Cache-Control: no-store, no-cache, must-revalidate');
            readfile(DB_PATH);exit;
        }
    }
}
function mb_size(int $b): string {return number_format($b/1048576,2,',','.').' MB';}
$size=free_hosting_db_size();$pct=free_hosting_db_percent();$warn=free_hosting_db_warning();
admin_header('Modo Grátis — Publicação');
?>
<style>
.free-mode-hero{background:linear-gradient(135deg,#111,#050505);border:1px solid var(--site-gold);border-radius:18px;padding:22px;margin-bottom:18px;display:grid;grid-template-columns:auto 1fr;gap:18px;align-items:center}.free-mode-hero .seal{width:86px;height:86px;border:2px solid var(--site-gold);border-radius:50%;display:grid;place-items:center;color:var(--site-gold);font-weight:900;font-size:24px}.free-mode-hero h2{margin:0 0 5px;color:#fff}.free-mode-hero p{margin:0;color:#ddd}.free-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.free-card{background:#111;border:1px solid #3b3426;border-radius:16px;padding:18px}.free-card h3{color:var(--site-gold);margin-top:0}.meter{height:14px;background:#262626;border-radius:999px;overflow:hidden;border:1px solid #444}.meter>span{display:block;height:100%;background:linear-gradient(90deg,var(--site-gold),#8d1528);width:<?=max(1,min(100,$pct))?>%}.big-number{font-size:30px;font-weight:900;color:#fff}.status-ok{color:#77d99a}.status-warn{color:#ffce6a}.free-steps{margin:0;padding-left:20px;color:#eee;line-height:1.65}.free-note{background:#21160a;border:1px solid #8a6425;padding:13px;border-radius:12px;color:#f7dfae}.free-danger{background:#2a1115;border:1px solid #8d2b3a;padding:13px;border-radius:12px;color:#ffd6db}@media(max-width:760px){.free-grid{grid-template-columns:1fr}.free-mode-hero{grid-template-columns:1fr;text-align:center}.free-mode-hero .seal{margin:auto}}
</style>
<section class="free-mode-hero"><div class="seal">R$0</div><div><span style="color:var(--site-gold);font-weight:800">MODO HOSPEDAGEM GRATUITA ATIVO</span><h2>BAMAB <?=e(APP_VERSION)?> — preparado para InfinityFree</h2><p>Configuração reduzida para respeitar os limites do plano gratuito sem remover os recursos principais do sistema.</p></div></section>
<?php if($err):?><div class="alert danger"><?=e($err)?></div><?php endif;?>
<div class="free-grid">
<section class="free-card"><h3>Banco de dados SQLite</h3><div class="big-number <?= $warn?'status-warn':'status-ok' ?>"><?=e(mb_size($size))?></div><p>Limite de segurança configurado: <strong>10 MB por arquivo</strong>. Aviso preventivo a partir de 8 MB.</p><div class="meter"><span></span></div><p><?=number_format($pct,1,',','.')?>% do limite de referência.</p><?php if($warn):?><div class="free-danger"><strong>ATENÇÃO:</strong> o banco está perto do limite do plano gratuito. Faça backup agora e planeje migrar para MySQL ou hospedagem paga.</div><?php else:?><div class="free-note">O sistema avisará antes do banco se aproximar do limite do plano gratuito.</div><?php endif;?><form method="post" style="margin-top:14px"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="backup"><button class="primary">BAIXAR BACKUP DO BANCO AGORA</button></form></section>
<section class="free-card"><h3>Limites aplicados automaticamente</h3><ul class="free-steps"><li>Fotos: até <strong>8 MB</strong> por arquivo.</li><li>Vídeos locais: até <strong>8 MB</strong> por arquivo.</li><li>Para vídeos maiores, prefira links externos/Instagram.</li><li>Banco protegido por <code>.htaccess</code>.</li><li>Uploads sem execução de PHP.</li><li>HTTPS recomendado para câmera e QR no celular.</li></ul></section>
<section class="free-card"><h3>Publicação gratuita</h3><ol class="free-steps"><li>Crie a hospedagem gratuita.</li><li>Abra a pasta <strong>htdocs</strong> do servidor.</li><li>Envie o conteúdo da pasta <strong>BAMAB</strong>.</li><li>Acesse <strong>/admin/setup.php</strong> no endereço público.</li><li>Depois execute <strong>/admin/diagnostico.php</strong>.</li><li>Abra o site pelo endereço <strong>HTTPS</strong>.</li></ol></section>
<section class="free-card"><h3>Backup é obrigatório</h3><p>Em hospedagem gratuita, mantenha cópias frequentes de <strong>data/bamab.sqlite</strong> e da pasta <strong>uploads</strong>.</p><div class="free-danger">Não substitua uma versão publicada sem primeiro salvar <strong>data</strong> e <strong>uploads</strong>.</div></section>
</div>
<?php admin_footer();?>
