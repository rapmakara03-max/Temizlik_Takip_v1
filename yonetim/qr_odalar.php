<?php
require_once __DIR__.'/inc/header.php';

/*
  Toplu Oda QR Yazdırma
  Filtreler: bina, kat, birim (hepsi opsiyonel)
  Kullanım:
    /yonetim/qr_odalar.php
    /yonetim/qr_odalar.php?bina=1
    /yonetim/qr_odalar.php?bina=1&kat=2
    /yonetim/qr_odalar.php?bina=1&kat=2&birim=5
*/

$fil_bina  = isset($_GET['bina'])  && $_GET['bina']  !== '' ? (int)$_GET['bina']  : null;
$fil_kat   = isset($_GET['kat'])   && $_GET['kat']   !== '' ? (int)$_GET['kat']   : null;
$fil_birim = isset($_GET['birim']) && $_GET['birim'] !== '' ? (int)$_GET['birim'] : null;

$where = " WHERE 1=1 ";
$params = [];
if($fil_bina){
  $where.=" AND o.bina_id=?"; $params[]=$fil_bina;
}
if($fil_kat){
  $where.=" AND o.kat_id=?"; $params[]=$fil_kat;
}
if($fil_birim){
  $where.=" AND o.birim_id=?"; $params[]=$fil_birim;
}

/* Eksik QR üret (isteğe bağlı, güvenli limit) */
$missing = fetch_all("SELECT id FROM odalar WHERE qr_kod IS NULL LIMIT 200");
foreach($missing as $m){
    $code=generate_generic_qr_code();
    exec_stmt("UPDATE odalar SET qr_kod=? WHERE id=? AND qr_kod IS NULL",[$code,$m['id']]);
}

$rows = fetch_all("
  SELECT o.id,o.ad,o.qr_kod,
         b.ad AS bina_ad,
         k.ad AS kat_ad,
         bi.ad AS birim_ad
  FROM odalar o
  LEFT JOIN binalar b ON b.id=o.bina_id
  LEFT JOIN katlar k  ON k.id=o.kat_id
  LEFT JOIN birimler bi ON bi.id=o.birim_id
  $where
  ORDER BY b.ad, k.ad, bi.ad, o.ad
",$params);

$qrGenBase = app_url('kutuphane/qr.php');
$gateBase  = absolute_url('/qr_gate.php?c=');
$version   = 'v1.0';
$createdAt = date('d.m.Y H:i');

/* Filtre listeleri (basit) */
$binalar = fetch_all("SELECT id,ad FROM binalar ORDER BY ad");
$katlar  = $fil_bina
  ? fetch_all("SELECT id,ad FROM katlar WHERE bina_id=? ORDER BY ad",[$fil_bina])
  : [];
$birimler = $fil_kat
  ? fetch_all("SELECT id,ad FROM birimler WHERE kat_id=? ORDER BY ad",[$fil_kat])
  : [];

function sel($cur,$val){ return (string)$cur===(string)$val?'selected':''; }
?>
<style>
:root{
  --font:"Arial","Segoe UI",system-ui,sans-serif;
  --bg:#f4f6f9;
  --border:#d1d6db;
  --border-dark:#222;
  --qr-size:200px;
  --gap-page:10mm;
}
html,body{
  margin:0;
  padding:0;
  font-family:var(--font);
  background:var(--bg);
  -webkit-print-color-adjust:exact;
  print-color-adjust:exact;
  font-size:14px;
  color:#111;
}
.top-bar{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  align-items:center;
  justify-content:space-between;
  background:#fff;
  padding:14px 18px;
  border-bottom:1px solid #d9d9d9;
  position:sticky;
  top:0;
  z-index:10;
}
.top-bar h1{
  margin:0;
  font-size:22px;
  font-weight:700;
  letter-spacing:.4px;
}
.top-bar .actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}
.top-bar button.btn,
.top-bar a.btn{
  background:#222;
  color:#fff;
  border:none;
  padding:10px 18px;
  border-radius:6px;
  font-size:13px;
  font-weight:600;
  text-decoration:none;
  cursor:pointer;
}
.top-bar a.back{
  background:#555;
}
.filter-box{
  background:#fff;
  border-bottom:1px solid #d9d9d9;
  padding:10px 18px 2px;
  display:flex;
  gap:14px;
  flex-wrap:wrap;
  align-items:flex-end;
}
.filter-box .fgroup{
  display:flex;
  flex-direction:column;
  min-width:140px;
}
.filter-box label{
  font-size:11px;
  font-weight:600;
  margin-bottom:4px;
  letter-spacing:.3px;
  color:#333;
}
.filter-box select{
  padding:6px 8px;
  border:1px solid #ccc;
  border-radius:4px;
  font-size:13px;
  background:#fff;
}
.filter-box button{
  padding:8px 14px;
  border:none;
  border-radius:5px;
  background:#1d6ef0;
  color:#fff;
  font-size:13px;
  font-weight:600;
  cursor:pointer;
}
.filter-box a.clear{
  font-size:12px;
  text-decoration:none;
  padding:6px 10px;
  background:#eee;
  border-radius:5px;
  color:#333;
}

