<?php
// Debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "conexao.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = $_POST["email"] ?? '';
    $senha = $_POST["senha"] ?? '';

    if (empty($email) || empty($senha)) {
        header("Location: ../index.html?erro=campos");
        exit;
    }

    $sql = "SELECT id_usuario, senha FROM usuarios WHERE email = ?";
    $stmt = mysqli_prepare($conexao, $sql);

    if (!$stmt) {
        header("Location: ../index.html?erro=sistema");
        exit;
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $usuario = mysqli_fetch_assoc($resultado);

    if ($usuario) {
        if (password_verify($senha, $usuario["senha"])) {

            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['email'] = $email;

            header("Location: ../Paginas/mapadesala.php");
            exit;

        } else {
            header("Location: ../../index.html?erro=senha");
            exit;
        }
    } else {
        header("Location: ../../index.html?erro=email");
        exit;
    }
}
?>