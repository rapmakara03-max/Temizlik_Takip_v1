<?php
require_once __DIR__ . '/inc/header.php';
$rows=fetch_all("
SELECT u.id,u.ad,u.email
FROM kullanicilar u
WHERE u.rol='PERSONEL' AND u.aktif=1
AND u.id NOT IN (
  SELECT DISTINCT personel_id
  FROM temizlik_kayitlari
  WHERE DATE(tarih)=CURDATE()
)
AND u.id NOT IN (
  SELECT DISTINCT atanan_personel_id
  FROM gorevler
  WHERE atanan_personel_id IS NOT NULL
    AND DATE(created_at)=CURDATE()
)
ORDER BY u.id DESC");
?>
<div class="card card-outline card-warning">
  <div class="card-header"><h3 class="card-title mb-0">Bugün İşlem Yapmayan Personel</h3></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover mb-0">
        <thead class="thead-light"><tr><th>ID</th><th>Ad</th><th>E-posta</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?php echo h($r['id']); ?></td>
            <td><?php echo h($r['ad']); ?></td>
            <td><?php echo h($r['email']); ?></td>
          </tr>
        <?php endforeach; if(!$rows): ?>
          <tr><td colspan="3" class="text-muted">Tüm personel işlem yapmış ya da personel yok.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>