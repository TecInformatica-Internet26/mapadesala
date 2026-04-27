<?php
$servidor = "localhost";
$usuario  = "root";
$senha    = "root"; 
$dbname   = "mapa_sala";

$conexao = mysqli_connect($servidor, $usuario, $senha, $dbname);

if (!$conexao) {
    die("Erro na conexão: " . mysqli_connect_error());
}
?>