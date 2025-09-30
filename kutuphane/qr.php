<?php
declare(strict_types=1);

/**
 * Geliştirilmiş QR Üretici
 * - Dış servis (goqr -> quickchart fallback)
 * - Otomatik logo.png bind (kök dizinde varsa)
 * - uploads/qrresimleri/<slug>.qrkod.png kalıcı kopya
 * - Eski parametreler korunur (logo parametresi verilirse o kullanılır; yoksa logo.png denenir)
 *
 * Örnek:
 *   /kutuphane/qr.php?url=https://site/qr_gate.php?c=abcd-XYZ1234&boyut=8
 */

////////////////////  AYARLAR  ////////////////////
const TIMEOUT_SEC = 4;
const LOGO_SCALE  = 0.22;
const CACHE_DEFAULT = true;
///////////////////////////////////////////////////

function qr_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function qr_env(string $k, ?string $d=null): ?string {
    if(isset($_ENV[$k])) return $_ENV[$k];
    $v=getenv($k);
    return $v===false? $d : $v;
}

/* .env (gerekliyse) */
if(!isset($_ENV['__QR_ENV_LOADED'])){
    $envPath=dirname(__DIR__) . '/.env';
    if(is_file($envPath)){
        foreach(file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line){
            $t=trim($line);
            if($t==='' || $t[0]=='#') continue;
            $p=strpos($line,'=');
            if($p===false) continue;
            $k=trim(substr($line,0,$p));
            $v=trim(substr($line,$p+1));
            if(preg_match('/^"(.*)"$/',$v,$m) || preg_match("/^'(.*)'$/",$v,$m)) $v=$m[1];
            $_ENV[$k]=$v; putenv("$k=$v");
        }
    }
    $_ENV['__QR_ENV_LOADED']=1;
}

/* Parametreler */
$urlParam = $_GET['url'] ?? '';
if($urlParam === ''){
    usage_help();
}
$dataRaw = trim($urlParam);
if(strlen($dataRaw) > 1024){
    $dataRaw = substr($dataRaw,0,1024);
}

$boyut = (int)($_GET['boyut'] ?? 8);
if($boyut < 2) $boyut=2;
if($boyut > 30) $boyut=30;
$pixel = $boyut * 40;
if($pixel < 80) $pixel = 80;
if($pixel > 1200) $pixel = 1200;

$margin = (int)($_GET['kenarbosluk'] ?? 4);
if($margin<0) $margin=0;
if($margin>20) $margin=20;

$ec = strtoupper($_GET['ec'] ?? 'H');
if(!in_array($ec,['L','M','Q','H'],true)) $ec='H';

/* Logo mantığı:
 * - ?logo= verilirse onu dene
 * - verilmezse kök logo.png otomatik
 */
$logoRelParam = $_GET['logo'] ?? '';
$logoRel = $logoRelParam !== '' ? $logoRelParam : 'logo.png';

$cacheEnabled = (isset($_GET['cache']) ? ($_GET['cache']=='1') : CACHE_DEFAULT);
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh']=='1';
$debug        = isset($_GET['debug']) && $_GET['debug']=='1';

/* Cache dizini */
$cacheDir = rtrim(qr_env('QR_CACHE_DIR',''),'/');
if(!$cacheDir){
    $cacheDir = __DIR__ . '/qr_cache';
}
$cacheUrlBase = rtrim(qr_env('QR_CACHE_URL',''),'/');
if(!$cacheUrlBase){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/kutuphane/qr.php';
    $base   = rtrim(dirname($script),'/');
    $cacheUrlBase = $scheme.'://'.$host.($base!=='/'?$base:'').'/qr_cache';
}
if(!is_dir($cacheDir)){
    @mkdir($cacheDir,0775,true);
}

/* Cache anahtarı */
$cacheKeyParts = [
    'd'=>$dataRaw,'p'=>$pixel,'m'=>$margin,'e'=>$ec,'logo'=>$logoRel
];
$cacheKey = sha1(json_encode($cacheKeyParts));
$cacheFile = $cacheDir . '/qr_' . $cacheKey . '.png';

