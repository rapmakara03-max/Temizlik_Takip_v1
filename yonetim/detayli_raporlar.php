<?php
require_once __DIR__ . '/inc/header.php';

/*
  Detaylı Raporlar:
  - Varsayılan tarih aralığı: son 7 gün (bugün dahil)
  - Filtreler: başlangıç (bas), bitiş (bit), personel (pid), arama (q)
  - Sayfalama: 50 sabit
  - Çıktı: temizlik_kayitlari kayıtları (tarih DESC)
*/

$today = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime('-7 days'));

$bas = $_GET['bas'] ?? $defaultStart;
$bit = $_GET['bit'] ?? $today;
$pid = (int)($_GET['pid'] ?? 0);
$q   = trim($_GET['q'] ?? '');

$errors = [];
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bas)) $errors[] = 'Başlangıç tarihi geçersiz.';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bit)) $errors[] = 'Bitiş tarihi geçersiz.';
if (!$errors) {
    if (strtotime($bas) > strtotime($bit)) {
        $errors[] = 'Başlangıç tarihi bitişten büyük olamaz.';
    }
}

// Personel listesi (aktif personel + şef vs.)
$personeller = fetch_all("SELECT id, ad FROM kullanicilar WHERE rol IN('PERSONEL','SEF','MUDUR','GENEL') AND aktif=1 ORDER BY ad");

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 50;
$offset = ($page - 1) * $per;

$where = " WHERE 1=1 ";
$params = [];

// Tarih aralığı (hatalı değilse)
if (!$errors) {
    $where .= " AND DATE(tk.tarih) BETWEEN ? AND ? ";
    $params[] = $bas;
    $params[] = $bit;
}

// Personel filtresi
if ($pid > 0) {
    $where .= " AND tk.personel_id=? ";
    $params[] = $pid;
}

// Arama (oda, bina, kat, birim, personel adı)
if ($q !== '') {
    $like = "%$q%";
    $where .= " AND (o.ad LIKE ? OR b.ad LIKE ? OR k.ad LIKE ? OR bi.ad LIKE ? OR u.ad LIKE ?)";
    array_push($params, $like,$like,$like,$like,$like);
}

