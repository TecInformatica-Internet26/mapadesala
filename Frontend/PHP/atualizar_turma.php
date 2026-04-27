<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "conexao.php";
require __DIR__ . "/FeriadosNacionais.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

/**
 * Verifica se uma data cai em feriado nacional (fixo ou móvel),
 * usando cache por ano pra não recalcular toda hora.
 */
function isHoliday(DateTime $dt, array &$cacheByYear): bool {
    $year = (int)$dt->format('Y');

    if (!isset($cacheByYear[$year])) {
        $feriados = FeriadosNacionais::getFeriadosAno($year);

        // vira um "set" (array associativo) pra lookup O(1)
        $set = [];
        foreach ($feriados as $f) {
            if (!empty($f['data'])) {
                $set[$f['data']] = true; // 'YYYY-mm-dd' => true
            }
        }
        $cacheByYear[$year] = $set;
    }

    return isset($cacheByYear[$year][$dt->format('Y-m-d')]);
}


/**
 * bind dinâmico seguro (por referência)
 * @param mysqli_stmt $stmt
 * @param string $types
 * @param array $params
 */
function bindParams(mysqli_stmt $stmt, string $types, array &$params): void {
    $refs = [];
    foreach ($params as $k => &$v) {
        $refs[$k] = &$v;
    }

    // A ORDEM CERTA: stmt, types, &param1, &param2...
    array_unshift($refs, $types);
    array_unshift($refs, $stmt);

    if (!call_user_func_array('mysqli_stmt_bind_param', $refs)) {
        throw new Exception("Erro ao bindar parâmetros (bindParams).");
    }
}

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithError("Método não permitido.");
}

// Validar campos obrigatórios
$campos_obrigatorios = ['id_turma', 'nome_turma', 'cod_turma', 'carga_horaria', 'turno'];
$campos_faltando = [];

foreach ($campos_obrigatorios as $campo) {
    if (!isset($_POST[$campo]) || trim((string)$_POST[$campo]) === '') {
        $campos_faltando[] = $campo;
    }
}

if (!empty($campos_faltando)) {
    redirectWithError("Campos obrigatórios faltando: " . implode(', ', $campos_faltando));
}

// Sanitização/normalização
$id_turma      = (int)$_POST['id_turma'];
$nome_turma    = trim((string)$_POST['nome_turma']);
$cod_turma     = trim((string)$_POST['cod_turma']);
$carga_horaria = (int)$_POST['carga_horaria'];
$turno         = trim((string)$_POST['turno']);
$data_recalculo = isset($_POST['data_recalculo']) ? trim((string)$_POST['data_recalculo']) : null;

// Validar turno
if (!in_array($turno, ['manha', 'tarde', 'noite'], true)) {
    redirectWithError("Turno inválido.");
}

// Campos opcionais
$id_professor = (isset($_POST['id_professor']) && $_POST['id_professor'] !== '') ? (int)$_POST['id_professor'] : 0; // 0 => NULL
$id_sala      = (isset($_POST['id_sala']) && $_POST['id_sala'] !== '') ? (int)$_POST['id_sala'] : 0; // 0 => NULL

// Dias da semana (bitmask)
$dias_semana = 0;
$dias_map = [
    'seg' => 1,
    'ter' => 2,
    'qua' => 4,
    'qui' => 8,
    'sex' => 16
];

if (!empty($_POST['dias_semana']) && is_array($_POST['dias_semana'])) {
    foreach ($_POST['dias_semana'] as $dia) {
        if (isset($dias_map[$dia])) {
            $dias_semana |= $dias_map[$dia];
        }
    }
} else {
    // Se não enviou dias, mantém os atuais
    $sql_get_dias = "SELECT dias_semana FROM turmas WHERE id_turma = ?";
    $stmt_get = mysqli_prepare($conexao, $sql_get_dias);
    if (!$stmt_get) {
        redirectWithError("Erro ao preparar busca de dias: " . mysqli_error($conexao));
    }
    mysqli_stmt_bind_param($stmt_get, 'i', $id_turma);
    mysqli_stmt_execute($stmt_get);
    $result_get = mysqli_stmt_get_result($stmt_get);
    if ($row = mysqli_fetch_assoc($result_get)) {
        $dias_semana = (int)$row['dias_semana'];
    }
}

