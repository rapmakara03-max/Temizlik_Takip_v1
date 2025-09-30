<?php
require_once __DIR__ . '/inc/header.php';

$kullaniciSay = fetch_one("SELECT COUNT(*) c FROM kullanicilar")['c'] ?? 0;
$binaSay      = fetch_one("SELECT COUNT(*) c FROM binalar")['c'] ?? 0;
$odaSay       = fetch_one("SELECT COUNT(*) c FROM odalar")['c'] ?? 0;
$temizlikSay  = fetch_one("SELECT COUNT(*) c FROM temizlik_kayitlari")['c'] ?? 0;
$gorevSay     = fetch_one("SELECT COUNT(*) c FROM gorevler")['c'] ?? 0;
$sikayetSay   = fetch_one("SELECT COUNT(*) c FROM sikayetler")['c'] ?? 0;

$bugunTemizlikSay = fetch_one("SELECT COUNT(*) c FROM temizlik_kayitlari WHERE DATE(tarih)=CURDATE()")['c'] ?? 0;
$bugunTemizlenmeyen = fetch_one("
SELECT COUNT(*) c FROM odalar o
LEFT JOIN (SELECT DISTINCT oda_id FROM temizlik_kayitlari WHERE DATE(tarih)=CURDATE()) t ON t.oda_id=o.id
WHERE t.oda_id IS NULL")['c'] ?? 0;
$bugunIslemYapmayan = fetch_one("
SELECT COUNT(*) c FROM kullanicilar u
WHERE u.rol='PERSONEL' AND u.aktif=1
AND u.id NOT IN (SELECT DISTINCT personel_id FROM temizlik_kayitlari WHERE DATE(tarih)=CURDATE())
AND u.id NOT IN (
  SELECT DISTINCT atanan_personel_id
  FROM gorevler
  WHERE atanan_personel_id IS NOT NULL
    AND DATE(created_at)=CURDATE()
)")['c'] ?? 0;

$sonTemizlik = fetch_all("SELECT tk.*,o.ad AS oda_ad,u.ad AS personel_ad
 FROM temizlik_kayitlari tk
 LEFT JOIN odalar o ON o.id=tk.oda_id
 LEFT JOIN kullanicilar u ON u.id=tk.personel_id
 ORDER BY tk.tarih DESC LIMIT 5");

$sonSikayet = fetch_all("SELECT * FROM sikayetler ORDER BY olusturma_tarihi DESC LIMIT 5");

$sonGorevler = fetch_all("SELECT g.id,g.baslik,g.durum,g.created_at,
 b.ad bina_ad,k.ad kat_ad,bi.ad birim_ad,o.ad oda_ad
 FROM gorevler g
 LEFT JOIN binalar b ON b.id=g.bina_id
 LEFT JOIN katlar k ON k.id=g.kat_id
 LEFT JOIN birimler bi ON bi.id=g.birim_id
 LEFT JOIN odalar o ON o.id=g.oda_id
 ORDER BY g.id DESC LIMIT 10");
?>
<div class="row">
  <div class="col-lg-2 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo h($kullaniciSay); ?></h3><p>Kullanıcı</p></div><div class="icon"><i class="fas fa-users"></i></div></div></div>
  <div class="col-lg-2 col-6"><div class="small-box bg-info"><div class="inner"><h3><?php echo h($binaSay); ?></h3><p>Bina</p></div><div class="icon"><i class="fas fa-building"></i></div></div></div>
  <div class="col-lg-2 col-6"><div class="small-box bg-secondary"><div class="inner"><h3><?php echo h($odaSay); ?></h3><p>Oda</p></div><div class="icon"><i class="fas fa-door-open"></i></div></div></div>
  <div class="col-lg-2 col-6"><div class="small-box bg-success"><div class="inner"><h3><?php echo h($temizlikSay); ?></h3><p>Temizlik</p></div><div class="icon"><i class="fas fa-soap"></i></div></div></div>
  <div class="col-lg-2 col-6"><div class="small-box bg-warning"><div class="inner"><h3><?php echo h($gorevSay); ?></h3><p>Görev</p></div><div class="icon"><i class="fas fa-tasks"></i></div></div></div>
  <div class="col-lg-2 col-6"><div class="small-box bg-danger"><div class="inner"><h3><?php echo h($sikayetSay); ?></h3><p>Şikayet</p></div><div class="icon"><i class="fas fa-comment-dots"></i></div></div></div>
</div>

<div class="row">
  <div class="col-lg-4 col-12">
    <div class="info-box bg-danger">
      <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Bugün Temizlenmeyen Oda</span>
        <span class="info-box-number"><?php echo h($bugunTemizlenmeyen); ?></span>
        <a class="text-white" href="<?php echo h(app_url('yonetim/temizlenmeyen_bugun.php')); ?>">Görüntüle &raquo;</a>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-12">
    <div class="info-box bg-secondary">
      <span class="info-box-icon"><i class="fas fa-user-clock"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Bugün İşlem Yapmayan Personel</span>
        <span class="info-box-number"><?php echo h($bugunIslemYapmayan); ?></span>
        <a class="text-white" href="<?php echo h(app_url('yonetim/islem_yapmayan_bugun.php')); ?>">Görüntüle &raquo;</a>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-12">
    <div class="info-box bg-success">
      <span class="info-box-icon"><i class="fas fa-calendar-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Bugün Yapılan Temizlik</span>
        <span class="info-box-number"><?php echo h($bugunTemizlikSay); ?></span>
        <a class="text-white" href="<?php echo h(app_url('yonetim/temizlik_bugun.php')); ?>">Görüntüle &raquo;</a>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-6">
    <div class="card card-outline card-success">
      <div class="card-header"><h3 class="card-title mb-0">Son Temizlik Kayıtları</h3></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead class="thead-light"><tr><th>ID</th><th>Oda</th><th>Personel</th><th>Tarih</th><th>Detay</th></tr></thead>
            <tbody>
            <?php foreach($sonTemizlik as $r): ?>
              <tr>
                <td><?php echo h($r['id']); ?></td>
                <td><?php echo h($r['oda_ad']); ?></td>
                <td><?php echo h($r['personel_ad']); ?></td>
                <td><?php echo h($r['tarih']); ?></td>
                <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/temizlik_detay.php?id='.$r['id'])); ?>">Detay</a></td>
              </tr>
            <?php endforeach; if(!$sonTemizlik): ?>
              <tr><td colspan="5" class="text-muted">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card card-outline card-danger">
      <div class="card-header"><h3 class="card-title mb-0">Son Şikayetler</h3></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead class="thead-light"><tr><th>ID</th><th>Ad Soyad</th><th>Mesaj</th><th>Durum</th><th>Tarih</th></tr></thead>
            <tbody>
            <?php foreach($sonSikayet as $r): ?>
              <tr>
                <td><?php echo h($r['id']); ?></td>
                <td><?php echo h($r['ad_soyad']); ?></td>
                <td><?php echo h(mb_strimwidth($r['mesaj'],0,50,'...')); ?></td>
                <td><span class="badge <?php echo h($r['durum']); ?>"><?php echo h($r['durum']); ?></span></td>
                <td><?php echo h($r['olusturma_tarihi']); ?></td>
              </tr>
            <?php endforeach; if(!$sonSikayet): ?>
              <tr><td colspan="5" class="text-muted">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-12">
    <div class="card card-outline card-primary">
      <div class="card-header"><h3 class="card-title mb-0">Son 10 Görev</h3></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead class="thead-light"><tr><th>ID</th><th>Başlık</th><th>Konum</th><th>Durum</th><th>Oluşturma</th><th>Detay</th></tr></thead>
            <tbody>
            <?php foreach($sonGorevler as $g): ?>
              <tr>
                <td><?php echo h($g['id']); ?></td>
                <td><?php echo h($g['baslik']); ?></td>
                <td><?php echo h($g['bina_ad']?:'-'); ?>/<?php echo h($g['kat_ad']?:'-'); ?>/<?php echo h($g['birim_ad']?:'-'); ?>/<?php echo h($g['oda_ad']?:'-'); ?></td>
                <td><span class="badge <?php echo h($g['durum']); ?>"><?php echo h($g['durum']); ?></span></td>
                <td><?php echo h($g['created_at']); ?></td>
                <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/gorev_detay.php?id='.$g['id'])); ?>">Detay</a></td>
              </tr>
            <?php endforeach; if(!$sonGorevler): ?>
              <tr><td colspan="6" class="text-muted">Görev yok.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>