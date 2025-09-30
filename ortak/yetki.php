<?php
declare(strict_types=1);
require_once __DIR__ . '/sabitler.php';
require_once __DIR__ . '/vt.php';

function current_user(): ?array { return $_SESSION['user'] ?? null; }
function set_user(?array $u): void { if($u===null) unset($_SESSION['user']); else $_SESSION['user']=$u; }
function user_has_role(array|string $roles): bool {
    $u=current_user(); if(!$u) return false;
    $r=$u['rol']??'';
    if(is_string($roles)) return $r===$roles;
    return in_array($r,$roles,true);
}
function require_role(array $roles, ?string $loginUrl=null): void {
    if(!current_user()){
        $ret=urlencode(current_path());
        redirect(($loginUrl?:app_url('yonetim/login.php'))."?return=$ret");
    }
    if(!user_has_role($roles)){
        http_response_code(403); echo "<h1>403</h1> Erişim reddedildi."; exit;
    }
}
function fetch_user_by_email(string $email): ?array {
    $pdo=db(); if(!$pdo) return null;
    try{
        $st=$pdo->prepare("SELECT * FROM kullanicilar WHERE email=? LIMIT 1");
        $st->execute([$email]);
        $u=$st->fetch(); return $u?:null;
    }catch(Throwable $e){ error_log("fetch_user_by_email: ".$e->getMessage()); }
    return null;
}
function login_user_demo_or_db(string $email,string $pass,array $allowed=[]): bool {
    $u=fetch_user_by_email($email);
    $isDemo=false;
    if(!$u && $email===env('ADMIN_DEMO_EMAIL')){
        if(hash_equals(env('ADMIN_DEMO_PASS'),$pass)){
            $u=['id'=>-1,'ad'=>'Demo Admin','email'=>$email,'rol'=>'GENEL','aktif'=>1]; $isDemo=true;
        }
    }
    if(!$u && $email===env('PERSONEL_DEMO_EMAIL')){
        if(hash_equals(env('PERSONEL_DEMO_PASS'),$pass)){
            $u=['id'=>-2,'ad'=>'Demo Personel','email'=>$email,'rol'=>'PERSONEL','aktif'=>1]; $isDemo=true;
        }
    }
    if(!$u) return false;
    if(isset($u['aktif']) && (int)$u['aktif']!==1) return false;
    if(!$isDemo && !password_verify($pass,$u['parola'])) return false;
    if($allowed && !in_array($u['rol'],$allowed,true)) return false;
    set_user($u); return true;
}
function logout_user(): void {
    session_regenerate_id(true);
    $_SESSION=[];
    if(ini_get('session.use_cookies')){
        $p=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy();
}