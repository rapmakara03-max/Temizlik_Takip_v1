-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Anamakine: localhost:3306
-- Üretim Zamanı: 30 Eyl 2025, 10:30:22
-- Sunucu sürümü: 8.0.43-cll-lve
-- PHP Sürümü: 8.4.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `sinorselcom_sinorsel`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `binalar`
--

CREATE TABLE `binalar` (
  `id` int NOT NULL,
  `ad` varchar(150) NOT NULL,
  `aciklama` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Tablo döküm verisi `binalar`
--

INSERT INTO `binalar` (`id`, `ad`, `aciklama`) VALUES
(1, 'A Blok Hastane', 'A Blok Hastane'),
(2, 'B Blok Hastane', 'B Blok Hastane'),
(3, 'Rektörlük Binası', 'Rektörlük Binası');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `birimler`
--

CREATE TABLE `birimler` (
  `id` int NOT NULL,
  `ad` varchar(150) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `kat_id` int DEFAULT NULL,
  `qr_kod` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Tablo döküm verisi `birimler`
--

INSERT INTO `birimler` (`id`, `ad`, `parent_id`, `kat_id`, `qr_kod`) VALUES
(1, 'Tuvaletler', NULL, 1, 'knco-rjzN02X'),
(2, 'Genel Cerrahi Servisi', NULL, 2, 'haed-Kv0Fjj4');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gorevler`
--

CREATE TABLE `gorevler` (
  `id` int NOT NULL,
  `baslik` varchar(200) NOT NULL,
  `aciklama` text,
  `assigned_user_id` int DEFAULT NULL,
  `atanan_personel_id` int DEFAULT NULL,
  `durum` enum('YENI','ATANDI','DEVAM','BEKLEME','TAMAM','TAMAMLANDI','IPTAL') NOT NULL DEFAULT 'YENI',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `sikayet_id` int DEFAULT NULL,
  `kaynak_turu` enum('GOREV','SIKAYET') NOT NULL DEFAULT 'GOREV',
  `bina_id` int DEFAULT NULL,
  `kat_id` int DEFAULT NULL,
  `birim_id` int DEFAULT NULL,
  `oda_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Tablo döküm verisi `gorevler`
--

INSERT INTO `gorevler` (`id`, `baslik`, `aciklama`, `assigned_user_id`, `atanan_personel_id`, `durum`, `created_at`, `updated_at`, `sikayet_id`, `kaynak_turu`, `bina_id`, `kat_id`, `birim_id`, `oda_id`) VALUES
(1, 'test', 'test', 2, 2, 'TAMAMLANDI', '2025-09-24 17:21:58', '2025-09-29 16:52:31', NULL, 'GOREV', 2, 2, 1, 1),
(2, 'Şikayet #1', NULL, 2, NULL, 'TAMAM', '2025-09-29 17:16:25', '2025-09-29 21:22:08', 1, 'GOREV', 1, 2, 2, 2);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `katlar`
--

CREATE TABLE `katlar` (
  `id` int NOT NULL,
  `bina_id` int NOT NULL,
  `ad` varchar(100) NOT NULL,
  `qr_kod` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Tablo döküm verisi `katlar`
--

INSERT INTO `katlar` (`id`, `bina_id`, `ad`, `qr_kod`) VALUES
(1, 1, 'Zemin Kat', 'ocyy-LXW2EPF'),
(2, 1, '1. Kat', 'quid-bLOELZ6');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanicilar`
--

CREATE TABLE `kullanicilar` (
  `id` int NOT NULL,
  `ad` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `parola` varchar(255) DEFAULT NULL,
  `rol` enum('GENEL','MUDUR','SEF','PERSONEL') NOT NULL DEFAULT 'PERSONEL',
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `telefon` varchar(20) DEFAULT NULL,
  `gorevi` varchar(100) DEFAULT NULL,
  `sorumlu_birim_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Tablo döküm verisi `kullanicilar`
--

INSERT INTO `kullanicilar` (`id`, `ad`, `email`, `parola`, `rol`, `aktif`, `telefon`, `gorevi`, `sorumlu_birim_id`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$u1pC3RrO5tlRhTJCOSb5ROolCmVcHSKz0pN7SzVg1gZRB0YsRY.E6', 'GENEL', 1, NULL, NULL, NULL, '2025-09-24 13:01:17', '2025-09-24 13:01:17'),
(2, 'personel 1', 'personel@personel.com', '$2y$10$xJquzsfn7Qqz7xqztwhBueg9gi0HZfaRoV/o0n.bM5OODTyvAVfqW', 'PERSONEL', 1, '', 'Temizlik Görevlisi', 1, '2025-09-24 13:02:19', '2025-09-30 10:20:17'),
(3, 'Ertuğrul', 'et@et.com', '$2y$10$lk9Sdxf7IsdPaX5N4LHi3OdQ078rBriyZtVTYeSlaIjgLcoviGwa2', 'SEF', 1, '', 'Şef', 1, '2025-09-29 21:46:15', '2025-09-30 10:12:43');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `odalar`
--

CREATE TABLE `odalar` (
  `id` int NOT NULL,
  `bina_id` int NOT NULL,
  `kat_id` int NOT NULL,
  `birim_id` int DEFAULT NULL,
  `ad` varchar(150) NOT NULL,
  `aciklama` text,
  `qr_kod` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Tablo döküm verisi `odalar`
--

INSERT INTO `odalar` (`id`, `bina_id`, `kat_id`, `birim_id`, `ad`, `aciklama`, `qr_kod`) VALUES
(1, 1, 1, 1, '1 Nolu Tuvalet', '1 Nolu giriş sol tuvalet', 'pcho-hGK6vTf'),
(2, 1, 2, 2, '304', '', 'hedv-Gl1zKPW'),
(3, 1, 1, 1, '2 Nolu Tuvalet', '', 'xkdi-35tJm4V');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sikayetler`
--

CREATE TABLE `sikayetler` (
  `id` int NOT NULL,
  `ad_soyad` varchar(150) NOT NULL,
  `telefon` varchar(60) DEFAULT NULL,
  `mesaj` text NOT NULL,
  `oda_id` int DEFAULT NULL,
  `durum` enum('YENI','INCELEME','KAPALI') NOT NULL DEFAULT 'YENI',
  `atanan_personel_id` int DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL,
  `guncelleme_tarihi` datetime DEFAULT NULL,
  `foto1` varchar(255) DEFAULT NULL,
  `foto2` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Tablo döküm verisi `sikayetler`
--

INSERT INTO `sikayetler` (`id`, `ad_soyad`, `telefon`, `mesaj`, `oda_id`, `durum`, `atanan_personel_id`, `olusturma_tarihi`, `guncelleme_tarihi`, `foto1`, `foto2`) VALUES
(1, 'Test', 'test', 'test', 2, 'INCELEME', 2, '2025-09-24 17:38:05', '2025-09-24 17:42:05', 'sikayet/sk_20250924_173805_1_f47e55b3.jpg', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sikayet_islemleri`
--

CREATE TABLE `sikayet_islemleri` (
  `id` int NOT NULL,
  `sikayet_id` int NOT NULL,
  `kullanici_id` int NOT NULL,
  `aciklama` text NOT NULL,
  `foto1` varchar(300) DEFAULT NULL,
  `foto2` varchar(300) DEFAULT NULL,
  `tarih` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `temizlik_kayitlari`
--

CREATE TABLE `temizlik_kayitlari` (
  `id` int NOT NULL,
  `oda_id` int NOT NULL,
  `personel_id` int NOT NULL,
  `gorev_id` int DEFAULT NULL,
  `sikayet_id` int DEFAULT NULL,
  `tarih` datetime NOT NULL,
  `foto_yol` varchar(300) DEFAULT NULL,
  `foto_yol2` varchar(300) DEFAULT NULL,
  `aciklama` text,
  `isaretler` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Tablo döküm verisi `temizlik_kayitlari`
--

INSERT INTO `temizlik_kayitlari` (`id`, `oda_id`, `personel_id`, `gorev_id`, `sikayet_id`, `tarih`, `foto_yol`, `foto_yol2`, `aciklama`, `isaretler`) VALUES
(1, 1, 2, NULL, NULL, '2025-09-24 14:41:28', '/tk_20250924_144128_2_1_0b84f03e.png', NULL, 's', 'zemin,cam,cop,toz'),
(2, 2, 2, NULL, NULL, '2025-09-24 17:36:24', 'temizlik/tk_20250924_173624_2_1_45f85547.jpg', NULL, 'test', 'zemin,cam,cop,toz,dezenfeksiyon'),
(3, 2, 2, NULL, NULL, '2025-09-29 17:17:40', 'temizlik/tsk_20250929_171740_2_2_1_f1b5cba0.jpg', NULL, 'İşlem yapıldı', 'zemin,cam,cop,toz,dezenfeksiyon');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `binalar`
--
ALTER TABLE `binalar`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `birimler`
--
ALTER TABLE `birimler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_kod` (`qr_kod`),
  ADD KEY `idx_birim_parent` (`parent_id`),
  ADD KEY `fk_birim_kat` (`kat_id`);

--
-- Tablo için indeksler `gorevler`
--
ALTER TABLE `gorevler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gorev_personel` (`atanan_personel_id`),
  ADD KEY `fk_gorev_sikayet` (`sikayet_id`),
  ADD KEY `fk_gorev_bina` (`bina_id`),
  ADD KEY `fk_gorev_kat` (`kat_id`),
  ADD KEY `fk_gorev_birim` (`birim_id`),
  ADD KEY `fk_gorev_oda` (`oda_id`),
  ADD KEY `idx_gorev_kaynak_turu` (`kaynak_turu`),
  ADD KEY `idx_gorev_assigned` (`assigned_user_id`);

--
-- Tablo için indeksler `katlar`
--
ALTER TABLE `katlar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_kod` (`qr_kod`),
  ADD KEY `fk_kat_bina` (`bina_id`);

--
-- Tablo için indeksler `kullanicilar`
--
ALTER TABLE `kullanicilar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_kullanici_birim` (`sorumlu_birim_id`);

--
-- Tablo için indeksler `odalar`
--
ALTER TABLE `odalar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_kod` (`qr_kod`),
  ADD KEY `fk_oda_bina` (`bina_id`),
  ADD KEY `fk_oda_kat` (`kat_id`),
  ADD KEY `fk_oda_birim` (`birim_id`);

--
-- Tablo için indeksler `sikayetler`
--
ALTER TABLE `sikayetler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sikayet_oda` (`oda_id`),
  ADD KEY `idx_sikayetler_atanan` (`atanan_personel_id`);

--
-- Tablo için indeksler `sikayet_islemleri`
--
ALTER TABLE `sikayet_islemleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_si_sikayet` (`sikayet_id`),
  ADD KEY `fk_si_kullanici` (`kullanici_id`);

--
-- Tablo için indeksler `temizlik_kayitlari`
--
ALTER TABLE `temizlik_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tk_oda` (`oda_id`),
  ADD KEY `fk_tk_personel` (`personel_id`),
  ADD KEY `idx_tk_gorev` (`gorev_id`),
  ADD KEY `idx_tk_sikayet` (`sikayet_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `binalar`
--
ALTER TABLE `binalar`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `birimler`
--
ALTER TABLE `birimler`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `gorevler`
--
ALTER TABLE `gorevler`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `katlar`
--
ALTER TABLE `katlar`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `kullanicilar`
--
ALTER TABLE `kullanicilar`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `odalar`
--
ALTER TABLE `odalar`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `sikayetler`
--
ALTER TABLE `sikayetler`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `sikayet_islemleri`
--
ALTER TABLE `sikayet_islemleri`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `temizlik_kayitlari`
--
ALTER TABLE `temizlik_kayitlari`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `birimler`
--
ALTER TABLE `birimler`
  ADD CONSTRAINT `fk_birim_kat` FOREIGN KEY (`kat_id`) REFERENCES `katlar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_birim_parent` FOREIGN KEY (`parent_id`) REFERENCES `birimler` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `gorevler`
--
ALTER TABLE `gorevler`
  ADD CONSTRAINT `fk_gorev_assigned` FOREIGN KEY (`assigned_user_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gorev_bina` FOREIGN KEY (`bina_id`) REFERENCES `binalar` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gorev_birim` FOREIGN KEY (`birim_id`) REFERENCES `birimler` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gorev_kat` FOREIGN KEY (`kat_id`) REFERENCES `katlar` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gorev_oda` FOREIGN KEY (`oda_id`) REFERENCES `odalar` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gorev_personel` FOREIGN KEY (`atanan_personel_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gorev_sikayet` FOREIGN KEY (`sikayet_id`) REFERENCES `sikayetler` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `katlar`
--
ALTER TABLE `katlar`
  ADD CONSTRAINT `fk_kat_bina` FOREIGN KEY (`bina_id`) REFERENCES `binalar` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `kullanicilar`
--
ALTER TABLE `kullanicilar`
  ADD CONSTRAINT `fk_kullanici_birim` FOREIGN KEY (`sorumlu_birim_id`) REFERENCES `birimler` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `odalar`
--
ALTER TABLE `odalar`
  ADD CONSTRAINT `fk_oda_bina` FOREIGN KEY (`bina_id`) REFERENCES `binalar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oda_birim` FOREIGN KEY (`birim_id`) REFERENCES `birimler` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_oda_kat` FOREIGN KEY (`kat_id`) REFERENCES `katlar` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `sikayetler`
--
ALTER TABLE `sikayetler`
  ADD CONSTRAINT `fk_sikayet_oda` FOREIGN KEY (`oda_id`) REFERENCES `odalar` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `sikayet_islemleri`
--
ALTER TABLE `sikayet_islemleri`
  ADD CONSTRAINT `fk_si_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_si_sikayet` FOREIGN KEY (`sikayet_id`) REFERENCES `sikayetler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `temizlik_kayitlari`
--
ALTER TABLE `temizlik_kayitlari`
  ADD CONSTRAINT `fk_tk_gorev` FOREIGN KEY (`gorev_id`) REFERENCES `gorevler` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tk_oda` FOREIGN KEY (`oda_id`) REFERENCES `odalar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tk_personel` FOREIGN KEY (`personel_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tk_sikayet` FOREIGN KEY (`sikayet_id`) REFERENCES `sikayetler` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
