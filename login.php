<?php
// Portal için bağımsız login kaldırıldı; QR tabanlı giriş index.php üzerinde.
// Eski linkler için bilgilendirici yönlendirme.
require_once __DIR__ . '/ortak/oturum.php';
require_once __DIR__ . '/ortak/sabitler.php';
flash_set('error','Lütfen önce QR kod okutarak giriş yapın.');
redirect(app_url());