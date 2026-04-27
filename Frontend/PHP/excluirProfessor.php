<?php
require "conexao.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $id = (int)($_POST["id_professor"] ?? 0);
  if ($id <= 0) {
    header("Location: ../Paginas/professores.php?status=erro");
    exit;
  }

  // Se existir foto, apaga o arquivo também
  $foto = '';
  $chk = $conexao->query("SHOW COLUMNS FROM professores LIKE 'foto'");
  if ($chk && $chk->num_rows > 0) {
    $stf = $conexao->prepare("SELECT foto FROM professores WHERE id_professor = ? LIMIT 1");
    $stf->bind_param("i", $id);
    $stf->execute();
    $res = $stf->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
      $foto = trim((string)($row['foto'] ?? ''));
    }
    $stf->close();
  }

  $stmt = $conexao->prepare("DELETE FROM professores WHERE id_professor = ?");
  $stmt->bind_param("i", $id);

  if ($stmt->execute()) {
    if ($foto) {
      $path = __DIR__ . '/../IMG/professores/' . $foto;
      if (is_file($path)) @unlink($path);
    }
    header("Location: ../Paginas/professores.php?status=excluido");
    exit;
  }

  header("Location: ../Paginas/professores.php?status=erro");
  exit;
}
