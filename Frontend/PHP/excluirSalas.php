<?php
require 'conexao.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  $_SESSION['erro'] = "Método não permitido.";
  header('Location: ../Paginas/salas.php');
  exit;
}

$id = (int)($_POST['id_sala'] ?? 0);
if ($id <= 0) {
  $_SESSION['erro'] = "Sala inválida.";
  header('Location: ../Paginas/salas.php');
  exit;
}

// Se houver FK, pode falhar. Mantém mensagem amigável.
$sql = "DELETE FROM salas WHERE id_sala = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
  $_SESSION['sucesso'] = "Sala excluída!";
} else {
  $_SESSION['erro'] = "Não foi possível excluir (pode haver turmas vinculadas).";
}

$stmt->close();
$conexao->close();

header('Location: ../Paginas/salas.php');
exit;
