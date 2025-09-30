<?php
require_once __DIR__ . '/inc/header.php';

/* Listeler */
$binalar  = fetch_all("SELECT id, ad FROM binalar ORDER BY ad");

/* Filtre parametreleri */
$fil_bina  = isset($_GET['bina']) && $_GET['bina']!=='' ? (int)$_GET['bina'] : null;
$fil_kat   = isset($_GET['kat']) && $_GET['kat']!=='' ? (int)$_GET['kat'] : null;
$fil_birim = isset($_GET['birim']) && $_GET['birim']!=='' ? (int)$_GET['birim'] : null;

/* Eksik qr üret */
$missing = fetch_all("SELECT id FROM odalar WHERE qr_kod IS NULL LIMIT 200");
foreach($missing as $m){
    $code=generate_generic_qr_code();
    exec_stmt("UPDATE odalar SET qr_kod=? WHERE id=? AND qr_kod IS NULL",[$code,$m['id']]);
}

/* POST işlemleri */
if(is_post()){
    csrf_check();
    $act=$_POST['act']??'';
    if($act==='ekle'){
        $bina_id=(int)($_POST['bina_id']??0);
        $kat_id=(int)($_POST['kat_id']??0);
        $birim_id=($_POST['birim_id']!=='') ? (int)$_POST['birim_id'] : null;
        $ad=trim($_POST['ad']??'');
        $aciklama=trim($_POST['aciklama']??'');
        if($bina_id<=0 || $kat_id<=0 || !$ad){
            flash_set('error','Zorunlu alanlar eksik.');
        } else {
            $qr=generate_generic_qr_code();
            if(exec_stmt("INSERT INTO odalar(bina_id,kat_id,birim_id,ad,aciklama,qr_kod) VALUES(?,?,?,?,?,?)",
                [$bina_id,$kat_id,$birim_id,$ad,$aciklama,$qr])) flash_set('success','Oda eklendi.');
            else flash_set('error','Eklenemedi.');
        }
        redirect(current_path().'?'.http_build_query(array_filter([
            'bina'=>$fil_bina,'kat'=>$fil_kat,'birim'=>$fil_birim
        ],fn($v)=>$v!==null)));
    }
    if($act==='guncelle'){
        $id=(int)($_POST['id']??0);
        $bina_id=(int)($_POST['bina_id']??0);
        $kat_id=(int)($_POST['kat_id']??0);
        $birim_id=$_POST['birim_id']!==''?(int)$_POST['birim_id']:null;
        $ad=trim($_POST['ad']??'');
        $aciklama=trim($_POST['aciklama']??'');
        if($id>0 && $bina_id>0 && $kat_id>0 && $ad){
            if(exec_stmt("UPDATE odalar SET bina_id=?, kat_id=?, birim_id=?, ad=?, aciklama=? WHERE id=?",
                [$bina_id,$kat_id,$birim_id,$ad,$aciklama,$id])) flash_set('success','Güncellendi.');
            else flash_set('error','Güncellenemedi.');
        } else flash_set('error','Eksik veri.');
        redirect(current_path().'?'.http_build_query(array_filter([
            'bina'=>$fil_bina,'kat'=>$fil_kat,'birim'=>$fil_birim
        ],fn($v)=>$v!==null)));
    }
    if($act==='sil'){
        $id=(int)($_POST['id']??0);
        if($id>0 && exec_stmt("DELETE FROM odalar WHERE id=?",[$id])) flash_set('success','Silindi.');
        else flash_set('error','Silinemedi.');
        redirect(current_path().'?'.http_build_query(array_filter([
            'bina'=>$fil_bina,'kat'=>$fil_kat,'birim'=>$fil_birim
        ],fn($v)=>$v!==null)));
    }
    if($act==='yenile_qr'){
        $id=(int)($_POST['id']??0);
        if($id>0){
            $code=generate_generic_qr_code();
            if(exec_stmt("UPDATE odalar SET qr_kod=? WHERE id=?",[$code,$id])) flash_set('success','QR yenilendi.');
            else flash_set('error','QR yenilenemedi.');
        }
        redirect(current_path().'?'.http_build_query(array_filter([
            'bina'=>$fil_bina,'kat'=>$fil_kat,'birim'=>$fil_birim
        ],fn($v)=>$v!==null)));
    }
}

/* Sayfalama & Arama */
[$page,$per,$offset]=paginate_params();
[$sWhere,$sParams]=search_clause('o.ad');

