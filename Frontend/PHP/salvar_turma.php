<?php
require __DIR__ . "/conexao.php";
require __DIR__ . "/FeriadosNacionais.php";


if (session_status() === PHP_SESSION_NONE) { session_start(); }

function redirectWithError(string $msg): void {
  $_SESSION['erro'] = $msg;
  header("Location: ../Paginas/turmas.php");
  exit;
}
function redirectWithSuccess(string $msg): void {
  $_SESSION['sucesso'] = $msg;
  header("Location: ../Paginas/turmas.php");
  exit;
}

// helpers

function isHoliday(DateTime $dt, array &$cacheByYear): bool {
  $year = (int)$dt->format('Y');
  if (!isset($cacheByYear[$year])) {
    $feriados = FeriadosNacionais::getFeriadosAno($year);
    $set = [];
    foreach ($feriados as $f) $set[$f['data']] = true;
    $cacheByYear[$year] = $set;
  }
  return isset($cacheByYear[$year][$dt->format('Y-m-d')]);
}

function dayToBit($n) {
  return match($n) {
    1 => 1, 2 => 2, 3 => 4, 4 => 8, 5 => 16, 6 => 32, 7 => 64
  };
}
function isDaySelected($mask, DateTime $dt) {
  $bit = dayToBit((int)$dt->format('N'));
  return (($mask & $bit) !== 0);
}

// POST
$id_sala = isset($_POST['id_sala']) && $_POST['id_sala'] !== "" ? (int)$_POST['id_sala'] : null;
$id_professor = isset($_POST['id_professor']) && $_POST['id_professor'] !== "" ? (int)$_POST['id_professor'] : null;

$nome_turma = trim($_POST['nome_turma'] ?? '');
$cod_turma  = trim($_POST['cod_turma'] ?? '');
$data_inicio = $_POST['data_inicio'] ?? '';
$turno = $_POST['turno'] ?? '';
$carga_horaria = (int)($_POST['carga_horaria'] ?? 0);

$dias = $_POST['dias_semana'] ?? [];
$map = ['seg'=>1,'ter'=>2,'qua'=>4,'qui'=>8,'sex'=>16,'sab'=>32,'dom'=>64];
$diasMask = 0;
foreach ($dias as $d) if (isset($map[$d])) $diasMask |= $map[$d];

// validações
if (!$id_professor || $id_professor <= 0) redirectWithError("Professor inválido.");
if ($nome_turma === '' || $cod_turma === '') redirectWithError("Nome/código inválidos.");
if (!$data_inicio) redirectWithError("Data início inválida.");
if (!in_array($turno, ['manha','tarde','noite'], true)) redirectWithError("Turno inválido.");
if ($carga_horaria <= 0) redirectWithError("Carga horária inválida.");
if ($diasMask === 0) redirectWithError("Selecione ao menos 1 dia da semana.");

if (!$id_sala || $id_sala <= 0) redirectWithError("Sala inválida.");
// regra horas
$horasPorEncontro = ($turno === 'noite') ? 3 : 4;
$totalEncontros = (int)ceil($carga_horaria / $horasPorEncontro);

$dt = new DateTime($data_inicio);

mysqli_begin_transaction($conexao);

