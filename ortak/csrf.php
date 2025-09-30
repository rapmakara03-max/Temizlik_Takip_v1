<?php
declare(strict_types=1);
function csrf_token(): string {
    if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_token" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8').'">';
}
function csrf_check(): void {
    if(!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token']??'',$_POST['_token'])){
        flash_set('error','CSRF doğrulama başarısız.');
        redirect($_SERVER['HTTP_REFERER'] ?? app_url());
    }
}