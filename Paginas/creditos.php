<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Créditos - Senac MA</title>

  <link rel="icon" type="image/x-icon" href="../IMG/favicon.png">
  <link rel="stylesheet" href="../CSS/padrao.css" />
  <link rel="stylesheet" href="../CSS/creditos.css" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <script src="../JS/padrao.js" defer></script>
</head>

<body>
  <!-- TOPBAR PADRÃO -->
  <header class="barra-topo">
    <button class="botao-menu" id="botao-menu" aria-label="Abrir menu" aria-expanded="false">☰</button>

    <div class="logo-topo">
      <img src="../IMG/senac_logo_branco.png" alt="Senac" />
    </div>

    <button class="botao-usuario" id="botao-usuario" aria-label="Usuário" aria-expanded="false"><img src="../IMG/usuarioIcon.png" alt="Usuário"></button>
  </header>

  <!-- SIDEBAR PADRÃO -->
  <aside class="barra-lateral">
    <nav class="nav-lateral">
      <ul>
        <li class="item-nav"><a href="mapadesala.php" class="conteudo-barra-lateral">Mapa de Salas</a></li>
        <li class="item-nav"><a href="professores.php" class="conteudo-barra-lateral">Professores</a></li>
        <li class="item-nav"><a href="salas.php" class="conteudo-barra-lateral">Salas</a></li>
        <li class="item-nav"><a href="turmas.php" class="conteudo-barra-lateral">Turmas</a></li>
        <li class="item-nav"><a href="relatorios.php" class="conteudo-barra-lateral">Relatórios</a></li>
        <?php if (isset($_SESSION['id_usuario']) && (int)$_SESSION['id_usuario'] === 1): ?>
          <li class="item-nav<?php echo (strpos(strtolower($_SERVER['PHP_SELF']), 'adm.php') !== false) ? ' ativo' : ''; ?>"><a href="adm.php" class="conteudo-barra-lateral">Administração</a></li>
        <?php endif; ?>

        <li class="item-nav ativo"><a href="creditos.php" class="conteudo-barra-lateral">Créditos</a></li>
      </ul>
    </nav>

    <div class="rodape-lateral">
      <div class="relogio-lateral" id="relogio-lateral">--:--</div>
      <div class="creditos-lateral">Desenvolvido pela Turma Técnico de Informatica para a Internet</div>
    </div>
  </aside>

  <!-- Overlay mobile -->
  <div class="sobreposicao-mobile"></div>

  <!-- CONTEÚDO -->
  <main class="conteudo-principal">
    <section class="pagina-creditos">
      <header class="cabecalho-pagina">
        <h1>Créditos</h1>
      </header>

      <div class="creditos-grid">
        <article class="creditos-card">
          <h2 class="creditos-titulo">Sobre o projeto</h2>
          <p class="creditos-texto">
            Este projeto foi desenvolvido no âmbito do curso Técnico em Informática para Internet, turma 2025.8.20,
            como parte das atividades acadêmicas voltadas ao aprimoramento técnico, profissional e colaborativo dos estudantes.
          </p>
        </article>

        <article class="creditos-card">
          <h2 class="creditos-titulo">Equipe de Desenvolvimento</h2>
          <p class="creditos-texto">
            O trabalho apresentado é resultado do esforço conjunto, da responsabilidade individual e do comprometimento coletivo da equipe.
          </p>

          <div class="nomes-equipe">
            <!-- FULL STACK (A-Z) -->
            <span class="nome-pill">
              <span class="nome">Guilherme Alexandre</span>
              <span class="funcao funcao--full">Full Stack</span>
            </span>

            <span class="nome-pill">
              <span class="nome">Hitallo Felix</span>
              <span class="funcao funcao--full">Full Stack</span>
            </span>

            <span class="nome-pill">
              <span class="nome">Isaac Sousa</span>
              <span class="funcao funcao--full">Full Stack</span>
            </span>

            <span class="nome-pill">
              <span class="nome">João Victor Frazão</span>
              <span class="funcao funcao--full">Full Stack</span>
            </span>

            <span class="nome-pill">
              <span class="nome">Miquéias Martins</span>
              <span class="funcao funcao--full">Full Stack</span>
            </span>

            <!-- FRONT-END (A-Z) -->
            <span class="nome-pill">
              <span class="nome">Breno Otaviano</span>
              <span class="funcao funcao--front">Front-end</span>
            </span>

            <span class="nome-pill">
              <span class="nome">Emanuella Aguiar</span>
              <span class="funcao funcao--front">Front-end</span>
            </span>

            <span class="nome-pill">
              <span class="nome">Hernandes dos Santos</span>
              <span class="funcao funcao--front">Front-end</span>
            </span>

            <span class="nome-pill">
              <span class="nome">Jardel Resende</span>
              <span class="funcao funcao--front">Front-end</span>
            </span>

            <span class="nome-pill">
              <span class="nome">Karolynne Andrade</span>
              <span class="funcao funcao--front">Front-end</span>
            </span>

            <span class="nome-pill">
              <span class="nome">Laura da Silva</span>
              <span class="funcao funcao--front">Front-end</span>
            </span>

            <span class="nome-pill">
              <span class="nome">Paulo Otavio Neves</span>
              <span class="funcao funcao--front">Front-end</span>
            </span>
          </div>

        </article>

        <article class="creditos-card destaque">
          <h2 class="creditos-titulo">Orientador</h2>
          <p class="creditos-destaque">Prof. Vinícius Aires</p>

          <div class="tech-stack">
            <div class="tech-titulo">Tecnologias Utilizadas</div>
            <div class="tech-badges">
              <span class="tech-badge">HTML</span>
              <span class="tech-badge">CSS</span>
              <span class="tech-badge">PHP</span>
              <span class="tech-badge">JavaScript</span>
              <span class="tech-badge">MySQL</span>
            </div>
          </div>
        </article>

        <article class="creditos-card rodape-projeto">
          <div class="rodape-linha"><strong>Sistema de Gerenciamento de Salas - SENAC-MA</strong></div>
          <div class="rodape-linha">Versão 1.0.0 | 2026</div>
          <div class="rodape-linha">Desenvolvido por Turma Técnico de Informatica para a Internet 2025.8.20</div>
        </article>
      </div>
    </section>
  </main>
</body>
</html>
