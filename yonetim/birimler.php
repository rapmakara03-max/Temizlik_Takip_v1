<?php
require_once __DIR__ . '/inc/header.php';

/* Kat listesi */
$katlar = fetch_all("SELECT k.id, k.ad, b.ad bina_ad FROM katlar k LEFT JOIN binalar b ON b.id=k.bina_id ORDER BY b.ad, k.ad");

/* Eksik birim qr_kod üret */
$missingBirim = fetch_all("SELECT id FROM birimler WHERE qr_kod IS NULL LIMIT 300");
foreach($missingBirim as $m){
    $code=generate_generic_qr_code();
    exec_stmt("UPDATE birimler SET qr_kod=? WHERE id=? AND qr_kod IS NULL",[$code,$m['id']]);
}

if (is_post()) {
    csrf_check();
    $act=$_POST['act']??'';
    if($act==='ekle'){
        $kat_id=(int)($_POST['kat_id']??0);
        $ad=trim($_POST['ad']??'');
        if($kat_id<=0 || !$ad){
            flash_set('error','Zorunlu alanlar.');
        } else {
            $qr=generate_generic_qr_code();
            if(exec_stmt("INSERT INTO birimler(ad,kat_id,qr_kod) VALUES(?,?,?)",[$ad,$kat_id,$qr])) flash_set('success','Birim eklendi.');
            else flash_set('error','Eklenemedi.');
        }
        redirect(current_path());
    }
    if($act==='guncelle'){
        $id=(int)($_POST['id']??0);
        $kat_id=(int)($_POST['kat_id']??0);
        $ad=trim($_POST['ad']??'');
        if($id>0 && $kat_id>0 && $ad){
            if(exec_stmt("UPDATE birimler SET ad=?, kat_id=? WHERE id=?",[$ad,$kat_id,$id])) flash_set('success','Güncellendi.');
            else flash_set('error','Güncellenemedi.');
        } else flash_set('error','Eksik veri.');
        redirect(current_path());
    }
    if($act==='sil'){
        $id=(int)($_POST['id']??0);
        if($id>0 && exec_stmt("UPDATE odalar SET birim_id=NULL WHERE birim_id=?",[$id]) && exec_stmt("DELETE FROM birimler WHERE id=?",[$id])) {
            flash_set('success','Silindi.');
        } else flash_set('error','Silinemedi.');
        redirect(current_path());
    }
    if($act==='yenile_qr'){
        $id=(int)($_POST['id']??0);
        if($id>0){
            $code=generate_generic_qr_code();
            if(exec_stmt("UPDATE birimler SET qr_kod=? WHERE id=?",[$code,$id])) flash_set('success','QR yenilendi.');
            else flash_set('error','QR yenilenemedi.');
        }
        redirect(current_path());
    }
}

[$page,$per,$offset]=paginate_params();
[$sWhere,$sParams]=search_clause('bi.ad');
$order=sort_clause(['id'=>'bi.id','ad'=>'bi.ad'],'bi.id DESC');
$total=fetch_one("SELECT COUNT(*) c FROM birimler bi WHERE 1=1 $sWhere",$sParams)['c'] ?? 0;

$rows=fetch_all("SELECT bi.*, k.ad kat_ad, b.ad bina_ad
 FROM birimler bi
 LEFT JOIN katlar k ON k.id=bi.kat_id
 LEFT JOIN binalar b ON b.id=k.bina_id
 WHERE 1=1 $sWhere
 ORDER BY $order LIMIT $per OFFSET $offset",$sParams);
?>
<div class="row">
  <div class="col-lg-4">
    <div class="card card-outline card-primary">
      <div class="card-header"><h3 class="card-title mb-0">Yeni Birim</h3></div>
      <form method="post">
        <div class="card-body">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="act" value="ekle">
          <div class="form-group">
            <label>Kat</label>
            <select name="kat_id" class="form-control form-control-sm" required>
              <option value="">Seçiniz</option>
              <?php foreach($katlar as $k): ?>
                <option value="<?php echo h($k['id']); ?>">
                  <?php echo h($k['bina_ad'].' / '.$k['ad']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Ad</label>
            <input type="text" name="ad" class="form-control form-control-sm" required>
          </div>
        </div>
        <div class="card-footer text-right">
          <button class="btn btn-sm btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
    <div class="card card-outline card-secondary">
      <div class="card-header"><h3 class="card-title mb-0">Arama</h3></div>
      <div class="card-body">
        <form method="get" class="form-inline">
          <input type="text" name="q" value="<?php echo h($_GET['q']??''); ?>" class="form-control form-control-sm mr-2 mb-2" placeholder="Ara...">
            <button class="btn btn-sm btn-outline-primary mb-2">Ara</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card card-outline card-info">
      <div class="card-header"><h3 class="card-title mb-0">Birimler</h3></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th>ID</th>
                <th>Ad</th>
                <th>Bina / Kat</th>
                <th>QR Kod</th>
                <th>Toplu QR</th>
                <th>Tekil QR</th>
                <th style="width:210px;">İşlemler</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?php echo h($r['id']); ?></td>
                <td><?php echo h($r['ad']); ?></td>
                <td><?php echo h($r['bina_ad'].' / '.$r['kat_ad']); ?></td>
                <td>
                  <code style="font-size:11px;"><?php echo h($r['qr_kod']); ?></code>
                  <form method="post" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="act" value="yenile_qr">
                    <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                    <button class="btn btn-xs btn-outline-secondary" onclick="return confirm('QR yenilensin mi?');">Yenile</button>
                  </form>
                </td>
                <td><a class="btn btn-xs btn-outline-primary" target="_blank" href="<?php echo h(app_url('yonetim/qr_birim.php?id='.$r['id'])); ?>">Toplu</a></td>
                <td><a class="btn btn-xs btn-outline-info" target="_blank" href="<?php echo h(app_url('yonetim/qr_birim_single.php?id='.$r['id'])); ?>">Tekil</a></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-warning" data-toggle="collapse" data-target="#editBirim<?php echo h($r['id']); ?>">Düzenle</button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Silinsin mi?');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="act" value="sil">
                      <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                      <button class="btn btn-danger">Sil</button>
                    </form>
                  </div>
                  <div id="editBirim<?php echo h($r['id']); ?>" class="collapse mt-2">
                    <form method="post" class="border rounded p-2 bg-light">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="act" value="guncelle">
                      <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                      <div class="form-group mb-1">
                        <select name="kat_id" class="form-control form-control-sm" required>
                          <?php foreach($katlar as $k): ?>
                            <option value="<?php echo h($k['id']); ?>" <?php echo selected($k['id'],$r['kat_id']); ?>>
                              <?php echo h($k['bina_ad'].' / '.$k['ad']); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group mb-1">
                        <input type="text" name="ad" class="form-control form-control-sm" required value="<?php echo h($r['ad']); ?>">
                      </div>
                      <button class="btn btn-sm btn-primary">Kaydet</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; if(!$rows): ?>
              <tr><td colspan="7" class="text-muted">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php $pages=(int)ceil($total/$per); if($pages>1): ?>
      <div class="card-footer">
        <ul class="pagination pagination-sm mb-0">
          <?php for($i=1;$i<=$pages;$i++): $qs=$_GET;$qs['page']=$i; ?>
            <li class="page-item <?php echo $i===$page?'active':'';?>"><a class="page-link" href="?<?php echo http_build_query($qs); ?>"><?php echo $i; ?></a></li>
          <?php endfor; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>