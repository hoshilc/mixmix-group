<?php
require __DIR__ . '/_lib.php';
require_method('POST');
read_json();
start_session();
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $cp = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $cp['path'], $cp['domain'], $cp['secure'], $cp['httponly']);
}
session_destroy();
ok();