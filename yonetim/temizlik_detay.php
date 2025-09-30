<?php
require_once __DIR__.'/inc/header.php';

$id=(int)($_GET['id']??0);

function tk_has(string $col): bool {
    static $c=[]; if(isset($c[$col])) return $c[$col];
    $row=fetch_one("SHOW COLUMNS FROM temizlik_kayitlari LIKE ?",[$col]);
    return $c[$col]=(bool)$row;
}

$selCols = "tk.*, o.ad oda_ad, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad, u.ad personel_ad";
if(tk_has('gorev_id'))    $selCols .= ", tk.gorev_id";
if(tk_has('sikayet_id'))  $selCols .= ", tk.sikayet_id";

$t = fetch_one("
 SELECT $selCols
 FROM temizlik_kayitlari tk
 LEFT JOIN odalar o ON o.id=tk.oda_id
 LEFT JOIN binalar b ON b.id=o.bina_id
 LEFT JOIN katlar k ON k.id=o.kat_id
 LEFT JOIN birimler bi ON bi.id=o.birim_id
 LEFT JOIN kullanicilar u ON u.id=tk.personel_id
 WHERE tk.id=?",[$id]);

if(!$t){
  echo "<div class='alert alert-danger'>Kayıt bulunamadı.</div>";
  require_once __DIR__ . '/inc/footer.php'; exit;
}

function foto_link_rel(?string $rel): ?string {
    if(!$rel) return null;
    $r=trim($rel);
    if($r==='') return null;
    if(preg_match('~^https?://~i',$r)) return $r;
    if($r[0]==='/') return app_url(ltrim($r,'/'));
    return upload_url($r);
}
?>
<div class="card card-outline card-success">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title mb-0">Temizlik Kaydı #<?php echo h($t['id']); ?></h3>
    <div class="ml-auto">
      <a href="<?php echo h($_SERVER['HTTP_REFERER']??app_url('yonetim/raporlar.php')); ?>" class="btn btn-sm btn-secondary">&larr; Geri</a>
    </div>
  </div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">Konum</dt>
      <dd class="col-sm-9"><?php echo h(($t['bina_ad']??'-')." / ".($t['kat_ad']??'-')." / ".(($t['birim_ad']??'-')?:'-')." / ".($t['oda_ad']??'-')); ?></dd>
      <dt class="col-sm-3">Personel</dt><dd class="col-sm-9"><?php echo h($t['personel_ad']); ?></dd>
      <dt class="col-sm-3">Tarih</dt><dd class="col-sm-9"><?php echo h($t['tarih']); ?></dd>
      <?php if(isset($t['gorev_id']) && $t['gorev_id']): ?>
        <dt class="col-sm-3">Bağlı Görev</dt>
        <dd class="col-sm-9"><a href="<?php echo h(app_url('yonetim/gorev_detay.php?id='.(int)$t['gorev_id'])); ?>">Görev #<?php echo (int)$t['gorev_id']; ?></a></dd>
      <?php endif; ?>
      <?php if(isset($t['sikayet_id']) && $t['sikayet_id']): ?>
        <dt class="col-sm-3">Bağlı Şikayet</dt>
        <dd class="col-sm-9"><a href="<?php echo h(app_url('yonetim/sikayet_detay.php?id='.(int)$t['sikayet_id'])); ?>">Şikayet #<?php echo (int)$t['sikayet_id']; ?></a></dd>
      <?php endif; ?>
      <dt class="col-sm-3">İşaretler</dt>
      <dd class="col-sm-9">
        <?php
        if($t['isaretler']){
          foreach(explode(',',$t['isaretler']) as $tag){
            $tag=trim($tag);
            if($tag!=='') echo '<span class="badge badge-info mr-1">'.h($tag).'</span>';
          }
        } else echo '-';
        ?>
      </dd>
      <dt class="col-sm-3">Açıklama</dt><dd class="col-sm-9"><?php echo nl2br(h($t['aciklama'])); ?></dd>
      <dt class="col-sm-3">Foto 1</dt>
      <dd class="col-sm-9">
        <?php $f1=foto_link_rel($t['foto_yol']); echo $f1?'<a href="'.h($f1).'" target="_blank">Görüntüle</a>':'-'; ?>
      </dd>
      <dt class="col-sm-3">Foto 2</dt>
      <dd class="col-sm-9">
        <?php $f2=foto_link_rel($t['foto_yol2']); echo $f2?'<a href="'.h($f2).'" target="_blank">Görüntüle</a>':'-'; ?>
      </dd>
    </dl>
  </div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>