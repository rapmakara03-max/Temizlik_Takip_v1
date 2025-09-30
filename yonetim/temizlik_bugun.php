<?php
require_once __DIR__ . '/inc/header.php';

$page=max(1,(int)($_GET['page']??1));
$per=50;
$offset=($page-1)*$per;

$totalRow=fetch_one("SELECT COUNT(*) c FROM temizlik_kayitlari tk WHERE DATE(tk.tarih)=CURDATE()");
$total=(int)($totalRow['c']??0);
$pages=(int)ceil($total/$per);

$rows=fetch_all("
SELECT tk.id, tk.tarih, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad, o.ad oda_ad, u.ad personel_ad
FROM temizlik_kayitlari tk
LEFT JOIN odalar o ON o.id=tk.oda_id
LEFT JOIN binalar b ON b.id=o.bina_id
LEFT JOIN katlar k ON k.id=o.kat_id
LEFT JOIN birimler bi ON bi.id=o.birim_id
LEFT JOIN kullanicilar u ON u.id=tk.personel_id
WHERE DATE(tk.tarih)=CURDATE()
ORDER BY tk.tarih DESC
LIMIT $per OFFSET $offset
");
?>
<div class="card card-outline card-success">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h3 class="card-title mb-0">Bugün Yapılan Temizlik İşlemleri</h3>
    <div class="d-flex align-items-center">
      <button class="btn btn-sm btn-outline-secondary mr-2" onclick="window.print()">Yazdır</button>
      <span class="small text-muted">Toplam: <?php echo h($total); ?></span>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover mb-0">
        <thead class="thead-light"><tr><th>ID</th><th>Konum</th><th>Personel</th><th>Tarih</th><th>Detay</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?php echo h($r['id']); ?></td>
            <td><?php echo h($r['bina_ad']); ?> / <?php echo h($r['kat_ad']); ?> / <?php echo h($r['birim_ad']?:'-'); ?> / <?php echo h($r['oda_ad']); ?></td>
            <td><?php echo h($r['personel_ad']); ?></td>
            <td><?php echo h($r['tarih']); ?></td>
            <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/temizlik_detay.php?id='.$r['id'])); ?>">Detay</a></td>
          </tr>
        <?php endforeach; if(!$rows): ?>
          <tr><td colspan="5" class="text-muted">Bugün kayıt yok.</td></tr>
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