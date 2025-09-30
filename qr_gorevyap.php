<?php
// (Ön bölüm: oturum, yetki, yardımcı includelar aynı kalsın)
require_once __DIR__ . '/ortak/oturum.php';
require_once __DIR__ . '/ortak/sabitler.php';
require_once __DIR__ . '/ortak/yetki.php';
require_once __DIR__ . '/ortak/csrf.php';
require_once __DIR__ . '/ortak/db_helpers.php';

$u = current_user();
if (!$u || $u['rol'] !== 'PERSONEL') {
    echo "<div class='alert alert-warning m-3'>Lütfen giriş yapınız.</div>";
    exit;
}

$qrSession = $_SESSION['qr_ok'] ?? null;
if (!$qrSession || time() > ($qrSession['exp'] ?? 0)) {
    unset($_SESSION['qr_ok']);
    flash_set('error', 'QR süresi doldu.');
    redirect(app_url());
}

$odaId = ($qrSession['scope'] ?? '') === 'oda'
  ? (int)($qrSession['scope_id'] ?? 0)
  : (int)($qrSession['oda_id'] ?? 0);
if ($odaId <= 0) {
    echo "<div class='alert alert-danger m-3'>Oda bulunamadı.</div>";
    exit;
}

$gid = (int)($_GET['g'] ?? ($_GET['id'] ?? 0));
if ($gid <= 0) {
    echo "<div class='alert alert-danger m-3'>Geçersiz görev.</div>";
    exit;
}

// Görev (şikayet ilişkisi ile birlikte)
$g = fetch_one("
  SELECT g.*,
         s.id AS sikayet_id_ref,
         o.id AS oda_id, o.ad AS oda_ad
  FROM gorevler g
  LEFT JOIN sikayetler s ON s.id = g.sikayet_id
  LEFT JOIN odalar o ON o.id=g.oda_id
  WHERE g.id=?
",[$gid]);
if(!$g){
    echo "<div class='alert alert-danger m-3'>Görev bulunamadı.</div>";
    exit;
}

// Kolon var mı kontrolü
function tk_has(string $col): bool {
    static $c=[];
    if(isset($c[$col])) return $c[$col];
    $row=fetch_one("SHOW COLUMNS FROM temizlik_kayitlari LIKE ?",[$col]);
    return $c[$col]=(bool)$row;
}

if(is_post()){
    csrf_check();
    $aciklama = trim($_POST['aciklama'] ?? '');
    $isaretlerArr=[];
    foreach(['zemin','cam','cop','toz','dezenfeksiyon'] as $k){
        if(!empty($_POST['isaret_'.$k])) $isaretlerArr[]=$k;
    }
    $isaretler = $isaretlerArr ? implode(',',$isaretlerArr) : null;

    $temizlikDir = ensure_upload_dir().'/temizlik';
    if(!is_dir($temizlikDir)) @mkdir($temizlikDir,0775,true);

    $paths=[null,null];
    for($i=1;$i<=2;$i++){
        if(!empty($_FILES["foto$i"]['name']) && $_FILES["foto$i"]['error']===UPLOAD_ERR_OK){
            if($_FILES["foto$i"]['size'] > 15*1024*1024){
                flash_set('error',"Fotoğraf $i 15MB üzeri.");
                redirect(current_path().'?g='.$gid);
            }
            $fname='tsk_'.date('Ymd_His').'_'.$u['id'].'_'.$gid.'_'.$i.'_'.bin2hex(random_bytes(4)).'.jpg';
            $dest=$temizlikDir.'/'.$fname;
            $tmp=$_FILES["foto$i"]['tmp_name'];
            if(!@move_uploaded_file($tmp,$dest)){
                $data=@file_get_contents($tmp);
                if($data!==false) file_put_contents($dest,$data);
            }
            if(is_file($dest)){
                $paths[$i-1]='temizlik/'.$fname;
            } elseif($i===1){
                flash_set('error','Birinci foto yüklenemedi.');
                redirect(current_path().'?g='.$gid);
            }
        }
    }

    // Dinamik kolon seti
    $cols = ['oda_id','personel_id','tarih','foto_yol','foto_yol2','aciklama','isaretler'];
    $ph   = ['?','?','NOW()','?','?','?','?'];
    $vals = [($g['oda_id'] ?: $odaId), $u['id'], $paths[0], $paths[1], $aciklama, $isaretler];

    if(tk_has('gorev_id')){
        $cols[]='gorev_id'; $ph[]='?'; $vals[]=$gid;
    }
    if(tk_has('sikayet_id') && !empty($g['sikayet_id'])){
        $cols[]='sikayet_id'; $ph[]='?'; $vals[]=(int)$g['sikayet_id'];
    }

    $sql="INSERT INTO temizlik_kayitlari (".implode(',',$cols).") VALUES (".implode(',',$ph).")";
    $ok=exec_stmt($sql,$vals);

    if($ok){
        // Görevi otomatik 'DEVAM' a çek (ilk kayıt ise)
        if(gorev_has('durum') && in_array($g['durum'],['YENI','ATANDI'])){
            exec_stmt("UPDATE gorevler SET durum='DEVAM'".(gorev_has('updated_at')?",updated_at=NOW()":"")." WHERE id=?",[$gid]);
        }
        flash_set('success','Kayıt eklendi.');
    } else {
        flash_set('error','Kayıt başarısız.');
    }
    redirect(current_path().'?g='.$gid);
}

// (Form HTML burada devam eder – mevcut tasarımına uyarlayabilirsin)
echo "<div class='m-3'><h3>Görev #".h($g['id'])." - Oda #".h($g['oda_id'])."</h3><p>Buraya mevcut formunuz gelecek.</p></div>";
?>