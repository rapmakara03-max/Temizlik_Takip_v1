<?php
require_once __DIR__ . '/inc/header.php';

/* Kolon var mı? (gorevler) */
function gorevler_has(string $col): bool {
    static $cache = [];
    if (array_key_exists($col, $cache)) return $cache[$col];
    $row = fetch_one("SHOW COLUMNS FROM gorevler LIKE ?", [$col]);
    $cache[$col] = $row ? true : false;
    return $cache[$col];
}

/* === Ekle === */
if (is_post() && ($_POST['act'] ?? '') === 'ekle') {
    csrf_check();
    $baslik       = trim($_POST['baslik'] ?? '');
    $bina_id      = (int)($_POST['bina_id'] ?? 0);
    $kat_id       = (int)($_POST['kat_id'] ?? 0);
    $birim_id     = (int)($_POST['birim_id'] ?? 0) ?: null;
    $oda_id       = (int)($_POST['oda_id'] ?? 0);
    $assigned_id  = (int)($_POST['assigned_user_id'] ?? 0) ?: null;
    $durum        = trim($_POST['durum'] ?? 'YENI');

    if ($baslik === '' || $bina_id === 0 || $kat_id === 0 || $oda_id === 0) {
        flash_set('error', 'Zorunlu alanlar eksik: Başlık, Bina, Kat, Oda');
        redirect(current_path());
    }

    $cols=[]; $ph=[]; $vals=[];
    if (gorevler_has('baslik')) { $cols[]='baslik'; $ph[]='?'; $vals[]=$baslik; }
    if (gorevler_has('durum'))  { $cols[]='durum';  $ph[]='?'; $vals[]=$durum; }
    if (gorevler_has('bina_id'))  { $cols[]='bina_id';  $ph[]='?'; $vals[]=$bina_id; }
    if (gorevler_has('kat_id'))   { $cols[]='kat_id';   $ph[]='?'; $vals[]=$kat_id; }
    if (gorevler_has('birim_id')) { $cols[]='birim_id'; $ph[]='?'; $vals[]=$birim_id; }
    if (gorevler_has('oda_id'))   { $cols[]='oda_id';   $ph[]='?'; $vals[]=$oda_id; }
    if (gorevler_has('assigned_user_id')) { $cols[]='assigned_user_id'; $ph[]='?'; $vals[]=$assigned_id; }
    if (gorevler_has('created_at')) { $cols[]='created_at'; $ph[]='NOW()'; }
    if (gorevler_has('updated_at')) { $cols[]='updated_at'; $ph[]='NOW()'; }

    if (!$cols) {
        flash_set('error','Görev oluşturulamadı (uyumlu kolon yok).');
        redirect(current_path());
    }

    $sqlPh=[]; $bind=[]; $vi=0;
    foreach($ph as $p){
        if($p==='NOW()'){ $sqlPh[]='NOW()'; }
        else { $sqlPh[]='?'; $bind[]=$vals[$vi++]; }
    }
    $sql="INSERT INTO gorevler(".implode(',',$cols).") VALUES(".implode(',',$sqlPh).")";
    $ok=exec_stmt($sql,$bind);
    flash_set($ok?'success':'error',$ok?'Görev oluşturuldu.':'Görev oluşturulamadı.');
    redirect(current_path());
}

/* === Durum Güncelle === */
if (is_post() && ($_POST['act'] ?? '') === 'durum_guncelle') {
    csrf_check();
    $gid   = (int)($_POST['id'] ?? 0);
    $durum = trim($_POST['durum'] ?? '');
    if ($gid > 0 && $durum !== '') {
        $set=['durum=?']; $prm=[$durum];
        if(gorevler_has('updated_at')) $set[]='updated_at=NOW()';
        $prm[]=$gid;
        $ok=exec_stmt("UPDATE gorevler SET ".implode(', ',$set)." WHERE id=?",$prm);
        flash_set($ok?'success':'error',$ok?'Durum güncellendi.':'Güncellenemedi.');
    } else {
        flash_set('error','Eksik bilgi.');
    }
    redirect(current_path().'?'.http_build_query(array_diff_key($_GET,['page'=>1])));
}

