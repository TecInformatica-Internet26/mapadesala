<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Paginas/cadastro.html');
    exit;
}

$email  = $_POST['email']  ?? '';
$senha  = $_POST['senha']  ?? '';
$Csenha = $_POST['Csenha'] ?? '';

if (empty($email) || empty($senha) || empty($Csenha)) {
    header("Location: ../Paginas/cadastro.html?erro=campos");
    exit;
}

if ($senha !== $Csenha) {
    header("Location: ../Paginas/cadastro.html?erro=senhas");
    exit;
}

/* VERIFICAR SE EMAIL JÁ EXISTE */
$sqlCheck = "SELECT id_usuario FROM usuarios WHERE email = ?";
$stmtCheck = mysqli_prepare($conexao, $sqlCheck);

if (!$stmtCheck) {
    header("Location: ../Paginas/cadastro.html?erro=sistema");
    exit;
}

mysqli_stmt_bind_param($stmtCheck, "s", $email);
mysqli_stmt_execute($stmtCheck);
mysqli_stmt_store_result($stmtCheck);

if (mysqli_stmt_num_rows($stmtCheck) > 0) {
    header("Location: ../Paginas/cadastro.html?erro=email");
    exit;
}

/* HASH DA SENHA */
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

/* INSERT */
$sql = "INSERT INTO usuarios (email, senha) VALUES (?, ?)";
$stmt = mysqli_prepare($conexao, $sql);

if (!$stmt) {
    header("Location: ../Paginas/cadastro.html?erro=sistema");
    exit;
}

mysqli_stmt_bind_param($stmt, "ss", $email, $senhaHash);

if (mysqli_stmt_execute($stmt)) {
    header("Location: ../../index.html");
    exit;
} else {
    header("Location: ../Paginas/cadastro.html?erro=sistema");
    exit;
}
?>