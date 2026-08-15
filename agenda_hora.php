<?php
require __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$tz = new DateTimeZone('America/Sao_Paulo');
$now = new DateTimeImmutable('now', $tz);
echo json_encode([
    'ok' => true,
    'timezone' => 'America/Sao_Paulo',
    'timestamp_ms' => (int) round(microtime(true) * 1000),
    'date' => $now->format('d/m/Y'),
    'time' => $now->format('H:i:s'),
    'iso' => $now->format(DateTimeInterface::ATOM),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
