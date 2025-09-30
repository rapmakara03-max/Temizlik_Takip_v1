<?php
require_once __DIR__ . '/inc/header.php';
$rows=fetch_all("
SELECT u.id,u.ad,u.email,
 (SELECT COUNT(*) FROM temizlik_kayitlari tk WHERE tk.personel_id=u.id) temizlik_say,
 (SELECT COUNT(*) FROM gorevler g WHERE g.atanan_personel_id=u.id) gorev_say
FROM kullanicilar u
WHERE u.rol='PERSONEL' AND u.aktif=1
ORDER BY u.ad");
?>
<div class="card card-outline card-primary">
  <div class="card-header"><h3 class="card-title mb-0">Personel Takip</h3></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover mb-0">
        <thead class="thead-light">
          <tr>
            <th>ID</th><th>Ad</th><th>E-posta</th><th>Temizlik</th><th>Görev</th><th>Personel Detay</th><th>Personel Raporu</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?php echo h($r['id']); ?></td>
            <td><?php echo h($r['ad']); ?></td>
            <td><?php echo h($r['email']); ?></td>
            <td><?php echo h($r['temizlik_say']); ?></td>
            <td><?php echo h($r['gorev_say']); ?></td>
            <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/personel_islemler.php?id='.$r['id'])); ?>">İşlemler</a></td>
			 <td><a class="btn btn-xs btn-primary" href="<?php echo h(app_url('yonetim/rapor_temizlik_personel.php?personel='.$r['id'])); ?>">Rapor</a></td>
          </tr>
        <?php endforeach; if(!$rows): ?>
          <tr><td colspan="6" class="text-muted">Kayıt yok.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>