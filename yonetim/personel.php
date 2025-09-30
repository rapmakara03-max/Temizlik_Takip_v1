<?php
/*
  personel.php (revize)

  Değişiklik Özeti:
  - Eski "sorumlu_bolge" kaldırıldı; yerine INTEGER "sorumlu_birim_id" kullanılıyor.
  - Ekle / Güncelle formları bu yeni alanı kullanır.
  - Liste görünümünde, sorumlu birim seçilmişse ilgili Birim -> (Bina(lar) / Kat(lar) / Birim Adı) biçiminde konum özetini gösterir.
  - Birim konum bilgileri tek sorguda (GROUP_CONCAT) toplanır.
  - Telefon alanı validasyonu: 0 ile başlayan 11 hane.
  - Rol seçeneklerine SEF (ŞEF) eklendi.
  - Güvenlik: CSRF, password_hash, prepared statements (exec_stmt kullanıldığı varsayımıyla).
  - Sayfalama ve arama (ad alanında) korunmuştur.
  - Hata durumlarında flash mesajları.
  - (İsteğe bağlı) Migration notu:
      ALTER TABLE kullanicilar
        ADD COLUMN telefon VARCHAR(20) NULL AFTER rol,
        ADD COLUMN gorevi VARCHAR(120) NULL AFTER telefon,
        ADD COLUMN sorumlu_birim_id INT NULL AFTER gorevi,
        MODIFY rol ENUM('GENEL','MUDUR','SEF','PERSONEL') NOT NULL DEFAULT 'PERSONEL';

  NOT: Eğer bazı kolonlar henüz eklenmediyse migration uyguladıktan sonra bu dosyayı kullanın.
*/

require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/../ortak/user_events.php';

/* ------------ Yardımcı Fonksiyonlar ------------ */

function valid_tel(?string $t): bool {
    if (!$t) return true;              // Boş bırakılabilir
    $t = trim($t);
    return (bool)preg_match('/^0\d{10}$/', $t);
}

function render_options(array $options, ?string $selected): string {
    $out='';
    foreach($options as $value=>$label){
        $sel = ($selected !== null && (string)$selected === (string)$value) ? ' selected' : '';
        $out .= '<option value="'.h($value).'"'.$sel.'>'.h($label).'</option>';
    }
    return $out;
}

/* ------------ Görev (Ünvan) Seçenekleri ------------ */
$gorevOptions = [
    'Hastane Müdürü'      => 'Hastane Müdürü',
    'Müdür Yardımcısı'    => 'Müdür Yardımcısı',
    'Birim Sorumlusu'     => 'Birim Sorumlusu',
    'Şef'                 => 'Şef',
    'Temizlik Görevlisi'  => 'Temizlik Görevlisi',
];

