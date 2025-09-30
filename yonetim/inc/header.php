<?php
require_once __DIR__ . '/../../ortak/oturum.php';
require_once __DIR__ . '/../../ortak/sabitler.php';
require_once __DIR__ . '/../../ortak/yetki.php';
require_once __DIR__ . '/../../ortak/csrf.php';
require_once __DIR__ . '/../../ortak/db_helpers.php';
require_once __DIR__ . '/../../ortak/form_helpers.php';

/*
  Dinamik erişim modeli:
  - GENEL & MUDUR: varsayılan olarak tüm yönetim sayfalarına erişir.
  - SEF: yalnızca tanımlanan belirli operasyon & rapor sayfalarına erişir:
        gorevler.php, gorev_detay.php, sikayetler.php, raporlar.php,
        personel_islemler.php, temizlenmeyen_bugun.php
  - PERSONEL: yönetim paneline (header) hiç alınmaz.
*/
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');

$roleAccessMap = [
    'gorevler.php'            => ['GENEL','MUDUR','SEF'],
    'gorev_detay.php'         => ['GENEL','MUDUR','SEF'],
    'sikayetler.php'          => ['GENEL','MUDUR','SEF'],
    'raporlar.php'            => ['GENEL','MUDUR','SEF'],
    'personel_islemler.php'   => ['GENEL','MUDUR','SEF'],
    'temizlenmeyen_bugun.php' => ['GENEL','MUDUR','SEF'],
    // Diğer tüm sayfalarda SEF yok → sadece GENEL & MUDUR
];

$allowedRoles = $roleAccessMap[$script] ?? ['GENEL','MUDUR'];
require_role($allowedRoles, app_url('yonetim/login.php'));

$titleMap = [
    'pano.php'                   => 'Pano',
    'binalar.php'                => 'Binalar',
    'katlar.php'                 => 'Katlar',
    'birimler.php'               => 'Birimler',
    'odalar.php'                 => 'Odalar',
    'personel.php'               => 'Personel',
    'gorevler.php'               => 'Görevler',
    'sikayetler.php'             => 'Şikayetler',
    'raporlar.php'               => 'Raporlar',
    'temizlenmeyen_bugun.php'    => 'Bugün Temizlenmeyen',
    'islem_yapmayan_bugun.php'   => 'İşlem Yapmayan Personel',
    'rapor_temizlik_bina.php'    => 'Bina Bazlı Rapor',
    'rapor_temizlik_kat.php'     => 'Kat Bazlı Rapor',
    'rapor_temizlik_oda.php'     => 'Oda Bazlı Rapor',
    'rapor_temizlik_personel.php'=> 'Temizlik Personel Detay',
    'temizlik_bugun.php'         => 'Bugün Temizlik',
    'temizlik_detay.php'         => 'Temizlik Detayı',
    'gorev_detay.php'            => 'Görev Detayı',
    'personel_takip.php'         => 'Personel Takip',
    'personel_islemler.php'      => 'Personel İşlemleri',
    'qr_oda.php'                 => 'Oda QR',
    'qr_kat.php'                 => 'Kat QR',
    'qr_birim.php'               => 'Birim QR',
	'detayli_raporlar.php'		 => 'Detaylı Raporlar',
];
$pageTitle = $titleMap[$script] ?? 'Yönetim Paneli';

function nav_active(string $f): string {
    return basename($_SERVER['SCRIPT_NAME']??'')===$f?' active ':'';
}

$user = current_user();
$userRole = $user['rol'] ?? '';

/**
 * Menüde hangi dosyanın gösterileceğini rol bazlı kontrol et.
 */
