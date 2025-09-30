<?php
require_once __DIR__ . '/inc/header.php';

$page=max(1,(int)($_GET['page']??1));
$per=50;
$offset=($page-1)*$per;

$totalRow=fetch_one("SELECT COUNT(*) c FROM binalar");
$total=(int)($totalRow['c']??0);
$pages=(int)ceil($total/$per);

$binalar = fetch_all("
SELECT b.id,b.ad,
 (SELECT COUNT(*) FROM temizlik_kayitlari tk
  LEFT JOIN odalar o2 ON o2.id=tk.oda_id
  WHERE o2.bina_id=b.id) AS temizlik_say
FROM binalar b
ORDER BY b.ad
LIMIT $per OFFSET $offset
");
?>
<div class="card card-outline card-primary">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h3 class="card-title mb-0">Bina Bazlı Temizlik Raporu</h3>
    <div class="d-flex align-items-center">
      <button class="btn btn-sm btn-outline-secondary mr-2" onclick="window.print()">Yazdır</button>
      <span class="small text-muted">Toplam: <?php echo h($total); ?></span>
    </div>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-striped table-hover mb-0">
      <thead class="thead-light"><tr><th>Bina</th><th>Toplam Temizlik</th><th>Kat Detay</th></tr></thead>
      <tbody>
      <?php foreach($binalar as $b): ?>
        <tr>
          <td><?php echo h($b['ad']); ?></td>
          <td><?php echo h($b['temizlik_say']); ?></td>
          <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/rapor_temizlik_kat.php?bina='.$b['id'])); ?>">Katlar</a></td>
        </tr>
      <?php endforeach; if(!$binalar): ?>
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