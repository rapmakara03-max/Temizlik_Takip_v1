<?php
require_once __DIR__ . '/inc/header.php';

if (is_post()) {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'ekle') {
        $ad = trim($_POST['ad'] ?? '');
        $aciklama = trim($_POST['aciklama'] ?? '');
        if (!$ad) {
            flash_set('error','Ad zorunlu.');
        } else {
            if (exec_stmt("INSERT INTO binalar(ad,aciklama) VALUES(?,?)", [$ad,$aciklama])) {
                flash_set('success','Bina eklendi.');
            } else flash_set('error','Eklenemedi.');
        }
        redirect(current_path());
    }
    if ($act === 'guncelle') {
        $id = (int)($_POST['id'] ?? 0);
        $ad = trim($_POST['ad'] ?? '');
        $aciklama = trim($_POST['aciklama'] ?? '');
        if ($id>0 && $ad) {
            if (exec_stmt("UPDATE binalar SET ad=?, aciklama=? WHERE id=?", [$ad,$aciklama,$id])) {
                flash_set('success','Güncellendi.');
            } else flash_set('error','Güncellenemedi.');
        } else flash_set('error','Eksik veri.');
        redirect(current_path());
    }
    if ($act === 'sil') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0 && exec_stmt("DELETE FROM binalar WHERE id=?",[$id])) {
            flash_set('success','Silindi.');
        } else flash_set('error','Silinemedi (bağımlı kayıtlar olabilir).');
        redirect(current_path());
    }
}

[$page,$per,$offset] = paginate_params();
[$sWhere,$sParams] = search_clause('ad');
$order = sort_clause(['id'=>'id','ad'=>'ad'], 'id DESC');
$total = fetch_one("SELECT COUNT(*) c FROM binalar WHERE 1=1 $sWhere",$sParams)['c'] ?? 0;
$rows = fetch_all("SELECT * FROM binalar WHERE 1=1 $sWhere ORDER BY $order LIMIT $per OFFSET $offset",$sParams);
?>
<div class="row">
  <div class="col-lg-4">
    <div class="card card-outline card-primary">
      <div class="card-header"><h3 class="card-title mb-0">Yeni Bina</h3></div>
      <form method="post">
        <div class="card-body">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="act" value="ekle">
          <div class="form-group">
            <label>Ad</label>
            <input type="text" name="ad" class="form-control form-control-sm" required>
          </div>
          <div class="form-group">
            <label>Açıklama</label>
            <textarea name="aciklama" class="form-control form-control-sm" rows="3"></textarea>
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
      <div class="card-header"><h3 class="card-title mb-0">Binalar</h3></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th><a href="?<?php echo http_build_query(array_merge($_GET,['sort'=>'id','dir'=>(($_GET['dir']??'ASC')==='ASC'?'DESC':'ASC')])); ?>">ID</a></th>
                <th><a href="?<?php echo http_build_query(array_merge($_GET,['sort'=>'ad','dir'=>(($_GET['dir']??'ASC')==='ASC'?'DESC':'ASC')])); ?>">Ad</a></th>
                <th>Açıklama</th>
                <th style="width:170px;">İşlemler</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?php echo h($r['id']); ?></td>
                <td><?php echo h($r['ad']); ?></td>
                <td><?php echo h($r['aciklama']); ?></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-warning" data-toggle="collapse" data-target="#editBina<?php echo h($r['id']); ?>">Düzenle</button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Silinsin mi?');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="act" value="sil">
                      <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                      <button class="btn btn-danger">Sil</button>
                    </form>
                  </div>
                  <div id="editBina<?php echo h($r['id']); ?>" class="collapse mt-2">
                    <form method="post" class="border rounded p-2 bg-light">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="act" value="guncelle">
                      <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                      <div class="form-group mb-1">
                        <input type="text" name="ad" class="form-control form-control-sm" required value="<?php echo h($r['ad']); ?>">
                      </div>
                      <div class="form-group mb-1">
                        <textarea name="aciklama" class="form-control form-control-sm" rows="2"><?php echo h($r['aciklama']); ?></textarea>
                      </div>
                      <button class="btn btn-sm btn-primary">Kaydet</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; if(!$rows): ?>
              <tr><td colspan="4" class="text-muted">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php $totalPages=(int)ceil($total/$per); if($totalPages>1): ?>
      <div class="card-footer">
        <ul class="pagination pagination-sm mb-0">
          <?php for($i=1;$i<=$totalPages;$i++): $qs=$_GET; $qs['page']=$i; ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>">
              <a class="page-link" href="?<?php echo http_build_query($qs); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>