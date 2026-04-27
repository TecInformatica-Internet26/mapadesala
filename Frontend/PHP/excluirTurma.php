<?php
require __DIR__ . '/conexao.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

function redirectWith(string $type, string $msg): void {
  $_SESSION[$type] = $msg;
  header('Location: ../Paginas/turmas.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirectWith('erro', 'Método não permitido.');
}

$id_turma = (int)($_POST['id_turma'] ?? 0);
if ($id_turma <= 0) {
  redirectWith('erro', 'Turma inválida.');
}

mysqli_begin_transaction($conexao);

try {
  // Remove encontros primeiro (evita erro por FK)
  $stmt1 = mysqli_prepare($conexao, 'DELETE FROM turma_encontros WHERE id_turma = ?');
  if (!$stmt1) throw new Exception('Falha ao preparar exclusão de encontros.');
  mysqli_stmt_bind_param($stmt1, 'i', $id_turma);
  mysqli_stmt_execute($stmt1);

  // Remove a turma
  $stmt2 = mysqli_prepare($conexao, 'DELETE FROM turmas WHERE id_turma = ?');
  if (!$stmt2) throw new Exception('Falha ao preparar exclusão da turma.');
  mysqli_stmt_bind_param($stmt2, 'i', $id_turma);
  mysqli_stmt_execute($stmt2);

  if (mysqli_stmt_affected_rows($stmt2) <= 0) {
    throw new Exception('Turma não encontrada (ou já removida).');
  }

  mysqli_commit($conexao);
  redirectWith('sucesso', 'Turma excluída com sucesso!');

} catch (Throwable $e) {
  mysqli_rollback($conexao);
  redirectWith('erro', 'Não foi possível excluir a turma: ' . $e->getMessage());
}
