<?php
/*******************************
 * FLASH USDT — Admin Panel
 * Login + Kayıt Listeleme + Durum Güncelleme (AJAX)
 *******************************/
session_start();

/* ====== Basit Kimlik Doğrulama ====== */
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'qwert9asd8';

function is_logged_in() {
  return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin_panel.php'); exit; }

if (!is_logged_in()) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if ($u === $ADMIN_USER && $p === $ADMIN_PASS) {
      $_SESSION['logged_in'] = true;
      header('Location: admin_panel.php'); exit;
    } else { $error = '❌ Invalid username or password.'; }
  }
  // Login ekranı (USDT-2 logolu)
  echo '<!doctype html><html><head><meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FLASH USDT — Admin Login</title>
  <style>
    :root{--bg1:#030615;--bg2:#0b1130;--text:#eef3ff;--muted:#9aa0c2;--border:rgba(255,255,255,.12)}
    *{box-sizing:border-box} html,body{margin:0;height:100%}
    body{font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:linear-gradient(180deg,var(--bg1),var(--bg2));color:var(--text);display:flex;align-items:center;justify-content:center}
    .card{width:min(420px,94vw);background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:16px;padding:26px;backdrop-filter:blur(14px);box-shadow:0 20px 80px rgba(0,0,0,.5)}
    .brand{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:12px}
    .brand img{width:36px;height:36px;border-radius:10px}
    h1{margin:6px 0 8px;text-align:center;font-size:22px}
    p.sub{text-align:center;color:#c5cdf2;margin:0 0 16px}
    input{width:100%;height:48px;border-radius:12px;border:1px solid var(--border);background:rgba(255,255,255,.08);color:var(--text);padding:0 12px;outline:none;margin:8px 0}
    button{width:100%;height:48px;border:none;border-radius:12px;background:linear-gradient(90deg,#3aa8ff,#7c5cff);color:#fff;font-weight:800;cursor:pointer;margin-top:6px}
    .error{color:#ff4b9f;margin-top:10px;text-align:center}
  </style></head><body>
    <form class="card" method="post" autocomplete="off">
      <div class="brand">
        <img src="assets/USDT-2.png" alt="logo">
        <strong>FLASH USDT</strong>
      </div>
      <h1>Admin Login</h1>
      <p class="sub">Secure • AES-256 • Session Protected</p>
      <input type="text" name="username" placeholder="Username" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit">Login</button>'.
      (!empty($error)?'<div class="error">'.$error.'</div>':'').'
    </form>
  </body></html>';
  exit;
}

/* ====== Veri Yardımcıları ====== */
define('PAY_FILE', __DIR__ . '/payments.json');

/* 🔹 Türkiye Saati için tarih formatı */
function format_tr_date($iso) {
  if (!$iso) return '—';
  $dt = new DateTime($iso);
  $dt->setTimezone(new DateTimeZone('Europe/Istanbul'));
  return $dt->format('d.m.Y H:i:s');
}

function read_payments() {
  if (!file_exists(PAY_FILE)) return [];
  $raw = file_get_contents(PAY_FILE);
  if ($raw === false || $raw==='') return [];
  $data = json_decode($raw,true);
  return is_array($data)?$data:[];
}
function write_payments($arr) {
  $fp = fopen(PAY_FILE,'c+');
  if (!$fp) return false;
  if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
  ftruncate($fp,0);
  rewind($fp);
  $ok = fwrite($fp, json_encode($arr, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return $ok !== false;
}

/* ====== AJAX: Durum Güncelle ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='update_status') {
  header('Content-Type: application/json');
  $txid = strtolower(trim($_POST['txid'] ?? ''));
  $status = trim($_POST['status'] ?? '');
  $allowed = ['pending','processing','approved','rejected','delivered'];
  if ($txid==='' || !in_array($status,$allowed,true)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid args']); exit;
  }
  $all = read_payments();
  if (!isset($all[$txid])) { echo json_encode(['ok'=>false,'error'=>'TXID not found']); exit; }
  $all[$txid]['status'] = $status;
  $all[$txid]['updated_at'] = gmdate('c');
  write_payments($all);
  echo json_encode(['ok'=>true,'status'=>$status]); exit;
}

/* ====== Sayfa ====== */
$items = read_payments();

/* İstatistikler */
$counts = ['total'=>0,'pending'=>0,'processing'=>0,'approved'=>0,'rejected'=>0,'delivered'=>0];
foreach ($items as $k=>$r) {
  $counts['total']++;
  $s = $r['status'] ?? 'pending';
  if (isset($counts[$s])) $counts[$s]++;
}

/* Filtre & Arama */
$filter = $_GET['status'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$view = [];
foreach ($items as $tx=>$r) {
  if ($filter!=='all' && ($r['status']??'')!==$filter) continue;
  if ($q!=='') {
    $hay = strtolower(json_encode($r,JSON_UNESCAPED_UNICODE));
    if (strpos($hay, strtolower($q))===false && strpos($tx, strtolower($q))===false) continue;
  }
  $view[$tx] = $r;
}

/* Basit sıralama: updated_at desc */
uasort($view, function($a,$b){
  $ta = $a['updated_at'] ?? $a['date'] ?? '';
  $tb = $b['updated_at'] ?? $b['date'] ?? '';
  return strcmp($tb,$ta);
});
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>FLASH USDT — Admin Panel</title>
<style>
  :root{
    --bg1:#030615; --bg2:#0b1130; --bg3:#0f153d;
    --text:#eef3ff; --muted:#9aa0c2; --border:rgba(255,255,255,.12);
    --cyan:#00f0ff; --vio:#5b2cff; --rose:#ff4b9f; --lime:#16d18a; --amber:#ffcc4d;
  }
  *{box-sizing:border-box} html,body{margin:0;height:100%}
  body{font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:var(--text);
    background:radial-gradient(1000px 600px at 10% -10%, rgba(0,240,255,.12), transparent 55%),
               radial-gradient(800px 500px at 90% 0, rgba(91,44,255,.16), transparent 60%),
               linear-gradient(180deg, var(--bg1), var(--bg2) 50%, var(--bg3))}
  header{position:sticky;top:0;z-index:5;background:rgba(10,15,40,.75);backdrop-filter:blur(14px);border-bottom:1px solid var(--border)}
  .nav{max-width:1200px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:12px;justify-content:space-between}
  .brand{display:flex;align-items:center;gap:10px;font-weight:900}
  .brand img{width:28px;height:28px;border-radius:8px}
  .wrap{max-width:1200px;margin:0 auto;padding:18px 20px}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:12px 0 18px}
  .chip{border:1px solid var(--border);background:rgba(255,255,255,.05);border-radius:14px;padding:12px}
  .grid{overflow:auto;border:1px solid var(--border);border-radius:14px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:12px;border-bottom:1px solid rgba(255,255,255,.06);font-size:13.5px}
  th{position:sticky;top:0;background:rgba(10,15,40,.85);backdrop-filter:blur(10px);text-align:left}
  tr:hover{background:rgba(255,255,255,.03)}
  .badge{font-weight:800;font-size:11px;padding:4px 8px;border-radius:999px;border:1px solid var(--border)}
  .b-pending{color:#ffcc4d;border-color:rgba(255,204,77,.35)}
  .b-processing{color:#80d0ff;border-color:rgba(0,240,255,.25)}
  .b-approved{color:#16d18a;border-color:rgba(22,209,138,.35)}
  .b-rejected{color:#ff4b9f;border-color:rgba(255,75,159,.35)}
  .b-delivered{color:#93ffb0;border-color:rgba(22,209,138,.45)}
  select, input[type="search"]{height:38px;border-radius:10px;border:1px solid var(--border);background:rgba(255,255,255,.06);color:var(--text);padding:0 10px;outline:none}
  .actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .btn{height:38px;border:none;border-radius:10px;padding:0 14px;font-weight:800;cursor:pointer;color:#fff;background:linear-gradient(90deg,#3aa8ff,#7c5cff)}
  .btn.ghost{background:transparent;color:#cfe0ff;border:1px solid var(--border)}
  .muted{color:#aab2d8;font-size:12px}
  .mono{font-family:ui-monospace,monospace}
  .right{display:flex;gap:10px;align-items:center}
  .logout{color:#ff9bbf;text-decoration:none}
  .statusSel{min-width:150px}
  .viewBtn{background:linear-gradient(90deg,#16d18a,#0be7a3)}
  .cell{max-width:320px;overflow-wrap:anywhere}
  /* Modal */
  .modal{position:fixed;inset:0;background:rgba(3,6,21,.6);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;z-index:10}
  .box{width:min(780px,94vw);background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:18px;padding:20px}
</style>
</head>
<body>
<header>
  <div class="nav">
    <div class="brand">
      <img src="assets/USDT-2.png" alt="">
      <div>FLASH <span style="background:linear-gradient(90deg,#cfe8ff,#93b4ff);-webkit-background-clip:text;color:transparent">USDT</span> — Admin</div>
    </div>
    <div class="right">
      <a class="btn ghost" href="admin_panel.php?logout=1">Logout</a>
    </div>
  </div>
</header>

<main class="wrap">
  <!-- Stats -->
  <div class="stats">
    <div class="chip">Total <div style="font-size:22px;font-weight:900;margin-top:4px"><?= (int)$counts['total'] ?></div></div>
    <div class="chip">Pending <div style="font-size:22px;font-weight:900;color:#ffcc4d;margin-top:4px"><?= (int)$counts['pending'] ?></div></div>
    <div class="chip">Processing <div style="font-size:22px;font-weight:900;color:#7dd3fc;margin-top:4px"><?= (int)$counts['processing'] ?></div></div>
    <div class="chip">Approved <div style="font-size:22px;font-weight:900;color:#16d18a;margin-top:4px"><?= (int)$counts['approved'] ?></div></div>
    <div class="chip">Rejected <div style="font-size:22px;font-weight:900;color:#ff4b9f;margin-top:4px"><?= (int)$counts['rejected'] ?></div></div>
    <div class="chip">Delivered <div style="font-size:22px;font-weight:900;color:#93ffb0;margin-top:4px"><?= (int)$counts['delivered'] ?></div></div>
  </div>

  <!-- Filters -->
  <form method="get" class="actions" style="margin-bottom:12px">
    <select name="status" class="statusSel">
      <?php
      $opts = ['all'=>'All','pending'=>'Pending','processing'=>'Processing','approved'=>'Approved','rejected'=>'Rejected','delivered'=>'Delivered'];
      foreach ($opts as $k=>$v){ $sel = $filter===$k?'selected':''; echo "<option value=\"$k\" $sel>$v</option>"; }
      ?>
    </select>
    <input type="search" name="q" placeholder="Search txid, wallet, product..." value="<?= htmlspecialchars($q) ?>">
    <button class="btn" type="submit">Apply</button>
  </form>

  <!-- Table -->
  <div class="grid">
    <table>
      <thead>
        <tr>
          <th style="width:210px">TXID</th>
          <th>Product</th>
          <th>Amount</th>
          <th>Price</th>
          <th>Network</th>
          <th>Wallet</th>
          <th style="width:160px">Status</th>
          <th style="width:210px">Updated</th>
          <th style="width:150px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($view)): ?>
          <tr><td colspan="9" class="muted" style="padding:16px">No records found.</td></tr>
        <?php else: foreach ($view as $tx=>$r):
          $status = $r['status'] ?? 'pending';
          $badgeClass = 'b-'.$status;
        ?>
          <tr data-tx="<?= htmlspecialchars($tx) ?>">
            <td class="cell mono"><?= htmlspecialchars($tx) ?></td>
            <td class="cell"><?= htmlspecialchars($r['product'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['amount'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['price'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['network'] ?? '—') ?></td>
            <td class="cell mono"><?= htmlspecialchars($r['wallet'] ?? '—') ?></td>
            <td>
              <span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
              <div style="margin-top:6px">
                <select class="statusSel js-status">
                  <?php foreach (['pending','processing','approved','rejected','delivered'] as $st): $sel=$st===$status?'selected':''; ?>
                    <option value="<?= $st ?>" <?= $sel ?>><?= ucfirst($st) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </td>
<td class="mono"><?= htmlspecialchars(format_tr_date($r['updated_at'] ?? ($r['date'] ?? ''))) ?></td>
            <td>
              <button class="btn viewBtn js-view" type="button">View</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <p class="muted" style="margin-top:10px">✅ Status değiştirildiğinde, frontend (Ödeme sayfaları) <b>verify_status.php</b> üzerinden 8 sn sonra ve her 15 sn’de bir güncelleyerek yeni durumu gösterecek.</p>
</main>

<!-- Modal -->
<div class="modal" id="modal">
  <div class="box">
    <h3 style="margin:0 0 8px">Payment Details</h3>
    <pre id="detail" style="white-space:pre-wrap;word-break:break-word;font-size:13px;line-height:1.5"></pre>
    <div style="text-align:right;margin-top:8px"><button class="btn ghost" id="closeM">Close</button></div>
  </div>
</div>

<script>
  // View modal
  const modal = document.getElementById('modal');
  const detail = document.getElementById('detail');
  document.querySelectorAll('.js-view').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const tr = btn.closest('tr');
      const tx = tr?.dataset?.tx || '';
      if (!tx) return;
      // satırdan hücreleri topla
      const cells = tr.querySelectorAll('td');
      const o = {
        txid: cells[0]?.innerText?.trim(),
        product: cells[1]?.innerText?.trim(),
        amount: cells[2]?.innerText?.trim(),
        price: cells[3]?.innerText?.trim(),
        network: cells[4]?.innerText?.trim(),
        wallet: cells[5]?.innerText?.trim(),
        status: tr.querySelector('.badge')?.innerText?.trim(),
        updated: cells[7]?.innerText?.trim()
      };
      detail.textContent = JSON.stringify(o, null, 2);
      modal.style.display='flex';
    });
  });
  document.getElementById('closeM').addEventListener('click', ()=> modal.style.display='none');
  modal.addEventListener('click', (e)=>{ if(e.target===modal) modal.style.display='none'; });

  // Inline status change (AJAX)
  document.querySelectorAll('.js-status').forEach(sel=>{
    sel.addEventListener('change', async ()=>{
      const tr = sel.closest('tr');
      const tx = tr?.dataset?.tx || '';
      const status = sel.value;
      if (!tx) return;
      try{
        const fd = new FormData();
        fd.append('action','update_status');
        fd.append('txid',tx);
        fd.append('status',status);
        const res = await fetch('admin_panel.php', { method:'POST', body:fd });
        const data = await res.json();
        if(!data.ok){ alert('Update failed: '+(data.error||'')); return; }
        // Badge güncelle
        const b = tr.querySelector('.badge');
        b.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        b.className = 'badge b-' + status;
        // Updated alanını hızlı güncelle
        tr.querySelectorAll('td')[7].innerText = new Date().toISOString();
      }catch(err){ console.error(err); alert('Network error.'); }
    });
  });
</script>
</body>
</html>