try {
  // Names para mensagens mais claras
  $nomeSala = "";
  $nomeProf = "";
  if ($id_sala) {
    $stmtNS = mysqli_prepare($conexao, "SELECT nome_sala FROM salas WHERE id_sala=? LIMIT 1");
    if ($stmtNS) { mysqli_stmt_bind_param($stmtNS, "i", $id_sala); mysqli_stmt_execute($stmtNS); $rs = mysqli_stmt_get_result($stmtNS); if ($r = mysqli_fetch_assoc($rs)) $nomeSala = $r['nome_sala'] ?? ""; }
  }
  if ($id_professor) {
    $stmtNP = mysqli_prepare($conexao, "SELECT nome FROM professores WHERE id_professor=? LIMIT 1");
    if ($stmtNP) { mysqli_stmt_bind_param($stmtNP, "i", $id_professor); mysqli_stmt_execute($stmtNP); $rp = mysqli_stmt_get_result($stmtNP); if ($r = mysqli_fetch_assoc($rp)) $nomeProf = $r['nome'] ?? ""; }
  }

  // Preparar checagens de conflito (pra dizer exatamente ONDE e POR QUÊ)
  $sqlCProf = "
    SELECT te.data, t.nome_turma
    FROM turma_encontros te
    INNER JOIN turmas t ON t.id_turma = te.id_turma
    WHERE te.id_professor = ?
      AND te.turno = ?
      AND te.status = 'marcado'
      AND te.data = ?
    LIMIT 1
  ";
  $stmtCProf = mysqli_prepare($conexao, $sqlCProf);

  $sqlCSala = "
    SELECT te.data, t.nome_turma
    FROM turma_encontros te
    INNER JOIN turmas t ON t.id_turma = te.id_turma
    WHERE te.id_sala = ?
      AND te.turno = ?
      AND te.status = 'marcado'
      AND te.data = ?
    LIMIT 1
  ";
  $stmtCSala = mysqli_prepare($conexao, $sqlCSala);

  // insere turma
  $sqlTurma = "
    INSERT INTO turmas
      (id_sala, id_professor, nome_turma, cod_turma, data_inicio, carga_horaria, dias_semana, turno)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  ";
  $stmt = mysqli_prepare($conexao, $sqlTurma);
  mysqli_stmt_bind_param(
    $stmt,
    "iisssiis",
    $id_sala,
$id_professor,
    $nome_turma,
    $cod_turma,
    $data_inicio,
    $carga_horaria,
    $diasMask,
    $turno
  );
  mysqli_stmt_execute($stmt);
  $id_turma = mysqli_insert_id($conexao);

  // insere encontros
  $sqlE = "
    INSERT INTO turma_encontros (id_turma, id_sala, id_professor, data, turno, horas)
    VALUES (?, ?, ?, ?, ?, ?)
  ";
  $stmtE = mysqli_prepare($conexao, $sqlE);

  $encontrosCriados = 0;
  $ultimaData = null;

  $maxDias = 366 * 3;
  $tentativas = 0;
  $feriadosCache = [];

  while ($encontrosCriados < $totalEncontros) {
    if (++$tentativas > $maxDias) throw new Exception("Falha ao gerar datas (verifique dias/data início).");

    if (isDaySelected($diasMask, $dt)) {
      if (isHoliday($dt, $feriadosCache)) {
      $dt->modify('+1 day');
      continue; // <- empurra pra frente
    }
      $dataStr = $dt->format('Y-m-d');

      // 1) conflito professor
      if ($stmtCProf) {
        mysqli_stmt_bind_param($stmtCProf, "iss", $id_professor, $turno, $dataStr);
        mysqli_stmt_execute($stmtCProf);
        $r = mysqli_stmt_get_result($stmtCProf);
        if ($row = mysqli_fetch_assoc($r)) {
          $turmaConflito = $row['nome_turma'] ?? 'outra turma';
          throw new Exception("Conflito de PROFESSOR: {$nomeProf} já está alocado em {$dataStr} (turno {$turno}) na turma '{$turmaConflito}'.");
        }
      }

      // 2) conflito sala
      if ($stmtCSala) {
        mysqli_stmt_bind_param($stmtCSala, "iss", $id_sala, $turno, $dataStr);
        mysqli_stmt_execute($stmtCSala);
        $r2 = mysqli_stmt_get_result($stmtCSala);
        if ($row2 = mysqli_fetch_assoc($r2)) {
          $turmaConflito = $row2['nome_turma'] ?? 'outra turma';
          $nomeSalaMsg = $nomeSala ? "{$nomeSala}" : "Sala ID {$id_sala}";
          throw new Exception("Conflito de SALA: {$nomeSalaMsg} já está ocupada em {$dataStr} (turno {$turno}) pela turma '{$turmaConflito}'.");
        }
      }

      $restante = $carga_horaria - ($encontrosCriados * $horasPorEncontro);
      $horasDoDia = min($horasPorEncontro, $restante);

      $id_sala_insert = $id_sala;
      mysqli_stmt_bind_param($stmtE, "iiissi", $id_turma, $id_sala_insert, $id_professor, $dataStr, $turno, $horasDoDia);
      mysqli_stmt_execute($stmtE); // aqui o UNIQUE impede choque de professor e/ou sala

      $encontrosCriados++;
      $ultimaData = $dataStr;
    }
    $dt->modify('+1 day');
  }

  mysqli_commit($conexao);

  redirectWithSuccess("Turma cadastrada com sucesso! (Último encontro: {$ultimaData})");

} catch (Throwable $e) {
  mysqli_rollback($conexao);

  $msg = $e->getMessage();

  if (str_contains($msg, 'uk_prof_data_turno')) {
    redirectWithError("Conflito: o professor já está ocupado em alguma das datas geradas nesse turno.");
  } elseif (str_contains($msg, 'uk_sala_data_turno')) {
    redirectWithError("Conflito: a sala já está ocupada em alguma das datas geradas nesse turno.");
  } elseif (str_starts_with($msg, 'Conflito de')) {
    redirectWithError($msg);
  } else {
    redirectWithError("Erro ao cadastrar turma: {$msg}");
  }
}