// Toplam
$totalRow = fetch_one("
  SELECT COUNT(*) c
  FROM temizlik_kayitlari tk
  LEFT JOIN odalar o ON o.id=tk.oda_id
  LEFT JOIN binalar b ON b.id=o.bina_id
  LEFT JOIN katlar k ON k.id=o.kat_id
  LEFT JOIN birimler bi ON bi.id=o.birim_id
  LEFT JOIN kullanicilar u ON u.id=tk.personel_id
  $where
", $params);
$total = (int)($totalRow['c'] ?? 0);

// Kayıtlar
$rows = fetch_all("
 SELECT tk.id, tk.tarih, tk.isaretler, tk.aciklama,
        u.ad AS personel_ad,
        o.ad AS oda_ad,
        b.ad AS bina_ad,
        k.ad AS kat_ad,
        bi.ad AS birim_ad
 FROM temizlik_kayitlari tk
 LEFT JOIN odalar o ON o.id=tk.oda_id
 LEFT JOIN binalar b ON b.id=o.bina_id
 LEFT JOIN katlar k ON k.id=o.kat_id
 LEFT JOIN birimler bi ON bi.id=o.birim_id
 LEFT JOIN kullanicilar u ON u.id=tk.personel_id
 $where
 ORDER BY tk.tarih DESC
 LIMIT $per OFFSET $offset
", $params);

$pages = (int)ceil($total / $per);

// Özet (gösterim amaçlı)
$ozet = fetch_all("
  SELECT u.ad personel_ad, COUNT(*) say
  FROM temizlik_kayitlari tk
  LEFT JOIN kullanicilar u ON u.id=tk.personel_id
  $where
  GROUP BY tk.personel_id
  ORDER BY say DESC
  LIMIT 10
", $params);
?>
<div class="card card-outline card-dark">
  <div class="card-header">
    <h3 class="card-title mb-0">Detaylı Temizlik Raporları</h3>
    <div class="card-tools">
      <a href="<?php echo h(app_url('yonetim/raporlar.php')); ?>" class="btn btn-tool"><i class="fas fa-arrow-left"></i></a>
    </div>
  </div>
  <div class="card-body">
    <?php if($errors): ?>
      <div class="alert alert-danger">
        <?php foreach($errors as $e) echo '<div>'.h($e).'</div>'; ?>
      </div>
    <?php endif; ?>

    <form method="get" class="mb-3">
      <div class="form-row">
        <div class="col-md-2 col-6 mb-2">
          <label class="small mb-1">Başlangıç</label>
          <input type="date" name="bas" value="<?php echo h($bas); ?>" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 col-6 mb-2">
          <label class="small mb-1">Bitiş</label>
          <input type="date" name="bit" value="<?php echo h($bit); ?>" class="form-control form-control-sm">
        </div>
        <div class="col-md-3 mb-2">
          <label class="small mb-1">Personel</label>
          <select name="pid" class="form-control form-control-sm">
            <option value="0">(Hepsi)</option>
            <?php foreach($personeller as $p): ?>
              <option value="<?php echo (int)$p['id']; ?>" <?php echo selected($pid,$p['id']); ?>>
                <?php echo h($p['ad']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 mb-2">
          <label class="small mb-1">Arama (Konum / Personel)</label>
          <input type="text" name="q" value="<?php echo h($q); ?>" class="form-control form-control-sm" placeholder="oda / bina / personel...">
        </div>
        <div class="col-md-2 mb-2 d-flex align-items-end">
          <button class="btn btn-sm btn-primary btn-block">Uygula</button>
        </div>
      </div>
      <?php if($bas!==$defaultStart || $bit!==$today || $pid>0 || $q!==''): ?>
        <a href="<?php echo h(current_path()); ?>" class="btn btn-sm btn-outline-secondary">Sıfırla</a>
      <?php endif; ?>
      <!--
      İstersen CSV export:
      <button name="export" value="csv" class="btn btn-sm btn-outline-success">CSV</button>
      -->
    </form>

    <div class="mb-3 small">
      Toplam Kayıt: <strong><?php echo h($total); ?></strong>
      <?php if($pid>0): ?> | Filtrelenen Personel ID: <?php echo (int)$pid; ?><?php endif; ?>
      | Sayfa: <?php echo h($page); ?>/<?php echo h($pages ?: 1); ?>
    </div>

    <?php if($ozet): ?>
      <div class="mb-3">
        <h6 class="mb-2">İlk 10 Personel (Kayıt Sayısına Göre)</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
              <tr><th>Personel</th><th>Kayıt</th></tr>
            </thead>
            <tbody>
              <?php foreach($ozet as $o): ?>
                <tr>
                  <td><?php echo h($o['personel_ad'] ?: '-'); ?></td>
                  <td><?php echo h($o['say']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover mb-0">
        <thead class="thead-light">
          <tr>
            <th>ID</th>
            <th>Tarih</th>
            <th>Personel</th>
            <th>Konum</th>
            <th>İşaretler</th>
            <th>Açıklama</th>
            <th>Detay</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?php echo h($r['id']); ?></td>
            <td><?php echo h($r['tarih']); ?></td>
            <td><?php echo h($r['personel_ad'] ?: '-'); ?></td>
            <td><?php echo h(($r['bina_ad']??'-').'/'.($r['kat_ad']??'-').'/'.(($r['birim_ad']??'-')?:'-').'/'.($r['oda_ad']??'-')); ?></td>
            <td>
              <?php
                if($r['isaretler']){
                  foreach(explode(',',$r['isaretler']) as $tag){
                    $tag=trim($tag); if($tag==='') continue;
                    echo '<span class="badge badge-info mr-1">'.h($tag).'</span>';
                  }
                } else echo '<span class="text-muted">-</span>';
              ?>
            </td>
            <td style="max-width:180px;">
              <div class="small" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?php echo h($r['aciklama'] ?? ''); ?>
              </div>
            </td>
            <td>
              <a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/temizlik_detay.php?id='.$r['id'])); ?>">Detay</a>
            </td>
          </tr>
        <?php endforeach; if(!$rows): ?>
          <tr><td colspan="7" class="text-muted">Kayıt yok.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if($pages>1): ?>
      <nav class="mt-3">
        <ul class="pagination pagination-sm mb-0">
          <?php
            for($i=1;$i<=$pages;$i++):
              $qs = $_GET; $qs['page']=$i;
              $active = $i===$page?' active':'';
              echo '<li class="page-item'.$active.'"><a class="page-link" href="?'.h(http_build_query($qs)).'">'.$i.'</a></li>';
            endfor;
          ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>