<?php
// Frontend/api/mapa_salas.php
require __DIR__ . "/../PHP/conexao.php";
require __DIR__ . "/../PHP/FeriadosNacionais.php";

header("Content-Type: application/json; charset=utf-8");

function bad($msg) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

$start = $_GET["start"] ?? null;
$end   = $_GET["end"] ?? null;

if (!$start || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) bad("start inválido (YYYY-MM-DD)");
if (!$end   || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) bad("end inválido (YYYY-MM-DD)");

// 1) SALAS
$salas = [];
$q1 = mysqli_query($conexao, "SELECT id_sala, nome_sala FROM salas ORDER BY nome_sala ASC");
if (!$q1) bad("Erro ao buscar salas: " . mysqli_error($conexao));

while ($r = mysqli_fetch_assoc($q1)) {
  $salas[] = [
    "id" => (int)$r["id_sala"],
    "nome" => $r["nome_sala"],
    "tipo" => "Sala",
  ];
}

// 2) FERIADOS (dinâmicos)
$feriados = FeriadosNacionais::getFeriadosPeriodo($start, $end);

// 3) AGENDAMENTOS
$sql = "
  SELECT
    te.id_sala,
    te.data,
    te.turno,
    p.nome AS professor_nome,
    t.nome_turma,
    t.cod_turma
  FROM turma_encontros te
  JOIN turmas t ON t.id_turma = te.id_turma
  JOIN professores p ON p.id_professor = te.id_professor
  WHERE te.status = 'marcado'
    AND te.data >= ?
    AND te.data <= ?
";

$stmt = mysqli_prepare($conexao, $sql);
if (!$stmt) bad("Erro prepare: " . mysqli_error($conexao));

mysqli_stmt_bind_param($stmt, "ss", $start, $end);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$turnoMap = [
  "manha" => "matutino",
  "tarde" => "vespertino",
  "noite" => "noturno",
];

$agendamentos = [];
while ($r = mysqli_fetch_assoc($res)) {
  if (empty($r["id_sala"])) continue;

  $agendamentos[] = [
    "salaId" => (int)$r["id_sala"],
    "data" => $r["data"],
    "turno" => $turnoMap[$r["turno"]] ?? $r["turno"],
    "professor" => $r["professor_nome"] ?? "",
    "curso" => $r["nome_turma"] ?? "",
    "codigoTurma" => $r["cod_turma"] ?? "",
  ];
}

echo json_encode([
  "ok" => true,
  "salas" => $salas,
  "agendamentos" => $agendamentos,
  "feriados" => $feriados
], JSON_UNESCAPED_UNICODE);
?>