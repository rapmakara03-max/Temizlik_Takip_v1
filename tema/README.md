# Tema / AdminLTE Entegrasyonu

Uygulama `yonetim/inc/header.php` dosyasında AdminLTE 3 CDN yükler. Yerel dosya kullanmak istiyorsanız:

```
tema/
 └─ adminlte/
     ├─ css/adminlte.min.css
     ├─ js/adminlte.min.js
     ├─ plugins/
     │   ├─ bootstrap/js/bootstrap.bundle.min.js
     │   └─ fontawesome-free/css/all.min.css
     └─ app.css (opsiyonel)
```

`discovered_assets()` fonksiyonu tema altından dosyaları bulup otomatik ekler. Bulamazsa CDN fallback devreye girer.

Özel stiller: `app.css` ya da `style.css` ekleyebilirsiniz.

AdminLTE bileşenleri:
- Kart: `<div class="card card-outline card-primary">...</div>`
- Küçük kutular: `.small-box`
- Info box: `.info-box`

Sidebar menüsünde aktif link `nav_active()` fonksiyonu ile “active” sınıfı kazanır.

## QR Baskı Tasarımı

QR sayfaları (qr_oda.php, qr_kat.php, qr_birim.php) A5 portrait formatında:
- @page ile A5 tanımlı
- Ortalanmış kart
- Konum hiyerarşisi: Bina > Kat > Birim > Oda
- Fallback: SVG üretilemezse PNG (Google Chart) embed edilir

## Öneriler

- Renkleri override etmek için `:root` değişkenlerini (var(--...)) AdminLTE kaynaklarına ek bir CSS ile değiştirin.
- Çoklu dil desteği istenirse (tr/en) başlıkları `titleMap` yerine i18n dizininden besleyin.
