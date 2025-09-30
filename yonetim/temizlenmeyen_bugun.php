<?php
require_once __DIR__ . '/inc/header.php';

$page=max(1,(int)($_GET['page']??1));
$per=50;
$offset=($page-1)*$per;

$totalRow=fetch_one("
SELECT COUNT(*) c
FROM odalar o
LEFT JOIN (
  SELECT DISTINCT oda_id FROM temizlik_kayitlari WHERE DATE(tarih)=CURDATE()
) t ON t.oda_id=o.id
WHERE t.oda_id IS NULL
");
$total=(int)($totalRow['c']??0);
$pages=(int)ceil($total/$per);

$rows=fetch_all("
SELECT o.id,o.ad,o.aciklama
FROM odalar o
LEFT JOIN (
  SELECT DISTINCT oda_id FROM temizlik_kayitlari WHERE DATE(tarih)=CURDATE()
) t ON t.oda_id=o.id
WHERE t.oda_id IS NULL
ORDER BY o.id DESC
LIMIT $per OFFSET $offset
");
?>
<div class="card card-outline card-danger">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h3 class="card-title mb-0">Bugün Temizlenmeyen Odalar</h3>
    <div class="d-flex align-items-center">
      <button class="btn btn-sm btn-outline-secondary mr-2" onclick="window.print()">Yazdır</button>
      <span class="small text-muted">Toplam: <?php echo h($total); ?></span>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover mb-0">
        <thead class="thead-light"><tr><th>ID</th><th>Ad</th><th>Açıklama</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?php echo h($r['id']); ?></td>
            <td><?php echo h($r['ad']); ?></td>
            <td><?php echo h($r['aciklama']); ?></td>
          </tr>
        <?php endforeach; if(!$rows): ?>
          <tr><td colspan="3" class="text-muted">Tüm odalar için bugün kayıt var veya oda yok.</td></tr>
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