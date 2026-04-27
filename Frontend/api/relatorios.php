<?php
// Frontend/api/relatorios.php
require __DIR__ . "/../PHP/conexao.php";

function bad($msg) {
  http_response_code(400);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(["ok" => false, "error" => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

function is_date_ymd($v) {
  return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
}

function ymd_to_br($v) {
  if (!is_date_ymd($v)) return $v;
  [$y, $m, $d] = explode('-', $v);
  return $d . '/' . $m . '/' . $y;
}

function turno_label($turno) {
  $map = [
    "manha" => "matutino",
    "tarde" => "vespertino",
    "noite" => "noturno",
  ];
  return $map[$turno] ?? $turno;
}

function csv_escape($v) {
  $s = (string)$v;
  $s = str_replace('"', '""', $s);
  return '"' . $s . '"';
}

$tipo = $_GET["tipo"] ?? "";
$format = strtolower($_GET["format"] ?? "json");

$start = $_GET["start"] ?? null;
$end   = $_GET["end"] ?? null;

if (!in_array($tipo, ["turmas_prof", "ocupacao_sala", "agenda_sala", "disponibilidade_sala"], true)) {
  bad("tipo inválido");
}

if (!$start || !is_date_ymd($start)) bad("start inválido (YYYY-MM-DD)");
if (!$end   || !is_date_ymd($end))   bad("end inválido (YYYY-MM-DD)");

$id_professor = isset($_GET["id_professor"]) ? (int)$_GET["id_professor"] : 0;
$id_sala      = isset($_GET["id_sala"]) ? (int)$_GET["id_sala"] : 0;

$rows = [];
$meta = ["tipo" => $tipo, "start" => $start, "end" => $end];

// =====================================================================================
// TURMAS DO PROFESSOR
// =====================================================================================
if ($tipo === "turmas_prof") {
  if ($id_professor <= 0) bad("id_professor obrigatório");

  $sql = "
    SELECT
      MIN(p.nome) AS professor,
      t.id_turma,
      MIN(t.cod_turma) AS cod_turma,
      MIN(t.nome_turma) AS nome_turma,
      MIN(t.turno) AS turno,
      MIN(te.data) AS primeiro_encontro,
      MAX(te.data) AS ultimo_encontro,
      COUNT(*) AS encontros,
      COALESCE(SUM(te.horas), 0) AS horas_total
    FROM turma_encontros te
    JOIN turmas t ON t.id_turma = te.id_turma
    JOIN professores p ON p.id_professor = te.id_professor
    WHERE te.status = 'marcado'
      AND te.id_professor = ?
      AND te.data >= ?
      AND te.data <= ?
    GROUP BY t.id_turma
    ORDER BY primeiro_encontro ASC
  ";

  $stmt = mysqli_prepare($conexao, $sql);
  if (!$stmt) bad("Erro prepare: " . mysqli_error($conexao));
  mysqli_stmt_bind_param($stmt, "iss", $id_professor, $start, $end);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);

  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = [
      "professor" => $r["professor"] ?? "",
      "cod_turma" => $r["cod_turma"] ?? "",
      "nome_turma" => $r["nome_turma"] ?? "",
      "turno" => $r["turno"] ?? "",
      "primeiro_encontro" => $r["primeiro_encontro"] ?? "",
      "ultimo_encontro" => $r["ultimo_encontro"] ?? "",
      "encontros" => (int)($r["encontros"] ?? 0),
      "horas_total" => (float)($r["horas_total"] ?? 0),
    ];
  }
}

// =====================================================================================
// OCUPAÇÃO DE SALA
// =====================================================================================
if ($tipo === "ocupacao_sala") {
  $sql = "
    SELECT
      s.nome_sala,
      te.turno,
      COUNT(*) AS turnos_ocupados,
      COALESCE(SUM(te.horas), 0) AS horas_ocupadas
    FROM turma_encontros te
    JOIN salas s ON s.id_sala = te.id_sala
    WHERE te.status = 'marcado'
      AND te.data >= ?
      AND te.data <= ?
  ";
  if ($id_sala > 0) $sql .= " AND te.id_sala = ? ";
  $sql .= " GROUP BY te.id_sala, te.turno ORDER BY s.nome_sala ASC, FIELD(te.turno,'manha','tarde','noite') ";

  $stmt = mysqli_prepare($conexao, $sql);
  if (!$stmt) bad("Erro prepare: " . mysqli_error($conexao));
  if ($id_sala > 0) {
    mysqli_stmt_bind_param($stmt, "ssi", $start, $end, $id_sala);
  } else {
    mysqli_stmt_bind_param($stmt, "ss", $start, $end);
  }
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = [
      "sala" => $r["nome_sala"] ?? "",
      "turno" => turno_label($r["turno"] ?? ""),
      "turnos_ocupados" => (int)($r["turnos_ocupados"] ?? $r["slots_ocupados"] ?? 0),
      "horas_ocupadas" => (float)($r["horas_ocupadas"] ?? 0),
    ];
  }
}

