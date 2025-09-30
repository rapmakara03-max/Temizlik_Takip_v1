<?php
require_once __DIR__ . '/inc/header.php';

$q = trim($_GET['q'] ?? '');
$params=[]; $where=" WHERE 1=1 ";
if($q!==''){
    $where.=" AND (s.baslik LIKE ? OR s.aciklama LIKE ?)";
    $like="%$q%";
    $params=[$like,$like];
}

$sort=strtolower($_GET['sort']??'id');
$dir=strtoupper($_GET['dir']??'DESC');
$dir=in_array($dir,['ASC','DESC'],true)?$dir:'DESC';
$sortMap=['id'=>'s.id','durum'=>'g.durum'];
$sortExpr=$sortMap[$sort]??$sortMap['id'];
$order=$sortExpr.' '.$dir.', s.id DESC';

$page=max(1,(int)($_GET['page']??1));
$per=50;
$offset=($page-1)*$per;

$totalRow=fetch_one("
  SELECT COUNT(DISTINCT s.id) c
  FROM sikayetler s
  LEFT JOIN odalar o ON o.id=s.oda_id
  LEFT JOIN binalar b ON b.id=o.bina_id
  LEFT JOIN katlar k ON k.id=o.kat_id
  LEFT JOIN birimler bi ON bi.id=o.birim_id
  LEFT JOIN gorevler g ON g.sikayet_id=s.id
  LEFT JOIN kullanicilar u ON u.id=g.assigned_user_id
  $where
",$params);
$total=(int)($totalRow['c']??0);
$pages=(int)ceil($total/$per);

$rows=fetch_all("
  SELECT s.*, o.ad oda_ad, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad,
         g.id gorev_id, g.durum gorev_durum, u.ad atanan_ad, u.telefon atanan_tel
  FROM sikayetler s
  LEFT JOIN odalar o ON o.id=s.oda_id
  LEFT JOIN binalar b ON b.id=o.bina_id
  LEFT JOIN katlar k ON k.id=o.kat_id
  LEFT JOIN birimler bi ON bi.id=o.birim_id
  LEFT JOIN gorevler g ON g.sikayet_id=s.id
  LEFT JOIN kullanicilar u ON u.id=g.assigned_user_id
  $where
  GROUP BY s.id
  ORDER BY $order
  LIMIT $per OFFSET $offset
",$params);

function sikayet_display_name(array $s): string {
    foreach(['sikayet_eden','ad','adsoyad','ad_soyad','isim','adi','baslik'] as $k){
        if(!empty($s[$k])) return (string)$s[$k];
    }
    return '—';
}
function sort_link(string $field): string {
    $cur=strtolower($_GET['sort']??'id');
    $dir=strtoupper($_GET['dir']??'DESC');
    $next=($cur===strtolower($field) && $dir==='ASC')?'DESC':'ASC';
    $qs=$_GET; $qs['sort']=$field; $qs['dir']=$next;
    return '?'.http_build_query($qs);
}
?>
<div class="card card-outline card-danger">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h3 class="card-title mb-0">Şikayetler</h3>
    <div class="d-flex align-items-center">
      <button class="btn btn-sm btn-outline-secondary mr-2" onclick="window.print()">Yazdır</button>
      <form method="get" class="d-inline-block">
        <div class="input-group input-group-sm" style="width:260px;">
          <input type="text" name="q" class="form-control" placeholder="Ara (başlık/açıklama)" value="<?php echo h($q); ?>">
          <button class="btn btn-outline-secondary btn-sm">Ara</button>
        </div>
      </form>
    </div>
  </div>
  <div class="card-body table-responsive p-0">
    <table class="table table-hover table-sm align-middle mb-0">
      <thead>
        <tr>
          <th><a href="<?php echo h(sort_link('id')); ?>">ID</a></th>
          <th>Ad / Soyad</th>
          <th>Konum</th>
          <th>Atanan</th>
          <th><a href="<?php echo h(sort_link('durum')); ?>">Görev Durumu</a></th>
          <th style="width:160px;">İşlem</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($rows as $s): ?>
        <tr>
          <td><?php echo h($s['id']); ?></td>
          <td><?php echo h(sikayet_display_name($s)); ?></td>
          <td><?php echo h(($s['bina_ad']??'-').' / '.($s['kat_ad']??'-').' / '.(($s['birim_ad']??'-')?:'-').' / '.($s['oda_ad']??'-')); ?></td>
          <td>
            <?php if(!empty($s['atanan_ad'])): ?>
              <?php echo h($s['atanan_ad']); ?>
              <?php if(!empty($s['atanan_tel'])): ?><div class="text-muted small"><?php echo h($s['atanan_tel']); ?></div><?php endif; ?>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td><?php echo h($s['gorev_durum'] ?? '-'); ?></td>
          <td>
            <div class="btn-group btn-group-sm">
              <a class="btn btn-warning" href="<?php echo h(app_url('yonetim/sikayet_atama.php?id='.$s['id'])); ?>">Görev Ata</a>
              <a class="btn btn-info" href="<?php echo h(app_url('yonetim/sikayet_detay.php?id='.$s['id'])); ?>">Detay Gör</a>
            </div>
          </td>
        </tr>
      <?php endforeach; if(!$rows): ?>
        <tr><td colspan="6" class="text-muted">Kayıt yok.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-content-between flex-wrap align-items-center">
    <div class="small text-muted mb-2">Toplam: <?php echo h($total); ?></div>
    <?php if($pages>1): ?>
    <ul class="pagination pagination-sm mb-0">
      <?php for($i=1;$i<=$pages;$i++):
        $qs=$_GET; $qs['page']=$i; $active=$i===$page?' active':'';
      ?>
        <li class="page-item<?php echo $active; ?>"><a class="page-link" href="?<?php echo h(http_build_query($qs)); ?>"><?php echo $i; ?></a></li>
      <?php endfor; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>