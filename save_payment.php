<?php
/* =====================================================
   FLASH USDT — save_payment.php (Safe Final Version)
   Author: FLASH USDT ADMIN
   Updated: 2025-11-03
   ===================================================== */
define('PAY_FILE', __DIR__ . '/payments.json');

/* ------------------ READ ------------------ */
function read_all(){
  if (!file_exists(PAY_FILE)) return [];
  $raw = file_get_contents(PAY_FILE);
  if ($raw === false || $raw==='') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

/* ------------------ WRITE ------------------ */
function write_all($arr){
  $fp = @fopen(PAY_FILE,'c+');
  if (!$fp) return false;
  if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
  ftruncate($fp,0); rewind($fp);
  $ok = fwrite($fp, json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  fflush($fp); flock($fp, LOCK_UN); fclose($fp);
  return $ok !== false;
}

/* ------------------ METHOD CHECK ------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
  http_response_code(405);
  echo 'METHOD';
  exit;
}

/* ------------------ TXID CHECK ------------------ */
$txid = strtolower(trim($_POST['txid'] ?? ''));
if ($txid === ''){
  http_response_code(400);
  echo 'NO_TXID';
  exit;
}

/* ------------------ MODE CHECK (verify_only) ------------------ */
// Eğer sadece sorgulama için çağrıldıysa yazma işlemi yapılmaz
if (isset($_POST['verify_only']) && $_POST['verify_only'] == '1') {
  echo 'SKIP_WRITE';
  exit;
}

/* ------------------ READ EXISTING DATA ------------------ */
$all = read_all();

/* ------------------ IF TXID EXISTS: DON’T TOUCH ------------------ */
if (isset($all[$txid])) {
  echo 'EXISTS'; // zaten var → yazma yok, koru
  exit;
}

/* ------------------ NEW RECORD CREATION ------------------ */
$record = [
  'txid'      => $txid,
  'product'   => trim($_POST['product'] ?? '—'),
  'amount'    => trim($_POST['amount'] ?? '—'),
  'price'     => trim($_POST['price'] ?? '—'),
  'wallet'    => trim($_POST['wallet'] ?? '—'),
  'user_address'   => trim($_POST['user_address'] ?? '—'),
  'deposit_address'=> trim($_POST['deposit_address'] ?? '—'),
  'network'   => trim($_POST['network'] ?? 'TRC20'),
  'date'      => gmdate('c'),
  'status'    => in_array(($_POST['status'] ?? 'pending'),
                   ['pending','processing','approved','rejected','delivered'])
                   ? $_POST['status'] : 'pending',
  'updated_at'=> gmdate('c'),
  'meta'      => [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
  ]
];

/* ------------------ SAVE RECORD ------------------ */
$all[$txid] = $record;

/* --- Dosya izin kontrolü --- */
if (!file_exists(PAY_FILE)) {
  @file_put_contents(PAY_FILE, '{}');
  @chmod(PAY_FILE, 0666);
}

/* --- Kaydet ve hata yakala --- */
if (!write_all($all)){
  http_response_code(500);
  echo 'WRITE_FAIL';
  error_log("[FLASHUSDT] write_all failed at ".date('c')." for TXID: ".$txid);
  exit;
}

/* ------------------ SUCCESS ------------------ */
echo 'OK';
?>
