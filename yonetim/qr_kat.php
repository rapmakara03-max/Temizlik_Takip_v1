<?php
require_once __DIR__.'/inc/header.php';

$katlar = fetch_all("SELECT k.id,k.ad,k.qr_kod,b.ad bina_ad
 FROM katlar k
 LEFT JOIN binalar b ON b.id=k.bina_id
 ORDER BY b.ad, k.ad");

$qrGenBase = app_url('kutuphane/qr.php');
$gateBase  = absolute_url('/qr_gate.php?c=');
$versiyon  = 'v1.0';
$olusturma = date('d.m.Y H:i');
?>
<style>
:root{
  --font:"Arial","Segoe UI",system-ui,sans-serif;
  --bg:#f4f6f9;
  --border:#d1d6db;
  --border-dark:#222;
  --qr-size:230px;
  --gap-page:8mm;
}
html,body{
  margin:0;
  padding:0;
  background:var(--bg);
  font-family:var(--font);
  color:#111;
  -webkit-print-color-adjust:exact;
  print-color-adjust:exact;
  font-size:14px;
}
.page-wrap{
  max-width:1000px;
  margin:24px auto 60px;
  padding:0 18px 40px;
  box-sizing:border-box;
}
.top-bar{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  align-items:center;
  justify-content:space-between;
  margin-bottom:18px;
}
.top-bar h1{
  margin:0;
  font-size:24px;
  font-weight:700;
  letter-spacing:.4px;
}
.top-bar .actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}
.top-bar a.btn,
.top-bar button.btn{
  background:#222;
  color:#fff;
  border:none;
  padding:10px 18px;
  border-radius:6px;
  font-size:13px;
  font-weight:600;
  cursor:pointer;
  text-decoration:none;
}
.top-bar a.back{
  background:#555;
}
.list{
  display:flex;
  flex-direction:column;
  gap:18px;
}
.qr-item{
  background:#fff;
  border:1px solid var(--border);
  border-radius:10px;
  padding:18px 20px 22px;
  box-sizing:border-box;
  position:relative;
  display:flex;
  flex-direction:column;
}
.qr-item h2{
  font-size:16px;
  font-weight:700;
  letter-spacing:.5px;
  text-align:center;
  margin:0 0 14px;
}
.field-label{
  font-size:12px;
  font-weight:600;
  margin:0 0 4px;
  letter-spacing:.3px;
}
.value-box{
  background:#fff;
  border:1px solid var(--border);
  border-radius:4px;
  padding:6px 10px 7px;
  font-size:12.8px;
  line-height:1.35;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  margin:0 0 16px;
}
.center-zone{
  text-align:center;
}
.qr-wrapper{
  display:inline-block;
  background:#fff;
  border:2px solid var(--border-dark);
  padding:10px 16px;
  border-radius:6px;
}
.qr-wrapper img{
  width:var(--qr-size);
  max-width:var(--qr-size);
  height:auto;
  display:block;
}
.helper{
  margin-top:8px;
  font-size:11.5px;
  font-weight:600;
  letter-spacing:.25px;
}
.meta-bottom{
  margin-top:14px;
  font-size:10.5px;
  display:flex;
  justify-content:space-between;
  color:#444;
  letter-spacing:.2px;
  font-weight:500;
}
.meta-brand{
  margin-top:4px;
  font-size:10px;
  display:flex;
  justify-content:space-between;
  color:#666;
  letter-spacing:.25px;
}
.empty{
  background:#fff;
  border:1px dashed var(--border);
  padding:40px;
  text-align:center;
  border-radius:12px;
  font-size:14px;
  color:#555;
}

/* PRINT LAYOUT */
@media print {
  .top-bar{display:none !important;}
  @page{size:A4 portrait;margin:0;}
  body,html{background:var(--bg);}
  .page-wrap{
    max-width:unset;
    width:210mm;
    padding:0;
    margin:0;
  }
  .print-sheet{
    width:210mm;
    height:297mm;
    padding:10mm;
    box-sizing:border-box;
    page-break-after:always;
    display:flex;
    flex-direction:column;
    gap:var(--gap-page);
  }
  .print-block{
    flex:1 0 calc(50% - var(--gap-page)/2);
    background:#fff;
    border:1px solid var(--border);
    border-radius:4px;
    padding:12mm 14mm 12mm;
    box-sizing:border-box;
    display:flex;
    flex-direction:column;
    position:relative;
    overflow:hidden;
  }
  .print-block h2{
    font-size:16px;
    font-weight:700;
    letter-spacing:.5px;
    text-align:center;
    margin:0 0 6mm;
  }
  .print-block .value-box{
    margin:0 0 8mm;
  }
  .print-center{
    text-align:center;
    flex:1;
    display:flex;
    flex-direction:column;
    justify-content:flex-start;
    align-items:center;
  }
  .print-qr{
    background:#fff;
    border:2px solid #222;
    padding:10px 14px;
    border-radius:6px;
  }
  .print-qr img{
    width:var(--qr-size);
    max-width:var(--qr-size);
    height:auto;
    display:block;
  }
  .print-helper{
    margin-top:8px;
    font-size:11.2px;
    font-weight:600;
    letter-spacing:.25px;
  }
  .print-footer{
    position:absolute;
    left:14mm;
    right:14mm;
    bottom:14mm;
    display:flex;
    justify-content:space-between;
    font-size:10px;
    color:#444;
    font-weight:500;
  }
  .print-brand{
    position:absolute;
    left:14mm;
    right:14mm;
    bottom:6mm;
    display:flex;
    justify-content:space-between;
    font-size:9.6px;
    color:#666;
    letter-spacing:.2px;
  }
}