if($cacheEnabled && !$forceRefresh && is_file($cacheFile)){
    serve_png(@file_get_contents($cacheFile), $debug, [
        'cache_hit'=>true,
        'source'=>'cache',
        'pixel'=>$pixel,
        'margin'=>$margin,
        'ec'=>$ec,
        'logo_used'=>false, // cache'te hangi logo kullanıldığını meta saklamıyoruz
        'file'=>$cacheFile
    ]);
    exit;
}

/* Servisler */
$services = [
    'goqr' => function(string $data,int $px,int $margin,string $ec): string {
        return 'https://api.qrserver.com/v1/create-qr-code/?data='.rawurlencode($data)
             .'&size='.$px.'x'.$px.'&margin='.$margin.'&ecc='.$ec;
    },
    'quickchart' => function(string $data,int $px,int $margin,string $ec): string {
        return 'https://quickchart.io/qr?text='.rawurlencode($data)
             .'&size='.$px.'&margin='.$margin.'&ecLevel='.$ec;
    },
];

$attempts=[];
$rawImage=null;
foreach($services as $name=>$builder){
    $url = $builder($dataRaw,$pixel,$margin,$ec);
    $start = microtime(true);
    $resp = http_get_binary($url, TIMEOUT_SEC);
    $elapsed = round((microtime(true)-$start)*1000);
    $ok = $resp['ok'] && $resp['status']===200 && $resp['body']!=='' && is_png($resp['body']);
    $attempts[]=[
        'service'=>$name,'request_url'=>$url,'status'=>$resp['status'],'time_ms'=>$elapsed,'ok'=>$ok
    ];
    if($ok){
        $rawImage = $resp['body'];
        break;
    }
}

