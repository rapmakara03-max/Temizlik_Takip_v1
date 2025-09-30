# Temizlik Yönetim Uygulaması (Final Sürüm)

Kurumsal temizlik operasyonlarının yönetimi için yalın PHP 8+ ile geliştirilmiş üretim‑yakın örnek uygulama.

## ÖNE ÇIKAN GÜNCEL ÖZELLİKLER (Son Revizyon)

- AdminLTE 3 arayüz (collapsible sidebar, kartlar, info box)
- Roller: GENEL, MUDUR, PERSONEL
- Hiyerarşi: Bina > Kat > (Çok seviyeli Birim / üst birim) > Oda
- Odalar için benzersiz kalıcı QR Kodu (qr_kod kolonu, format: `xxxx-XxX9aZ`)
- Kalıcı QR → qr_gate.php?c=<qr_kod> → dinamik ts+sig üreterek portal (index) yönlendirme
- Portal birleşik ekran:
  - QR doğrulanmamışsa: Uyarı “Lütfen QR Kod okutarak giriş yapın”
  - QR doğrulanmışsa: Personel giriş formu + alt sekmede Ziyaretçi Şikayet Formu (oda sabitlenmiş)
- Personel giriş sonrası:
  - QR süresi geçerliliği kontrolü
  - Temizlik formu (5 işaret kutusu: zemin, cam, çöp, toz, dezenfeksiyon)
  - 2 foto yükleme + dinamik watermark (GD varsa)
  - İşaretler temizlik_kayitlari.isaretler alanında CSV tutulur
- Ziyaretçi şikayet formu QR olmadan görünmez ve sadece ilgili oda’ya kayıt yapar
- Görevler: Konum alanları (bina_id, kat_id, birim_id, oda_id)
- Geniş raporlar:
  - Bina / Kat / Oda bazlı temizlik raporları
  - Oda veya Personel bazlı detay listeleri
  - Personel takip (istatistik + işlemler)
  - Bugün yapılan temizlik, temizlenmeyen odalar, işlem yapmayan personel
- Görev detay ve temizlik detay sayfaları
- QR Yazdır (A5) sayfaları:
  - Oda: qr_oda.php
  - Kat’taki odalar: qr_kat.php
  - Birimdeki odalar: qr_birim.php
- Otomatik eksik QR üretimi (odalar sayfasına girişte)
- QR yenile (odalar listesinde)
- Watermark (USER id / ODA id / Zaman damgası)
- Güvenlik:
  - HMAC-SHA256 QR token (oda_id|ts)
  - CSRF Token
  - Prepared statements
  - XSS için htmlspecialchars
  - Rol kontrolü
  - Session hardening (httponly, samesite, strict mode)
  - hash_equals ile imza doğrulama
- Tema otomatik asset keşfi + CDN fallback
- Pagination, arama, sıralama tüm CRUD listelerinde
- Flash mesajlar
- Migrations + Seed

## DİZİN YAPISI (Özet)

```
project1/temizlik/
  ortak/              Yardımcı & çekirdek dosyalar
  kutuphane/          QR üretici
  sql/                Şema + migration + seed
  tema/               Tema dokümantasyonu
  uploads/            Yüklenen fotoğraflar
  yonetim/            Yönetim paneli (AdminLTE)
  index.php           Portal giriş / ziyaretçi formu
  qr_form.php         Personel QR sonrası form
  qr_gate.php         Kalıcı QR yönlendirici
```

## KURULUM

