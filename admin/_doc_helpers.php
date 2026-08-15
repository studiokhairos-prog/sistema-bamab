<?php
function bamab_doc_hex(string $key,string $default): string {
    $v=trim((string)setting($key,$default));
    return preg_match('/^#[0-9a-fA-F]{6}$/',$v) ? $v : $default;
}
function bamab_doc_num(string $key,float $default,float $min,float $max): float {
    $raw=setting($key,(string)$default);
    $v=is_numeric($raw)?(float)$raw:$default;
    return max($min,min($max,$v));
}
function bamab_doc_design(): array {
    return [
        'dark'=>bamab_doc_hex('doc_primary_color','#0b1118'),
        'gold'=>bamab_doc_hex('doc_gold_color','#d1a33f'),
        'wine'=>bamab_doc_hex('doc_wine_color','#7d1020'),
        'light'=>bamab_doc_hex('doc_light_color','#f5f1e9'),
        'text'=>bamab_doc_hex('doc_text_color','#171717'),
        // Faixas seguras: impedem configurações que façam o conteúdo sumir ou sair do documento.
        'cardTitle'=>bamab_doc_num('doc_card_title_size',4.2,2.4,5.2),
        'cardName'=>bamab_doc_num('doc_card_name_size',2.8,2.0,3.6),
        'cardField'=>bamab_doc_num('doc_card_field_size',2.2,1.65,2.8),
        'cardLabel'=>bamab_doc_num('doc_card_label_size',1.65,1.2,2.1),
        'badgeTitle'=>bamab_doc_num('doc_badge_title_size',3.8,2.6,4.8),
        'badgeName'=>bamab_doc_num('doc_badge_name_size',4.7,2.9,5.6),
        'badgeField'=>bamab_doc_num('doc_badge_field_size',2.45,1.75,3.1),
        'badgeLabel'=>bamab_doc_num('doc_badge_label_size',2.0,1.35,2.4),
        'backTitle'=>bamab_doc_num('doc_back_title_size',4.0,2.5,4.8),
        'backText'=>bamab_doc_num('doc_back_text_size',1.85,1.3,2.25),
    ];
}
function bamab_doc_len(string $value): int { return mb_strlen(trim($value),'UTF-8'); }
function bamab_doc_fit_class(string $value,string $kind): string {
    $n=bamab_doc_len($value);
    $limits=[
        'card-name'=>[22,32,42,54],
        'card-title'=>[19,25,32,40],
        'card-value'=>[18,25,33,44],
        'badge-name'=>[21,30,40,52],
        'badge-title'=>[23,31,40,50],
        'badge-value'=>[22,31,42,55],
        'back-title'=>[26,36,48,60],
        'back-text'=>[170,260,360,500],
    ];
    $a=$limits[$kind]??[25,35,45,60];
    if($n>=$a[3]) return 'fit-tiny';
    if($n>=$a[2]) return 'fit-xxs';
    if($n>=$a[1]) return 'fit-xs';
    if($n>=$a[0]) return 'fit-sm';
    return 'fit-normal';
}
function bamab_doc_date(?string $date): string {
    $date=trim((string)$date);
    if($date==='') return '—';
    $ts=strtotime($date);
    return $ts===false?'—':date('d/m/Y',$ts);
}
function bamab_doc_root_asset(string $relative,string $fallback='assets/brasao_bamab_2026.png'): string {
    $relative=trim(str_replace('\\','/',$relative));
    $relative=ltrim($relative,'/');
    if(str_contains($relative,'..')) $relative='';
    if($relative!=='' && is_file(dirname(__DIR__).'/'.$relative)) return $relative;
    return $fallback;
}
function bamab_doc_web_asset(string $relative,string $prefix='../',string $fallback='assets/brasao_bamab_2026.png'): string {
    return $prefix.bamab_doc_root_asset($relative,$fallback);
}
function bamab_doc_logo(string $prefix='../'): string {
    return bamab_doc_web_asset((string)setting('logo_path','assets/brasao_bamab_2026.png'),$prefix);
}
function bamab_doc_photo(string $relative,string $prefix='../'): string {
    return bamab_doc_web_asset($relative,$prefix,(string)setting('logo_path','assets/brasao_bamab_2026.png'));
}
function bamab_doc_clean_title(string $value,string $fallback): string {
    $value=trim(preg_replace('/\s+/u',' ',$value)??$value);
    return $value!==''?$value:$fallback;
}
function bamab_doc_safe_text(string $value,int $max=700): string {
    $value=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$value)??$value);
    return mb_substr($value,0,$max,'UTF-8');
}