.list-container{
  max-width:1280px;
  margin:20px auto 60px;
  padding:0 18px;
  box-sizing:border-box;
}
.room-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
  gap:18px;
}
.room-card{
  background:#fff;
  border:1px solid var(--border);
  border-radius:10px;
  padding:16px 16px 18px;
  display:flex;
  flex-direction:column;
  position:relative;
  box-shadow:0 2px 6px rgba(0,0,0,0.06);
}
.room-card h2{
  margin:0 0 6px;
  font-size:15px;
  font-weight:700;
  letter-spacing:.4px;
  text-align:center;
  color:#111;
}
.loc-line{
  font-size:11px;
  line-height:1.35;
  text-align:center;
  color:#444;
  margin:0 0 10px;
  min-height:32px;
  overflow:hidden;
  text-overflow:ellipsis;
}
.qr-wrapper{
  margin:0 auto;
  border:2px solid var(--border-dark);
  padding:8px 12px;
  border-radius:6px;
  background:#fff;
  display:flex;
  justify-content:center;
  align-items:center;
}
.qr-wrapper img{
  display:block;
  width:var(--qr-size);
  max-width:var(--qr-size);
  height:auto;
}
.helper{
  margin-top:8px;
  font-size:11px;
  text-align:center;
  font-weight:600;
  letter-spacing:.25px;
  color:#222;
}
.meta-bottom{
  margin-top:10px;
  font-size:10px;
  display:flex;
  justify-content:space-between;
  color:#666;
  letter-spacing:.25px;
  font-weight:500;
}
.empty{
  background:#fff;
  border:1px dashed #bbb;
  padding:50px 30px;
  text-align:center;
  border-radius:12px;
  font-size:15px;
  color:#555;
  margin-top:30px;
}

