<?php
require_once __DIR__.'/inc/header.php';

if(function_exists('require_role')) require_role(['GENEL','MUDUR']);

function col_exists(string $t,string $c): bool {
    $r=fetch_one("SHOW COLUMNS FROM `$t` LIKE ?",[$c]);
    return (bool)$r;
}

$hasBirimFk = col_exists('kullanicilar','sorumlu_birim_id');
$hasTelefon = col_exists('kullanicilar','telefon');
$hasGorevi  = col_exists('kullanicilar','gorevi');
$hasBolgeTxt = col_exists('kullanicilar','sorumlu_bolge');

$q = trim($_GET['q'] ?? '');
$where="WHERE k.rol='PERSONEL'";
$params=[];
if($q!==''){
    $where.=" AND (k.ad LIKE ? OR k.email LIKE ?)";
    $like="%$q%"; $params=[$like,$like];
}

if($hasBirimFk){
    $rows=fetch_all("
      SELECT k.id,k.ad,k.email,
             ".($hasTelefon?'k.telefon,':'NULL AS telefon,')."
             ".($hasGorevi?'k.gorevi,':'NULL AS gorevi,')."
             CONCAT_WS(' / ',b.ad,ka.ad,bi.ad) AS bolge,
             k.aktif
      FROM kullanicilar k
      LEFT JOIN birimler bi ON bi.id=k.sorumlu_birim_id
      LEFT JOIN katlar ka ON ka.id=bi.kat_id
      LEFT JOIN binalar b ON b.id=ka.bina_id
      $where
      ORDER BY bolge IS NULL, bolge, k.ad
      LIMIT 50
    ",$params);
} else {
    $rows=fetch_all("
      SELECT k.id,k.ad,k.email,
             ".($hasTelefon?'k.telefon,':'NULL AS telefon,')."
             ".($hasGorevi?'k.gorevi,':'NULL AS gorevi,')."
             ".($hasBolgeTxt?'k.sorumlu_bolge':'NULL')." AS bolge,
             k.aktif
      FROM kullanicilar k
      $where
      ORDER BY bolge IS NULL, bolge, k.ad
      LIMIT 50
    ",$params);
}

$toplam = fetch_one("SELECT COUNT(*) c FROM kullanicilar WHERE rol='PERSONEL'")['c'] ?? 0;
?>
<div class="card card-outline card-primary">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title mb-0">Personel Sorumlu Bölge Raporu</h3>
    <div class="ml-auto">
      <form method="get" class="form-inline mb-0">
        <input type="text" name="q" class="form-control form-control-sm mr-2" placeholder="Ad / E-posta" value="<?php echo h($q); ?>">
        <button class="btn btn-sm btn-outline-light">Ara</button>
        <?php if($q!==''): ?>
          <a href="<?php echo h(current_path()); ?>" class="btn btn-sm btn-outline-secondary ml-2">Temizle</a>
        <?php endif; ?>
      </form>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Ad</th>
            <th>E-posta</th>
            <?php if($hasGorevi): ?><th>Görevi</th><?php endif; ?>
            <th>Sorumlu Bölge</th>
            <?php if($hasTelefon): ?><th>Telefon</th><?php endif; ?>
            <th>Aktif</th>
            <th style="width:80px;">Detay</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><?php echo h($r['ad']); ?></td>
              <td><?php echo h($r['email']); ?></td>
              <?php if($hasGorevi): ?><td><?php echo h($r['gorevi'] ?? ''); ?></td><?php endif; ?>
              <td><?php echo h($r['bolge'] ?: '-'); ?></td>
              <?php if($hasTelefon): ?><td><?php echo h($r['telefon'] ?? ''); ?></td><?php endif; ?>
              <td><?php echo (int)$r['aktif']===1?'<span class="badge badge-success">Evet</span>':'<span class="badge badge-secondary">Hayır</span>'; ?></td>
              <td>
                <a href="<?php echo h(app_url('yonetim/personel_islemler.php?id='.(int)$r['id'])); ?>" class="btn btn-xs btn-info">Detay</a>
              </td>
            </tr>
          <?php endforeach; if(!$rows): ?>
            <tr><td colspan="<?php echo 7+($hasGorevi?1:0)+($hasTelefon?1:0); ?>" class="text-muted">Kayıt yok.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer small d-flex justify-content-between">
    <div><strong>Toplam PERSONEL:</strong> <?php echo (int)$toplam; ?> (İlk 50)</div>
    <div>Sıralama: Bölge → Ad</div>
  </div>
</div>
<?php require_once __DIR__.'/inc/footer.php'; ?>