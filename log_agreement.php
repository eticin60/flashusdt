<?php
/* FLASH USDT — Agreement Log Writer */
header('Content-Type: application/json; charset=utf-8');
define('LOG_FILE', __DIR__ . '/agreement_logs.json');

$raw = file_get_contents('php://input');
if (!$raw) { echo json_encode(['ok'=>false,'msg'=>'No input']); exit; }

$data = json_decode($raw, true);
if (!is_array($data)) { echo json_encode(['ok'=>false,'msg'=>'Invalid JSON']); exit; }

$entry = [
  'txid' => $data['txid'] ?? '',
  'network' => $data['network'] ?? '',
  'contract_id' => $data['contractId'] ?? '',
  'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
  'timestamp' => date('Y-m-d H:i:s'),
  'ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

$existing = file_exists(LOG_FILE) ? json_decode(file_get_contents(LOG_FILE), true) : [];
if (!is_array($existing)) $existing = [];

$existing[] = $entry;
file_put_contents(LOG_FILE, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo json_encode(['ok'=>true,'msg'=>'Logged']);
?>
