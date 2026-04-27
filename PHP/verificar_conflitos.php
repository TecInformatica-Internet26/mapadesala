<?php
require __DIR__ . "/conexao.php";

header("Content-Type: application/json; charset=utf-8");

function bad($msg) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => $msg]);
  exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) bad("JSON inválido.");

$id_sala = isset($data["id_sala"]) && $data["id_sala"] !== "" ? (int)$data["id_sala"] : null;
$id_professor = isset($data["id_professor"]) && $data["id_professor"] !== "" ? (int)$data["id_professor"] : null;
$turno = $data["turno"] ?? "";
$datas = $data["datas"] ?? [];

if (!$id_professor || $id_professor <= 0) bad("Professor inválido.");
if (!$id_sala || $id_sala <= 0) bad("Sala inválida.");
if (!in_array($turno, ["manha","tarde","noite"], true)) bad("Turno inválido.");
if (!is_array($datas) || count($datas) === 0) bad("Lista de datas vazia.");

$datas = array_values(array_unique(array_filter($datas, fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))));
if (count($datas) === 0) bad("Datas inválidas.");

$conflitos_prof = [];
$conflitos_sala = [];

// Helper pra montar IN (?, ?, ...)
$placeholders = implode(",", array_fill(0, count($datas), "?"));
$types = str_repeat("s", count($datas));

// 1) Conflitos do professor (sempre)
$sqlProf = "
  SELECT data
  FROM turma_encontros
  WHERE id_professor = ?
    AND turno = ?
    AND status = 'marcado'
    AND data IN ($placeholders)
";
$stmt = mysqli_prepare($conexao, $sqlProf);
if (!$stmt) bad("Erro prepare professor.");

$bindTypes = "is" . $types;
$params = array_merge([$id_professor, $turno], $datas);

// bind_param com spread (PHP 8+)
mysqli_stmt_bind_param($stmt, $bindTypes, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
  $conflitos_prof[] = $row["data"];
}

// 2) Conflitos da sala (sempre)
$sqlSala = "
    SELECT data
    FROM turma_encontros
    WHERE id_sala = ?
      AND turno = ?
      AND status = 'marcado'
      AND data IN ($placeholders)
  ";
  $stmt2 = mysqli_prepare($conexao, $sqlSala);
  if (!$stmt2) bad("Erro prepare sala.");

  $bindTypes2 = "is" . $types;
  $params2 = array_merge([$id_sala, $turno], $datas);

  mysqli_stmt_bind_param($stmt2, $bindTypes2, ...$params2);
  mysqli_stmt_execute($stmt2);
  $res2 = mysqli_stmt_get_result($stmt2);
  while ($row = mysqli_fetch_assoc($res2)) {
    $conflitos_sala[] = $row["data"];
  }

echo json_encode([
  "ok" => true,
  "conflitos_professor" => array_values(array_unique($conflitos_prof)),
  "conflitos_sala" => array_values(array_unique($conflitos_sala)),
]);
