<?php
require "./conexao.php";
session_start();

/* 🔒 Apenas admin */
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_usuario'] != 1) {
    header("Location: ../../index.html");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../Paginas/adm.php");
    exit;
}

$id    = $_POST['id_usuario'] ?? null;
$email = $_POST['email'] ?? null;
$senha = $_POST['senha'] ?? null;

if (!$id || !$email) {
    header("Location: ../Paginas/adm.php");
    exit;
}

if (!empty($senha)) {
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conexao->prepare(
        "UPDATE usuarios SET email=?, senha=? WHERE id_usuario=?"
    );
    $stmt->bind_param("ssi", $email, $hash, $id);
} else {
    $stmt = $conexao->prepare(
        "UPDATE usuarios SET email=? WHERE id_usuario=?"
    );
    $stmt->bind_param("si", $email, $id);
}

$stmt->execute();
$stmt->close();

header("Location: ../Paginas/adm.php");
exit;
