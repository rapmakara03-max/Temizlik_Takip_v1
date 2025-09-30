<?php
require_once __DIR__ . '/inc/header.php';

/*
  Değişiklik:
    - "Son Temizlik Kayıtları (20)" tablosunda önceki açıklama sütunu kaldırıldı,
      yerine her satır için Detay linki (temizlik_detay.php?id=...) eklendi.
*/

if(function_exists('require_role')) require_role(['GENEL','MUDUR','SEF']);

$id = (int)($_GET['id'] ?? 0);

$u = fetch_one("
  SELECT k.*,
         CONCAT_WS(' / ', b.ad, ka.ad, bi.ad) AS sorumlu_zincir
  FROM kullanicilar k
  LEFT JOIN birimler bi ON bi.id = k.sorumlu_birim_id
  LEFT JOIN katlar   ka ON ka.id = bi.kat_id
  LEFT JOIN binalar  b  ON b.id = ka.bina_id
  WHERE k.id=? AND k.rol='PERSONEL'
  LIMIT 1
", [$id]);

if(!$u){
  echo "<div class='alert alert-danger m-3'>Personel bulunamadı.</div>";
  require_once __DIR__.'/inc/footer.php';
  exit;
}

$LIMIT_TEMIZLIK = 20;
$LIMIT_GOREV    = 20;

/* Son Temizlik Kayıtları (limit 20) */
$tk = fetch_all("
  SELECT tk.id, tk.tarih, tk.aciklama, tk.isaretler,
         o.ad oda_ad, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad
  FROM temizlik_kayitlari tk
  LEFT JOIN odalar   o  ON o.id  = tk.oda_id
  LEFT JOIN binalar  b  ON b.id  = o.bina_id
  LEFT JOIN katlar   k  ON k.id  = o.kat_id
  LEFT JOIN birimler bi ON bi.id = o.birim_id
  WHERE tk.personel_id=?
  ORDER BY tk.tarih DESC
  LIMIT {$LIMIT_TEMIZLIK}
", [$id]);

/* Görevler (atanan olduğu) limit 20 */
$atananCol = null;
if(fetch_one("SHOW COLUMNS FROM gorevler LIKE 'assigned_user_id'")) {
  $atananCol = 'assigned_user_id';
} elseif(fetch_one("SHOW COLUMNS FROM gorevler LIKE 'atanan_personel_id'")) {
  $atananCol = 'atanan_personel_id';
}

$gorevler = [];
if($atananCol){
  $gorevler = fetch_all("
    SELECT g.id, g.baslik, g.durum, g.oda_id,
           o.ad oda_ad, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad
    FROM gorevler g
    LEFT JOIN odalar   o  ON o.id  = g.oda_id
    LEFT JOIN binalar  b  ON b.id  = o.bina_id
    LEFT JOIN katlar   k  ON k.id  = o.kat_id
    LEFT JOIN birimler bi ON bi.id = o.birim_id
    WHERE g.$atananCol=?
    ORDER BY g.id DESC
    LIMIT {$LIMIT_GOREV}
  ", [$id]);
}

/* Basit görev istatistikleri */
$totalG = 0; $doneG = 0;
foreach($gorevler as $g){
  $totalG++;
  if(in_array($g['durum'], ['TAMAMLANDI','TAMAM','KAPALI'], true)) $doneG++;
}
?>
<div class="card card-outline card-info">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title mb-0">Personel Detay - <?php echo h($u['ad']); ?></h3>
    <div class="ml-auto">
      <a href="<?php echo h(app_url('yonetim/rapor_personel_bolgeler.php')); ?>" class="btn btn-sm btn-secondary">&larr; Liste</a>
    </div>
  </div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">Ad</dt><dd class="col-sm-9"><?php echo h($u['ad']); ?></dd>
      <dt class="col-sm-3">E-posta</dt><dd class="col-sm-9"><?php echo h($u['email']); ?></dd>
      <dt class="col-sm-3">Telefon</dt><dd class="col-sm-9"><?php echo h($u['telefon'] ?? '-'); ?></dd>
      <dt class="col-sm-3">Görevi</dt><dd class="col-sm-9"><?php echo h($u['gorevi'] ?? '-'); ?></dd>
      <dt class="col-sm-3">Sorumlu Bölge</dt><dd class="col-sm-9"><?php echo h($u['sorumlu_zincir'] ?? '-'); ?></dd>
      <dt class="col-sm-3">Oluşturulma</dt><dd class="col-sm-9"><?php echo h($u['created_at'] ?? '-'); ?></dd>
      <dt class="col-sm-3">Aktif</dt>
      <dd class="col-sm-9">
        <?php echo (int)$u['aktif']===1
          ? '<span class="badge badge-success">Evet</span>'
          : '<span class="badge badge-secondary">Hayır</span>'; ?>
      </dd>
      <dt class="col-sm-3">Görev İstatistik</dt>
      <dd class="col-sm-9">
        Toplam: <?php echo (int)$totalG; ?> /
        Tamamlanan: <?php echo (int)$doneG; ?> /
        Aktif: <?php echo (int)($totalG - $doneG); ?>
      </dd>
    </dl>

    <hr>

    <h5 class="mb-2">Son Temizlik Kayıtları (<?php echo $LIMIT_TEMIZLIK; ?>)</h5>
    <div class="table-responsive mb-4">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tarih</th>
            <th>Konum</th>
            <th>İşaretler</th>
            <th>Detay</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($tk as $r): ?>
          <tr>
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo h($r['tarih']); ?></td>
            <td><?php echo h(
                ($r['bina_ad']??'-').' / '.
                ($r['kat_ad']??'-').' / '.
                (($r['birim_ad']??'-')?:'-').' / '.
                ($r['oda_ad']??'-')
            ); ?></td>
            <td>
              <?php
                if(!empty($r['isaretler'])){
                  foreach(explode(',',$r['isaretler']) as $tg){
                    $tg=trim($tg); if($tg==='') continue;
                    echo '<span class="badge badge-info mr-1">'.h($tg).'</span>';
                  }
                } else {
                  echo '-';
                }
              ?>
            </td>
            <td>
              <a class="btn btn-xs btn-primary"
                 href="<?php echo h(app_url('yonetim/temizlik_detay.php?id='.(int)$r['id'])); ?>">
                 Detay
              </a>
            </td>
          </tr>
        <?php endforeach; if(!$tk): ?>
          <tr><td colspan="5" class="text-muted">Kayıt yok.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <h5 class="mb-2">Son Görevler (<?php echo $LIMIT_GOREV; ?>)</h5>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Başlık</th>
            <th>Konum</th>
            <th>Durum</th>
            <th>Detay</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($gorevler as $g): ?>
          <tr>
            <td><?php echo (int)$g['id']; ?></td>
            <td><?php echo h($g['baslik']); ?></td>
            <td><?php echo h(
                ($g['bina_ad']??'-').' / '.
                ($g['kat_ad']??'-').' / '.
                (($g['birim_ad']??'-')?:'-').' / '.
                ($g['oda_ad']??'-')
            ); ?></td>
            <td>
              <?php
                $d   = $g['durum'] ?? '-';
                $cls = 'badge badge-secondary';
                if(in_array($d,['TAMAMLANDI','TAMAM','KAPALI'], true)) $cls='badge badge-success';
                elseif(in_array($d,['ATANDI','DEVAM'], true))          $cls='badge badge-warning';
                echo '<span class="'.$cls.'">'.h($d).'</span>';
              ?>
            </td>
            <td>
              <a class="btn btn-xs btn-primary"
                 href="<?php echo h(app_url('yonetim/gorev_detay.php?id='.(int)$g['id'])); ?>">
                 Aç
              </a>
            </td>
          </tr>
        <?php endforeach; if(!$gorevler): ?>
          <tr><td colspan="5" class="text-muted">Görev yok.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
<?php require_once __DIR__.'/inc/footer.php'; ?>