/* Responsive */
@media (max-width:800px){
  .qr-wrapper img{width:200px;max-width:200px;}
  :root{--qr-size:200px;}
}
</style>

<div class="page-wrap">
  <div class="top-bar">
    <h1>Kat QR Kodları</h1>
    <div class="actions">
      <button class="btn" onclick="window.print()">Tümünü Yazdır</button>
      <a class="btn back" href="<?php echo h(app_url('yonetim/birimler.php')); ?>">&larr; Birimler</a>
    </div>
  </div>

  <?php if(!$katlar): ?>
    <div class="empty">Kayıtlı kat bulunamadı.</div>
  <?php else: ?>
    <div class="list" id="screenList">
      <?php foreach($katlar as $k):
        $konum = $k['bina_ad'].' - '.$k['ad'];
        $qrLink = $gateBase.$k['qr_kod'];
        $qrImg  = $qrGenBase.'?url='.rawurlencode($qrLink).'&boyut=9&kenarbosluk=2&ec=H';
      ?>
      <div class="qr-item"
           data-konum="<?php echo h($konum); ?>"
           data-url="<?php echo h($qrLink); ?>"
           data-kod="<?php echo h($k['qr_kod']); ?>">
        <h2>KAT QR</h2>
        <div class="field">
          <div class="field-label">Konum:</div>
          <div class="value-box" title="<?php echo h($konum); ?>"><?php echo h($konum); ?></div>
        </div>
        <div class="center-zone">
          <div class="qr-wrapper">
            <img src="<?php echo h($qrImg); ?>" alt="QR">
          </div>
          <div class="helper">Bu kat ile ilgili şikayet / temizlik kaydı için QR kodu okutunuz.</div>
        </div>
        <div class="meta-bottom">
          <span>Oluşturma: <?php echo h($olusturma); ?></span>
          <span>Sistem: AFSU <?php echo h($versiyon); ?></span>
        </div>
        <div class="meta-brand">
          <span>&copy; <?php echo date('Y'); ?> Temizlik Yönetimi</span>
          <span>Sürüm: <?php echo h($versiyon); ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- PRINT (JS ile dinamik bloklar) -->
<div id="printContainer" style="display:none;"></div>

<script>
(function(){
  const items = Array.from(document.querySelectorAll('.qr-item'));
  const printContainer = document.getElementById('printContainer');
  const qrGenBase = <?php echo json_encode($qrGenBase); ?>;
  const now = <?php echo json_encode($olusturma); ?>;
  const version = <?php echo json_encode($versiyon); ?>;

  // Yazdırmadan hemen önce tüm listeyi print sheet'lere dönüştür
  window.addEventListener('beforeprint', buildPrint);

  function esc(s){return (s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));}

  function buildPrint(){
    printContainer.innerHTML='';
    if(!items.length) return;

    let sheet=null;
    let count=0;

    items.forEach(item=>{
      if(count % 2 === 0){
        sheet=document.createElement('div');
        sheet.className='print-sheet';
        printContainer.appendChild(sheet);
      }
      const konum = item.dataset.konum;
      const url   = item.dataset.url;
      const kod   = item.dataset.kod;
      const qrImg = qrGenBase+'?url='+encodeURIComponent(url)+'&boyut=9&kenarbosluk=2&ec=H';

      const block=document.createElement('div');
      block.className='print-block';
      block.innerHTML = `
        <h2>KAT QR</h2>
        <div class="pb-field">
          <div class="pb-field-label">Konum:</div>
          <div class="value-box" title="${esc(konum)}">${esc(konum)}</div>
        </div>
        <div class="print-center">
          <div class="print-qr"><img src="${qrImg}" alt="QR"></div>
          <div class="print-helper">Bu kat ile ilgili şikayet / temizlik kaydı için QR kodu okutunuz.</div>
        </div>
        <div class="print-footer">
          <span>Oluşturma: ${esc(now)}</span>
          <span>Sistem: AFSU ${esc(version)}</span>
        </div>
        <div class="print-brand">
          <span>&copy; <?php echo date('Y'); ?> Temizlik Yönetimi</span>
          <span>Sürüm: ${esc(version)}</span>
        </div>
      `;
      sheet.appendChild(block);
      count++;
    });
  }
})();
</script>

<?php require_once __DIR__.'/inc/footer.php'; ?>