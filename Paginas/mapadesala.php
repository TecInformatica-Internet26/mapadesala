<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mapa de Salas - Senac MA</title>
  <link rel="icon" type="image/x-icon" href="../IMG/favicon.png">
  <link rel="stylesheet" href="../CSS/padrao.css" />
  <link rel="stylesheet" href="../CSS/mapadesala.css" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <script src="../JS/mapadesala.api.js" defer></script>
  <script src="../JS/mapadesala.render.js" defer></script>
  <script src="../JS/mapadesala.core.js" defer></script>
  


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
        <li class="item-nav ativo"><a href="mapadesala.php" class="conteudo-barra-lateral">Mapa de Salas</a></li>
        <li class="item-nav"><a href="professores.php" class="conteudo-barra-lateral">Professores</a></li>
        <li class="item-nav"><a href="salas.php" class="conteudo-barra-lateral">Salas</a></li>
        <li class="item-nav"><a href="turmas.php" class="conteudo-barra-lateral">Turmas</a></li>
        <li class="item-nav"><a href="relatorios.php" class="conteudo-barra-lateral">Relatórios</a></li>
        <?php if (isset($_SESSION['id_usuario']) && (int)$_SESSION['id_usuario'] === 1): ?>
          <li class="item-nav<?php echo (strpos(strtolower($_SERVER['PHP_SELF']), 'adm.php') !== false) ? ' ativo' : ''; ?>"><a href="adm.php" class="conteudo-barra-lateral">Administração</a></li>
        <?php endif; ?>

         <li class="item-nav"><a href="creditos.php" class="conteudo-barra-lateral">Créditos</a></li>
      </ul>
    </nav>

    <div class="rodape-lateral">
      <div class="relogio-lateral" id="relogio-lateral">--:--</div>
      <div class="creditos-lateral">
        Desenvolvido pela Turma Técnico de Informatica para a Internet
      </div>
    </div>
  </aside>

  <!-- Overlay mobile (futuro) -->
  <div class="sobreposicao-mobile"></div>

  <!-- CONTEÚDO -->
  <main class="conteudo-principal">
    <section class="mapa-salas">
      <header class="cabecalho-mapa">
        <div class="bloco-titulo-mapa">
          <h1>Mapa de salas</h1>
          <div class="subtitulo-mapa" id="subtitulo-mapa"></div>
        </div>

        <div class="controles-mapa">
          <div class="filtro-periodo" role="tablist" aria-label="Visualização">
            <button class="botao-periodo ativo" data-view="day" role="tab" aria-selected="true">Dia</button>
            <button class="botao-periodo" data-view="week" role="tab" aria-selected="false">Semana</button>
            <button class="botao-periodo" data-view="month" role="tab" aria-selected="false">Mês</button>
            <button class="botao-periodo" data-view="year" role="tab" aria-selected="false">Ano</button>
          </div>

          <input
            type="date"
            id="mapa-data"
            class="mapa-data"
            aria-label="Escolher data"
          />

          <button class="botao-filtro" id="botao-filtro" aria-label="Filtros">
            <img src="../IMG/filtro.png" alt="Filtro" />
          </button>
        </div>
      </header>

      <!-- Stage dinâmico -->
      <div id="container-salas" class="palco-salas" aria-live="polite"></div>
    </section>
  </main>
</body>
</html>