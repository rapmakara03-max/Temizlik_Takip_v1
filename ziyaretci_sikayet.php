<?php
// Eski ziyaretçi form yolunu korumak için yönlendirme.
// Artık form index.php içindedir (QR olmadan görünmez).
require_once __DIR__ . '/ortak/oturum.php';
require_once __DIR__ . '/ortak/sabitler.php';
flash_set('error','Lütfen QR kod okutarak giriş yapın.');
redirect(app_url());