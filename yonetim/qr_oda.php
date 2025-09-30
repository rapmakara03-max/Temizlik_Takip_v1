<?php
require_once __DIR__ . '/inc/header.php';

$oda_id = (int)($_GET['id'] ?? 0);
$oda = fetch_one("SELECT o.*, b.ad bina_ad, k.ad kat_ad, bi.ad birim_ad
 FROM odalar o
 LEFT JOIN binalar b ON b.id=o.bina_id
 LEFT JOIN katlar k ON k.id=o.kat_id
 LEFT JOIN birimler bi ON bi.id=o.birim_id
 WHERE o.id=?", [$oda_id]);

if(!$oda){
  echo "<div class='alert alert-danger'>Oda bulunamadı.</div>";
  require_once __DIR__.'/inc/footer.php'; exit;
}

$qrLink = absolute_url('/qr_gate.php?c='.$oda['qr_kod']);
$qrImg  = app_url('kutuphane/qr.php?url='.rawurlencode($qrLink).'&boyut=10&kenarbosluk=2&ec=H'); // Biraz daha yüksek çözünürlük (boyut=10)
?>
<style>
:root{
  --font-main: "Arial", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  --color-accent: #b60012;
  --color-border: #1d1d1d;
  --color-muted: #666;
  --a5-w: 148mm;
  --a5-h: 210mm;
  --page-pad: 6mm;
  --qr-max: 65mm;
  --radius: 10px;
}
/* Ekran Önizleme Alanı */
body{
  margin:0;
  font-family:var(--font-main);
  background:#ececec;
  -webkit-print-color-adjust:exact;
  print-color-adjust:exact;
}
.toolbar{
  display:flex;
  gap:8px;
  align-items:center;
  padding:14px 18px;
  background:#fff;
  border-bottom:1px solid #d0d0d0;
  position:sticky;
  top:0;
  z-index:5;
}
.toolbar h1{
  font-size:16px;
  margin:0;
  font-weight:600;
  letter-spacing:.5px;
  color:#222;
}
.toolbar .spacer{flex:1;}
.toolbar a.btn,
.toolbar button.btn{
  background:var(--color-accent);
  border:none;
  padding:9px 16px;
  font-size:13px;
  font-weight:600;
  border-radius:6px;
  color:#fff;
  cursor:pointer;
  text-decoration:none;
  line-height:1.1;
}
.toolbar a.btn.secondary{
  background:#555;
}
.preview-stage{
  width:100%;
  min-height:calc(100vh - 60px);
  display:flex;
  justify-content:center;
  align-items:flex-start;
  padding:30px 18px 60px;
  box-sizing:border-box;
}
.sheet{
  width:var(--a5-w);
  height:var(--a5-h);
  background:#fff;
  box-shadow:0 4px 28px rgba(0,0,0,.25);
  position:relative;
  display:flex;
  flex-direction:column;
  padding:var(--page-pad);
  box-sizing:border-box;
  border:1px solid #d8d8d8;
}
.header{
  text-align:center;
  padding:4px 4px 2px;
}
.header .title{
  font-size:22px;
  margin:0 0 2mm;
  font-weight:700;
  letter-spacing:.6px;
  color:#111;
}
.meta-block{
  font-size:12.2px;
  line-height:1.42;
  margin:0 auto;
  text-align:left;
  border:1px solid #d5d5d5;
  padding:6px 10px 7px;
  border-radius:6px;
  background:#fafafa;
  width:100%;
  max-width:100%;
  box-sizing:border-box;
}
.meta-block .row{
  display:flex;
  font-weight:500;
  gap:4px;
}
.meta-block .row span.label{
  width:38mm;
  color:#222;
  flex-shrink:0;
}
.meta-block .row span.value{
  color:#333;
  flex:1;
  word-break:break-word;
}
.qr-area{
  flex:1;
  display:flex;
  flex-direction:column;
  justify-content:center;
  align-items:center;
  padding:6mm 0 4mm;
}
.qr-box{
  border:2px solid var(--color-border);
  padding:5mm;
  border-radius:8px;
  display:flex;
  justify-content:center;
  align-items:center;
  background:#fff;
  position:relative;
}
.qr-box img{
  display:block;
  max-width:var(--qr-max);
  max-height:var(--qr-max);
  width:100%;
  height:auto;
}
.helper-text{
  text-align:center;
  font-size:12.8px;
  font-weight:600;
  margin:6mm 0 0;
  letter-spacing:.3px;
  color:#111;
}
.url-block{
  margin:4mm auto 0;
  font-size:10.5px;
  line-height:1.25;
  color:#444;
  word-break:break-all;
  text-align:center;
  max-width:100%;
  padding:4px 6px 5px;
  border:1px dashed #bbb;
  border-radius:6px;
  background:#fcfcfc;
}
.footer-bar{
  position:absolute;
  bottom:var(--page-pad);
  left:var(--page-pad);
  right:var(--page-pad);
  display:flex;
  justify-content:space-between;
  font-size:9.8px;
  color:#666;
  letter-spacing:.3px;
  font-weight:500;
}
.footer-bar span{
  max-width:48%;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* Baskı İçin */
@media print {
  @page { size:A5 portrait; margin:6mm; }
  html, body{
    width:var(--a5-w);
    height:var(--a5-h);
    background:#fff !important;
  }
  body{
    margin:0;
    padding:0;
  }
  .toolbar,
  .preview-stage{display:block; padding:0; margin:0;}
  .toolbar{display:none !important;}
  .sheet{
    width:100%;
    height:100%;
    margin:0;
    box-shadow:none;
    border:none;
    padding:var(--page-pad);
  }
  .url-block{
    border:1px solid #999;
    background:#fff;
  }
  .helper-text{
    margin-top:5mm;
  }
}

/* Küçük ekran uyumu */
@media (max-width:600px){
  .preview-stage{padding:15px 8px 40px;}
  .sheet{
    transform:scale(.9);
    transform-origin:top center;
  }
}

/* Çok uzun değerler olursa görsel taşmayı önlemek için adaptif küçültme */
@media print {
  .meta-block{font-size:11.5px;}
  .header .title{font-size:21px;}
  .helper-text{font-size:12px;}
}
</style>

<div class="toolbar noprint">
  <h1>QR Oda Yazdırma</h1>
  <div class="spacer"></div>
  <a href="<?php echo h(app_url('yonetim/odalar.php')); ?>" class="btn secondary">&larr; Geri</a>
  <button class="btn" onclick="window.print()">Yazdır</button>
</div>

<div class="preview-stage">
  <div class="sheet">
    <div class="header">
      <h2 class="title">ODA TEMİZLİK QR</h2>
    </div>

    <div class="meta-block">
      <div class="row"><span class="label">Konum:</span><center><?php echo h($oda['bina_ad']); ?>- <?php echo h($oda['kat_ad']); ?>- <?php echo h($oda['birim_ad'] ?: '-'); ?> - <?php echo h($oda['ad']); ?></center></div>

      
    </div>

    <div class="qr-area">
      <div class="qr-box">
        <img src="<?php echo h($qrImg); ?>" alt="QR Kod">
      </div>
      <div class="helper-text">Bu alan ile ilgili şikayet / temizlik kaydı için QR kodu okutunuz.</div>
    
    </div>

    <div class="footer-bar">
      <span>Oluşturma: <?php echo date('d.m.Y H:i'); ?></span>
      <span>Sistem: AFSU • v1</span>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>