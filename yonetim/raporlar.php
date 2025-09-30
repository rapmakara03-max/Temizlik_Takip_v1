<?php
require_once __DIR__ . '/inc/header.php';

$gorevDurum  = fetch_all("SELECT durum, COUNT(*) c FROM gorevler GROUP BY durum");
$son10Temizlik = fetch_all("SELECT tk.id, tk.tarih, o.ad AS oda_ad, u.ad AS personel_ad
 FROM temizlik_kayitlari tk
 LEFT JOIN odalar o ON o.id=tk.oda_id
 LEFT JOIN kullanicilar u ON u.id=tk.personel_id
 ORDER BY tk.tarih DESC LIMIT 10");

$durumLabels=[]; $durumValues=[];
foreach($gorevDurum as $gd){
    $durumLabels[]=$gd['durum'];
    $durumValues[]=(int)$gd['c'];
}
?>
<div class="row">
  <div class="col-md-3">
    <a class="small-box bg-primary" href="<?php echo h(app_url('yonetim/rapor_temizlik_bina.php')); ?>" style="display:block;color:#fff;text-decoration:none;">
      <div class="inner">
        <h4 style="font-size:20px;">Bina Bazlı</h4>
        <p>Temizlik Raporu</p>
      </div>
      <div class="icon"><i class="fas fa-building"></i></div>
    </a>
  </div>
  <div class="col-md-3">
    <a class="small-box bg-info" href="<?php echo h(app_url('yonetim/personel_takip.php')); ?>" style="display:block;color:#fff;text-decoration:none;">
      <div class="inner">
        <h4 style="font-size:20px;">Personel</h4>
        <p>Takip & Rapor</p>
      </div>
      <div class="icon"><i class="fas fa-users"></i></div>
    </a>
  </div>
   <div class="col-md-3">
    <a class="small-box bg-dark" href="<?php echo h(app_url('yonetim/detayli_raporlar.php')); ?>" style="display:block;color:#fff;text-decoration:none;">
      <div class="inner">
        <h4 style="font-size:20px;">Detaylı</h4>
        <p>Gelişmiş Rapor</p>
      </div>
      <div class="icon"><i class="fas fa-chart-line"></i></div>
    </a>
  </div>

  <div class="col-md-3">
    <a class="small-box bg-warning" href="<?php echo h(app_url('yonetim/temizlik_bugun.php')); ?>" style="display:block;color:#fff;text-decoration:none;">
      <div class="inner">
        <h4 style="font-size:20px;">Bugün</h4>
        <p>İşlemler</p>
      </div>
      <div class="icon"><i class="fas fa-calendar-day"></i></div>
    </a>
  </div>
</div>



<div class="row">
  <div class="col-xl-6">
    <div class="card card-outline card-primary">
      <div class="card-header"><h3 class="card-title mb-0">Görev Durum Dağılımı</h3></div>
      <div class="card-body">
        <?php if($gorevDurum): ?>
          <canvas id="raporGorevChart" height="220"></canvas>
        <?php else: ?>
          <p class="text-muted mb-0">Görev bulunamadı.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card card-outline card-success">
      <div class="card-header"><h3 class="card-title mb-0">Son 10 Temizlik</h3></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0">
            <thead class="thead-light">
              <tr><th>ID</th><th>Oda</th><th>Personel</th><th>Tarih</th><th>Detay</th></tr>
            </thead>
            <tbody>
            <?php foreach($son10Temizlik as $r): ?>
              <tr>
                <td><?php echo h($r['id']); ?></td>
                <td><?php echo h($r['oda_ad']); ?></td>
                <td><?php echo h($r['personel_ad']); ?></td>
                <td><?php echo h($r['tarih']); ?></td>
                <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/temizlik_detay.php?id='.$r['id'])); ?>">Detay</a></td>
              </tr>
            <?php endforeach; if(!$son10Temizlik): ?>
              <tr><td colspan="5" class="text-muted">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('DOMContentLoaded',function(){
  <?php if($gorevDurum): ?>
  if(window.Chart){
    var ctx=document.getElementById('raporGorevChart').getContext('2d');
    new Chart(ctx,{
      type:'bar',
      data:{
        labels: <?php echo json_encode($durumLabels, JSON_UNESCAPED_UNICODE); ?>,
        datasets:[{label:'Görev',data:<?php echo json_encode($durumValues, JSON_UNESCAPED_UNICODE); ?>,backgroundColor:'#007bff'}]
      },
      options:{responsive:true,scales:{y:{beginAtZero:true}}}
    });
  }
  <?php endif; ?>
});
</script>
<?php require_once __DIR__ . '/inc/footer.php'; ?>