function can_show_nav(string $file, string $role): bool {
    if (in_array($role, ['GENEL','MUDUR'], true)) {
        return true; // tam yetki
    }
    if ($role === 'SEF') {
        $sefAllowed = [
            'gorevler.php',
            'gorev_detay.php',
            'sikayetler.php',
            'raporlar.php',
            'personel_islemler.php',
            'temizlenmeyen_bugun.php',
        ];
        return in_array($file, $sefAllowed, true);
    }
    return false;
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title><?php echo h($pageTitle); ?> - Yönetim</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<style>
.nav-sidebar .nav-item>.nav-link.active{background:#007bff;color:#fff;}
.badge.YENI{background:#0073b7;}
.badge.DEVAM{background:#f39c12;}
.badge.TAMAMLANDI{background:#00a65a;}
.badge.IPTAL{background:#dd4b39;}
.badge.INCELEME{background:#f39c12;}
.badge.KAPANDI{background:#00a65a;}
.table td,.table th{vertical-align:top;}
</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <ul class="navbar-nav">
    <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a></li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="<?php echo h(app_url('yonetim/pano.php')); ?>" class="nav-link">Ana Sayfa</a>
    </li>
  </ul>
  <ul class="navbar-nav ml-auto">
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#">
        <i class="far fa-user"></i> <?php echo h(($user['ad']??'').' ('.($user['rol']??'').')'); ?>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <a href="<?php echo h(app_url()); ?>" target="_blank" class="dropdown-item"><i class="fas fa-external-link-alt mr-2"></i> Portal</a>
        <div class="dropdown-divider"></div>
        <a href="<?php echo h(app_url('yonetim/logout.php')); ?>" class="dropdown-item text-danger">
          <i class="fas fa-sign-out-alt mr-2"></i> Çıkış
        </a>
      </div>
    </li>
  </ul>
</nav>

<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="<?php echo h(app_url('yonetim/pano.php')); ?>" class="brand-link">
    <i class="fas fa-broom ml-2 mr-2"></i>
    <span class="brand-text font-weight-light">Temizlik Yönetimi</span>
  </a>
  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

        <?php
        // Menü tanımı (dosya, etiket, ikon, grup)
        $menu = [
          ['pano.php','Pano','fas fa-tachometer-alt','root'],

          // TANIMLAR
          ['__HDR__','TANIMLAR','', 'header'],
          ['binalar.php','Binalar','fas fa-building','defs'],
          ['katlar.php','Katlar','fas fa-layer-group','defs'],
            ['birimler.php','Birimler','fas fa-sitemap','defs'],
          ['odalar.php','Odalar','fas fa-door-open','defs'],

          // OPERASYON
          ['__HDR__','OPERASYON','', 'header'],
          ['personel.php','Personel','fas fa-users','ops'],
          ['gorevler.php','Görevler','fas fa-tasks','ops'],
          ['sikayetler.php','Şikayetler','fas fa-comment-dots','ops'],

          // RAPORLAR
          ['__HDR__','RAPORLAR','', 'header'],
          ['raporlar.php','Raporlar','fas fa-chart-pie','reps'],
          ['temizlenmeyen_bugun.php','Temizlenmeyen','fas fa-exclamation-triangle','reps'],
          ['islem_yapmayan_bugun.php','İşlem Yapmayan','fas fa-user-clock','reps'],
          ['personel_takip.php','Personel Takip','fas fa-user-friends','reps'],
		
          
          
        ];

        foreach ($menu as $m) {
            [$file,$label,$icon,$grp] = $m;
            if ($file === '__HDR__') {
                // Header satırı: SEF için yalnızca erişimi olan grup başlıkları gösterilsin
                // SEF'in görebildiği gruplar: OPERASYON (görev/şikayet), RAPORLAR (raporlar, temizlenmeyen)
                if ($userRole === 'SEF') {
                    if (!in_array($label, ['OPERASYON','RAPORLAR'], true)) continue;
                }
                echo '<li class="nav-header">'.h($label).'</li>';
                continue;
            }
            if (!can_show_nav($file, $userRole)) {
                continue;
            }
            echo '<li class="nav-item"><a href="'.
              h(app_url('yonetim/'.$file)).
              '" class="nav-link'.nav_active($file).
              '">'.($icon?'<i class="nav-icon '.$icon.'"></i> ':'').
              '<p>'.h($label).'</p></a></li>';
        }

        // Portal & Çıkış (her zaman)
        ?>
        <li class="nav-item"><a href="<?php echo h(app_url()); ?>" target="_blank" class="nav-link"><i class="nav-icon fas fa-external-link-alt"></i><p>Portal</p></a></li>
        <li class="nav-item"><a href="<?php echo h(app_url('yonetim/logout.php')); ?>" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Çıkış</p></a></li>
      </ul>
    </nav>
  </div>
</aside>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0 text-dark"><?php echo h($pageTitle); ?></h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="<?php echo h(app_url('yonetim/pano.php')); ?>">Pano</a></li>
            <li class="breadcrumb-item active"><?php echo h($pageTitle); ?></li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">
      <?php foreach(flash_get_all() as $f): ?>
        <div class="alert alert-<?php echo $f['t']==='error'?'danger':h($f['t']); ?> alert-dismissible fade show" role="alert">
          <?php echo h($f['m']); ?>
          <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
      <?php endforeach; ?>