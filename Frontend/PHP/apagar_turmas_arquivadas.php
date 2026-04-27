<?php
require "conexao.php";

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Método inválido. Use POST.']);
  exit;
}

try {
  // 1) Busca as turmas cujo progresso chegou a 100% (horas_realizadas >= carga_horaria)
  $sqlIds = "
    SELECT t.id_turma
    FROM turmas t
    WHERE t.carga_horaria > 0
      AND (
        SELECT COALESCE(SUM(te.horas), 0)
        FROM turma_encontros te
        WHERE te.id_turma = t.id_turma
          AND te.status = 'marcado'
          AND te.data < CURDATE()
      ) >= t.carga_horaria
  ";

  $res = mysqli_query($conexao, $sqlIds);
  if (!$res) {
    throw new Exception('Falha ao buscar turmas arquivadas: ' . mysqli_error($conexao));
  }

  $ids = [];
  while ($row = mysqli_fetch_assoc($res)) {
    $ids[] = (int)$row['id_turma'];
  }

  if (count($ids) === 0) {
    echo json_encode(['ok' => true, 'deleted_turmas' => 0, 'deleted_encontros' => 0]);
    exit;
  }

  $idList = implode(',', array_map('intval', $ids));

  mysqli_begin_transaction($conexao);

  // 2) Apaga encontros dessas turmas
  $delEncontrosSql = "DELETE FROM turma_encontros WHERE id_turma IN ($idList)";
  $ok1 = mysqli_query($conexao, $delEncontrosSql);
  if (!$ok1) {
    throw new Exception('Erro ao apagar encontros: ' . mysqli_error($conexao));
  }
  $deletedEncontros = mysqli_affected_rows($conexao);

  // 3) Apaga as turmas
  $delTurmasSql = "DELETE FROM turmas WHERE id_turma IN ($idList)";
  $ok2 = mysqli_query($conexao, $delTurmasSql);
  if (!$ok2) {
    throw new Exception('Erro ao apagar turmas: ' . mysqli_error($conexao));
  }
  $deletedTurmas = mysqli_affected_rows($conexao);

  mysqli_commit($conexao);

  echo json_encode([
    'ok' => true,
    'deleted_turmas' => $deletedTurmas,
    'deleted_encontros' => $deletedEncontros
  ]);
} catch (Throwable $e) {
  if (isset($conexao)) { @mysqli_rollback($conexao); }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
