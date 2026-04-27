<?php
// Retorna informações mínimas de sessão pro front (papel do usuário)
header('Content-Type: application/json; charset=utf-8');

session_start();

$id = $_SESSION['id_usuario'] ?? null;

// regra atual do projeto: id 1 = admin
$papel = ($id === 1 || $id === '1') ? 'admin' : 'user';

echo json_encode([
  'logado' => $id ? true : false,
  'papel' => $papel,
]);
?>
