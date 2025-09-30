<?php
require_once __DIR__ . '/inc/header.php';
$bina_id=(int)($_GET['bina']??0);
$bina=fetch_one("SELECT * FROM binalar WHERE id=?",[$bina_id]);
if(!$bina){
  echo "<div class='alert alert-danger'>Bina bulunamadı.</div>";
  require_once __DIR__.'/inc/footer.php'; exit;
}

$page=max(1,(int)($_GET['page']??1));
$per=50;
$offset=($page-1)*$per;

$totalRow=fetch_one("SELECT COUNT(*) c FROM katlar WHERE bina_id=?",[$bina_id]);
$total=(int)($totalRow['c']??0);
$pages=(int)ceil($total/$per);

$katlar=fetch_all("
SELECT k.id,k.ad,
 (SELECT COUNT(*) FROM temizlik_kayitlari tk
  LEFT JOIN odalar o2 ON o2.id=tk.oda_id
  WHERE o2.kat_id=k.id) AS temizlik_say
FROM katlar k
WHERE k.bina_id=?
ORDER BY k.ad
LIMIT $per OFFSET $offset
",[$bina_id]);
?>
<div class="card card-outline card-info">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h3 class="card-title mb-0">Kat Bazlı (Bina: <?php echo h($bina['ad']); ?>)</h3>
    <div class="d-flex align-items-center">
      <button class="btn btn-sm btn-outline-secondary mr-2" onclick="window.print()">Yazdır</button>
      <span class="small text-muted">Toplam: <?php echo h($total); ?></span>
      <a href="<?php echo h(app_url('yonetim/rapor_temizlik_bina.php')); ?>" class="btn btn-tool ml-2"><i class="fas fa-arrow-left"></i></a>
    </div>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-striped table-hover mb-0">
      <thead class="thead-light"><tr><th>Kat</th><th>Temizlik Sayısı</th><th>Oda Detay</th></tr></thead>
      <tbody>
      <?php foreach($katlar as $k): ?>
        <tr>
          <td><?php echo h($k['ad']); ?></td>
          <td><?php echo h($k['temizlik_say']); ?></td>
          <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/rapor_temizlik_oda.php?kat='.$k['id'])); ?>">Odalar</a></td>
        </tr>
      <?php endforeach; if(!$katlar): ?>
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
<?php require_once __DIR__.'/inc/footer.php'; ?>