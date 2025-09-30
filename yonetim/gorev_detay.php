<?php
require_once __DIR__ . '/inc/header.php';

function gorev_has(string $col): bool {
    static $c=[]; if(isset($c[$col])) return $c[$col];
    $row=fetch_one("SHOW COLUMNS FROM gorevler LIKE ?",[$col]);
    return $c[$col]=(bool)$row;
}
function gorev_atanan_kolon(): ?string {
    if(gorev_has('assigned_user_id')) return 'assigned_user_id';
    if(gorev_has('atanan_personel_id')) return 'atanan_personel_id';
    return null;
}
function table_has_col(string $t,string $c): bool {
    $r=fetch_one("SHOW COLUMNS FROM `$t` LIKE ?",[$c]);
    return (bool)$r;
}
$colAtanan = gorev_atanan_kolon();

/* Durum güncelle */
if(is_post() && ($_POST['act'] ?? '')==='durum'){
    csrf_check();
    $id=(int)($_POST['id'] ?? 0);
    $durum=trim($_POST['durum'] ?? '');
    if($id>0 && $durum!==''){
        $sets=['durum=?']; $prm=[$durum];
        if(gorev_has('updated_at')) $sets[]='updated_at=NOW()';
        $prm[]=$id;
        $ok=exec_stmt("UPDATE gorevler SET ".implode(', ',$sets)." WHERE id=?",$prm);
        flash_set($ok?'success':'error',$ok?'Durum güncellendi.':'Güncelleme hatası.');
    } else flash_set('error','Eksik bilgi.');
    redirect(current_path().'?id='.$id);
}

/* Atama güncelle */
if(is_post() && ($_POST['act'] ?? '')==='assign' && $colAtanan){
    csrf_check();
    $id=(int)($_POST['id'] ?? 0);
    $uid=(int)($_POST['assigned_user_id'] ?? 0);
    $set=["$colAtanan=?"]; $prm=[$uid?:null];
    if(gorev_has('updated_at')) $set[]='updated_at=NOW()';
    $prm[]=$id;
    $ok=exec_stmt("UPDATE gorevler SET ".implode(', ',$set)." WHERE id=?",$prm);
    flash_set($ok?'success':'error',$ok?'Atama güncellendi.':'Atama güncellenemedi.');
    redirect(current_path().'?id='.$id);
}

/* Tamamlandı (Kapat) */
if(is_post() && ($_POST['act'] ?? '')==='kapat'){
    csrf_check();
    $id=(int)($_POST['id'] ?? 0);
    if($id>0 && gorev_has('durum')){
        $sets=["durum='TAMAMLANDI'"];
        if(gorev_has('updated_at')) $sets[]='updated_at=NOW()';
        exec_stmt("UPDATE gorevler SET ".implode(', ',$sets)." WHERE id=?",[$id]);
        flash_set('success','Görev kapatıldı.');
    }
    redirect(current_path().'?id='.$id);
}