/* === Liste Filtreleri / Arama === */
$q = trim($_GET['q'] ?? '');
$params=[]; $where=" WHERE 1=1 ";
if($q!==''){
    $where.=" AND (g.baslik LIKE ? OR u.ad LIKE ? OR b.ad LIKE ? OR k.ad LIKE ? OR bi.ad LIKE ? OR o.ad LIKE ?)";
    $like="%$q%";
    $params=[$like,$like,$like,$like,$like,$like];
}

/* === Sıralama === */
$sort=strtolower($_GET['sort']??'id');
$dir=strtoupper($_GET['dir']??'DESC');
$dir=in_array($dir,['ASC','DESC'],true)?$dir:'DESC';
$sortMap=['id'=>'g.id','durum'=>'g.durum'];
$order=($sortMap[$sort]??'g.id').' '.$dir.', g.id DESC';

/* === Sayfalama === */
$page = max(1,(int)($_GET['page']??1));
$per  = 50;
$offset = ($page-1)*$per;

/* Toplam */
$totalRow = fetch_one("
  SELECT COUNT(DISTINCT g.id) c
  FROM gorevler g
  LEFT JOIN binalar b ON b.id=g.bina_id
  LEFT JOIN katlar k ON k.id=g.kat_id
  LEFT JOIN birimler bi ON bi.id=g.birim_id
  LEFT JOIN odalar o ON o.id=g.oda_id
  LEFT JOIN kullanicilar u ON u.id=g.assigned_user_id
  $where
",$params);
$total=(int)($totalRow['c']??0);
$pages=(int)ceil($total/$per);

/* Kayıtlar */
$rows = fetch_all("
  SELECT g.id,g.baslik,g.durum,g.assigned_user_id,
         g.bina_id,g.kat_id,g.birim_id,g.oda_id,g.sikayet_id,
         b.ad bina_ad,k.ad kat_ad,bi.ad birim_ad,o.ad oda_ad,
         u.ad atanan_ad,u.telefon atanan_tel
  FROM gorevler g
  LEFT JOIN binalar b ON b.id=g.bina_id
  LEFT JOIN katlar k ON k.id=g.kat_id
  LEFT JOIN birimler bi ON bi.id=g.birim_id
  LEFT JOIN odalar o ON o.id=g.oda_id
  LEFT JOIN kullanicilar u ON u.id=g.assigned_user_id
  $where
  GROUP BY g.id
  ORDER BY $order
  LIMIT $per OFFSET $offset
",$params);

/* Yardımcı */
function sort_link(string $field): string {
    $curSort=strtolower($_GET['sort']??'id');
    $curDir=strtoupper($_GET['dir']??'DESC');
    $nextDir=($curSort===strtolower($field) && $curDir==='ASC')?'DESC':'ASC';
    $qs=$_GET; $qs['sort']=$field; $qs['dir']=$nextDir;
    return '?'.http_build_query($qs);
}

/* Form için binalar ve durum seçenekleri */
$binalar=fetch_all("SELECT id,ad FROM binalar ORDER BY ad",[]);
$durumOptions=['YENI','ATANDI','DEVAM','BEKLEME','TAMAM'];
?>
<div class="row">
  <div class="col-12">
    <div class="card card-outline card-info">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <h3 class="card-title mb-0">Görevler</h3>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <button class="btn btn-sm btn-outline-secondary mr-2" onclick="window.print()">Yazdır</button>
          <form method="get" class="d-inline-block">
            <div class="input-group input-group-sm" style="width:280px;">
              <input type="text" name="q" class="form-control" placeholder="Ara (başlık/atanan/konum)" value="<?php echo h($q); ?>">
              <button class="btn btn-outline-secondary btn-sm">Ara</button>
            </div>
          </form>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0">
            <thead>
              <tr>
                <th><a href="<?php echo h(sort_link('id')); ?>">ID</a></th>
                <th>Başlık</th>
                <th>Konum</th>
                <th><a href="<?php echo h(sort_link('durum')); ?>">Durum</a></th>
                <th>Atanan</th>
                <th>Detay</th>
                <th>Durum Güncelle</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo h($r['baslik']??''); ?></td>
                <td><?php echo h(($r['bina_ad']??'-').' / '.($r['kat_ad']??'-').' / '.(($r['birim_ad']??'-')?:'-').' / '.($r['oda_ad']??'-')); ?></td>
                <td><?php echo h($r['durum']??'-'); ?></td>
                <td>
                  <?php if(!empty($r['atanan_ad'])): ?>
                    <?php echo h($r['atanan_ad']); ?>
                    <?php if(!empty($r['atanan_tel'])): ?><div class="small text-muted"><?php echo h($r['atanan_tel']); ?></div><?php endif; ?>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <?php if((int)($r['sikayet_id']??0)>0): ?>
                      <a class="btn btn-warning" href="<?php echo h(app_url('yonetim/sikayet_atama.php?id='.$r['sikayet_id'])); ?>">Görev Ata</a>
                    <?php else: ?>
                      <a class="btn btn-warning" href="<?php echo h(app_url('yonetim/gorev_detay.php?id='.$r['id'])); ?>">Görev Ata</a>
                    <?php endif; ?>
                    <a class="btn btn-info" href="<?php echo h(app_url('yonetim/gorev_detay.php?id='.$r['id'])); ?>">Detay Gör</a>
                  </div>
                </td>
                <td>
                  <form method="post" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="act" value="durum_guncelle">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <div class="input-group input-group-sm">
                      <select name="durum" class="form-control form-control-sm">
                        <?php foreach($durumOptions as $d): ?>
                          <option value="<?php echo h($d); ?>" <?php echo selected($r['durum']??'', $d); ?>><?php echo h($d); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-primary btn-sm">Kaydet</button>
                    </div>
                  </form>
                </td>
              </tr>
              <?php endforeach; if(!$rows): ?>
                <tr><td colspan="7" class="text-muted">Kayıt yok.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if($pages>1): ?>
      <div class="card-footer">
        <ul class="pagination pagination-sm mb-0">
          <?php for($i=1;$i<=$pages;$i++):
            $qs=$_GET; $qs['page']=$i; $active=$i===$page?' active':'';
          ?>
            <li class="page-item<?php echo $active; ?>">
              <a class="page-link" href="?<?php echo h(http_build_query($qs)); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-outline card-primary">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Yeni Görev</h3>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">Yazdır</button>
      </div>
      <form method="post" autocomplete="off" id="frmYeniGorev">
        <div class="card-body">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="act" value="ekle">
          <div class="row">
            <div class="col-md-5">
              <div class="form-group">
                <label>Başlık</label>
                <input type="text" name="baslik" class="form-control form-control-sm" required>
              </div>
            </div>
            <div class="col-md-7">
              <div class="form-group">
                <label>Atanacak Personel (Arama ile)</label>
                <div class="input-group input-group-sm">
                  <input type="text" id="pSearch" class="form-control form-control-sm" placeholder="İsim/e-posta/telefon">
                  <select id="personel_list" class="form-control form-control-sm" style="max-width:50%;">
                    <option value="">— sonuç yok —</option>
                  </select>
                  <input type="hidden" name="assigned_user_id" id="assigned_user_id">
                </div>
                <small class="text-muted">Arama yazınca listeden seç.</small>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-3"><div class="form-group">
              <label>Bina</label>
              <select name="bina_id" id="bina_id" class="form-control form-control-sm" required>
                <option value="">Seçiniz</option>
                <?php foreach($binalar as $b): ?>
                  <option value="<?php echo (int)$b['id']; ?>"><?php echo h($b['ad']); ?></option>
                <?php endforeach; ?>
              </select>
            </div></div>
            <div class="col-md-3"><div class="form-group">
              <label>Kat</label>
              <select name="kat_id" id="kat_id" class="form-control form-control-sm" required>
                <option value="">Önce Bina Seçin</option>
              </select>
            </div></div>
            <div class="col-md-3"><div class="form-group">
              <label>Birim</label>
              <select name="birim_id" id="birim_id" class="form-control form-control-sm">
                <option value="">(Opsiyonel)</option>
              </select>
            </div></div>
            <div class="col-md-3"><div class="form-group">
              <label>Oda</label>
              <select name="oda_id" id="oda_id" class="form-control form-control-sm" required>
                <option value="">Önce kat/birim seçiniz</option>
              </select>
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-3"><div class="form-group">
              <label>Durum</label>
              <select name="durum" class="form-control form-control-sm">
                <?php foreach($durumOptions as $d): ?>
                  <option value="<?php echo h($d); ?>"><?php echo h($d); ?></option>
                <?php endforeach; ?>
              </select>
            </div></div>
          </div>
        </div>
        <div class="card-footer text-right">
          <button class="btn btn-sm btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* (Aynı JS - sadece print butonu eklendi) */
(function(){
  const URL_PERSONEL = '<?php echo h(app_url('yonetim/ajax_personel.php')); ?>';
  const pSearch=document.getElementById('pSearch');
  const pList=document.getElementById('personel_list');
  const pHidden=document.getElementById('assigned_user_id');
  if(pSearch&&pList&&pHidden){
    let timer=null;
    function doSearch(){
      fetch(URL_PERSONEL+'?q='+encodeURIComponent(pSearch.value.trim()))
        .then(r=>r.json()).then(j=>{
          while(pList.firstChild) pList.removeChild(pList.firstChild);
            if(j.ok && Array.isArray(j.data) && j.data.length){
              j.data.forEach(it=>{
                const opt=document.createElement('option');
                opt.value=it.id;
                opt.textContent=it.ad+(it.telefon?' ('+it.telefon+')':'')+(it.gorevi?' — '+it.gorevi:'');
                pList.appendChild(opt);
              });
            }else{
              const opt=document.createElement('option');
              opt.value=''; opt.textContent='— sonuç yok —';
              pList.appendChild(opt);
            }
        });
    }
    pSearch.addEventListener('input',()=>{ clearTimeout(timer); timer=setTimeout(doSearch,250); });
    doSearch();
    pList.addEventListener('change',()=>{ pHidden.value=pList.value||''; });
  }
  const URL_KATLAR='<?php echo h(app_url('yonetim/ajax_katlar.php')); ?>';
  const URL_BIRIMLER='<?php echo h(app_url('yonetim/ajax_birimler.php')); ?>';
  const URL_ODALAR='<?php echo h(app_url('yonetim/ajax_odalar.php')); ?>';
  const selBina=document.getElementById('bina_id');
  const selKat=document.getElementById('kat_id');
  const selBirim=document.getElementById('birim_id');
  const selOda=document.getElementById('oda_id');
  function fillSelect(sel,items,placeholder){
    while(sel.firstChild) sel.removeChild(sel.firstChild);
    const opt0=document.createElement('option');
    opt0.value=''; opt0.textContent=placeholder||'Seçiniz';
    sel.appendChild(opt0);
    (items||[]).forEach(it=>{
      const opt=document.createElement('option');
      opt.value=it.id; opt.textContent=it.ad;
      sel.appendChild(opt);
    });
  }
  function loadKatlar(){
    fillSelect(selKat,[],'Önce Bina Seçin');
    fillSelect(selBirim,[],'(Opsiyonel)');
    fillSelect(selOda,[],'Önce kat/birim seçiniz');
    if(!selBina.value) return;
    fetch(URL_KATLAR+'?bina_id='+encodeURIComponent(selBina.value))
      .then(r=>r.json()).then(j=>fillSelect(selKat,j.data||[],'Seçiniz'));
  }
  function loadBirimler(){
    fillSelect(selBirim,[],'(Opsiyonel)');
    fillSelect(selOda,[],'Önce kat/birim seçiniz');
    if(!selKat.value) return;
    fetch(URL_BIRIMLER+'?kat_id='+encodeURIComponent(selKat.value))
      .then(r=>r.json()).then(j=>{
        fillSelect(selBirim,j.data||[],'(Opsiyonel)');
        loadOdalar();
      });
  }
  function loadOdalar(){
    fillSelect(selOda,[],'Seçiniz');
    if(!selBina.value||!selKat.value) return;
    const qs=new URLSearchParams({bina_id:selBina.value,kat_id:selKat.value});
    if(selBirim.value) qs.set('birim_id',selBirim.value);
    fetch(URL_ODALAR+'?'+qs.toString())
      .then(r=>r.json()).then(j=>fillSelect(selOda,j.data||[],'Seçiniz'));
  }
  selBina&&selBina.addEventListener('change',loadKatlar);
  selKat&&selKat.addEventListener('change',loadBirimler);
  selBirim&&selBirim.addEventListener('change',loadOdalar);
})();
</script>
<?php require_once __DIR__ . '/inc/footer.php'; ?>