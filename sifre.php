<?php
// Gerekli dosyaları dahil et
require_once __DIR__ . '/ortak/sabitler.php';
require_once __DIR__ . '/ortak/csrf.php';
require_once __DIR__ . '/ortak/db_helpers.php';
require_once __DIR__ . '/ortak/guvenlik.php';
require_once __DIR__ . '/ortak/sms_netgsm.php'; // send_sms için

// current_user fonksiyonu burada tanımlı
function current_user() {
    // Kullanıcı oturumdan alınır (ör: $_SESSION['user'])
    // Oturum yönetimini kendinize göre uyarlayın!
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return $_SESSION['user'] ?? null;
}

$u = current_user();
if (!$u || ($u['rol'] ?? '') !== 'PERSONEL') {
    // Sadece personel erişebilir
    header("Location: " . app_url());
    exit;
}

// Kullanıcı detaylarını al
$detay = fetch_one("
    SELECT k.*,
           b.ad  AS bina_ad,
           ka.ad AS kat_ad,
           bi.ad AS birim_ad
    FROM kullanicilar k
    LEFT JOIN birimler bi ON bi.id = k.sorumlu_birim_id
    LEFT JOIN katlar   ka ON ka.id = bi.kat_id
    LEFT JOIN binalar  b ON b.id = ka.bina_id
    WHERE k.id=?
    LIMIT 1
", [$u['id']]);

if(!$detay){
    flash_set('error','Kullanıcı bulunamadı.');
    header("Location: " . app_url());
    exit;
}

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $eski = $_POST['eski_sifre'] ?? '';
    $yeni1 = $_POST['yeni_sifre'] ?? '';
    $yeni2 = $_POST['yeni_sifre_tekrar'] ?? '';

    if ($eski === '' || $yeni1 === '' || $yeni2 === '') {
        flash_set('error','Tüm alanları doldurunuz.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (!password_verify($eski, $detay['parola'])) {
        flash_set('error','Eski şifre hatalı.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($yeni1 !== $yeni2) {
        flash_set('error','Yeni şifre tekrar ile uyuşmuyor.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (strlen($yeni1) < 6) {
        flash_set('error','Yeni şifre en az 6 karakter olmalı.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $hash = password_hash($yeni1, PASSWORD_DEFAULT);
    $ok = exec_stmt("UPDATE kullanicilar SET parola=?, updated_at=NOW() WHERE id=?", [$hash, $u['id']]);

    if ($ok) {
        // SMS gönder
        $tel = trim($detay['telefon'] ?? '');
        if ($tel !== '') {
            $smsMesaj = "Sn. ".$detay['ad'].", sistem üzerinden şifreniz değiştirilmiştir. E Posta : ".$detay['email']
                      ." Yeni Şifreniz : ".$yeni1." eğer bu işlemi siz yapmadıysanız lütfen ilgili amirinize bildirin.";
            $smsOk = send_sms($tel, $smsMesaj);
            if(!$smsOk){
                error_log('[SIFRE_SMS] Gönderilemedi kullanıcıID='.$u['id']);
            }
        }
        flash_set('success','Şifre güncellendi.');
    } else {
        flash_set('error','Şifre güncellenemedi.');
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Görsel veriler
$adSoyad  = $detay['ad'] ?? '-';
$gorevi   = $detay['gorevi'] ?? '-';
$tel      = $detay['telefon'] ?? '-';
$olustur  = $detay['created_at'] ?? '-';
$sorumluBolum = '-';

// Sorumlu bölüm zinciri
$parcalar = [];
if(!empty($detay['bina_ad']))  $parcalar[] = $detay['bina_ad'];
if(!empty($detay['kat_ad']))   $parcalar[] = $detay['kat_ad'];
if(!empty($detay['birim_ad'])) $parcalar[] = $detay['birim_ad'];
if($parcalar) $sorumluBolum = implode(' / ', $parcalar);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Şifre Değiştir</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:Arial,sans-serif;background:#f1f1f1;margin:0;padding:20px;}
.container{max-width:600px;margin:0 auto;}
.card{background:#fff;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.12);padding:22px 24px;margin-bottom:25px;}
h1{margin:0 0 18px;font-size:22px;color:#333;}
dl{margin:0;}
dt{font-weight:600;margin-top:10px;font-size:13px;color:#444;}
dd{margin:0 0 4px 0;font-size:13px;}
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:13px;margin-bottom:6px;color:#333;}
.form-group input{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:14px;box-sizing:border-box;}
.btn{display:inline-block;padding:10px 18px;border:none;border-radius:6px;background:#0d6efd;color:#fff;font-weight:600;cursor:pointer;font-size:14px;text-decoration:none;}
.btn.secondary{background:#6c757d;}
.flash{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:15px;}
.flash.success{background:#e6ffec;border:1px solid #9ad7aa;}
.flash.error{background:#ffe5e5;border:1px solid #ff9d9d;}
.topbar{display:flex;align-items:center;gap:10px;margin-bottom:15px;}
.topbar a{font-size:12px;text-decoration:none;color:#0d6efd;}
.note{font-size:11px;color:#666;line-height:1.4;margin-top:-6px;margin-bottom:14px;}
</style>
</head>
<body>
<div class="container">

  <div class="topbar">
    <a href="<?php echo h(app_url('qr_form.php')); ?>">&larr; Geri</a>
  </div>

  <?php foreach(flash_get_all() as $f): ?>
    <div class="flash <?php echo h($f['t']); ?>"><?php echo h($f['m']); ?></div>
  <?php endforeach; ?>

  <div class="card">
    <h1>Şifre Değiştir</h1>

    <dl>
      <dt>Ad Soyad</dt><dd><?php echo h($adSoyad); ?></dd>
      <dt>Sorumlu Olduğu Bölüm</dt><dd><?php echo h($sorumluBolum ?: '-'); ?></dd>
      <dt>Oluşturulma Tarihi</dt><dd><?php echo h($olustur); ?></dd>
      <dt>Telefon</dt><dd><?php echo h($tel); ?></dd>
      <dt>Görevi</dt><dd><?php echo h($gorevi); ?></dd>
    </dl>

    <hr style="margin:18px 0;">

    <form method="post" autocomplete="off">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="act" value="change">

      <div class="form-group">
        <label>Eski Şifre</label>
        <input type="password" name="eski_sifre" required>
      </div>
      <div class="form-group">
        <label>Yeni Şifre (Min 6 Karakter)</label>
        <input type="password" name="yeni_sifre" minlength="6" required>
      </div>
      <div class="form-group">
        <label>Yeni Şifre (Tekrar)</label>
        <input type="password" name="yeni_sifre_tekrar" minlength="6" required>
      </div>
      <div class="note">Şifre karmaşıklığı zorunlu değildir; en az 6 karakter olması yeterlidir.</div>
      <button class="btn">Şifre Değiştir</button>
      <a class="btn secondary" href="<?php echo h(app_url('qr_form.php')); ?>">İptal</a>
    </form>
  </div>

</div>
</body>
</html>