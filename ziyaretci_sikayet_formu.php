<?php
require_once __DIR__.'/ortak/oturum.php';
require_once __DIR__.'/ortak/sabitler.php';
require_once __DIR__.'/ortak/csrf.php';
require_once __DIR__.'/ortak/db_helpers.php';
require_once __DIR__.'/ortak/guvenlik.php';

$qrSession = $_SESSION['qr_ok'] ?? null;
if(!$qrSession){
    flash_set('error','Lütfen QR kod okutarak giriş yapın.');
    redirect(app_url());
}
$oda_id = ($qrSession['scope']==='oda') ? ($qrSession['scope_id'] ?? 0) : ($qrSession['oda_id'] ?? 0);
if($oda_id <= 0){
    flash_set('error','Önce oda seçmelisiniz.');
    redirect(app_url());
}

if(is_post()){
    csrf_check();

    $ad      = trim($_POST['ad_soyad'] ?? '');
    $tel     = trim($_POST['telefon'] ?? '');
    $mesaj   = trim($_POST['mesaj'] ?? '');

    // Zorunlu alan kontrolü
    if($ad === '' || $tel === '' || $mesaj === ''){
        flash_set('error','Ad Soyad, Telefon ve Mesaj alanları zorunludur.');
        redirect(app_url('ziyaretci_sikayet_formu.php'));
    }

    // Upload dizinleri
    $baseUpload = rtrim(getenv('UPLOAD_DIR') ?: __DIR__.'/uploads','/');
    $subDir = $baseUpload.'/sikayet';
    if(!is_dir($subDir)){
        @mkdir($subDir,0775,true);
    }

    $foto1 = null;
    $foto2 = null;

    // Fotoğraf işleme fonksiyonu (JPG'e dönüştürme)
    $processPhoto = function(string $tmpPath, string $destPath): bool {
        if(!function_exists('imagecreatefromstring')) {
            return move_uploaded_file($tmpPath,$destPath);
        }
        $data = @file_get_contents($tmpPath);
        if($data === false) return false;
        $im = @imagecreatefromstring($data);
        if(!$im) return move_uploaded_file($tmpPath,$destPath);
        // Çok büyük görüntülerde hafıza sorunu riskini azaltmak için opsiyonel yeniden boyut (gerekirse):
        // (Şu an dokunmuyoruz, sadece JPEG sıkıştırıyoruz)
        $ok = imagejpeg($im,$destPath,85);
        imagedestroy($im);
        return $ok;
    };

    // Foto 1 ZORUNLU
    if(empty($_FILES['foto1']['name']) || $_FILES['foto1']['error'] !== UPLOAD_ERR_OK){
        flash_set('error','Fotoğraf 1 zorunludur.');
        redirect(app_url('ziyaretci_sikayet_formu.php'));
    } else {
        if($_FILES['foto1']['size'] > 10*1024*1024){
            flash_set('error','Fotoğraf 1 boyutu 10MB sınırını aşıyor.');
            redirect(app_url('ziyaretci_sikayet_formu.php'));
        }
        $fname1 = 'sk_'.date('Ymd_His').'_1_'.bin2hex(random_bytes(4)).'.jpg';
        $dest1  = $subDir.'/'.$fname1;
        if(!$processPhoto($_FILES['foto1']['tmp_name'],$dest1)){
            flash_set('error','Fotoğraf 1 işlenemedi.');
            redirect(app_url('ziyaretci_sikayet_formu.php'));
        }
        $foto1 = 'sikayet/'.$fname1; // relatif yol
    }

    // Foto 2 OPSİYONEL
    if(!empty($_FILES['foto2']['name']) && $_FILES['foto2']['error'] === UPLOAD_ERR_OK){
        if($_FILES['foto2']['size'] > 10*1024*1024){
            flash_set('error','Fotoğraf 2 boyutu 10MB sınırını aşıyor.');
            redirect(app_url('ziyaretci_sikayet_formu.php'));
        }
        $fname2 = 'sk_'.date('Ymd_His').'_2_'.bin2hex(random_bytes(4)).'.jpg';
        $dest2  = $subDir.'/'.$fname2;
        if($processPhoto($_FILES['foto2']['tmp_name'],$dest2)){
            $foto2 = 'sikayet/'.$fname2;
        } else {
            // Foto2 başarısız olursa tamamen iptal etmek istemiyorsanız yorum satırındaki satırı açabilirsiniz.
            // flash_set('error','Fotoğraf 2 işlenemedi.');
            // redirect(app_url('ziyaretci_sikayet_formu.php'));
        }
    }

    $sql = "INSERT INTO sikayetler(ad_soyad,telefon,mesaj,oda_id,durum,olusturma_tarihi,foto1,foto2)
            VALUES(?,?,?,?, 'YENI', NOW(),?,?)";
    $params = [$ad,$tel,$mesaj,$oda_id,$foto1,$foto2];

    $ok = exec_stmt($sql,$params);
    if($ok){
        flash_set('success','Şikayet kaydedildi.');
        redirect(app_url());
    } else {
        if(function_exists('db')){
            try{
                $pdo = db();
                if($pdo){
                    $err = $pdo->errorInfo();
                    error_log('Şikayet INSERT hata: '.json_encode([
                        'sql'=>$sql,
                        'params'=>$params,
                        'error'=>$err
                    ],JSON_UNESCAPED_UNICODE));
                }
            }catch(Throwable $e){
                error_log('Şikayet kaydı hata (pdo erişimi): '.$e->getMessage());
            }
        }
        flash_set('error','Şikayet kaydedilemedi.');
        redirect(app_url('ziyaretci_sikayet_formu.php'));
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Ziyaretçi Şikayet</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:Arial,sans-serif;background:#f1f1f1;margin:0;padding:20px;}
.container{max-width:480px;margin:0 auto;background:#fff;padding:25px;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.15);}
h1{margin:0 0 20px;font-size:22px;text-align:center;}
.form-group{margin-bottom:15px;}
.form-group label{display:block;font-size:13px;margin-bottom:6px;color:#444;}
.form-group input,textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:14px;}
textarea{min-height:90px;resize:vertical;}
.btn{width:100%;background:#d70000;color:#fff;border:none;border-radius:6px;padding:12px;font-weight:600;cursor:pointer;}
.flash{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:15px;}
.flash.success{background:#e6ffec;border:1px solid #9ad7aa;}
.flash.error{background:#ffe5e5;border:1px solid #ff9d9d;}
.back{text-align:center;margin-top:12px;}
.note{font-size:11px;color:#666;margin-top:6px;line-height:1.3;}
</style>
</head>
<body>
<div class="container">
  <h1>Ziyaretçi Şikayet</h1>
  <?php foreach(flash_get_all() as $f): ?>
    <div class="flash <?php echo h($f['t']); ?>"><?php echo h($f['m']); ?></div>
  <?php endforeach; ?>
  <form method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <div class="form-group">
      <label>Ad Soyad *</label>
      <input type="text" name="ad_soyad" required>
    </div>
    <div class="form-group">
      <label>Telefon *</label>
      <input type="text" name="telefon" required>
    </div>
    <div class="form-group">
      <label>Mesaj *</label>
      <textarea name="mesaj" required></textarea>
    </div>
    <div class="form-group">
      <label>Fotoğraf 1 (Zorunlu)</label>
      <input type="file" name="foto1" accept="image/*" required>
    </div>
    <div class="form-group">
      <label>Fotoğraf 2 (Opsiyonel)</label>
      <input type="file" name="foto2" accept="image/*">
    </div>
    <div class="note">
      Maksimum dosya boyutu: 10MB. Yüklenen fotoğraflar JPG formatında saklanır.
    </div>
    <button class="btn">Gönder</button>
  </form>
  <div class="back"><a href="<?php echo h(app_url()); ?>">Geri Dön</a></div>
</div>
</body>
</html>