/* PRINT LAYOUT */
@media print {
  .top-bar, .filter-box{display:none !important;}
  @page { size:A4 portrait; margin:0; }
  body,html{background:#fff;}
  .list-container{
    max-width:unset;
    margin:0;
    padding:0;
  }

  /* Sayfa: 4 blok (2x2) - esnek yapı */
  .print-sheet{
    width:210mm;
    height:297mm;
    display:grid;
    grid-template-columns:1fr 1fr;
    grid-template-rows:1fr 1fr;
    gap:0;
    page-break-after:always;
    padding:10mm;
    box-sizing:border-box;
  }
  .print-block{
    border:1px solid #ccc;
    margin:0;
    padding:6mm 6mm 8mm;
    box-sizing:border-box;
    display:flex;
    flex-direction:column;
    justify-content:flex-start;
    position:relative;
  }
  .print-block h2{
    margin:0 0 4mm;
    font-size:15px;
    font-weight:700;
    text-align:center;
  }
  .print-loc{
    font-size:11px;
    text-align:center;
    line-height:1.4;
    margin:0 0 4mm;
    min-height:20mm;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:2mm 3mm;
    border:1px solid #e0e0e0;
    border-radius:4px;
    background:#fafafa;
  }
  .print-qr{
    margin:0 auto;
    border:2px solid #111;
    padding:5mm 6mm;
    border-radius:6px;
  }
  .print-qr img{
    width:46mm;
    max-width:46mm;
    height:auto;
    display:block;
  }
  .print-helper{
    margin-top:4mm;
    font-size:11px;
    font-weight:600;
    text-align:center;
  }
  .print-footer{
    position:absolute;
    left:6mm;
    right:6mm;
    bottom:6mm;
    display:flex;
    justify-content:space-between;
    font-size:9px;
    color:#555;
    font-weight:500;
  }
}

/* Küçük ekran */
@media (max-width:700px){
  :root{--qr-size:160px;}
  .room-card{padding:14px;}
}

</style>

<div class="top-bar">
  <h1>Oda QR Kodları</h1>
  <div class="actions">
    <button class="btn" onclick="window.print()">Tümünü Yazdır</button>
    <a class="btn back" href="<?php echo h(app_url('yonetim/odalar.php')); ?>">&larr; Odalar</a>
  </div>
</div>

<form method="get" class="filter-box" id="filterBox">
  <div class="fgroup">
    <label>Bina</label>
    <select name="bina" id="fBina">
      <option value="">(Hepsi)</option>
      <?php foreach($binalar as $b): ?>
        <option value="<?php echo h($b['id']); ?>" <?php echo sel($fil_bina,$b['id']); ?>><?php echo h($b['ad']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fgroup">
    <label>Kat</label>
    <select name="kat" id="fKat">
      <option value="">(<?php echo $fil_bina?'Hepsi':'Önce Bina'; ?>)</option>
      <?php foreach($katlar as $k): ?>
        <option value="<?php echo h($k['id']); ?>" <?php echo sel($fil_kat,$k['id']); ?>><?php echo h($k['ad']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fgroup">
    <label>Birim</label>
    <select name="birim" id="fBirim">
      <option value="">(<?php echo $fil_kat?'Hepsi':'Önce Kat'; ?>)</option>
      <?php foreach($birimler as $bi): ?>
        <option value="<?php echo h($bi['id']); ?>" <?php echo sel($fil_birim,$bi['id']); ?>><?php echo h($bi['ad']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fgroup" style="align-self:flex-end;">
    <button type="submit">Uygula</button>
  </div>
  <?php if($fil_bina || $fil_kat || $fil_birim): ?>
    <div class="fgroup" style="align-self:flex-end;">
      <a class="clear" href="<?php echo h(current_path()); ?>">Temizle</a>
    </div>
  <?php endif; ?>
</form>

<div class="list-container">
  <?php if(!$rows): ?>
    <div class="empty">Seçilen filtrelere uygun oda bulunamadı.</div>
  <?php else: ?>
    <div class="room-grid" id="screenList">
      <?php foreach($rows as $r):
        $konum = $r['bina_ad'].' / '.$r['kat_ad'].' / '.($r['birim_ad'] ?: '-').' / '.$r['ad'];
        $qrLink = $gateBase.$r['qr_kod'];
        $qrImg  = $qrGenBase.'?url='.rawurlencode($qrLink).'&boyut=9&kenarbosluk=4&ec=H';
      ?>
      <div class="room-card"
           data-konum="<?php echo h($konum); ?>"
           data-url="<?php echo h($qrLink); ?>"
           data-kod="<?php echo h($r['qr_kod']); ?>">
        <h2><?php echo h($r['ad']); ?></h2>
        <div class="loc-line" title="<?php echo h($konum); ?>">
          <?php echo h($r['bina_ad']); ?><br>
          <?php echo h($r['kat_ad']); ?> /
          <?php echo h($r['birim_ad'] ?: '-'); ?>
        </div>
        <div class="qr-wrapper">
          <img src="<?php echo h($qrImg); ?>" alt="QR">
        </div>
        <div class="helper">Şikayet / Temizlik için QR okutunuz.</div>
        <div class="meta-bottom">
          <span><?php echo h($createdAt); ?></span>
          <span>v<?php echo h($version); ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Print container build -->
<div id="printContainer" style="display:none;"></div>

<script>
(function(){
  const items = Array.from(document.querySelectorAll('.room-card'));
  const printContainer = document.getElementById('printContainer');

  window.addEventListener('beforeprint', buildPrintSheets);

  function esc(s){
    return (s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
  }

  function buildPrintSheets(){
    printContainer.innerHTML='';
    if(!items.length) return;
    let sheet=null;
    items.forEach((item,idx)=>{
      if(idx % 4 === 0){
        sheet=document.createElement('div');
        sheet.className='print-sheet';
        printContainer.appendChild(sheet);
      }
      const konum=item.dataset.konum;
      const url=item.dataset.url;
      const kod=item.dataset.kod;
      const qrImg="<?php echo $qrGenBase; ?>?url="+encodeURIComponent(url)+"&boyut=9&kenarbosluk=2&ec=H";
      const block=document.createElement('div');
      block.className='print-block';
      block.innerHTML = `
        <h2>ODA QR</h2>
        <div class="print-loc" title="${esc(konum)}">${esc(konum)}</div>
        <div class="print-qr"><img src="${qrImg}" alt="QR"></div>
        <div class="print-helper">Bu oda ile ilgili şikayet / temizlik kaydı için QR kodu okutunuz.</div>
        <div class="print-footer">
          <span><?php echo esc($createdAt); ?></span>
          <span>v<?php echo esc($version); ?></span>
        </div>
      `;
      sheet.appendChild(block);
    });
  }

  // Dinamik kat & birim yükleme (filtre formu)
  const fBina=document.getElementById('fBina');
  const fKat=document.getElementById('fKat');
  const fBirim=document.getElementById('fBirim');

  function loadKat(binaId, selected){
    fKat.innerHTML='<option value="">(Hepsi)</option>';
    fBirim.innerHTML='<option value="">Önce Kat</option>';
    if(!binaId){ fKat.innerHTML='<option value="">(Hepsi)</option>'; return; }
    fetch('<?php echo h(app_url('yonetim/ajax_katlar.php')); ?>?bina_id='+binaId)
      .then(r=>r.json()).then(j=>{
        if(j.data){
          j.data.forEach(k=>{
            const opt=document.createElement('option');
            opt.value=k.id; opt.textContent=k.ad;
            if(selected && selected==k.id) opt.selected=true;
            fKat.appendChild(opt);
          });
        }
      });
  }
  function loadBirim(katId, selected){
    fBirim.innerHTML='<option value="">(Hepsi)</option>';
    if(!katId){ fBirim.innerHTML='<option value="">Önce Kat</option>'; return; }
    fetch('<?php echo h(app_url('yonetim/ajax_birimler.php')); ?>?kat_id='+katId)
      .then(r=>r.json()).then(j=>{
        if(j.data){
          j.data.forEach(b=>{
            const opt=document.createElement('option');
            opt.value=b.id; opt.textContent=b.ad;
            if(selected && selected==b.id) opt.selected=true;
            fBirim.appendChild(opt);
          });
        }
      });
  }

  const curKat = '<?php echo $fil_kat ?: ''; ?>';
  const curBirim = '<?php echo $fil_birim ?: ''; ?>';
  if(fBina && fBina.value){
     loadKat(fBina.value, curKat);
     if(curKat){
       // küçük gecikme ile birim yükle
       setTimeout(()=> loadBirim(curKat, curBirim), 350);
     }
  }
  fBina && fBina.addEventListener('change',()=> loadKat(fBina.value,''));
  fKat && fKat.addEventListener('change',()=> loadBirim(fKat.value,''));

})();
</script>

<?php require_once __DIR__.'/inc/footer.php'; ?>