<?php
require_once __DIR__.'/ortak/oturum.php';
require_once __DIR__.'/ortak/sabitler.php';
require_once __DIR__.'/ortak/guvenlik.php';
require_once __DIR__.'/ortak/yetki.php';
require_once __DIR__.'/ortak/csrf.php';
require_once __DIR__.'/ortak/db_helpers.php';

$scope      = $_GET['scope'] ?? '';
$scopeId    = $_GET['scope_id'] ?? '';
$ts         = $_GET['ts'] ?? '';
$sig        = $_GET['sig'] ?? '';

if($scope && $scopeId && $ts && $sig && qr_token_is_valid($scopeId,$ts,$sig)){
    $_SESSION['qr_ok']=[
        'exp'=>(int)$ts+(int)env('TOKEN_TTL_S','180'),
        'scope'=>$scope,
        'scope_id'=>(int)$scopeId,
        'oda_id'=>null
    ];
    flash_set('success','QR doğrulandı.');
    redirect(app_url());
}

$qrSession=$_SESSION['qr_ok'] ?? null;
if($qrSession && time()>($qrSession['exp']??0)){
    unset($_SESSION['qr_ok']); $qrSession=null;
}

$selectedOda = $qrSession['oda_id'] ?? null;

// Kat/Birim ise Oda seçimi POST
if($qrSession && !$selectedOda && in_array($qrSession['scope'],['kat','birim'],true) && is_post() && ($_POST['_form']??'')==='odaSec'){
    csrf_check();
    $oda_id=(int)($_POST['oda_id']??0);
    if($oda_id>0){
        // doğrulama: oda gerçekten bu kat/birime bağlı mı
        if($qrSession['scope']==='kat'){
            $ok=fetch_one("SELECT id FROM odalar WHERE id=? AND kat_id=?",[$oda_id,$qrSession['scope_id']]);
        }else{
            $ok=fetch_one("SELECT o.id FROM odalar o 
                           LEFT JOIN birimler b ON b.id=o.birim_id 
                           WHERE o.id=? AND b.id=?",[ $oda_id,$qrSession['scope_id'] ]);
        }
        if($ok){
            $_SESSION['qr_ok']['oda_id']=$oda_id;
            flash_set('success','Oda seçildi.');
            redirect(app_url());
        } else {
            flash_set('error','Seçilen oda bu alana ait değil.');
            redirect(app_url());
        }
    }
}

// Personel login (ancak oda seçildiyse veya scope=oda ise)
if($qrSession && ( ($qrSession['scope']==='oda') || ($_SESSION['qr_ok']['oda_id']) ) && is_post() && ($_POST['_form']??'')==='login'){
    csrf_check();
    $email=trim($_POST['email']??'');
    $pass=trim($_POST['parola']??'');
    if(!$email||!$pass){ flash_set('error','E-posta ve parola gerekli.'); redirect(app_url()); }
    if(login_user_demo_or_db($email,$pass,['PERSONEL'])){
        flash_set('success','Giriş başarılı.');
        redirect(app_url('qr_form.php'));
    } else {
        flash_set('error','Geçersiz bilgiler.');
        redirect(app_url());
    }
}

$user=current_user();
if($user && $user['rol']==='PERSONEL'){
    redirect(app_url('qr_form.php'));
}

function fetchScopeOdas(array $qrSession){
    if($qrSession['scope']==='kat'){
        return fetch_all("SELECT id,ad FROM odalar WHERE kat_id=? ORDER BY ad",[$qrSession['scope_id']]);
    }elseif($qrSession['scope']==='birim'){
        return fetch_all("SELECT o.id,o.ad FROM odalar o WHERE o.birim_id=? ORDER BY o.ad",[$qrSession['scope_id']]);
    }
    return [];
}

$odaData=null;
if($qrSession){
    $odaIdFinal = $qrSession['scope']==='oda' ? $qrSession['scope_id'] : ($qrSession['oda_id'] ?? null);
    if($odaIdFinal){
        $odaData=fetch_one("SELECT o.*, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad
          FROM odalar o
          LEFT JOIN binalar b ON b.id=o.bina_id
          LEFT JOIN katlar k ON k.id=o.kat_id
          LEFT JOIN birimler bi ON bi.id=o.birim_id
          WHERE o.id=?",[$odaIdFinal]);
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Portal</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
/* Eski stil (kısaltıldı) */
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f1f1f1;}
.header-band{background:#d70000;padding:50px 15px 70px;position:relative;}
.logo-badge{width:72px;height:72px;background:#fff;border-radius:50%;position:absolute;left:50%;top:15px;transform:translateX(-50%);display:flex;align-items:center;justify-content:center;font-size:38px;font-weight:bold;color:#d70000;}
.panel{max-width:380px;margin:-40px auto 30px;background:#fff;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.15);overflow:hidden;}
.panel h2{text-align:center;margin:50px 0 15px;font-weight:600;font-size:24px;color:#333;}
.flash{max-width:380px;margin:12px auto;padding:10px 14px;border-radius:6px;font-size:13px;}
.flash.success{background:#e6ffec;border:1px solid #9ad7aa;}
.flash.error{background:#ffe5e5;border:1px solid #ff9d9d;}
.form-group{margin:0 25px 18px;}
.form-group label{display:block;font-size:13px;color:#444;margin-bottom:6px;}
.form-group input, .form-group select{width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:14px;}
.btn{display:inline-block;width:100%;padding:12px 15px;border:none;border-radius:6px;background:#d70000;color:#fff;font-size:15px;font-weight:600;cursor:pointer;text-align:center;text-decoration:none;}
.notice{max-width:380px;margin:20px auto;text-align:center;font-size:14px;color:#555;}
.link-btn{display:block;margin:10px 25px 25px;}
.meta-box{background:#fff;margin:0 auto 20px;max-width:380px;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:12px;line-height:1.4;color:#333;}
.qr-location{font-size:13px;color:#333;margin:0 25px 15px;padding:10px;background:#f5f5f5;border-radius:6px;}
</style>
</head>
<body>
<div class="header-band"><div class="logo-badge">🧹</div></div>
<?php foreach(flash_get_all() as $f): ?>
  <div class="flash <?php echo h($f['t']); ?>"><?php echo h($f['m']); ?></div>
<?php endforeach; ?>

<?php if(!$qrSession): ?>
  <div class="panel" style="padding:25px 0 35px;">
    <h2>Giriş</h2>
    <p style="text-align:center;color:#666;margin:0 25px 25px;">Lütfen QR Kod okutarak giriş yapın.</p>
  </div>
  <div class="notice">Kat / Birim / Oda QR kodu okutunuz.</div>
<?php else: ?>

  <?php if($qrSession['scope']!=='oda' && !$qrSession['oda_id']): ?>
    <!-- Oda seçimi ekranı -->
    <div class="panel">
      <h2>Oda Seç</h2>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="_form" value="odaSec">
        <div class="form-group">
          <label>Oda</label>
          <select name="oda_id" required>
            <option value="">Seçiniz</option>
            <?php foreach(fetchScopeOdas($qrSession) as $o): ?>
            <option value="<?php echo h($o['id']); ?>"><?php echo h($o['ad']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <button class="btn">Devam Et</button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div class="panel">
      <h2>Giriş Yap</h2>
      <?php if($odaData): ?>
      <div class="qr-location">
        <strong>Konum:</strong><br>
        Bina: <?php echo h($odaData['bina_ad']); ?><br>
        Kat: <?php echo h($odaData['kat_ad']); ?><br>
        Birim: <?php echo h($odaData['birim_ad'] ?: '-'); ?><br>
        Oda: <?php echo h($odaData['ad']); ?>
      </div>
      <?php endif; ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="_form" value="login">
        <div class="form-group">
          <label>Kullanıcı Adı (E-posta)</label>
          <input type="email" name="email" required>
        </div>
        <div class="form-group">
          <label>Şifre</label>
          <input type="password" name="parola" required>
        </div>
        <div class="form-group">
          <button class="btn" type="submit">Devam Et</button>
        </div>
      </form>
      <a class="btn link-btn" style="background:#fff;color:#d70000;border:2px solid #d70000" href="<?php echo h(app_url('ziyaretci_sikayet_formu.php')); ?>">Ziyaretçi Şikayet Formu</a>
    </div>
    <div class="meta-box">
      QR Süresi Bitiş: <?php echo date('H:i:s',$qrSession['exp']); ?><br>
      Süre dolarsa yeniden QR okutmanız gerekir.
    </div>
  <?php endif; ?>

<?php endif; ?>
</body>
</html>