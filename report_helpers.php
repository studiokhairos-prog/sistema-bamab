<?php
function bamab_report_palette(): array {
    return [
        'primary' => (string)setting('primary_color','#090909'),
        'gold'    => (string)setting('secondary_color','#c99a34'),
        'dark'    => (string)setting('dark_color','#050505'),
        'light'   => (string)setting('light_color','#ededed'),
    ];
}
function bamab_report_logo_web(string $prefix='../'): string {
    return $prefix.ltrim((string)setting('logo_path','assets/brasao_bamab_2026.png'),'/');
}
function bamab_report_logo_abs(): string {
    $rel=ltrim((string)setting('logo_path','assets/brasao_bamab_2026.png'),'/');
    $abs=__DIR__.'/'.$rel;
    if(!is_file($abs)) $abs=__DIR__.'/assets/brasao_bamab_2026.png';
    return $abs;
}
function bamab_report_style_block(string $prefix='../'): string {
    $p=bamab_report_palette();
    $logo=bamab_report_logo_web($prefix);
    return '<style>:root{'
        .'--report-primary:'.htmlspecialchars($p['primary'],ENT_QUOTES,'UTF-8').';'
        .'--report-gold:'.htmlspecialchars($p['gold'],ENT_QUOTES,'UTF-8').';'
        .'--report-dark:'.htmlspecialchars($p['dark'],ENT_QUOTES,'UTF-8').';'
        .'--report-light:'.htmlspecialchars($p['light'],ENT_QUOTES,'UTF-8').';'
        .'--report-wm:url("'.htmlspecialchars($logo,ENT_QUOTES,'UTF-8').'");'
        .'}</style>';
}
function bamab_report_logo_data_uri(): string {
    $abs=bamab_report_logo_abs();
    if(!is_file($abs)) return '';
    $ext=strtolower((string)pathinfo($abs,PATHINFO_EXTENSION));
    $mime='image/png';
    if(in_array($ext,['jpg','jpeg'],true)) $mime='image/jpeg';
    elseif($ext==='webp') $mime='image/webp';
    $raw=file_get_contents($abs);
    if($raw===false) return '';
    return 'data:'.$mime.';base64,'.base64_encode($raw);
}
function bamab_report_excel_headers(string $filename): void {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "ï»¿";
}
function bamab_report_excel_style_block(): string {
    $p=bamab_report_palette();
    $primary=htmlspecialchars($p['primary'],ENT_QUOTES,'UTF-8');
    $gold=htmlspecialchars($p['gold'],ENT_QUOTES,'UTF-8');
    $dark=htmlspecialchars($p['dark'],ENT_QUOTES,'UTF-8');
    $light=htmlspecialchars($p['light'],ENT_QUOTES,'UTF-8');
    return '<style>'
        .'body{font-family:Arial,Helvetica,sans-serif;font-size:11pt;color:#111;margin:12px;background:'.$light.';}'
        .'.sheet-wrap{max-width:1200px;margin:0 auto;background:#fff;border:1px solid '.$gold.';padding:14px;}'
        .'.sheet-head{display:flex;align-items:center;gap:14px;padding:10px 12px;border:2px solid '.$gold.';background:linear-gradient(180deg,#fff,'. $light .');}'
        .'.sheet-head img{width:72px;height:72px;object-fit:contain;}'
        .'.sheet-head h1{margin:0;font-size:18pt;color:'.$primary.';font-family:Georgia,serif;}'
        .'.sheet-head p{margin:3px 0 0;font-size:10pt;color:'.$dark.';}'
        .'.sheet-block{margin-top:16px;}'
        .'.sheet-block h2{margin:0 0 8px;padding:8px 10px;background:'.$primary.';color:#fff;font-size:12pt;}'
        .'.sheet-table{width:100%;border-collapse:collapse;margin-top:8px;}'
        .'.sheet-table th,.sheet-table td{border:1px solid #666;padding:6px 7px;vertical-align:top;}'
        .'.sheet-table th{background:'.$gold.';color:#111;text-transform:uppercase;font-size:9pt;font-weight:700;}'
        .'.sheet-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px;}'
        .'.sheet-meta div{border:1px solid #999;padding:8px;background:#fafafa;}'
        .'.sheet-sign{margin-top:22px;width:100%;border-collapse:collapse;}'
        .'.sheet-sign td{width:33.33%;padding-top:26px;text-align:center;font-size:9pt;}'
        .'.sheet-sign .line{border-top:1px solid #111;margin-bottom:5px;}'
        .'.legal-box{margin-top:14px;border:1px solid #999;background:#f6f6f6;padding:10px;line-height:1.5;font-size:10pt;}'
        .'.warning-box{margin-top:12px;border-left:4px solid '.$primary.';background:#f5f3ec;padding:10px;line-height:1.5;font-size:10pt;}'
        .'.text-block{margin-top:12px;line-height:1.65;font-size:10.5pt;text-align:justify;}'
        .'.footer-note{margin-top:16px;font-size:8.5pt;color:#666;text-align:center;}'
        .'</style>';
}