if($fil_bina){
    $sWhere.=" AND o.bina_id=?";
    $sParams[]=$fil_bina;
}
if($fil_kat){
    $sWhere.=" AND o.kat_id=?";
    $sParams[]=$fil_kat;
}
if($fil_birim){
    $sWhere.=" AND o.birim_id=?";
    $sParams[]=$fil_birim;
}

$order=sort_clause(['id'=>'o.id','ad'=>'o.ad'],'o.id DESC');
$total=fetch_one("SELECT COUNT(*) c FROM odalar o WHERE 1=1 $sWhere",$sParams)['c']??0;

$rows=fetch_all("SELECT o.*, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad
 FROM odalar o
 LEFT JOIN binalar b ON b.id=o.bina_id
 LEFT JOIN katlar k ON k.id=o.kat_id
 LEFT JOIN birimler bi ON bi.id=o.birim_id
 WHERE 1=1 $sWhere
 ORDER BY $order LIMIT $per OFFSET $offset",$sParams);
?>
<div class="row">
  <div class="col-xl-4">
    <div class="card card-outline card-primary mb-3">
      <div class="card-header"><h3 class="card-title mb-0">Filtre</h3></div>
      <div class="card-body">
        <form method="get" id="filterForm">
          <div class="form-group">
            <label>Bina</label>
            <select name="bina" id="filBina" class="form-control form-control-sm">
              <option value="">(Hepsi)</option>
              <?php foreach($binalar as $b): ?>
                <option value="<?php echo h($b['id']); ?>" <?php echo selected($fil_bina,$b['id']); ?>><?php echo h($b['ad']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Kat</label>
            <select name="kat" id="filKat" class="form-control form-control-sm">
              <option value="">Önce Bina Seçin</option>
            </select>
          </div>
            <div class="form-group">
            <label>Birim</label>
            <select name="birim" id="filBirim" class="form-control form-control-sm">
              <option value="">Önce Kat Seçin</option>
            </select>
          </div>
          <button class="btn btn-sm btn-primary">Uygula</button>
          <?php if($fil_bina || $fil_kat || $fil_birim): ?>
            <a href="<?php echo h(current_path()); ?>" class="btn btn-sm btn-secondary">Temizle</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card card-outline card-success">
      <div class="card-header"><h3 class="card-title mb-0">Yeni Oda</h3></div>
      <form method="post" id="yeniOdaForm">
        <div class="card-body">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="act" value="ekle">
          <div class="form-group">
            <label>Bina *</label>
            <select name="bina_id" id="yBina" class="form-control form-control-sm" required>
              <option value="">Seçiniz</option>
              <?php foreach($binalar as $b): ?>
                <option value="<?php echo h($b['id']); ?>"><?php echo h($b['ad']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Kat *</label>
            <select name="kat_id" id="yKat" class="form-control form-control-sm" required>
              <option value="">Önce Bina Seçin</option>
            </select>
          </div>
          <div class="form-group">
            <label>Birim *</label>
            <select name="birim_id" id="yBirim" class="form-control form-control-sm" required>
              <option value="">(Yok)</option>
            </select>
          </div>
          <div class="form-group">
            <label>Ad *</label>
            <input type="text" name="ad" class="form-control form-control-sm" required>
          </div>
          <div class="form-group">
            <label>Açıklama</label>
            <textarea name="aciklama" class="form-control form-control-sm" rows="3"></textarea>
          </div>
        </div>
        <div class="card-footer text-right">
          <button class="btn btn-sm btn-success">Kaydet</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card card-outline card-info">
      <div class="card-header"><h3 class="card-title mb-0">Odalar -  <b><a href="/yonetim/qr_odalar.php" target="_blank">Toplu QR Kod Yazdır</a></b> </h3></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th>ID</th><th>Ad</th><th>Bina</th><th>Kat</th><th>Birim</th><th>QR Kod</th><th>Rapor</th><th>Yazdır</th><th style="width:185px;">İşlemler</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?php echo h($r['id']); ?></td>
                <td><?php echo h($r['ad']); ?></td>
                <td><?php echo h($r['bina_ad']); ?></td>
                <td><?php echo h($r['kat_ad']); ?></td>
                <td><?php echo h($r['birim_ad']); ?></td>
                <td>
                  <?php $qrLink=app_url('qr_gate.php?c='.$r['qr_kod']); ?>
                  <div style="max-width:230px;word-break:break-all;font-size:11px;">
                     <code><a href="<?php echo h($qrLink); ?>" target="_blank"><?php echo h($r['qr_kod']); ?></a><br>
                   </code>
                  </div>
                  <form method="post" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="act" value="yenile_qr">
                    <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                    <button class="btn btn-xs btn-outline-secondary" onclick="return confirm('QR yenilensin mi?');">Yenile</button>
                  </form>
                </td>
                <td><a class="btn btn-xs btn-outline-info" href="<?php echo h(app_url('yonetim/rapor_temizlik_personel.php?oda='.$r['id'])); ?>">Rapor</a></td>
                <td><a class="btn btn-xs btn-outline-primary" target="_blank" href="<?php echo h(app_url('yonetim/qr_oda.php?id='.$r['id'])); ?>">QR</a></td>
                <td>
                  <div class="btn-group btn-group-sm mb-1">
                    <button class="btn btn-warning" data-toggle="collapse" data-target="#editOda<?php echo h($r['id']); ?>">Düzenle</button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Silinsin mi?');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="act" value="sil">
                      <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                      <button class="btn btn-danger">Sil</button>
                    </form>
                  </div>
                  <div id="editOda<?php echo h($r['id']); ?>" class="collapse mt-2">
                    <form method="post" class="border rounded p-2 bg-light edit-oda-form"
                          data-oda-id="<?php echo h($r['id']); ?>"
                          data-bina-id="<?php echo h($r['bina_id']); ?>"
                          data-kat-id="<?php echo h($r['kat_id']); ?>"
                          data-birim-id="<?php echo h($r['birim_id']); ?>">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="act" value="guncelle">
                      <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                      <div class="form-group mb-1">
                        <select name="bina_id" class="form-control form-control-sm bina-select" required>
                          <option value="">Seçiniz</option>
                          <?php foreach($binalar as $b): ?>
                            <option value="<?php echo h($b['id']); ?>"><?php echo h($b['ad']); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group mb-1">
                        <select name="kat_id" class="form-control form-control-sm kat-select" required>
                          <option value="">Önce Bina</option>
                        </select>
                      </div>
                      <div class="form-group mb-1">
                        <select name="birim_id" class="form-control form-control-sm birim-select">
                          <option value="">(Yok)</option>
                        </select>
                      </div>
                      <div class="form-group mb-1">
                        <input type="text" name="ad" class="form-control form-control-sm" value="<?php echo h($r['ad']); ?>" required>
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
              <tr><td colspan="9" class="text-muted">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php $pages=(int)ceil($total/$per); if($pages>1): ?>
      <div class="card-footer">
        <ul class="pagination pagination-sm mb-0">
          <?php for($i=1;$i<=$pages;$i++): $qs=$_GET; $qs['page']=$i; ?>
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

<script>
(function(){
  const ajaxKat = (binaId) => fetch('<?php echo h(app_url('yonetim/ajax_katlar.php')); ?>?bina_id='+binaId).then(r=>r.json());
  const ajaxBirim = (katId) => fetch('<?php echo h(app_url('yonetim/ajax_birimler.php')); ?>?kat_id='+katId).then(r=>r.json());

  // FILTRE FORMU
  const filBina = document.getElementById('filBina');
  const filKat  = document.getElementById('filKat');
  const filBirim= document.getElementById('filBirim');

  const currentKat  = '<?php echo $fil_kat ?: ''; ?>';
  const currentBirim= '<?php echo $fil_birim ?: ''; ?>';

  function loadFilterKat(binaId, cb){
    filKat.innerHTML='<option value="">(Hepsi)</option>';
    filBirim.innerHTML='<option value="">Önce Kat Seçin</option>';
    if(!binaId){ return; }
    ajaxKat(binaId).then(js=>{
      if(js.data){
        js.data.forEach(k=>{
          const opt=document.createElement('option');
          opt.value=k.id; opt.textContent=k.ad;
          if(currentKat && currentKat==k.id) opt.selected=true;
          filKat.appendChild(opt);
        });
        if(currentKat){
          loadFilterBirim(currentKat);
        }
      }
    });
  }
  function loadFilterBirim(katId){
    filBirim.innerHTML='<option value="">(Hepsi)</option>';
    if(!katId){ filBirim.innerHTML='<option value="">Önce Kat Seçin</option>'; return; }
    ajaxBirim(katId).then(js=>{
      if(js.data){
        js.data.forEach(b=>{
          const opt=document.createElement('option');
            opt.value=b.id; opt.textContent=b.ad;
          if(currentBirim && currentBirim==b.id) opt.selected=true;
          filBirim.appendChild(opt);
        });
      }
    });
  }
  filBina && filBina.addEventListener('change',()=>{
    loadFilterKat(filBina.value);
  });
  filKat && filKat.addEventListener('change',()=>{
    loadFilterBirim(filKat.value);
  });
  if(filBina && filBina.value){
    loadFilterKat(filBina.value);
  }

  // YENI ODA FORMU
  const yBina=document.getElementById('yBina');
  const yKat=document.getElementById('yKat');
  const yBirim=document.getElementById('yBirim');

  function loadYeniKat(binaId){
    yKat.innerHTML='<option value="">Seçiniz</option>';
    yBirim.innerHTML='<option value="">(Yok)</option>';
    if(!binaId) return;
    ajaxKat(binaId).then(js=>{
      if(js.data){
        js.data.forEach(k=>{
          const opt=document.createElement('option');
          opt.value=k.id; opt.textContent=k.ad;
          yKat.appendChild(opt);
        });
      }
    });
  }
  function loadYeniBirim(katId){
    yBirim.innerHTML='<option value="">(Yok)</option>';
    if(!katId) return;
    ajaxBirim(katId).then(js=>{
      if(js.data){
        js.data.forEach(b=>{
          const opt=document.createElement('option');
          opt.value=b.id; opt.textContent=b.ad;
          yBirim.appendChild(opt);
        });
      }
    });
  }
  yBina && yBina.addEventListener('change',()=>loadYeniKat(yBina.value));
  yKat && yKat.addEventListener('change',()=>loadYeniBirim(yKat.value));

  // EDIT FORMLAR
  document.querySelectorAll('.edit-oda-form').forEach(form=>{
    const binaSel=form.querySelector('.bina-select');
    const katSel =form.querySelector('.kat-select');
    const birimSel=form.querySelector('.birim-select');
    const binaVal=form.dataset.binaId;
    const katVal =form.dataset.katId;
    const birimVal=form.dataset.birimId;

    // Bina doldur
    if(binaSel){
      Array.from(binaSel.options).forEach(o=>{
        if(o.value===binaVal) o.selected=true;
      });
      // Katları çek
      if(binaVal){
        katSel.innerHTML='<option value="">Yükleniyor...</option>';
        ajaxKat(binaVal).then(js=>{
          katSel.innerHTML='<option value="">Seçiniz</option>';
          if(js.data){
            js.data.forEach(k=>{
              const opt=document.createElement('option');
              opt.value=k.id; opt.textContent=k.ad;
              if(katVal==k.id) opt.selected=true;
              katSel.appendChild(opt);
            });
            // Birimler
            if(katVal){
              birimSel.innerHTML='<option value="">(Yok)</option>';
              ajaxBirim(katVal).then(jsb=>{
                if(jsb.data){
                  jsb.data.forEach(b=>{
                    const opt=document.createElement('option');
                    opt.value=b.id; opt.textContent=b.ad;
                    if(birimVal && birimVal==b.id) opt.selected=true;
                    birimSel.appendChild(opt);
                  });
                }
              });
            }
          }
        });
      }
    }

    binaSel.addEventListener('change',()=>{
      katSel.innerHTML='<option value="">Yükleniyor...</option>';
      birimSel.innerHTML='<option value="">(Yok)</option>';
      if(!binaSel.value){ katSel.innerHTML='<option value="">Önce Bina</option>'; return; }
      ajaxKat(binaSel.value).then(js=>{
        katSel.innerHTML='<option value="">Seçiniz</option>';
        js.data.forEach(k=>{
          const opt=document.createElement('option');
          opt.value=k.id; opt.textContent=k.ad;
          katSel.appendChild(opt);
        });
      });
    });
    katSel.addEventListener('change',()=>{
      birimSel.innerHTML='<option value="">(Yok)</option>';
      if(!katSel.value) return;
      ajaxBirim(katSel.value).then(js=>{
        js.data.forEach(b=>{
          const opt=document.createElement('option');
          opt.value=b.id; opt.textContent=b.ad;
          birimSel.appendChild(opt);
        });
      });
    });
  });
})();
</script>
<?php require_once __DIR__ . '/inc/footer.php'; ?>