/* ------------ Birim Seçenekleri (Form İçin) ------------ */
$birimRows = fetch_all("
    SELECT bi.id,
           bi.ad AS birim_ad,
           /* Basit opsiyon etiketi: ID - Birim Adı */
           (SELECT COUNT(*) FROM odalar o2 WHERE o2.birim_id=bi.id) AS oda_say
    FROM birimler bi
    ORDER BY bi.ad
", []);

$birimOptions = [];
foreach ($birimRows as $br) {
    $label = $br['id'].' - '.$br['birim_ad'].($br['oda_say'] ? ' ('.$br['oda_say'].' oda)' : '');
    $birimOptions[(string)$br['id']] = $label;
}

/* ------------ POST İşlemleri ------------ */
if (is_post()) {
    csrf_check();
    $act = $_POST['act'] ?? '';

    /* ---- Ekle ---- */
    if ($act === 'ekle') {
        $ad              = trim($_POST['ad'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $rol             = $_POST['rol'] ?? 'PERSONEL';
        $parolaPlain     = trim($_POST['parola'] ?? '');
        $telefon         = trim($_POST['telefon'] ?? '');
        $gorevi          = trim($_POST['gorevi'] ?? '');
        $sorumlu_birim_id= (int)($_POST['sorumlu_birim_id'] ?? 0) ?: null;

        if (!$ad || !$email || !$parolaPlain) {
            flash_set('error','Zorunlu alanlar eksik.');
            redirect(current_path());
        }
        if (!valid_tel($telefon)) {
            flash_set('error','Telefon 0 ile başlayan 11 hane olmalıdır. (Örn: 05436666666)');
            redirect(current_path());
        }

        $hash = password_hash($parolaPlain, PASSWORD_DEFAULT);
        $ok = exec_stmt(
            "INSERT INTO kullanicilar(ad,email,parola,rol,telefon,gorevi,sorumlu_birim_id,aktif,created_at,updated_at)
             VALUES(?,?,?,?,?,?,?,1,NOW(),NOW())",
            [$ad, $email, $hash, $rol, $telefon, $gorevi, $sorumlu_birim_id]
        );

        if ($ok) {
            notify_user_created_via_sms($email, $parolaPlain, $telefon ?: null);
            flash_set('success','Personel eklendi.');
        } else {
            flash_set('error','Eklenemedi (email benzersiz mi?).');
        }
        redirect(current_path());
    }

    /* ---- Güncelle ---- */
    if ($act === 'guncelle') {
        $id              = (int)($_POST['id'] ?? 0);
        $ad              = trim($_POST['ad'] ?? '');
        $rol             = $_POST['rol'] ?? 'PERSONEL';
        $parolaPlain     = trim($_POST['parola'] ?? '');
        $telefon         = trim($_POST['telefon'] ?? '');
        $gorevi          = trim($_POST['gorevi'] ?? '');
        $sorumlu_birim_id= (int)($_POST['sorumlu_birim_id'] ?? 0) ?: null;

        if (!($id > 0 && $ad)) {
            flash_set('error','Eksik veri.');
            redirect(current_path());
        }
        if (!valid_tel($telefon)) {
            flash_set('error','Telefon 0 ile başlayan 11 hane olmalıdır.');
            redirect(current_path());
        }

        if ($parolaPlain !== '') {
            $hash = password_hash($parolaPlain, PASSWORD_DEFAULT);
            $ok = exec_stmt(
                "UPDATE kullanicilar
                 SET ad=?, rol=?, parola=?, telefon=?, gorevi=?, sorumlu_birim_id=?, updated_at=NOW()
                 WHERE id=?",
                [$ad, $rol, $hash, $telefon, $gorevi, $sorumlu_birim_id, $id]
            );
        } else {
            $ok = exec_stmt(
                "UPDATE kullanicilar
                 SET ad=?, rol=?, telefon=?, gorevi=?, sorumlu_birim_id=?, updated_at=NOW()
                 WHERE id=?",
                [$ad, $rol, $telefon, $gorevi, $sorumlu_birim_id, $id]
            );
        }

        flash_set($ok ? 'success':'error', $ok ? 'Güncellendi.' : 'Güncellenemedi.');
        redirect(current_path());
    }

    /* ---- Aktif/Pasif Toggle ---- */
    if ($act === 'toggle') {
        $id    = (int)($_POST['id'] ?? 0);
        $aktif = (int)($_POST['aktif'] ?? 1);
        $ok = ($id>0) && exec_stmt("UPDATE kullanicilar SET aktif=?, updated_at=NOW() WHERE id=?", [$aktif, $id]);
        flash_set($ok?'success':'error', $ok?'Durum güncellendi.':'Güncellenemedi.');
        redirect(current_path());
    }

    /* ---- Sil ---- */
    if ($act === 'sil') {
        $id = (int)($_POST['id'] ?? 0);
        $ok = ($id>0) && exec_stmt("DELETE FROM kullanicilar WHERE id=?", [$id]);
        flash_set($ok?'success':'error', $ok?'Silindi.':'Silinemedi.');
        redirect(current_path());
    }
}

/* ------------ Listeleme / Sayfalama ------------ */
[$page, $per, $offset] = paginate_params();          // ortak fonksiyon varsayımı
[$sWhere, $sParams]    = search_clause('ad');        // sadece ad alanında arama
$order                 = sort_clause(
    ['id'=>'id','ad'=>'ad','email'=>'email','rol'=>'rol','sorumlu_birim_id'=>'sorumlu_birim_id'],
    'id DESC'
);

$total = fetch_one("SELECT COUNT(*) c FROM kullanicilar WHERE 1=1 $sWhere", $sParams)['c'] ?? 0;
$rows  = fetch_all("SELECT * FROM kullanicilar WHERE 1=1 $sWhere ORDER BY $order LIMIT $per OFFSET $offset", $sParams);

/* ------------ Sorumlu Birim Konum Verileri (Toplu) ------------ */
$birimIds = [];
foreach ($rows as $r) {
    if (!empty($r['sorumlu_birim_id'])) {
        $birimIds[] = (int)$r['sorumlu_birim_id'];
    }
}
$birimIds = array_unique($birimIds);

$konumMap = []; // birim_id => ['birim_ad'=>..., 'binalar'=>..., 'katlar'=>...]
if ($birimIds) {
    $placeholders = implode(',', array_fill(0, count($birimIds), '?'));
    $konumRows = fetch_all("
        SELECT
            bi.id AS birim_id,
            bi.ad AS birim_ad,
            GROUP_CONCAT(DISTINCT b.ad  ORDER BY b.ad  SEPARATOR ', ') AS binalar,
            GROUP_CONCAT(DISTINCT k.ad  ORDER BY k.ad  SEPARATOR ', ') AS katlar
        FROM birimler bi
        LEFT JOIN odalar  o ON o.birim_id = bi.id
        LEFT JOIN katlar  k ON k.id       = o.kat_id
        LEFT JOIN binalar b ON b.id       = o.bina_id
        WHERE bi.id IN ($placeholders)
        GROUP BY bi.id, bi.ad
    ", $birimIds);
    foreach ($konumRows as $kr) {
        $konumMap[(int)$kr['birim_id']] = [
            'birim_ad' => $kr['birim_ad'],
            'binalar'  => $kr['binalar'],
            'katlar'   => $kr['katlar'],
        ];
    }
}

/* ------------ HTML Başlangıcı ------------ */
?>
<div class="row">
  <!-- Yeni Personel -->
  <div class="col-xl-4">
    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title mb-0">Yeni Personel</h3>
      </div>
      <form method="post">
        <div class="card-body">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="act" value="ekle">

          <div class="form-group">
            <label>Ad</label>
            <input type="text" name="ad" class="form-control form-control-sm" required>
          </div>

            <div class="form-group">
            <label>E-posta</label>
            <input type="email" name="email" class="form-control form-control-sm" required>
          </div>

          <div class="form-group">
            <label>Rol</label>
            <select name="rol" class="form-control form-control-sm">
              <option value="PERSONEL">PERSONEL</option>
              <option value="SEF">ŞEF</option>
              <option value="MUDUR">MÜDÜR</option>
              <option value="GENEL">GENEL</option>
            </select>
          </div>

          <div class="form-group">
            <label>Telefon (0 ile başlayan 11 hane)</label>
            <input type="text" name="telefon" maxlength="11" pattern="0\d{10}" class="form-control form-control-sm" placeholder="05436666666">
          </div>

          <div class="form-group">
            <label>Görevi</label>
            <select name="gorevi" class="form-control form-control-sm">
              <option value="">Seçiniz</option>
              <?php echo render_options($gorevOptions, ''); ?>
            </select>
          </div>

          <div class="form-group">
            <label>Sorumlu Birim</label>
            <select name="sorumlu_birim_id" class="form-control form-control-sm">
              <option value="">(Seçilmemiş)</option>
              <?php echo render_options($birimOptions, ''); ?>
            </select>
            <small class="text-muted">Seçtiğiniz birim üzerinden bina ve kat bilgileri otomatik raporlanır.</small>
          </div>

          <div class="form-group">
            <label>Parola</label>
            <input type="password" name="parola" class="form-control form-control-sm" required>
          </div>
        </div>
        <div class="card-footer text-right">
          <button class="btn btn-sm btn-primary">Kaydet</button>
        </div>
      </form>
    </div>

    <!-- Arama -->
    <div class="card card-outline card-secondary">
      <div class="card-header"><h3 class="card-title mb-0">Arama</h3></div>
      <div class="card-body">
        <form method="get" class="form-inline">
          <input type="text" name="q" class="form-control form-control-sm mr-2 mb-2" placeholder="Ara (ad)..." value="<?php echo h($_GET['q'] ?? ''); ?>">
          <button class="btn btn-sm btn-outline-primary mb-2">Ara</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Liste -->
  <div class="col-xl-8">
    <div class="card card-outline card-info">
      <div class="card-header">
        <h3 class="card-title mb-0">Personel</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th>ID</th>
                <th>Ad</th>
                <th>E-posta</th>
                <th>Rol</th>
                <th>Telefon</th>
                <th>Görevi</th>
                <th>Sorumlu Konum (Bina / Kat / Birim)</th>
                <th>Aktif</th>
                <th style="width:300px;">İşlemler</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
                $konumMetin = '- / - / -';
                if (!empty($r['sorumlu_birim_id'])) {
                    $bid = (int)$r['sorumlu_birim_id'];
                    $info = $konumMap[$bid] ?? null;
                    if ($info) {
                        $binalar = $info['binalar'] ?: '-';
                        $katlar  = $info['katlar']  ?: '-';
                        $birimAd = $info['birim_ad'] ?: ('#'.$bid);
                        $konumMetin = $binalar.' / '.$katlar.' / '.$birimAd;
                    } else {
                        $konumMetin = '- / - / #'.$bid;
                    }
                }
              ?>
              <tr>
                <td><?php echo h($r['id']); ?></td>
                <td><?php echo h($r['ad']); ?></td>
                <td><?php echo h($r['email']); ?></td>
                <td><?php echo h($r['rol']); ?></td>
                <td><?php echo h($r['telefon'] ?? ''); ?></td>
                <td><?php echo h($r['gorevi'] ?? ''); ?></td>
                <td><?php echo h($konumMetin); ?></td>
                <td><?php echo (int)$r['aktif']===1
                        ? '<span class="badge badge-success">Evet</span>'
                        : '<span class="badge badge-secondary">Hayır</span>'; ?></td>
                <td>
                  <div class="btn-group btn-group-sm mb-1">
                    <button class="btn btn-warning" data-toggle="collapse" data-target="#editPers<?php echo h($r['id']); ?>">Düzenle</button>
                    <form method="post" class="d-inline">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="act" value="toggle">
                      <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                      <input type="hidden" name="aktif" value="<?php echo (int)$r['aktif']===1?0:1; ?>">
                      <button class="btn btn-secondary"><?php echo (int)$r['aktif']===1?'Pasifleştir':'Aktifleştir'; ?></button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Silinsin mi?');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="act" value="sil">
                      <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                      <button class="btn btn-danger">Sil</button>
                    </form>
                  </div>

                  <!-- Düzenleme Formu -->
                  <div id="editPers<?php echo h($r['id']); ?>" class="collapse">
                    <form method="post" class="border rounded p-2 bg-light">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="act" value="guncelle">
                      <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">

                      <div class="form-group mb-1">
                        <label class="small mb-0">Ad</label>
                        <input type="text" name="ad" class="form-control form-control-sm" required value="<?php echo h($r['ad']); ?>">
                      </div>

                      <div class="form-group mb-1">
                        <label class="small mb-0">Rol</label>
                        <select name="rol" class="form-control form-control-sm">
                          <option value="PERSONEL" <?php echo selected($r['rol'],'PERSONEL'); ?>>PERSONEL</option>
                          <option value="SEF" <?php echo selected($r['rol'],'SEF'); ?>>ŞEF</option>
                          <option value="MUDUR" <?php echo selected($r['rol'],'MUDUR'); ?>>MÜDÜR</option>
                          <option value="GENEL" <?php echo selected($r['rol'],'GENEL'); ?>>GENEL</option>
                        </select>
                      </div>

                      <div class="form-group mb-1">
                        <label class="small mb-0">Telefon (0 ile başlayan 11 hane)</label>
                        <input type="text" name="telefon" maxlength="11" pattern="0\d{10}" class="form-control form-control-sm" value="<?php echo h($r['telefon'] ?? ''); ?>">
                      </div>

                      <div class="form-group mb-1">
                        <label class="small mb-0">Görevi</label>
                        <select name="gorevi" class="form-control form-control-sm">
                          <option value="">Seçiniz</option>
                          <?php echo render_options($gorevOptions, $r['gorevi'] ?? ''); ?>
                        </select>
                      </div>

                      <div class="form-group mb-1">
                        <label class="small mb-0">Sorumlu Birim</label>
                        <select name="sorumlu_birim_id" class="form-control form-control-sm">
                          <option value="">(Seçilmemiş)</option>
                          <?php echo render_options($birimOptions, $r['sorumlu_birim_id'] ?? ''); ?>
                        </select>
                        <small class="text-muted d-block">
                          Mevcut Konum: <?php echo h($konumMetin); ?>
                        </small>
                      </div>

                      <div class="form-group mb-1">
                        <label class="small mb-0">Parola (boş ise değişmez)</label>
                        <input type="password" name="parola" class="form-control form-control-sm">
                      </div>

                      <button class="btn btn-sm btn-primary">Kaydet</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; if(!$rows): ?>
              <tr><td colspan="9" class="text-muted">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php $pages=(int)ceil($total/$per); if($pages>1): ?>
      <div class="card-footer">
        <ul class="pagination pagination-sm mb-0">
          <?php for($i=1;$i<=$pages;$i++):
              $qs=$_GET; $qs['page']=$i; ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>">
              <a class="page-link" href="?<?php echo http_build_query($qs); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>