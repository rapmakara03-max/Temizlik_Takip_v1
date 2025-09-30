<?php
require_once __DIR__ . '/inc/header.php';

$oda_id = (int)($_GET['oda'] ?? 0);
$personel_id = (int)($_GET['personel'] ?? 0);
$page = max(1,(int)($_GET['page']??1));
$per  = 50;
$offset = ($page-1)*$per;

$header=''; $rows=[]; $total=0;

if ($oda_id) {
    $oda = fetch_one("SELECT o.*, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad
        FROM odalar o
        LEFT JOIN binalar b ON b.id=o.bina_id
        LEFT JOIN katlar k ON k.id=o.kat_id
        LEFT JOIN birimler bi ON bi.id=o.birim_id
        WHERE o.id=?",[$oda_id]);
    if(!$oda){
        echo "<div class='alert alert-danger'>Oda bulunamadı.</div>";
        require_once __DIR__.'/inc/footer.php'; exit;
    }
    $totalRow = fetch_one("SELECT COUNT(*) c FROM temizlik_kayitlari WHERE oda_id=?",[$oda_id]);
    $total=(int)($totalRow['c']??0);
    $rows=fetch_all("SELECT tk.id, tk.tarih, u.ad personel_ad
      FROM temizlik_kayitlari tk
      LEFT JOIN kullanicilar u ON u.id=tk.personel_id
      WHERE tk.oda_id=?
      ORDER BY tk.tarih DESC
      LIMIT $per OFFSET $offset",[$oda_id]);
    $header="Oda Temizlik Personel Detay: ".h($oda['bina_ad'])." / ".h($oda['kat_ad'])." / ".h($oda['birim_ad']?:'-')." / ".h($oda['ad']);
} elseif ($personel_id) {
    $p = fetch_one("SELECT * FROM kullanicilar WHERE id=?",[$personel_id]);
    if(!$p){
        echo "<div class='alert alert-danger'>Personel bulunamadı.</div>";
        require_once __DIR__.'/inc/footer.php'; exit;
    }
    $totalRow = fetch_one("SELECT COUNT(*) c FROM temizlik_kayitlari WHERE personel_id=?",[$personel_id]);
    $total=(int)($totalRow['c']??0);
    $rows=fetch_all("SELECT tk.id, tk.tarih, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad, o.ad oda_ad
      FROM temizlik_kayitlari tk
      LEFT JOIN odalar o ON o.id=tk.oda_id
      LEFT JOIN binalar b ON b.id=o.bina_id
      LEFT JOIN katlar k ON k.id=o.kat_id
      LEFT JOIN birimler bi ON bi.id=o.birim_id
      WHERE tk.personel_id=?
      ORDER BY tk.tarih DESC
      LIMIT $per OFFSET $offset",[$personel_id]);
    $header="Personel Temizlik Detay: ".h($p['ad']);
} else {
    echo "<div class='alert alert-info'>Parametre eksik.</div>";
    require_once __DIR__.'/inc/footer.php'; exit;
}

$pages=(int)ceil($total/$per);
?>
<div class="card card-outline card-success">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h3 class="card-title mb-0"><?php echo $header; ?></h3>
    <div class="d-flex align-items-center">
      <button class="btn btn-sm btn-outline-secondary mr-2" onclick="window.print()">Yazdır</button>
      <a href="<?php echo h(app_url('yonetim/raporlar.php')); ?>" class="btn btn-tool"><i class="fas fa-arrow-left"></i></a>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover mb-0">
        <thead class="thead-light">
          <tr>
            <th>ID</th>
            <?php if($personel_id): ?><th>Konum</th><?php else: ?><th>Personel</th><?php endif; ?>
            <th>Tarih</th>
            <th>Detay</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?php echo h($r['id']); ?></td>
            <?php if($personel_id): ?>
              <td><?php echo h($r['bina_ad']); ?> / <?php echo h($r['kat_ad']); ?> / <?php echo h($r['birim_ad']?:'-'); ?> / <?php echo h($r['oda_ad']); ?></td>
            <?php else: ?>
              <td><?php echo h($r['personel_ad']); ?></td>
            <?php endif; ?>
            <td><?php echo h($r['tarih']); ?></td>
            <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/temizlik_detay.php?id='.$r['id'])); ?>">Detay</a></td>
          </tr>
        <?php endforeach; if(!$rows): ?>
          <tr><td colspan="4" class="text-muted">Kayıt yok.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if($pages>1): ?>
  <div class="card-footer">
    <ul class="pagination pagination-sm mb-0">
      <?php for($i=1;$i<=$pages;$i++):
        $qs=$_GET; $qs['page']=$i; $active=$i===$page?' active':'';
      ?>
        <li class="page-item<?php echo $active;?>"><a class="page-link" href="?<?php echo h(http_build_query($qs)); ?>"><?php echo $i; ?></a></li>
      <?php endfor;?>
    </ul>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>