// Normalizar data_recalculo: se vazia, tratar como null
if ($data_recalculo !== null && $data_recalculo === '') {
    $data_recalculo = null;
}

// Transação
mysqli_begin_transaction($conexao);

try {
    // ---------- 1) Verificar conflitos (antes de mexer) ----------
    if (!empty($data_recalculo) && $dias_semana > 0) {

        // Converter bitmask para array (1=seg ... 5=sex)
        $dias_numeros_array = [];
        if ($dias_semana & 1)  $dias_numeros_array[] = 1;
        if ($dias_semana & 2)  $dias_numeros_array[] = 2;
        if ($dias_semana & 4)  $dias_numeros_array[] = 3;
        if ($dias_semana & 8)  $dias_numeros_array[] = 4;
        if ($dias_semana & 16) $dias_numeros_array[] = 5;

        if (!empty($dias_numeros_array)) {
            $horas_por_encontro = ($turno === 'noite') ? 3 : 4;
            $total_encontros = (int)ceil($carga_horaria / $horas_por_encontro);

            // Gerar datas a verificar
            $datas_a_verificar = [];
            $data_atual = new DateTime($data_recalculo);
            $count = 0;
            $max_iteracoes = 365 * 3; // trava segurança

            $feriadosCache = []; // cache por ano

            while (count($datas_a_verificar) < $total_encontros && $count < $max_iteracoes) {
                $dia_semana_numero = (int)$data_atual->format('N'); // 1..7

                if (in_array($dia_semana_numero, $dias_numeros_array, true)) {
                    // PULA feriado: não entra na lista de verificação
                    if (!isHoliday($data_atual, $feriadosCache)) {
                        $datas_a_verificar[] = $data_atual->format('Y-m-d');
                    }
                }

                $data_atual->modify('+1 day');
                $count++;
            }


            if (!empty($datas_a_verificar)) {
                $placeholders = implode(',', array_fill(0, count($datas_a_verificar), '?'));
                $typesDates = str_repeat('s', count($datas_a_verificar));

                // Conflito professor (se tiver professor)
                if ($id_professor > 0) {
                    $sql_conflito_prof = "
                        SELECT DISTINCT te.data, t.nome_turma
                        FROM turma_encontros te
                        JOIN turmas t ON t.id_turma = te.id_turma
                        WHERE te.id_professor = ?
                          AND te.turno = ?
                          AND te.status = 'marcado'
                          AND te.data IN ($placeholders)
                          AND te.id_turma != ?
                    ";
                    $stmt_conflito = mysqli_prepare($conexao, $sql_conflito_prof);
                    if (!$stmt_conflito) {
                        throw new Exception("Erro prepare conflito professor: " . mysqli_error($conexao));
                    }

                    $bindTypes = "is" . $typesDates . "i";
                    $params = [];
                    $params[] = $id_professor;
                    $params[] = $turno;
                    foreach ($datas_a_verificar as $d) $params[] = $d;
                    $params[] = $id_turma;

                    bindParams($stmt_conflito, $bindTypes, $params);
                    mysqli_stmt_execute($stmt_conflito);
                    $result_conflito = mysqli_stmt_get_result($stmt_conflito);

                    $conflitos_prof = [];
                    while ($row = mysqli_fetch_assoc($result_conflito)) {
                        $conflitos_prof[] = $row;
                    }

                    if (!empty($conflitos_prof)) {
                        $itens = [];
                        foreach ($conflitos_prof as $c) {
                            $itens[] = ($c['data'] ?? '?') . " (" . ($c['nome_turma'] ?? 'turma') . ")";
                        }
                        throw new Exception(
                            "Conflito de PROFESSOR: o professor selecionado já está ocupado no turno '{$turno}' em: " .
                            implode(', ', $itens)
                        );
                    }
                }

                // Conflito sala (se tiver sala)
                if ($id_sala > 0) {
                    $sql_conflito_sala = "
                        SELECT DISTINCT te.data, t.nome_turma
                        FROM turma_encontros te
                        JOIN turmas t ON t.id_turma = te.id_turma
                        WHERE te.id_sala = ?
                          AND te.turno = ?
                          AND te.status = 'marcado'
                          AND te.data IN ($placeholders)
                          AND te.id_turma != ?
                    ";
                    $stmt_conflito_sala = mysqli_prepare($conexao, $sql_conflito_sala);
                    if (!$stmt_conflito_sala) {
                        throw new Exception("Erro prepare conflito sala: " . mysqli_error($conexao));
                    }

                    $bindTypes2 = "is" . $typesDates . "i";
                    $params2 = [];
                    $params2[] = $id_sala;
                    $params2[] = $turno;
                    foreach ($datas_a_verificar as $d) $params2[] = $d;
                    $params2[] = $id_turma;

                    bindParams($stmt_conflito_sala, $bindTypes2, $params2);
                    mysqli_stmt_execute($stmt_conflito_sala);
                    $result_conflito_sala = mysqli_stmt_get_result($stmt_conflito_sala);

                    $conflitos_sala = [];
                    while ($row = mysqli_fetch_assoc($result_conflito_sala)) {
                        $conflitos_sala[] = $row;
                    }

                    if (!empty($conflitos_sala)) {
                        $itens = [];
                        foreach ($conflitos_sala as $c) {
                            $itens[] = ($c['data'] ?? '?') . " (" . ($c['nome_turma'] ?? 'turma') . ")";
                        }
                        throw new Exception(
                            "Conflito de SALA: a sala selecionada já está ocupada no turno '{$turno}' em: " .
                            implode(', ', $itens)
                        );
                    }
                }
            }
        }
    }

    // ---------- 2) Atualizar dados da turma ----------
    // NULLIF(?,0) permite mandar 0 e virar NULL no banco.
    $sql_update = "UPDATE turmas SET
        nome_turma = ?,
        cod_turma = ?,
        carga_horaria = ?,
        turno = ?,
        dias_semana = ?,
        id_professor = NULLIF(?, 0),
        id_sala = NULLIF(?, 0)
        WHERE id_turma = ?";

    $stmt_update = mysqli_prepare($conexao, $sql_update);
    if (!$stmt_update) {
        throw new Exception("Erro prepare update: " . mysqli_error($conexao));
    }

    mysqli_stmt_bind_param(
        $stmt_update,
        'ssisiiii',
        $nome_turma,
        $cod_turma,
        $carga_horaria,
        $turno,
        $dias_semana,
        $id_professor,
        $id_sala,
        $id_turma
    );

    if (!mysqli_stmt_execute($stmt_update)) {
        throw new Exception("Erro ao executar update: " . mysqli_stmt_error($stmt_update));
    }

    // ---------- 3) Recalcular encontros (se solicitado) ----------
    if (!empty($data_recalculo) && $dias_semana > 0) {

        // Buscar encontros futuros marcados dessa turma a partir da data_recalculo
        $sql_verificar_encontros = "
            SELECT id_encontro, data
            FROM turma_encontros
            WHERE id_turma = ?
              AND data >= ?
              AND status = 'marcado'
            ORDER BY data
        ";
        $stmt_verificar = mysqli_prepare($conexao, $sql_verificar_encontros);
        if (!$stmt_verificar) {
            throw new Exception("Erro prepare verificar encontros: " . mysqli_error($conexao));
        }

        mysqli_stmt_bind_param($stmt_verificar, 'is', $id_turma, $data_recalculo);
        mysqli_stmt_execute($stmt_verificar);
        $result_verificar = mysqli_stmt_get_result($stmt_verificar);

        // Map: data => id_encontro (existentes marcados)
        $encontros_existentes = [];
        while ($row = mysqli_fetch_assoc($result_verificar)) {
            $encontros_existentes[$row['data']] = (int)$row['id_encontro'];
        }

        // Converter bitmask para array (1..5)
        $dias_numeros_array = [];
        if ($dias_semana & 1)  $dias_numeros_array[] = 1;
        if ($dias_semana & 2)  $dias_numeros_array[] = 2;
        if ($dias_semana & 4)  $dias_numeros_array[] = 3;
        if ($dias_semana & 8)  $dias_numeros_array[] = 4;
        if ($dias_semana & 16) $dias_numeros_array[] = 5;

        $horas_por_encontro = ($turno === 'noite') ? 3 : 4;
        $total_encontros = (int)ceil($carga_horaria / $horas_por_encontro);

        // Gerar datas novas
        $datas_geradas = [];
        $data_atual = new DateTime($data_recalculo);
        $count = 0;
        $max_iteracoes = 365 * 3;

        $feriadosCache = []; // cache por ano

        while (count($datas_geradas) < $total_encontros && $count < $max_iteracoes) {
            $dia_semana_numero = (int)$data_atual->format('N');

            if (in_array($dia_semana_numero, $dias_numeros_array, true)) {
                // PULA feriado: não gera encontro no feriado (vai "prolongar" o curso)
                if (!isHoliday($data_atual, $feriadosCache)) {
                    $datas_geradas[] = $data_atual->format('Y-m-d');
                }
            }

            $data_atual->modify('+1 day');
            $count++;
        }


        if (count($datas_geradas) === 0) {
            throw new Exception("Não foi possível gerar datas com os dias/turno informados.");
        }

        // IMPORTANTÍSSIMO: guardar as datas que já tinham encontro "marcado" e foram atualizadas
        $datas_ja_existiam = [];

        // 3.1 Atualizar encontros existentes nas mesmas datas (e remover da lista de cancelamento)
        $sql_atualizar_encontro = "
            UPDATE turma_encontros
            SET id_sala = NULLIF(?, 0),
                id_professor = NULLIF(?, 0),
                turno = ?,
                horas = ?
            WHERE id_encontro = ?
        ";
        $stmt_atualizar = mysqli_prepare($conexao, $sql_atualizar_encontro);
        if (!$stmt_atualizar) {
            throw new Exception("Erro prepare atualizar encontro: " . mysqli_error($conexao));
        }

        foreach ($datas_geradas as $i => $data_str) {
            // horas: último encontro pode ter horas diferentes
            $horas = ($i === $total_encontros - 1)
                ? max(1, $carga_horaria - (($total_encontros - 1) * $horas_por_encontro))
                : $horas_por_encontro;

            if (isset($encontros_existentes[$data_str])) {
                $id_encontro_existente = $encontros_existentes[$data_str];

                // marca como "já existia"
                $datas_ja_existiam[$data_str] = true;

                mysqli_stmt_bind_param(
                    $stmt_atualizar,
                    'iisii',
                    $id_sala,
                    $id_professor,
                    $turno,
                    $horas,
                    $id_encontro_existente
                );

                if (!mysqli_stmt_execute($stmt_atualizar)) {
                    throw new Exception("Erro ao atualizar encontro existente: " . mysqli_stmt_error($stmt_atualizar));
                }

                // remove para sobrar apenas os que serão cancelados
                unset($encontros_existentes[$data_str]);
            }
        }

        // 3.2 Cancelar encontros que sobraram (existiam, mas não batem mais com as novas datas)
        if (!empty($encontros_existentes)) {
            $ids_para_cancelar = array_values($encontros_existentes);
            $ids_para_cancelar = array_map('intval', $ids_para_cancelar);
            $ids_str = implode(',', $ids_para_cancelar);
            $sql_cancelar = "UPDATE turma_encontros SET status = 'cancelado' WHERE id_encontro IN ($ids_str)";
            if (!mysqli_query($conexao, $sql_cancelar)) {
                throw new Exception("Erro ao cancelar encontros: " . mysqli_error($conexao));
            }
        }

        // 3.3 Criar encontros para datas que não existiam antes
        // (aqui é onde antes você duplicava e quebrava tudo)
        $sql_verificar_cancelado = "
            SELECT id_encontro
            FROM turma_encontros
            WHERE id_turma = ?
              AND data = ?
              AND status = 'cancelado'
            LIMIT 1
        ";
        $stmt_verificar_cancelado = mysqli_prepare($conexao, $sql_verificar_cancelado);
        if (!$stmt_verificar_cancelado) {
            throw new Exception("Erro prepare verificar cancelado: " . mysqli_error($conexao));
        }

        $sql_reusar_cancelado = "
            UPDATE turma_encontros
            SET id_sala = NULLIF(?, 0),
                id_professor = NULLIF(?, 0),
                turno = ?,
                horas = ?,
                status = 'marcado'
            WHERE id_encontro = ?
        ";
        $stmt_reusar_cancelado = mysqli_prepare($conexao, $sql_reusar_cancelado);
        if (!$stmt_reusar_cancelado) {
            throw new Exception("Erro prepare reusar cancelado: " . mysqli_error($conexao));
        }

        $sql_inserir = "
            INSERT INTO turma_encontros
                (id_turma, id_sala, id_professor, data, turno, horas, status)
            VALUES
                (?, NULLIF(?,0), NULLIF(?,0), ?, ?, ?, 'marcado')
        ";
        $stmt_inserir = mysqli_prepare($conexao, $sql_inserir);
        if (!$stmt_inserir) {
            throw new Exception("Erro prepare inserir encontro: " . mysqli_error($conexao));
        }

        foreach ($datas_geradas as $i => $data_str) {
            // se já existia e foi atualizado, NÃO inserir
            if (isset($datas_ja_existiam[$data_str])) {
                continue;
            }

            $horas = ($i === $total_encontros - 1)
                ? max(1, $carga_horaria - (($total_encontros - 1) * $horas_por_encontro))
                : $horas_por_encontro;

            // tentar reusar cancelado
            mysqli_stmt_bind_param($stmt_verificar_cancelado, 'is', $id_turma, $data_str);
            mysqli_stmt_execute($stmt_verificar_cancelado);
            $res_cancelado = mysqli_stmt_get_result($stmt_verificar_cancelado);

            if ($row_cancelado = mysqli_fetch_assoc($res_cancelado)) {
                $id_encontro_cancelado = (int)$row_cancelado['id_encontro'];

                mysqli_stmt_bind_param(
                    $stmt_reusar_cancelado,
                    'iisii',
                    $id_sala,
                    $id_professor,
                    $turno,
                    $horas,
                    $id_encontro_cancelado
                );
                if (!mysqli_stmt_execute($stmt_reusar_cancelado)) {
                    throw new Exception("Erro ao reativar encontro cancelado: " . mysqli_stmt_error($stmt_reusar_cancelado));
                }
            } else {
                // inserir novo
                mysqli_stmt_bind_param(
                    $stmt_inserir,
                    'iiissi',
                    $id_turma,
                    $id_sala,
                    $id_professor,
                    $data_str,
                    $turno,
                    $horas
                );

                if (!mysqli_stmt_execute($stmt_inserir)) {
                    // se der duplicata, provavelmente tem conflito de UNIQUE/índice
                    if (mysqli_errno($conexao) == 1062) {
                        throw new Exception(
                            "Conflito detectado: a data $data_str já existe/está ocupada. " .
                            "Escolha outra data de recálculo ou ajuste os dias."
                        );
                    }
                    throw new Exception("Erro ao inserir encontro: " . mysqli_stmt_error($stmt_inserir));
                }
            }
        }
    }

    mysqli_commit($conexao);
    redirectWithSuccess("Turma atualizada com sucesso!");

} catch (Exception $e) {
    mysqli_rollback($conexao);
    redirectWithError("Erro ao atualizar turma: " . $e->getMessage());
}
