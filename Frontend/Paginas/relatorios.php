<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . "/../PHP/conexao.php";

// Professores
$professores = [];
$qp = mysqli_query($conexao, "SELECT id_professor, nome FROM professores ORDER BY nome ASC");
if ($qp) {
  while ($r = mysqli_fetch_assoc($qp)) {
    $professores[] = ["id" => (int)$r["id_professor"], "nome" => $r["nome"]];
  }
}

// Salas
$salas = [];
$qs = mysqli_query($conexao, "SELECT id_sala, nome_sala FROM salas ORDER BY nome_sala ASC");
if ($qs) {
  while ($r = mysqli_fetch_assoc($qs)) {
    $salas[] = ["id" => (int)$r["id_sala"], "nome" => $r["nome_sala"]];
  }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Relatórios - Senac MA</title>
  <link rel="icon" type="image/x-icon" href="../IMG/favicon.png">
  <link rel="stylesheet" href="../CSS/padrao.css" />
  <link rel="stylesheet" href="../CSS/relatorios.css" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <script src="../JS/relatorios.js" defer></script>
  <script src="../JS/padrao.js" defer></script>
</head>

<body>
  <!-- TOPBAR -->
  <header class="barra-topo">
    <button class="botao-menu" id="botao-menu" aria-label="Abrir menu" aria-expanded="false">☰</button>
    <div class="logo-topo">
      <img src="../IMG/senac_logo_branco.png" alt="Senac" />
    </div>
    <button class="botao-usuario" id="botao-usuario" aria-label="Usuário" aria-expanded="false"><img src="../IMG/usuarioIcon.png" alt="Usuário"></button>
  </header>

  <!-- SIDEBAR -->
  <aside class="barra-lateral">
    <nav class="nav-lateral">
      <ul>
        <li class="item-nav"><a href="mapadesala.php" class="conteudo-barra-lateral">Mapa de Salas</a></li>
        <li class="item-nav"><a href="professores.php" class="conteudo-barra-lateral">Professores</a></li>
        <li class="item-nav"><a href="salas.php" class="conteudo-barra-lateral">Salas</a></li>
        <li class="item-nav"><a href="turmas.php" class="conteudo-barra-lateral">Turmas</a></li>
        <li class="item-nav ativo"><a href="relatorios.php" class="conteudo-barra-lateral">Relatórios</a></li>
        <?php if (isset($_SESSION['id_usuario']) && (int)$_SESSION['id_usuario'] === 1): ?>
          <li class="item-nav<?php echo (strpos(strtolower($_SERVER['PHP_SELF']), 'adm.php') !== false) ? ' ativo' : ''; ?>"><a href="adm.php" class="conteudo-barra-lateral">Administração</a></li>
        <?php endif; ?>
        <li class="item-nav"><a href="creditos.php" class="conteudo-barra-lateral">Créditos</a></li>
      </ul>
    </nav>

    <div class="rodape-lateral">
      <div class="relogio-lateral" id="relogio-lateral">--:--</div>
      <div class="creditos-lateral">Desenvolvido pela Turma Técnico de Informatica para a Internet</div>
    </div>
  </aside>

  <div class="sobreposicao-mobile"></div>

  <!-- CONTEÚDO -->
  <main class="conteudo-principal">
    <section class="relatorios">
      <header class="cabecalho-relatorios">
        <div class="bloco-titulo">
          <h1>Relatórios</h1>
          <p class="subtitulo">Gere relatórios para visualização e PDF.</p>
        </div>
      </header>

      <div class="card-filtros">
        <div class="grid-filtros">
          <div class="campo">
            <label for="tipo">Tipo de relatório</label>
            <select id="tipo">
              <option value="turmas_prof">Turmas do professor</option>
              <option value="ocupacao_sala">Ocupação de sala</option>
              <option value="agenda_sala">Imprimir sala (dia/semana)</option>
              <option value="disponibilidade_sala">Disponibilidade da sala</option>
            </select>
          </div>

          <div class="campo" id="campo-professor">
            <label for="professor">Professor</label>
            <select id="professor">
              <option value="">Selecione...</option>
              <?php foreach ($professores as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="campo" id="campo-sala">
            <label for="sala">Sala</label>
            <select id="sala">
              <option value="">Selecione...</option>
              <?php foreach ($salas as $s): ?>
                <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['nome']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="acoes">
            <button class="btn" id="btn-gerar">Gerar</button>
            <button class="btn secundario" id="btn-pdf" type="button" title="Salvar como PDF" disabled>Salvar como PDF</button>
          </div>
        </div>

        <div class="atalhos">
          <button class="chip" data-range="today" type="button">Hoje</button>
          <button class="chip" data-range="week" type="button">Esta semana</button>
          <button class="chip" data-range="month" type="button">Este mês</button>

          <div class="atalho-data" title="Escolha a data base para Hoje/Semana/Mês">
            <label for="base-date">Data</label>
            <input id="base-date" type="date" />
          </div>
        </div>
      </div>

      <div class="card-resultado" id="area-resultado">
        <div class="resultado-topo">
          <h2 id="titulo-relatorio">Resultado</h2>
          <div class="status" id="status">Selecione os filtros e clique em “Gerar”.</div>
        </div>
        <div class="tabela-wrap" id="tabela-wrap"></div>
      </div>
    </section>
  </main>
</body>
</html>
