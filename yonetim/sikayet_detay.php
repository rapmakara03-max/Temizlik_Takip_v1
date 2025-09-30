<?php
require_once __DIR__.'/inc/header.php';

function table_has_col(string $t,string $c): bool {
    $r=fetch_one("SHOW COLUMNS FROM `$t` LIKE ?",[$c]);
    return (bool)$r;
}

/* Kapatma */
if(is_post() && ($_POST['act'] ?? '')==='kapat'){
    csrf_check();
    $id=(int)($_POST['id'] ?? 0);
    if($id>0){
        exec_stmt("UPDATE sikayetler SET durum='KAPALI', guncelleme_tarihi=NOW() WHERE id=?",[$id]);
        flash_set('success','Şikayet kapatıldı.');
    }
    redirect(current_path().'?id='.$id);
}

$id=(int)($_GET['id']??0);
$s=fetch_one("SELECT s.*, o.ad oda_ad, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad, u.ad atanan_ad
 FROM sikayetler s
 LEFT JOIN odalar o ON o.id=s.oda_id
 LEFT JOIN binalar b ON b.id=o.bina_id
 LEFT JOIN katlar k ON k.id=o.kat_id
 LEFT JOIN birimler bi ON bi.id=o.birim_id
 LEFT JOIN kullanicilar u ON u.id=s.atanan_personel_id
 WHERE s.id=?",[$id]);

if(!$s){ echo "<div class='alert alert-danger'>Şikayet yok.</div>"; require_once __DIR__.'/inc/footer.php'; exit; }

$tkHasSikayet = table_has_col('temizlik_kayitlari','sikayet_id');
$temizlikKayitlari=[];
if($tkHasSikayet){
    $temizlikKayitlari=fetch_all("
      SELECT tk.*, u.ad personel_ad
      FROM temizlik_kayitlari tk
      LEFT JOIN kullanicilar u ON u.id=tk.personel_id
      WHERE tk.sikayet_id=?
      ORDER BY tk.tarih DESC
    ",[$id]);
}

$kapali = $s['durum']==='KAPALI';

function foto_url_full($p){
    if(!$p) return null;
    if(preg_match('~^https?://~',$p)) return $p;
    if($p[0]==='/') return app_url(ltrim($p,'/'));
    return upload_url($p);
}
?>
<div class="card card-outline card-danger">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title mb-0">Şikayet Detayı #<?php echo h($s['id']); ?></h3>
    <div class="ml-auto">
      <a href="<?php echo h(app_url('yonetim/sikayetler.php')); ?>" class="btn btn-sm btn-secondary">&larr; Liste</a>
    </div>
  </div>
  <div class="card-body">
    <?php foreach(flash_get_all() as $f): ?>
      <div class="alert alert-<?php echo $f['t']==='error'?'danger':($f['t']==='success'?'success':'info'); ?> py-2"><?php echo h($f['m']); ?></div>
    <?php endforeach; ?>

    <dl class="row mb-0">
      <dt class="col-sm-3">Ad Soyad</dt><dd class="col-sm-9"><?php echo h($s['ad_soyad']); ?></dd>
      <dt class="col-sm-3">Telefon</dt><dd class="col-sm-9"><?php echo h($s['telefon']); ?></dd>
      <dt class="col-sm-3">Konum</dt>
      <dd class="col-sm-9">
        <?php
          if($s['oda_id'])
            echo h($s['bina_ad']).' / '.h($s['kat_ad']).' / '.h($s['birim_ad']?:'-').' / '.h($s['oda_ad']);
          else echo '-';
        ?>
      </dd>
      <dt class="col-sm-3">Mesaj</dt><dd class="col-sm-9"><div class="border rounded p-2 bg-light" style="white-space:pre-wrap;"><?php echo h($s['mesaj']); ?></div></dd>
      <dt class="col-sm-3">Durum</dt><dd class="col-sm-9">
        <span class="badge badge-<?php echo $kapali?'success':'warning'; ?>"><?php echo h($s['durum']); ?></span>
      </dd>
      <dt class="col-sm-3">Atanan</dt><dd class="col-sm-9"><?php echo h($s['atanan_ad'] ?: '-'); ?></dd>
      <dt class="col-sm-3">Tarih</dt><dd class="col-sm-9"><?php echo h($s['olusturma_tarihi']); ?></dd>
      <dt class="col-sm-3">Foto</dt>
      <dd class="col-sm-9">
        <?php if($s['foto1']): $f1=foto_url_full($s['foto1']); ?><a href="<?php echo h($f1); ?>" target="_blank">Foto1</a> <?php endif; ?>
        <?php if($s['foto2']): $f2=foto_url_full($s['foto2']); ?><a href="<?php echo h($f2); ?>" target="_blank">Foto2</a> <?php endif; ?>
        <?php if(!$s['foto1'] && !$s['foto2']) echo '-'; ?>
      </dd>
    </dl>

    <?php if(!$kapali && $temizlikKayitlari): ?>
      <form method="post" class="mt-3" onsubmit="return confirm('Şikayet kapatılsın mı?');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="kapat">
        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
        <button class="btn btn-sm btn-success">Şikayeti Kapat</button>
      </form>
    <?php endif; ?>

    <hr>
    <h5 class="mt-3">İlgili Temizlik / İşlem Kayıtları</h5>
    <?php if(!$temizlikKayitlari): ?>
      <div class="text-muted small">Kayıt yok (şikayet kaydına bağlı temizlik işlemi yapılmamış).</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Tarih</th>
              <th>Personel</th>
              <th>İşaretler</th>
              <th>Açıklama</th>
              <th>Foto1</th>
              <th>Foto2</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($temizlikKayitlari as $tk):
              $f1=foto_url_full($tk['foto_yol'] ?? null);
              $f2=foto_url_full($tk['foto_yol2'] ?? null);
            ?>
            <tr>
              <td><?php echo (int)$tk['id']; ?></td>
              <td><?php echo h($tk['tarih']); ?></td>
              <td><?php echo h($tk['personel_ad'] ?? ''); ?></td>
              <td>
                <?php
                  if(!empty($tk['isaretler'])){
                    foreach(explode(',',$tk['isaretler']) as $tg){
                      $tg=trim($tg);
                      if($tg==='') continue;
                      echo '<span class="badge badge-info mr-1">'.h($tg).'</span>';
                    }
                  } else echo '-';
                ?>
              </td>
              <td style="max-width:220px;"><div class="small" style="white-space:pre-wrap;"><?php echo h($tk['aciklama'] ?? ''); ?></div></td>
              <td>
                <?php if($f1): ?><a href="<?php echo h($f1); ?>" target="_blank">Gör</a><?php else: echo '-'; endif; ?>
              </td>
              <td>
                <?php if($f2): ?><a href="<?php echo h($f2); ?>" target="_blank">Gör</a><?php else: echo '-'; endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>
<?php require_once __DIR__.'/inc/footer.php'; ?>