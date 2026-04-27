<?php
  // Sessão é necessária aqui para mostrar corretamente o link de Administração no aside
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  require "../PHP/conexao.php";

  // Garante que a coluna de foto exista (não mexe em sessão; só evita erro no SELECT)
  $hasFotoCol = false;
  $chk = $conexao->query("SHOW COLUMNS FROM professores LIKE 'foto'");
  if ($chk && $chk->num_rows > 0) {
    $hasFotoCol = true;
  } else {
    // Se não existir, tenta criar. Se falhar por permissão, seguimos sem foto.
    @$conexao->query("ALTER TABLE professores ADD COLUMN foto VARCHAR(255) NULL");
    $chk2 = $conexao->query("SHOW COLUMNS FROM professores LIKE 'foto'");
    if ($chk2 && $chk2->num_rows > 0) $hasFotoCol = true;
  }

  $sql = "
    SELECT
      p.id_professor,
      p.nome,
      p.formacao,
      p.telefone,
      p.email,
      p.cursos_complementares" . ($hasFotoCol ? ",\n      p.foto" : "") . ",
      GROUP_CONCAT(DISTINCT t.nome_turma ORDER BY t.nome_turma SEPARATOR ', ') AS turmas,
      GROUP_CONCAT(DISTINCT t.turno ORDER BY t.turno SEPARATOR ', ') AS turnos
    FROM professores p
    LEFT JOIN turmas t ON t.id_professor = p.id_professor
    GROUP BY
      p.id_professor, p.nome, p.formacao, p.telefone, p.email, p.cursos_complementares" . ($hasFotoCol ? ", p.foto" : "") . "
  ";

  $result = $conexao->query($sql);

  function turnoBonito($turnosCsv) {
    if (!$turnosCsv) return "—";
    $map = [
      "manha" => "Manhã",
      "tarde" => "Tarde",
      "noite" => "Noite",
    ];
    $itens = array_map("trim", explode(",", $turnosCsv));
    $itens = array_values(array_unique(array_filter($itens)));
    $itens = array_map(fn($t) => $map[$t] ?? $t, $itens);
    return $itens ? implode(", ", $itens) : "—";
  }

  function textoOuTraco($txt) {
    $t = trim((string)$txt);
    return $t === "" ? "—" : $t;
  }

  $erroMsg = isset($_GET["erro"]) ? (string)$_GET["erro"] : "";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Professores - Senac MA</title>
  <link rel="icon" type="image/x-icon" href="../IMG/favicon.png">
  <link rel="stylesheet" href="../CSS/professores.css" />
  <link rel="stylesheet" href="../CSS/padrao.css" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <script src="../JS/padrao.js" defer></script>
  <script src="../JS/professores.js?v=<?= filemtime(__DIR__ . '/../JS/professores.js') ?>" defer></script>

  <style>
    .modal { width: 100%; background: none; }

    /* fallback da inicial (caso seu CSS não tenha) */
    .avatar__fallback{
      width: 100%;
      height: 100%;
      display: grid;
      place-items: center;
      font-weight: 700;
      font-size: 18px;
      text-transform: uppercase;
      line-height: 1;
    }
  </style>
</head>

