<?php
require 'conexao.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  $_SESSION['erro'] = "Método não permitido.";
  header('Location: ../Paginas/salas.php');
  exit;
}

$id = (int)($_POST['id_sala'] ?? 0);
$nome = trim($_POST['nomeSala'] ?? "");
$cap = (int)($_POST['capacidade'] ?? 0);

if ($id <= 0 || $nome === "" || $cap <= 0) {
  $_SESSION['erro'] = "Dados inválidos para editar sala.";
  header('Location: ../Paginas/salas.php');
  exit;
}

$sql = "UPDATE salas SET nome_sala = ?, capacidade = ? WHERE id_sala = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("sii", $nome, $cap, $id);

if ($stmt->execute()) {
  $_SESSION['sucesso'] = "Sala atualizada!";
} else {
  $_SESSION['erro'] = "Erro ao atualizar sala.";
}

$stmt->close();
$conexao->close();

header('Location: ../Paginas/salas.php');
exit;