1. Depoyu alın ve `project1/temizlik/` dizinine girin.
2. `.env.example` → `.env` kopyala, ayarları yap.
3. Veritabanı oluştur:
   ```bash
   mysql -u root -p -e "CREATE DATABASE temizlik CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
4. Ana şema:
   ```bash
   mysql -u root -p temizlik < sql/temizlik_schema.sql
   ```
5. Migration’lar (sırayla):
   ```bash
   mysql -u root -p temizlik < sql/migrations/002_remove_room_coords.sql
   mysql -u root -p temizlik < sql/migrations/003_add_second_photo.sql
   mysql -u root -p temizlik < sql/migrations/004_create_gorevler.sql
   mysql -u root -p temizlik < sql/migrations/005_gorevler_add_sikayet_id.sql
   mysql -u root -p temizlik < sql/migrations/006_birimler_add_parent.sql
   mysql -u root -p temizlik < sql/migrations/007_gorevler_add_location.sql
   mysql -u root -p temizlik < sql/migrations/008_odalar_add_qr_kod.sql
   mysql -u root -p temizlik < sql/migrations/009_temizlik_add_isaretler.sql
   ```
6. Seed:
   ```bash
   php sql/seed_admin.php
   ```
7. Web sunucusu root’u `project1/temizlik/` gösterir.
8. Tarayıcıda `APP_URL` aç.

## ENV

| Anahtar | Açıklama |
|--------|----------|
| APP_URL | Temel URL |
| GIZLI_ANAHTAR | HMAC gizli anahtarı |
| TOKEN_TTL_S | QR oturum süresi (sn) |
| UPLOAD_DIR / UPLOAD_URL | Yüklemeler (boşsa varsayılan uploads/) |
| DB_* | DB erişim bilgileri |
| ADMIN_DEMO_* | Demo admin |
| PERSONEL_DEMO_* | Demo personel |

## VERİTABANI (Özet Güncel Tablolar)

- kullanicilar (rol ENUM('GENEL','MUDUR','PERSONEL'))
- binalar
- katlar
- birimler (parent_id ile üst birim)
- odalar (qr_kod benzersiz)
- temizlik_kayitlari (foto_yol, foto_yol2, isaretler)
- sikayetler
- gorevler (sikayet_id + bina_id, kat_id, birim_id, oda_id)

Tam sütunlar için `sql/temizlik_schema.sql` dosyasına bakın.

## QR MEKANİZMASI

1. Basılı QR: `qr_gate.php?c=<qr_kod>`
2. qr_gate -> ilgili odanın id’sini bulur, dinamik ts & sig üretip index’e (`?oda_id=...&ts=...&sig=...`)
3. index token doğrular:
   - Süre = `TOKEN_TTL_S`
   - Session: `qr_ok = { exp, oda_id }`
4. QR yoksa portal formu gizli.

## PERSONEL TEMİZLİK FORMU

- 5 adet checkbox işaret alanı → CSV olarak isaretler
- 2 foto (isteğe bağlı)
- Watermark (GD + optional TTF)
- Son 10 kayıt listelenir

## RAPORLAR

- Bina / Kat / Oda bazlı
- Personel bazlı
- Günlük raporlar
- Detay sayfalarında foto linkleri (varsa)

## PERSONEL TAKİP

- Personel bazında temizlik / görev sayıları
- İşlem listesi (son 50, sayfalama)

## PANO

Bilgiler + son temizlik, şikayetler, son 10 görev, ayrıca:
- Bugün temizlenmeyen oda sayısı (link)
- Bugün işlem yapmayan personel sayısı (link)
- Bugün yapılan temizlik sayısı (link)

## GÜVENLİK

- CSRF token zorunlu POST isteklerinde
- password_hash / verify
- Hazırlanmış ifadeler
- XSS filtreleme (htmlspecialchars)
- hash_equals ile HMAC doğrulama
- Session güvenliği (httponly, samesite, strict mode)
- Oda manipülasyon engeli (qr_form.php POST'ta oda doğrulaması)

## OTOMATİK QR

- Migration 008 ile tüm odalara başlangıç qr_kod
- Odalar sayfası: eksik varsa üretir
- Yenile butonu ile qr_kod döngüsel değiştirilebilir

## TEMA

- AdminLTE 3 CDN (veya tema klasörüne koyup otomatik keşif)
- Fallback minimal CSS yoktur; AdminLTE varsayılan

## TEST SENARYOLARI

1. Admin login doğru/yanlış
2. QR olmadan portal → uyarı
3. QR okut → personel + şikayet formu görünür
4. Personel giriş → temizlik kaydı (checkbox + foto)
5. Şikayet formu → admin panelde listelenir
6. Görev oluştur ve konum set → pano son görevlerde
7. Raporlarda bina/kat/oda drill-down
8. Personel takipte istatistik & işlemler
9. QR süresi dolunca form erişimi kaybolur
10. CSRF token silinirse mutasyon reddi

## GÜVENLİK NOTLARI

- Production’da `GIZLI_ANAHTAR` değiştir
- Demo hesapları kaldır
- HTTPS zorunlu (cookie secure)
- Foto yükleme boyut & mime kontrolleri genişletilebilir

## GENİŞLETME ÖNERİLERİ

- AJAX bağımlı dropdown (bina seçince kat filtreleme)
- İleri rapor export (CSV/Excel)
- LDAP / SSO
- Çoklu foto + thumbnail
- Offline QR (statik token + refresh endpoint)

## LİSANS

İç kullanım / örnek temizlik yönetim sistemi. İhtiyaçlarınıza göre uyarlayınız.