/* Hata */
if(!$rawImage){
    $errInfo=[
        'error'=>'Servisler başarısız',
        'attempts'=>$attempts
    ];
    if($debug){
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($errInfo,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    }else{
        $im=imagecreatetruecolor(500,120);
        $w=imagecolorallocate($im,255,255,255);
        $r=imagecolorallocate($im,200,0,0);
        imagefill($im,0,0,$w);
        imagestring($im,5,10,10,'QR SERVIS HATA',$r);
        $y=40;
        foreach($attempts as $a){
            imagestring($im,2,10,$y, $a['service'].': '.$a['status'].' '.($a['ok']?'OK':'FAIL'), $r);
            $y+=15;
        }
        header('Content-Type: image/png');
        imagepng($im); imagedestroy($im);
    }
    exit;
}

/* Logo bind */
$logoUsed = false;
$logoPath = resolve_logo_path($logoRel);
if(!$logoPath){
    // parametre geçersizse kök logo dene
    $rootLogo = dirname(__DIR__).'/logo.png';
    if(is_file($rootLogo)) $logoPath = $rootLogo;
}
if($logoPath && is_file($logoPath)){
    $merged = add_logo_to_qr($rawImage,$logoPath);
    if($merged !== null){
        $rawImage = $merged;
        $logoUsed = true;
    }
}

/* Cache kaydet */
if($cacheEnabled){
    @file_put_contents($cacheFile,$rawImage);
}

/* Kalıcı kopya (oda QR slug) */
$qrSlug = extract_qr_code_slug($dataRaw);
if($qrSlug){
    $qrOutDir = dirname(__DIR__).'/uploads/qrresimleri';
    if(!is_dir($qrOutDir)) @mkdir($qrOutDir,0775,true);
    $outFile = $qrOutDir.'/'.$qrSlug.'.qrkod.png';
    @file_put_contents($outFile,$rawImage);
}

/* Çıktı */
serve_png($rawImage, $debug, [
    'cache_hit'=>false,
    'source'=>'api',
    'attempts'=>$attempts,
    'cache_file'=>$cacheFile,
    'cache_enabled'=>$cacheEnabled,
    'pixel'=>$pixel,
    'margin'=>$margin,
    'ec'=>$ec,
    'logo_used'=>$logoUsed,
    'qr_slug'=>$qrSlug
]);

/* =========== Fonksiyonlar =========== */

function usage_help(): void {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "QR API Kullanım:\n";
    echo "  qr.php?url=VERI&boyut=8&kenarbosluk=4&ec=H\n";
    echo "Parametreler:\n";
    echo "  url (zorunlu)  : Metin veya URL (1024 char limit)\n";
    echo "  boyut          : 2..30 (vars 8) -> piksel=boyut*40\n";
    echo "  kenarbosluk    : 0..20 (vars 4)\n";
    echo "  ec             : L|M|Q|H (vars H)\n";
    echo "  logo           : assets/logo.png vb.\n";
    echo "  cache=0/1      : Önbelleğe yaz (vars 1)\n";
    echo "  refresh=1      : Cache’i yoksay\n";
    echo "  debug=1        : JSON meta\n";
    exit;
}

function http_get_binary(string $url,int $timeout): array {
    $ch = curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>$timeout,
        CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_USERAGENT=>'QRFetcher/1.1'
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [
        'ok'=> $body!==false,
        'status'=>$status,
        'body'=> $body!==false ? $body : '',
        'error'=>$err
    ];
}

function is_png(string $bytes): bool {
    return strncmp($bytes, "\x89PNG\x0D\x0A\x1A\x0A",8)===0;
}

function serve_png(string $raw, bool $debug, array $meta): void {
    if($debug){
        header('Content-Type: application/json; charset=UTF-8');
        $meta['length']=strlen($raw);
        $meta['sha1']=sha1($raw);
        echo json_encode($meta,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
        return;
    }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800, immutable');
    echo $raw;
}

function resolve_logo_path(string $rel): ?string {
    $rel = ltrim($rel,'/');
    $base = dirname(__DIR__);
    $cand = $base . '/' . $rel;
    if(is_file($cand) && filesize($cand) < 5_000_000){
        return $cand;
    }
    return null;
}

function add_logo_to_qr(string $qrPng,string $logoPath): ?string {
    if(!function_exists('imagecreatetruecolor')) return null;
    $qrIm = imagecreatefromstring($qrPng);
    if(!$qrIm) return null;
    $info = getimagesize($logoPath);
    if(!$info) { imagedestroy($qrIm); return null; }
    $ext = strtolower(pathinfo($logoPath,PATHINFO_EXTENSION));
    switch($ext){
        case 'png': $logoIm = imagecreatefrompng($logoPath); break;
        case 'jpg':
        case 'jpeg': $logoIm = imagecreatefromjpeg($logoPath); break;
        case 'gif': $logoIm = imagecreatefromgif($logoPath); break;
        default: $logoIm = null;
    }
    if(!$logoIm){ imagedestroy($qrIm); return null; }

    $qrW = imagesx($qrIm);
    $qrH = imagesy($qrIm);
    $logoW = imagesx($logoIm);
    $logoH = imagesy($logoIm);

    $targetW = (int)($qrW * LOGO_SCALE);
    $targetH = (int)($logoH * ($targetW / $logoW));

    $dstX = (int)(($qrW - $targetW)/2);
    $dstY = (int)(($qrH - $targetH)/2);

    // Beyaz arka plan
    $bgMargin = (int)($targetW * 0.08);
    $bgX = $dstX - $bgMargin;
    $bgY = $dstY - $bgMargin;
    $bgW = $targetW + 2*$bgMargin;
    $bgH = $targetH + 2*$bgMargin;
    $white = imagecolorallocate($qrIm,255,255,255);
    imagefilledrectangle($qrIm,$bgX,$bgY,$bgX+$bgW,$bgY+$bgH,$white);

    imagecopyresampled(
        $qrIm,$logoIm,
        $dstX,$dstY,0,0,
        $targetW,$targetH,$logoW,$logoH
    );
    ob_start();
    imagepng($qrIm);
    imagedestroy($qrIm);
    imagedestroy($logoIm);
    return ob_get_clean();
}

function extract_qr_code_slug(string $data): ?string {
    $p = parse_url($data);
    $query = $p['query'] ?? '';
    if(!$query){
        $qPos = strpos($data,'?');
        if($qPos!==false){
            $query = substr($data,$qPos+1);
        }
    }
    if(!$query) return null;
    parse_str($query,$arr);
    if(empty($arr['c'])) return null;
    $slug = preg_replace('/[^A-Za-z0-9_\-]/','_', $arr['c']);
    return $slug ?: null;
}