/* Görev verisi */
$id=(int)($_GET['id'] ?? 0);
$selAtanan = $colAtanan ? "g.$colAtanan" : "NULL AS atanan_personel_id";
$g=fetch_one("
  SELECT g.*, $selAtanan,
         b.ad bina_ad,k.ad kat_ad,bi.ad birim_ad,o.ad oda_ad,
         u.ad atanan_ad,u.telefon atanan_tel
  FROM gorevler g
  LEFT JOIN binalar b ON b.id=g.bina_id
  LEFT JOIN katlar k ON k.id=g.kat_id
  LEFT JOIN birimler bi ON bi.id=g.birim_id
  LEFT JOIN odalar o ON o.id=g.oda_id
  LEFT JOIN kullanicilar u ON u.id=".($colAtanan? "g.$colAtanan":"0")."
  WHERE g.id=?",[$id]);

if(!$g){
    echo "<div class='alert alert-danger m-3'>Görev bulunamadı.</div>";
    require_once __DIR__.'/inc/footer.php'; exit;
}

/* Şikayet */
$s=null;
if(!empty($g['sikayet_id'])){
    $s=fetch_one("
      SELECT s.*,
             o.ad oda_ad,b.ad bina_ad,k.ad kat_ad,bi.ad birim_ad
      FROM sikayetler s
      LEFT JOIN odalar o ON o.id=s.oda_id
      LEFT JOIN binalar b ON b.id=o.bina_id
      LEFT JOIN katlar k ON k.id=o.kat_id
      LEFT JOIN birimler bi ON bi.id=o.birim_id
      WHERE s.id=?",[$g['sikayet_id']]);
}

/* Temizlik kaydı (gorev_id üzerinden) */
$tkHasGorev = table_has_col('temizlik_kayitlari','gorev_id');
$tkHasSikayet = table_has_col('temizlik_kayitlari','sikayet_id');
$temizlikKayitlari=[];
if($tkHasGorev){
    $temizlikKayitlari=fetch_all("
      SELECT tk.*, u.ad personel_ad
      FROM temizlik_kayitlari tk
      LEFT JOIN kullanicilar u ON u.id=tk.personel_id
      WHERE tk.gorev_id=?
      ORDER BY tk.tarih DESC
    ",[$id]);
}else{
    // fallback
    if($g['oda_id']){
        $after=$g['created_at']??'1970-01-01 00:00:00';
        $temizlikKayitlari=fetch_all("
          SELECT tk.*, u.ad personel_ad
          FROM temizlik_kayitlari tk
          LEFT JOIN kullanicilar u ON u.id=tk.personel_id
          WHERE tk.oda_id=? AND tk.tarih>=?
          ORDER BY tk.tarih DESC
        ",[(int)$g['oda_id'],$after]);
    }
}

/* Personeller (atama alanı varsa) */
$personeller = $colAtanan
  ? fetch_all("SELECT id,ad,telefon FROM kullanicilar WHERE rol='PERSONEL' AND aktif=1 ORDER BY ad")
  : [];

$durumOpts=['YENI','ATANDI','DEVAM','BEKLEME','TAMAMLANDI','IPTAL','TAMAM'];

/* Yardımcı */
function foto_url_full($p){
    if(!$p) return null;
    if(preg_match('~^https?://~',$p)) return $p;
    if($p[0]==='/') return app_url(ltrim($p,'/'));
    return upload_url($p);
}
$kapaliMi = in_array($g['durum'] ?? '',['TAMAMLANDI','TAMAM','KAPALI']);
?>
<div class="card card-outline card-info">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title mb-0">Görev Detay #<?php echo h($g['id']); ?></h3>
    <div class="ml-auto">
      <a href="<?php echo h(app_url('yonetim/gorevler.php')); ?>" class="btn btn-sm btn-secondary">&larr; Liste</a>
    </div>
  </div>
  <div class="card-body">
    <?php foreach(flash_get_all() as $f): ?>
      <div class="alert alert-<?php echo $f['t']==='error'?'danger':($f['t']==='success'?'success':'info'); ?> py-2">
        <?php echo h($f['m']); ?>
      </div>
    <?php endforeach; ?>

    <dl class="row mb-0">
      <dt class="col-sm-3">Başlık</dt>
      <dd class="col-sm-9"><?php echo h($g['baslik']); ?></dd>

      <?php if(gorev_has('durum')): ?>
      <dt class="col-sm-3">Durum</dt>
      <dd class="col-sm-9">
        <?php
          $d=$g['durum']??'';
          $cls='badge badge-secondary';
            if(in_array($d,['TAMAMLANDI','TAMAM'])) $cls='badge badge-success';
            elseif(in_array($d,['ATANDI','DEVAM'])) $cls='badge badge-warning';
            elseif($d==='IPTAL') $cls='badge badge-dark';
        ?>
        <span class="<?php echo h($cls); ?>"><?php echo h($d); ?></span>
      </dd>
      <?php endif; ?>

      <dt class="col-sm-3">Konum</dt>
      <dd class="col-sm-9"><?php echo h(($g['bina_ad']??'-').' / '.($g['kat_ad']??'-').' / '.(($g['birim_ad']??'-')?:'-').' / '.($g['oda_ad']??'-')); ?></dd>

      <dt class="col-sm-3">Atanan</dt>
      <dd class="col-sm-9">
        <?php if(!empty($g['atanan_ad'])): ?>
          <?php echo h($g['atanan_ad']); ?> <?php if(!empty($g['atanan_tel'])): ?><small class="text-muted">(<?php echo h($g['atanan_tel']); ?>)</small><?php endif; ?>
        <?php else: ?>
          <span class="text-muted">—</span>
        <?php endif; ?>
      </dd>

      <?php if(gorev_has('created_at')): ?>
        <dt class="col-sm-3">Oluşturma</dt><dd class="col-sm-9"><?php echo h($g['created_at']); ?></dd>
      <?php endif; ?>
      <?php if(gorev_has('updated_at')): ?>
        <dt class="col-sm-3">Güncelleme</dt><dd class="col-sm-9"><?php echo h($g['updated_at']); ?></dd>
      <?php endif; ?>

      <?php if(!empty($g['sikayet_id'])): ?>
        <dt class="col-sm-3">Bağlı Şikayet</dt>
        <dd class="col-sm-9">
          #<?php echo (int)$g['sikayet_id']; ?>
          <?php if($s): ?>
            <div class="small text-muted mt-1"><?php echo h(mb_strimwidth($s['mesaj'],0,80,'…','UTF-8')); ?></div>
          <?php endif; ?>
        </dd>
      <?php endif; ?>
    </dl>

    <?php if($s): ?>
      <hr>
      <h5 class="mt-3">Şikayet Özeti</h5>
      <dl class="row mb-0 small">
        <dt class="col-sm-3">Şikayet Mesajı</dt>
        <dd class="col-sm-9"><div class="border rounded p-2 bg-light" style="white-space:pre-wrap;"><?php echo h($s['mesaj']); ?></div></dd>
      </dl>
    <?php endif; ?>

    <hr>
    <div class="row">
      <?php if($colAtanan): ?>
      <div class="col-md-6">
        <form method="post" class="mb-3">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="act" value="assign">
          <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
          <label class="small font-weight-bold">Atama Değiştir</label>
          <div class="input-group input-group-sm">
            <select name="assigned_user_id" class="form-control form-control-sm">
              <option value="">— Seçilmemiş —</option>
              <?php foreach($personeller as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>" <?php echo selected($g[$colAtanan] ?? null,$p['id']); ?>>
                  <?php echo h($p['ad']); ?><?php echo $p['telefon']?' ('.h($p['telefon']).')':''; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-secondary">Kaydet</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <?php if(gorev_has('durum')): ?>
      <div class="col-md-6">
        <form method="post" class="mb-3">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="act" value="durum">
          <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
          <label class="small font-weight-bold">Durum Güncelle</label>
          <div class="input-group input-group-sm">
            <select name="durum" class="form-control form-control-sm">
              <?php foreach($durumOpts as $d): ?>
                <option value="<?php echo h($d); ?>" <?php echo selected($g['durum'] ?? '',$d); ?>><?php echo h($d); ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary">Güncelle</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <?php if(!$kapaliMi && $temizlikKayitlari): ?>
      <form method="post" onsubmit="return confirm('Görevi kapatmak istediğinize emin misiniz?');" class="mb-3">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="kapat">
        <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
        <button class="btn btn-sm btn-success">Görevi Tamamlandı (Kapat)</button>
      </form>
    <?php endif; ?>

    <hr>
    <h5 class="mb-3">İlişkili Temizlik / İşlem Kayıtları</h5>
    <?php if(!$temizlikKayitlari): ?>
      <div class="text-muted small">Henüz kayıt yok.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Tarih</th>
              <th>Personel</th>
              <th>İşaretler</th>
              <th>Kaynak</th>
              <th>Açıklama</th>
              <th>Foto 1</th>
              <th>Foto 2</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($temizlikKayitlari as $tk):
              $f1=foto_url_full($tk['foto_yol'] ?? null);
              $f2=foto_url_full($tk['foto_yol2'] ?? null);
              $kaynak='Serbest';
              if($tkHasGorev && !empty($tk['gorev_id'])){
                  $kaynak='Görev #'.$tk['gorev_id'];
              }
              if($tkHasSikayet && !empty($tk['sikayet_id'])){
                  $kaynak .= (strpos($kaynak,'Görev')===0?' / ':'').'Şikayet #'.$tk['sikayet_id'];
              }
            ?>
            <tr>
              <td><?php echo (int)$tk['id']; ?></td>
              <td><?php echo h($tk['tarih']); ?></td>
              <td><?php echo h($tk['personel_ad'] ?? ''); ?></td>
              <td>
                <?php
                  if(!empty($tk['isaretler'])){
                    foreach(explode(',',$tk['isaretler']) as $tg){
                      $tg=trim($tg); if($tg==='') continue;
                      echo '<span class="badge badge-info mr-1">'.h($tg).'</span>';
                    }
                  } else echo '<span class="text-muted">-</span>';
                ?>
              </td>
              <td><?php echo h($kaynak); ?></td>
              <td style="max-width:220px;">
                <div class="small" style="white-space:pre-wrap;"><?php echo h($tk['aciklama'] ?? ''); ?></div>
              </td>
              <td>
                <?php if($f1): ?>
                  <a href="<?php echo h($f1); ?>" target="_blank">
                    <img src="<?php echo h($f1); ?>" style="max-width:90px;max-height:70px;object-fit:cover;border:1px solid #ccc;">
                  </a>
                <?php else: echo '<span class="text-muted">-</span>'; endif; ?>
              </td>
              <td>
                <?php if($f2): ?>
                  <a href="<?php echo h($f2); ?>" target="_blank">
                    <img src="<?php echo h($f2); ?>" style="max-width:90px;max-height:70px;object-fit:cover;border:1px solid #ccc;">
                  </a>
                <?php else: echo '<span class="text-muted">-</span>'; endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>