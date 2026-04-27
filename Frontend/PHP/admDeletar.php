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

$id = $_POST['id_usuario'] ?? null;

if (!$id) {
    header("Location: ../Paginas/adm.php");
    exit;
}

/* Evita apagar o admin */
if ($id == 1) {
    header("Location: ../Paginas/adm.php");
    exit;
}

$stmt = $conexao->prepare(
    "DELETE FROM usuarios WHERE id_usuario=?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: ../Paginas/adm.php");
exit;