// =====================================================================================
// AGENDA (IMPRIMIR) SALA
// =====================================================================================
if ($tipo === "agenda_sala") {
  if ($id_sala <= 0) bad("id_sala obrigatório");

  $sql = "
    SELECT
      te.data,
      te.turno,
      p.nome AS professor,
      t.nome_turma,
      t.cod_turma,
      COALESCE(te.horas, 0) AS horas
    FROM turma_encontros te
    JOIN turmas t ON t.id_turma = te.id_turma
    JOIN professores p ON p.id_professor = te.id_professor
    WHERE te.status = 'marcado'
      AND te.id_sala = ?
      AND te.data >= ?
      AND te.data <= ?
    ORDER BY te.data ASC, FIELD(te.turno,'manha','tarde','noite')
  ";

  $stmt = mysqli_prepare($conexao, $sql);
  if (!$stmt) bad("Erro prepare: " . mysqli_error($conexao));
  mysqli_stmt_bind_param($stmt, "iss", $id_sala, $start, $end);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = [
      "data" => $r["data"] ?? "",
      "turno" => turno_label($r["turno"] ?? ""),
      "turma" => $r["nome_turma"] ?? "",
      "cod_turma" => $r["cod_turma"] ?? "",
      "professor" => $r["professor"] ?? "",
      "horas" => (float)($r["horas"] ?? 0),
    ];
  }
}

// =====================================================================================
// DISPONIBILIDADE (O QUE ESTÁ OCUPADO) DA SALA
// =====================================================================================
if ($tipo === "disponibilidade_sala") {
  if ($id_sala <= 0) bad("id_sala obrigatório");

  $sql = "
    SELECT
      te.data,
      te.turno,
      p.nome AS professor,
      t.nome_turma,
      t.cod_turma
    FROM turma_encontros te
    JOIN turmas t ON t.id_turma = te.id_turma
    JOIN professores p ON p.id_professor = te.id_professor
    WHERE te.status = 'marcado'
      AND te.id_sala = ?
      AND te.data >= ?
      AND te.data <= ?
    ORDER BY te.data ASC, FIELD(te.turno,'manha','tarde','noite')
  ";

  $stmt = mysqli_prepare($conexao, $sql);
  if (!$stmt) bad("Erro prepare: " . mysqli_error($conexao));
  mysqli_stmt_bind_param($stmt, "iss", $id_sala, $start, $end);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = [
      "data" => $r["data"] ?? "",
      "turno_raw" => $r["turno"] ?? "",
      "turno" => turno_label($r["turno"] ?? ""),
      "turma" => $r["nome_turma"] ?? "",
      "cod_turma" => $r["cod_turma"] ?? "",
      "professor" => $r["professor"] ?? "",
    ];
  }
}

// =====================================================================================
// OUTPUT
// =====================================================================================
if ($format === "csv") {
  header("Content-Type: text/csv; charset=utf-8");
  header('Content-Disposition: attachment; filename="relatorio_' . $tipo . '_' . $start . '_a_' . $end . '.csv"');

  $out = fopen('php://output', 'w');
  // BOM UTF-8 pra Excel
  fwrite($out, "\xEF\xBB\xBF");

  if (count($rows) === 0) {
    fputcsv($out, ["Sem dados para o período"], ';');
    fclose($out);
    exit;
  }

  $headers = array_keys($rows[0]);
  fputcsv($out, $headers, ';');
  foreach ($rows as $row) {
    $line = [];
    foreach ($headers as $h) {
      $val = $row[$h] ?? "";
      // Datas no padrão BR
      if (is_string($val) && is_date_ymd($val)) {
        $val = ymd_to_br($val);
      }
      $line[] = $val;
    }
    fputcsv($out, $line, ';');
  }
  fclose($out);
  exit;
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode([
  "ok" => true,
  "meta" => $meta,
  "rows" => $rows
], JSON_UNESCAPED_UNICODE);
?>
