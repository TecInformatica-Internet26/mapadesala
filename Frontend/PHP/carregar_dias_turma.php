<?php
require "conexao.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id_turma']) || !is_numeric($_GET['id_turma'])) {
    echo json_encode(['success' => false, 'error' => 'ID da turma inválido']);
    exit;
}

$id_turma = (int)$_GET['id_turma'];

$sql = "SELECT dias_semana, nome_turma FROM turmas WHERE id_turma = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id_turma);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'success' => true,
        'dias_semana' => (int)$row['dias_semana'],
        'nome_turma' => $row['nome_turma']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Turma não encontrada']);
}
?>