<body>
  <script>
    // Erro retornado do PHP (ex.: duplicidade). O JS decide como exibir.
    window.__PROF_ERRO__ = <?= json_encode($erroMsg) ?>;
  </script>

  <!-- TOPBAR PADRÃO -->
  <header class="barra-topo">
    <button class="botao-menu" id="botao-menu" aria-label="Abrir menu" aria-expanded="false">☰</button>

    <div class="logo-topo">
      <img src="../IMG/senac_logo_branco.png" alt="Senac" />
    </div>

    <button class="botao-usuario" id="botao-usuario" aria-label="Usuário" aria-expanded="false">
      <img src="../IMG/usuarioIcon.png" alt="Usuário">
    </button>
  </header>

  <!-- SIDEBAR PADRÃO -->
  <aside class="barra-lateral">
    <nav class="nav-lateral">
      <ul>
        <li class="item-nav"><a href="mapadesala.php" class="conteudo-barra-lateral">Mapa de Salas</a></li>
        <li class="item-nav ativo"><a href="professores.php" class="conteudo-barra-lateral">Professores</a></li>
        <li class="item-nav"><a href="salas.php" class="conteudo-barra-lateral">Salas</a></li>
        <li class="item-nav"><a href="turmas.php" class="conteudo-barra-lateral">Turmas</a></li>

        <li class="item-nav"><a href="relatorios.php" class="conteudo-barra-lateral">Relatórios</a></li>

        <?php if (isset($_SESSION['id_usuario']) && (int)$_SESSION['id_usuario'] === 1): ?>
          <li class="item-nav<?php echo (strpos(strtolower($_SERVER['PHP_SELF']), 'adm.php') !== false) ? ' ativo' : ''; ?>">
            <a href="adm.php" class="conteudo-barra-lateral">Administração</a>
          </li>
        <?php endif; ?>

        <li class="item-nav"><a href="creditos.php" class="conteudo-barra-lateral">Créditos</a></li>
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
    <section class="pagina-professores">
      <!-- TÍTULO + AÇÕES -->
      <div class="header-page">
        <h1>Professores</h1>

        <div class="actions-bar">
          <button class="btn-icon btn-add" title="Adicionar professor" id="btnAbrir">+</button>

          <!-- botão de filtro do padrão (já funciona via padrao.js) -->
          <button class="botao-icone botao-filtro" type="button" data-abrir-filtros title="Filtros">
            <img src="../IMG/filtro.png" alt="Filtro" style="width:22px;height:22px;">
          </button>
        </div>
      </div>

      <div class="cards" id="listaProfessores">
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $turmas = textoOuTraco($row["turmas"] ?? "");
            $turnos = turnoBonito($row["turnos"] ?? "");
            $formacao = textoOuTraco($row["formacao"] ?? "");
            $compl = textoOuTraco($row["cursos_complementares"] ?? "");
            $foto = $hasFotoCol ? trim((string)($row["foto"] ?? "")) : "";

            // Inicial do nome (UTF-8 / acentos)
            $nomeRaw = trim((string)($row['nome'] ?? ''));
            if ($nomeRaw === '') {
              $inicial = '?';
            } else {
              if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
                $inicial = mb_strtoupper(mb_substr($nomeRaw, 0, 1, 'UTF-8'), 'UTF-8');
              } else {
                $inicial = strtoupper(substr($nomeRaw, 0, 1));
              }
            }
          ?>

          <div class="card"
            data-id="<?= (int)$row['id_professor'] ?>"
            data-nome="<?= htmlspecialchars($row['nome'] ?? '') ?>"
            data-formacao="<?= htmlspecialchars($row['formacao'] ?? '') ?>"
            data-telefone="<?= htmlspecialchars($row['telefone'] ?? '') ?>"
            data-email="<?= htmlspecialchars($row['email'] ?? '') ?>"
            data-cursos="<?= htmlspecialchars($row['cursos_complementares'] ?? '') ?>"
            data-foto="<?= htmlspecialchars($foto) ?>"
          >
            <!-- Cabeçalho do card -->
            <div class="card-header">
              <?php $temFoto = ($foto !== "" && file_exists(__DIR__ . "/../IMG/professores/" . $foto)); ?>

              <button class="avatar <?= $temFoto ? '' : 'is-fallback' ?>" type="button" title="Ver foto" aria-label="Ver foto">
                <?php if ($temFoto): ?>
                  <img src="../IMG/professores/<?= htmlspecialchars($foto) ?>" alt="Foto de <?= htmlspecialchars($row['nome']) ?>" />
                <?php else: ?>
                  <span class="avatar__fallback" aria-hidden="true"><?= htmlspecialchars($inicial) ?></span>
                <?php endif; ?>
              </button>


              <h3 class="professor-nome"><?= htmlspecialchars($row["nome"] ?? "") ?></h3>

              <div class="card-actions">
                <button class="icon-btn edit btn-edit" type="button" title="Editar professor">
                  <img src="../IMG/lapisIcon.png" alt="Editar">
                </button>

                <button type="button" class="icon-btn delete btn-delete" title="Excluir professor"
                  data-id="<?= (int)$row['id_professor'] ?>"
                  data-nome="<?= htmlspecialchars($row['nome'] ?? '') ?>"
                >
                  <img src="../IMG/lixeiraIcon.png" alt="excluir">
                </button>
              </div>
            </div>

            <div class="line"></div>

            <div class="info">
              <p class="linha-info"><strong>Formação:</strong> <?= htmlspecialchars($formacao) ?></p>
              <p class="linha-info"><strong>Área de Atuação:</strong> <?= htmlspecialchars($compl) ?></p>
              <p class="linha-info"><strong>Telefone:</strong> <?= htmlspecialchars(textoOuTraco($row["telefone"] ?? "")) ?></p>
              <p class="linha-info"><strong>Email:</strong> <?= htmlspecialchars(textoOuTraco($row["email"] ?? "")) ?></p>
              <p class="linha-info"><strong>Turma(s):</strong> <?= htmlspecialchars($turmas) ?></p>
              <p class="linha-info"><strong>Turno(s):</strong> <?= htmlspecialchars($turnos) ?></p>
            </div>
          </div>

        <?php endwhile; ?>
      </div>
    </section>
  </main>

  <!-- MODAL -->
  <div class="modal" id="meuModal" aria-hidden="true">
    <div class="modal__backdrop" data-close></div>

    <div class="modal__content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <header class="modal__header">
        <h2 id="modalTitle">Cadastrar Professor</h2>
        <button class="modal__close" aria-label="Fechar" data-close>×</button>
      </header>

      <div class="modal__body">
        <form action="../PHP/criarProfessor.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="idProfessor" id="idProfessor" value="">
          <input type="hidden" name="fotoAtual" id="fotoAtual" value="">

          <!-- Foto do professor (preview redondo, clicável) -->
          <div class="foto-box">
            <button type="button" class="foto-preview" id="fotoPreview" aria-label="Selecionar foto">
              <span class="foto-preview__txt">Adicionar foto</span>
            </button>
            <input type="file" name="fotoProfessor" id="inputFoto" accept="image/*" hidden>
            <input type="hidden" name="fotoCortada" id="fotoCortada" value="">
            <small class="foto-ajuda">Clique no círculo para escolher/alterar a foto</small>
          </div>

          <div class="inputs">
            <label for="nomeProfessor">Nome do Professor</label>
            <input type="text" name="nomeProfessor" class="nome_prof" id="nomeProfessor" placeholder="Digite o nome" required>
          </div>

          <div class="inputs">
            <label for="formacao">Formação</label>
            <input type="text" name="formacao" class="inputFormacao" id="inputFormacao" placeholder="Ex: Técnico em informática" required>
          </div>

          <div class="inputs">
            <label for="Telefone">Telefone</label>
            <input type="text" name="telefone" class="inputTel" id="inputTel" placeholder="(99) 9 9999-9999" maxlength="16" required>
          </div>

          <div class="inputs">
            <label for="email">Email</label>
            <input type="email" name="email" class="inputEmail" id="inputEmail" placeholder="Digite o email" required>
          </div>

          <div class="inputs">
            <label for="text">Área de Atuação</label>
            <input type="text" name="cursosCompl" class="inputCompl" id="inputCompl" placeholder="Ex: Beleza, Estética, Informática...">
          </div>

          <button type="submit" class="buttonCriar" id="btnSalvarProfessor">Criar</button>
          <small class="msg-erro" id="msgErroProfessor" aria-live="polite"></small>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL EXCLUIR -->
  <div class="modal" id="modalExcluir" aria-hidden="true">
    <div class="modal__backdrop" data-close-excluir></div>

    <div class="modal__content modal__content--pequeno" role="dialog" aria-modal="true" aria-labelledby="modalExcluirTitle">
      <header class="modal__header">
        <h2 id="modalExcluirTitle" class="titulo-perigo">⚠️ Confirmar Exclusão</h2>
        <button class="modal__close" type="button" aria-label="Fechar" data-close-excluir>×</button>
      </header>

      <div class="modal__body modal__body--center">
        <p>Tem certeza que deseja excluir o professor <strong id="nomeProfessorExcluir" class="nome-destaque"></strong>?</p>
        <p class="texto-aviso">Esta ação não pode ser desfeita.</p>

        <form action="../PHP/excluirProfessor.php" method="POST" class="form-excluir">
          <input type="hidden" name="id_professor" id="delete_prof_id">
          <div class="acoes-excluir">
            <button type="button" class="botao-secundario" data-close-excluir>Cancelar</button>
            <button type="submit" class="botao-perigo">Excluir</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- VISUALIZADOR DE FOTO -->
  <div class="foto-viewer" id="fotoViewer" aria-hidden="true">
    <div class="foto-viewer__backdrop" data-viewer-close></div>
    <div class="foto-viewer__content" role="dialog" aria-modal="true" aria-label="Foto do professor">
      <button class="foto-viewer__close" type="button" aria-label="Fechar" data-viewer-close>×</button>
      <img id="fotoViewerImg" alt="Foto do professor">
    </div>
  </div>

  <!-- Modal de recorte de foto (estilo Discord) -->
  <div class="crop-modal" id="cropModal" aria-hidden="true">
    <div class="crop-modal__backdrop" data-crop-close></div>

    <div class="crop-modal__content" role="dialog" aria-modal="true" aria-label="Editar imagem">
      <div class="crop-modal__header">
        <h3>Editar imagem</h3>
        <button type="button" class="crop-modal__close" data-crop-close aria-label="Fechar">✕</button>
      </div>

      <div class="crop-modal__body">
        <div class="crop-stage">
          <canvas id="cropCanvas" width="520" height="300"></canvas>
          <div class="crop-overlay" aria-hidden="true"></div>
        </div>

        <div class="crop-controls">
          <button type="button" class="crop-btn" id="btnCropRotate" title="Girar 90°">↻</button>
          <input type="range" id="cropZoom" min="1" max="3" step="0.01" value="1" aria-label="Zoom">
          <button type="button" class="crop-btn" id="btnCropReset">Redefinir</button>
        </div>
      </div>

      <div class="crop-modal__footer">
        <button type="button" class="btn-sec" data-crop-close>Cancelar</button>
        <button type="button" class="btn-pri" id="btnCropApply">Aplicar</button>
      </div>
    </div>
  </div>

  <!-- Visualizador simples da foto (clique na miniatura) -->
  <div class="photo-viewer" id="photoViewer" aria-hidden="true">
    <div class="photo-viewer__backdrop" data-viewer-close></div>
    <div class="photo-viewer__content">
      <button type="button" class="photo-viewer__close" data-viewer-close aria-label="Fechar">✕</button>
      <img id="photoViewerImg" alt="Foto do professor">
    </div>
  </div>
</body>
</html>
