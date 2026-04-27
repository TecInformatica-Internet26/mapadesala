<?php
require 'conexao.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  $_SESSION['erro'] = "Método não permitido.";
  header('Location: ../Paginas/salas.php');
  exit;
}

$nomeSala  = trim($_POST["nomeSala"] ?? "");
$capacidade = (int)($_POST["capacidade"] ?? 0);

if ($nomeSala === "" || $capacidade <= 0) {
  $_SESSION['erro'] = "Preencha nome e capacidade corretamente.";
  header('Location: ../Paginas/salas.php');
  exit;
}

$sql = "INSERT INTO salas (nome_sala, capacidade) VALUES (?, ?)";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("si", $nomeSala, $capacidade);

if ($stmt->execute()) {
  $_SESSION['sucesso'] = "Sala criada com sucesso!";
} else {
  $_SESSION['erro'] = "Erro ao criar sala.";
}

$stmt->close();
$conexao->close();

header('Location: ../Paginas/salas.php');
exit;
