<?php
// Encerra a sessão e volta pro index
session_start();

// limpa variáveis de sessão
$_SESSION = [];

// remove cookie da sessão
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params['path'], $params['domain'], $params['secure'], $params['httponly']
  );
}

session_destroy();

header('Location: ../../index.html');
exit;
?>
