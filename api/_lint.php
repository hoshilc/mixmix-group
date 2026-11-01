<?php
/* TEMPORAL: revisa la sintaxis de código PHP que recibe por POST (solo analiza el texto, no lo ejecuta). Se borra enseguida. */
header('Content-Type: text/plain; charset=utf-8');
$code = file_get_contents('php://input');
try {
    token_get_all($code, TOKEN_PARSE);
    echo 'OK php ' . PHP_VERSION;
} catch (ParseError $e) {
    echo 'ERROR linea ' . $e->getLine() . ': ' . $e->getMessage();
}
