<?php
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/../ortak/sms_netgsm.php';

/*
 * Şikayet Atama (revizyon):
 * - İstenen başlık dizilimi ve alanlar.
 * - Yakınlık kaldırıldı; tüm aktif personeller (opsiyonel arama).
 * - Dinamik atama kolonu (assigned_user_id / atanan_personel_id) desteği.
 * - Arama: ad / email / telefon.
 * - Personeller listelenmiyorsa arama boşken de getirilecek şekilde düzenlendi.
 */

function gorevler_has(string $col): bool {
    static $c=[];
    if(isset($c[$col])) return $c[$col];
    $r=fetch_one("SHOW COLUMNS FROM gorevler LIKE ?",[$col]);
    return $c[$col]=(bool)$r;
}
function gorev_atanan_col(): ?string {
    if(gorevler_has('assigned_user_id')) return 'assigned_user_id';
    if(gorevler_has('atanan_personel_id')) return 'atanan_personel_id';
    return null;
}
$colAtanan = gorev_atanan_col();

/* Şikayeti çek */
$sid = (int)($_GET['id'] ?? 0);
$sikayet = fetch_one("
  SELECT s.*,
         o.id  AS oda_id,  o.ad  AS oda_ad,
         b.id  AS bina_id, b.ad  AS bina_ad,
         k.id  AS kat_id,  k.ad  AS kat_ad,
         bi.id AS birim_id, bi.ad AS birim_ad
  FROM sikayetler s
  LEFT JOIN odalar   o  ON o.id = s.oda_id
  LEFT JOIN binalar  b  ON b.id = o.bina_id
  LEFT JOIN katlar   k  ON k.id = o.kat_id
  LEFT JOIN birimler bi ON bi.id = o.birim_id
  WHERE s.id=?
",[$sid]);

if(!$sikayet){
    echo "<div class='alert alert-danger m-3'>Şikayet bulunamadı.</div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

/* Konum formatı */
$konumTam = implode(' / ', array_filter([
    $sikayet['bina_ad'] ?? '',
    $sikayet['kat_ad'] ?? '',
    $sikayet['birim_ad'] ?? '',
    $sikayet['oda_ad'] ?? ''
]));

/* Kısa konu (mesajın başı) */
$konuOzet = mb_strimwidth($sikayet['mesaj'] ?? '', 0, 60, '…','UTF-8');

/* Atama gönderimi */
if(is_post()){
    csrf_check();
    $pid = (int)($_POST['personel_id'] ?? 0);
    $personel = $pid>0
        ? fetch_one("SELECT id, ad, telefon FROM kullanicilar WHERE id=? AND rol='PERSONEL' LIMIT 1",[$pid])
        : null;
    if(!$personel){
        flash_set('error','Geçerli bir personel seçiniz.');
        redirect(current_path().'?id='.$sid);
    }

    // Var olan görev?
    $gorev = gorevler_has('sikayet_id')
        ? fetch_one("SELECT id FROM gorevler WHERE sikayet_id=? LIMIT 1",[$sid])
        : null;

    $ok=false;
    try{
        if($gorev){
            $sets=[]; $prm=[];
            if($colAtanan){ $sets[]="$colAtanan=?"; $prm[]=$personel['id']; }
            if(gorevler_has('durum')){ $sets[]="durum=?"; $prm[]='ATANDI'; }
            if(gorevler_has('updated_at')) $sets[]="updated_at=NOW()";
            if($sets){
                $prm[]=$gorev['id'];
                $ok=exec_stmt("UPDATE gorevler SET ".implode(', ',$sets)." WHERE id=?",$prm);
            }
        } else {
            $cols=[]; $ph=[]; $vals=[];
            if(gorevler_has('baslik')){ $cols[]='baslik'; $ph[]='?'; $vals[]='Şikayet #'.$sid; }
            if(gorevler_has('durum')) { $cols[]='durum';  $ph[]='?'; $vals[]='ATANDI'; }
            if(gorevler_has('sikayet_id')){ $cols[]='sikayet_id'; $ph[]='?'; $vals[]=$sid; }
            if($colAtanan){ $cols[]=$colAtanan; $ph[]='?'; $vals[]=$personel['id']; }
            foreach(['bina_id'=>$sikayet['bina_id'] ?? null,
                     'kat_id'=>$sikayet['kat_id'] ?? null,
                     'birim_id'=>$sikayet['birim_id'] ?? null,
                     'oda_id'=>$sikayet['oda_id'] ?? null] as $c=>$v){
                if($v!==null && gorevler_has($c)){
                    $cols[]=$c; $ph[]='?'; $vals[]=$v;
                }
            }
            if(gorevler_has('created_at')){ $cols[]='created_at'; $ph[]='NOW()'; }
            if(gorevler_has('updated_at')){ $cols[]='updated_at'; $ph[]='NOW()'; }

            if(!$cols){
                flash_set('error','Görev oluşturulacak uygun kolon bulunamadı.');
                redirect(current_path().'?id='.$sid);
            }
            $bind=[]; $sqlPh=[]; $i=0;
            foreach($ph as $p){
                if($p==='NOW()') $sqlPh[]='NOW()';
                else { $sqlPh[]='?'; $bind[]=$vals[$i++]; }
            }
            $sql="INSERT INTO gorevler (".implode(',',$cols).") VALUES (".implode(',',$sqlPh).")";
            $ok=exec_stmt($sql,$bind);
        }
    }catch(Throwable $e){
        $ok=false;
        error_log('Sikayet atama hata: '.$e->getMessage());
    }

    if($ok){
        if(!empty($personel['telefon'])){
            $smsMetin="Sn. ".$personel['ad'].", ".$konumTam." 'sına görev/şikayet ataması yapılmıştır. Lütfen ilgili odaya giderek QR Kod okutup işlemi tamamlayınız. Müdüriyet";
            send_sms($personel['telefon'],$smsMetin);
        }
        flash_set('success','Atama kaydedildi.');
        redirect(app_url('yonetim/sikayetler.php'));
    } else {
        flash_set('error','Atama kaydedilemedi.');
    }
}

/* Personeller (yakınlık yok, düz liste + arama) */
$qPer = trim($_GET['qper'] ?? '');
$where = " WHERE rol='PERSONEL' AND aktif=1 ";
$params=[];
if($qPer!==''){
    $where.=" AND (ad LIKE ? OR email LIKE ? OR telefon LIKE ?) ";
    $like="%$qPer%";
    $params=[$like,$like,$like];
}
$personeller = fetch_all("SELECT id, ad, email, telefon, gorevi FROM kullanicilar $where ORDER BY ad LIMIT 200",$params);

?>
<div class="card card-outline card-primary">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title mb-0">Şikayet Atama (#<?php echo (int)$sid; ?>)</h3>
    <div class="ml-auto">
      <a href="<?php echo h(app_url('yonetim/sikayetler.php')); ?>" class="btn btn-sm btn-secondary">&larr; Liste</a>
    </div>
  </div>
  <div class="card-body">
    <?php foreach(flash_get_all() as $f): ?>
      <div class="alert alert-<?php echo $f['t']==='error'?'danger':($f['t']==='success'?'success':'info'); ?> py-2">
        <?php echo h($f['m']); ?>
      </div>
    <?php endforeach; ?>

    <h5 class="mb-3">Şikayet Bilgileri</h5>
    <dl class="row mb-4 small">
      <dt class="col-sm-3">Şikayet Eden Ad Soyad</dt>
      <dd class="col-sm-9"><?php echo h($sikayet['ad_soyad'] ?? '-'); ?></dd>

      <dt class="col-sm-3">Şikayet Konusu</dt>
      <dd class="col-sm-9"><?php echo h($konuOzet ?: '-'); ?></dd>

      <dt class="col-sm-3">Şikayet Konumu</dt>
      <dd class="col-sm-9"><?php echo h($konumTam ?: '-'); ?></dd>

      <dt class="col-sm-3">Şikayet Mesajı</dt>
      <dd class="col-sm-9">
        <div class="border rounded bg-light p-2" style="white-space:pre-wrap;"><?php echo h($sikayet['mesaj'] ?? '-'); ?></div>
      </dd>

      <dt class="col-sm-3">Şikayet Foto 1</dt>
      <dd class="col-sm-9">
        <?php if(!empty($sikayet['foto1'])): ?>
          <a href="<?php echo h($sikayet['foto1']); ?>" target="_blank" class="btn btn-xs btn-outline-primary">Fotoğrafı Gör</a>
        <?php else: ?>
          <span class="text-muted">-</span>
        <?php endif; ?>
      </dd>

      <dt class="col-sm-3">Şikayet Foto 2</dt>
      <dd class="col-sm-9">
        <?php if(!empty($sikayet['foto2'])): ?>
          <a href="<?php echo h($sikayet['foto2']); ?>" target="_blank" class="btn btn-xs btn-outline-primary">Fotoğrafı Gör</a>
        <?php else: ?>
          <span class="text-muted">-</span>
        <?php endif; ?>
      </dd>
    </dl>

    <form method="get" class="form-inline mb-3">
      <input type="hidden" name="id" value="<?php echo (int)$sid; ?>">
      <input type="text" name="qper" value="<?php echo h($qPer); ?>" class="form-control form-control-sm mr-2" placeholder="Personel ara (ad/email/telefon)">
      <button class="btn btn-sm btn-outline-primary">Ara</button>
      <?php if($qPer!==''): ?>
        <a href="<?php echo h(current_path().'?id='.(int)$sid); ?>" class="btn btn-sm btn-outline-secondary ml-2">Temizle</a>
      <?php endif; ?>
    </form>

    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo (int)$sid; ?>">
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead>
            <tr>
              <th style="width:30px;"></th>
              <th>Ad</th>
              <th>E-posta</th>
              <th>Telefon</th>
              <th>Görevi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($personeller as $p): ?>
              <tr>
                <td><input type="radio" name="personel_id" value="<?php echo (int)$p['id']; ?>" required></td>
                <td><?php echo h($p['ad']); ?></td>
                <td><?php echo h($p['email']); ?></td>
                <td><?php echo h($p['telefon'] ?? ''); ?></td>
                <td><?php echo h($p['gorevi'] ?? ''); ?></td>
              </tr>
            <?php endforeach; if(!$personeller): ?>
              <tr><td colspan="5" class="text-muted">Personel bulunamadı.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="text-right mt-3">
        <button class="btn btn-primary">Atamayı Kaydet ve SMS Gönder</button>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__.'/inc/footer.php'; ?>