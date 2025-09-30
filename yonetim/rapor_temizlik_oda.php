<?php
require_once __DIR__ . '/inc/header.php';
$kat_id=(int)($_GET['kat']??0);
$kat=fetch_one("SELECT k.*, b.ad bina_ad FROM katlar k LEFT JOIN binalar b ON b.id=k.bina_id WHERE k.id=?",[$kat_id]);
if(!$kat){
  echo "<div class='alert alert-danger'>Kat bulunamadı.</div>";
  require_once __DIR__.'/inc/footer.php'; exit;
}

$page=max(1,(int)($_GET['page']??1));
$per=50;
$offset=($page-1)*$per;

$totalRow=fetch_one("SELECT COUNT(*) c FROM odalar WHERE kat_id=?",[$kat_id]);
$total=(int)($totalRow['c']??0);
$pages=(int)ceil($total/$per);

$odalar=fetch_all("
SELECT o.id,o.ad,
 (SELECT COUNT(*) FROM temizlik_kayitlari tk WHERE tk.oda_id=o.id) AS temizlik_say
FROM odalar o
WHERE o.kat_id=?
ORDER BY o.ad
LIMIT $per OFFSET $offset
",[$kat_id]);
?>
<div class="card card-outline card-warning">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h3 class="card-title mb-0">Oda Bazlı (Bina: <?php echo h($kat['bina_ad']); ?> / Kat: <?php echo h($kat['ad']); ?>)</h3>
    <div class="d-flex align-items-center">
      <button class="btn btn-sm btn-outline-secondary mr-2" onclick="window.print()">Yazdır</button>
      <span class="small text-muted">Toplam: <?php echo h($total); ?></span>
      <a href="<?php echo h(app_url('yonetim/rapor_temizlik_kat.php?bina='.$kat['bina_id'])); ?>" class="btn btn-tool ml-2"><i class="fas fa-arrow-left"></i></a>
    </div>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-striped table-hover mb-0">
      <thead class="thead-light"><tr><th>Oda</th><th>Temizlik Sayısı</th><th>Personel Detay</th></tr></thead>
      <tbody>
      <?php foreach($odalar as $o): ?>
        <tr>
          <td><?php echo h($o['ad']); ?></td>
          <td><?php echo h($o['temizlik_say']); ?></td>
          <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/rapor_temizlik_personel.php?oda='.$o['id'])); ?>">Personel</a></td>
        </tr>
      <?php endforeach; if(!$odalar): ?>
        <tr><td colspan="3" class="text-muted">Kayıt yok.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
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