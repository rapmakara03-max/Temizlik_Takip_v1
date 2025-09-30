<?php
require_once __DIR__ . '/../ortak/oturum.php';
require_once __DIR__ . '/../ortak/sabitler.php';
require_once __DIR__ . '/../ortak/yetki.php';
logout_user();
flash_set('success','Oturum kapatıldı.');
redirect(app_url('yonetim/login.php'));