<?php
require "../PHP/conexao.php";

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$toast_sucesso = $_SESSION['sucesso'] ?? null; unset($_SESSION['sucesso']);
$toast_erro = $_SESSION['erro'] ?? null; unset($_SESSION['erro']);

// SALAS
$salas = [];
$q1 = mysqli_query($conexao, "SELECT id_sala, nome_sala, capacidade FROM salas ORDER BY nome_sala ASC");
while ($row = mysqli_fetch_assoc($q1)) {
  $salas[] = $row;
}

// PROFESSORES
$professores = [];
$q2 = mysqli_query($conexao, "SELECT id_professor, nome, formacao FROM professores ORDER BY nome ASC");
while ($row = mysqli_fetch_assoc($q2)) {
  $professores[] = $row;
}

$sql = "
  SELECT
    t.id_turma,
    t.nome_turma,
    t.cod_turma,
    t.turno,    t.carga_horaria,

    
    t.id_professor, 
    t.id_sala,      

    p.nome AS professor_nome,
    s.nome_sala AS sala_nome,

    (
      SELECT MIN(te.data)
      FROM turma_encontros te
      WHERE te.id_turma = t.id_turma
      AND te.status = 'marcado'
    ) AS data_inicio,

    (
      SELECT MAX(te.data)
      FROM turma_encontros te
      WHERE te.id_turma = t.id_turma
        AND te.status = 'marcado'
    ) AS data_fim,

    (
      SELECT COUNT(*)
      FROM turma_encontros te
      WHERE te.id_turma = t.id_turma
        AND te.status = 'marcado'
        AND te.data >= CURDATE()
    ) AS aulas_restantes,

    (
      SELECT COALESCE(SUM(te.horas), 0)
      FROM turma_encontros te
      WHERE te.id_turma = t.id_turma
        AND te.status = 'marcado'
        AND te.data >= CURDATE()
    ) AS horas_restantes,

    (
      SELECT COALESCE(SUM(te.horas), 0)
      FROM turma_encontros te
      WHERE te.id_turma = t.id_turma
        AND te.status = 'marcado'
        AND te.data < CURDATE()
    ) AS horas_realizadas

  FROM turmas t
  LEFT JOIN professores p ON p.id_professor = t.id_professor
  LEFT JOIN salas s ON s.id_sala = t.id_sala
  ORDER BY
  (CASE
     WHEN t.carga_horaria > 0
      AND (
        SELECT COALESCE(SUM(te.horas), 0)
        FROM turma_encontros te
        WHERE te.id_turma = t.id_turma
          AND te.status = 'marcado'
          AND te.data < CURDATE()
      ) >= t.carga_horaria
     THEN 1 ELSE 0
   END) ASC,
  t.id_turma DESC

";

$result = mysqli_query($conexao, $sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Turmas - Senac MA</title>
  <link rel="icon" type="image/x-icon" href="../IMG/favicon.png">
  <link rel="stylesheet" href="../CSS/padrao.css" />
  <link rel="stylesheet" href="../CSS/turmas.css" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <script src="../JS/padrao.js" defer></script>
  <script src="../JS/turmas.js" defer></script>
  <!-- editar_turmas.js removido: estava duplicando handlers e quebrando Pré-visualizar/Cancelar -->
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
        <li class="item-nav ativo"><a href="turmas.php" class="conteudo-barra-lateral">Turmas</a></li>
        <li class="item-nav"><a href="relatorios.php" class="conteudo-barra-lateral">Relatórios</a></li>
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

  <!-- Overlay mobile -->
  <div class="sobreposicao-mobile"></div>

  <!-- CONTEÚDO -->
  <main class="conteudo-principal">
    <section class="pagina-turmas">
      <div class="header-page">
        <h1>Turmas</h1>

        <div class="actions-bar">
          <button class="btn-icon btn-add" title="Adicionar Turma" id="btnAbrir">+</button>
          <button class="botao-icone btn-arquivado" title="Turmas Arquivadas"><img src="../IMG/archive_120061.png" alt=""></button>
          <button class="botao-icone botao-filtro" type="button" data-abrir-filtros title="Filtros">
            <img src="../IMG/filtro.png" alt="Filtro" style="width:22px;height:22px;">
          </button>
        </div>
      </div>

      <div class="cards" id="listaTurmas">
        <?php if (mysqli_num_rows($result) == 0): ?>
          <p>Nenhuma turma cadastrada.</p>
        <?php else: ?>
          <?php while ($t = mysqli_fetch_assoc($result) ):
          $dataInicio = $t['data_inicio']
            ? date('d/m/Y', strtotime($t['data_inicio']))
            : '—';

          $dataFim = $t['data_fim']
            ? date('d/m/Y', strtotime($t['data_fim']))
            : '—';

            $carga = (int)($t['carga_horaria'] ?? 0);
            $horasRealizadas = (int)($t['horas_realizadas'] ?? 0);
            $horasRestantes  = (int)($t['horas_restantes'] ?? 0);
            $aulasRestantes  = (int)($t['aulas_restantes'] ?? 0);

            $progresso = 0;
            if ($carga > 0) {
              $progresso = (int) round(($horasRealizadas / $carga) * 100);
              if ($progresso < 0) $progresso = 0;
              if ($progresso > 100) $progresso = 100;
            }

            $diasRestantes = null;
            if (!empty($t['data_fim'])) {
              $hoje = new DateTime('today');
              $fim  = new DateTime($t['data_fim']);
              $diasRestantes = ($fim < $hoje) ? 0 : (int)$hoje->diff($fim)->days;
            }
          ?>
            <div class="card" data-id="<?= (int)$t['id_turma'] ?>">
              <button class="icon-btn edit btn-edit" title="Editar Turma" 
                data-id="<?= (int)$t['id_turma'] ?>"
                data-nome="<?= htmlspecialchars($t['nome_turma']) ?>"
                data-codigo="<?= htmlspecialchars($t['cod_turma']) ?>"
                data-carga="<?= (int)$t['carga_horaria'] ?>"
                data-turno="<?= htmlspecialchars($t['turno']) ?>"
                data-professor="<?= $t['id_professor'] ?? '' ?>"
                data-sala="<?= $t['id_sala'] ?? '' ?>">
                <img src="../IMG/lapisIcon.png" alt="editar"></button>

              <button class="icon-btn delete btn-delete" title="Excluir Turma"
                data-id="<?= (int)$t['id_turma'] ?>"
                data-nome="<?= htmlspecialchars($t['nome_turma']) ?>">
                <img src="../IMG/lixeiraIcon.png" alt="excluir"></button>

              <h3 class="card-h3"><?= htmlspecialchars($t['nome_turma']) ?></h3>
              <div class="line"></div>

              <div class="info">
                <p class="content-info"><b>Código da Turma:</b> <?= htmlspecialchars($t['cod_turma']) ?></p>
                <div class="line"></div>

                <p class="content-info"><b>Professor:</b> <?= $t['professor_nome'] ? htmlspecialchars($t['professor_nome']) : "—" ?></p>
                <div class="line"></div>

                <p class="content-info p_turno"><b>Turno:</b> <?= htmlspecialchars($t['turno']) ?></p>
                <div class="line"></div>

                <p class="content-info"><b>Sala:</b> <?= $t['sala_nome'] ? htmlspecialchars($t['sala_nome']) : "—" ?></p>
                <div class="line"></div>

               <p class="content-info"><b>Data início:</b> <?= $dataInicio ?></p>
                <div class="line"></div>

                <p class="content-info"><b>Data encerramento:</b> <?= $dataFim ?></p>
                <div class="line"></div>

                <p class="content-info"><b>Horas restantes:</b> <?= $horasRestantes ?>h</p>
                <div class="line"></div>

                <p class="content-info"><b>Aulas restantes:</b> <?= $aulasRestantes ?></p>
                <div class="line"></div>

                <p class="content-info"><b>Dias restantes:</b> <?= $diasRestantes !== null ? $diasRestantes." dia(s)" : "—" ?></p>
                <div class="line"></div> 

                <p class="content-info"><b>Progresso:</b> <?= $progresso ?>%</p>

                <div style="margin-top:8px; border:1px solid #ddd; border-radius:10px; overflow:hidden; height:14px;">
                  <div style="height:14px; width: <?= $progresso ?>%; background: #2b7;"></div>
                </div>

                <p class="content-info" style="margin-top:8px;">
                  <small><?= $horasRealizadas ?>h feitas de <?= $carga ?>h</small>
                </p>
              </div>
            </div>
          <?php endwhile; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <!-- MODAL -->
  <div class="modal" id="meuModal" aria-hidden="true">
    <div class="modal__backdrop" data-close></div>
    <div class="modal__content" role="dialog" aria-modal="true">
        <div class="modal__header">
            <h2>Adicionar Turma</h2>
            <button class="modal__close" data-close>×</button>
        </div>
        
        <div class="modal__body">
            <form id="formTurma" action="../PHP/salvar_turma.php" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Sala</label>
                        <select name="id_sala" id="id_sala" required>
                            <option value="">Selecione...</option>
                            <?php foreach($salas as $s): ?>
                                <option value="<?= (int)$s['id_sala'] ?>">
                                    <?= htmlspecialchars($s['nome_sala']) ?> (<?= (int)$s['capacidade'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Professor</label>
                        <select name="id_professor" id="id_professor">
                            <option value="">(Sem professor)</option>
                            <?php foreach($professores as $p): ?>
                                <option value="<?= (int)$p['id_professor'] ?>">
                                    <?= htmlspecialchars($p['nome']) ?> - <?= htmlspecialchars($p['formacao']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome da turma</label>
                        <input type="text" name="nome_turma" id="nome_turma" required placeholder="Ex: Informática Básica">
                    </div>
                    <div class="form-group">
                        <label>Código da turma</label>
                        <input type="text" name="cod_turma" id="cod_turma" required placeholder="Ex: 2026.0.000">
                    </div>
                </div>
<div class="form-row">
                    <div class="form-group">
                        <label>Data de início</label>
                        <input type="date" name="data_inicio" id="data_inicio" required>
                    </div>
                    <div class="form-group">
                        <label>Carga horária</label>
                        <input type="number" name="carga_horaria" id="carga_horaria" min="1" required placeholder="Ex: 80">
                    </div>
                    <div class="form-group">
                        <label>Turno</label>
                        <select name="turno" id="turno" required>
                            <option value="">Selecione...</option>
                            <option value="manha">Manhã</option>
                            <option value="tarde">Tarde</option>
                            <option value="noite">Noite</option>
                        </select>
                    </div>
                </div>

                <label>Dias da semana</label>
                <div class="dias">
                    <label><input type="checkbox" name="dias_semana[]" value="seg"> Seg</label>
                    <label><input type="checkbox" name="dias_semana[]" value="ter"> Ter</label>
                    <label><input type="checkbox" name="dias_semana[]" value="qua"> Qua</label>
                    <label><input type="checkbox" name="dias_semana[]" value="qui"> Qui</label>
                    <label><input type="checkbox" name="dias_semana[]" value="sex"> Sex</label>
                </div>

                <div class="actions">
                    <button type="submit" class="btn-submit">✅ Cadastrar</button>
                    <button type="button" id="btnPreview" class="btn-preview">📅 Pré-visualizar</button>
                </div>

                <div id="preview"></div>
            </form>
        </div>
    </div>
</div>
<!-- MODAL DE EDIÇÃO -->
<div class="modal" id="modalEditar" aria-hidden="true">
    <div class="modal__backdrop" data-close-editar></div>
    <div class="modal__content" role="dialog" aria-modal="true">
        <div class="modal__header">
            <h2>Editar Turma</h2>
            <button class="modal__close" data-close-editar>×</button>
        </div>
        
        <div class="modal__body">
            <form id="formEditarTurma" action="../PHP/atualizar_turma.php" method="POST">
                <input type="hidden" name="id_turma" id="edit_id_turma">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Sala</label>
                        <select name="id_sala" id="edit_id_sala">
                            <option value="">Selecione...</option>
                            <?php foreach($salas as $s): ?>
                                <option value="<?= (int)$s['id_sala'] ?>">
                                    <?= htmlspecialchars($s['nome_sala']) ?> (<?= (int)$s['capacidade'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Professor</label>
                        <select name="id_professor" id="edit_id_professor">
                            <option value="">(Sem professor)</option>
                            <?php foreach($professores as $p): ?>
                                <option value="<?= (int)$p['id_professor'] ?>">
                                    <?= htmlspecialchars($p['nome']) ?> - <?= htmlspecialchars($p['formacao']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome da turma</label>
                        <input type="text" name="nome_turma" id="edit_nome_turma" required>
                    </div>
                    <div class="form-group">
                        <label>Código da turma</label>
                        <input type="text" name="cod_turma" id="edit_cod_turma" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Data para recálculo</label>
                        <input type="date" name="data_recalculo" id="edit_data_recalculo" required>
                        <small style="color:#666;">Data a partir da qual os encontros serão recalculados</small>
                    </div>
                    <div class="form-group">
                        <label>Carga horária</label>
                        <input type="number" name="carga_horaria" id="edit_carga_horaria" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Turno</label>
                        <select name="turno" id="edit_turno" required>
                            <option value="">Selecione...</option>
                            <option value="manha">Manhã</option>
                            <option value="tarde">Tarde</option>
                            <option value="noite">Noite</option>
                        </select>
                    </div>
                </div>

                <label>Dias da semana (mantenha selecionados para manter os encontros existentes)</label>
                <div class="dias">
                    <label><input type="checkbox" name="dias_semana[]" value="seg"> Seg</label>
                    <label><input type="checkbox" name="dias_semana[]" value="ter"> Ter</label>
                    <label><input type="checkbox" name="dias_semana[]" value="qua"> Qua</label>
                    <label><input type="checkbox" name="dias_semana[]" value="qui"> Qui</label>
                    <label><input type="checkbox" name="dias_semana[]" value="sex"> Sex</label>
                </div>

                <div class="actions">
                    <button type="submit" class="btn-submit">💾 Salvar Alterações</button>
                    <button type="button" id="btnPreviewEditar" class="btn-preview">📅 Pré-visualizar Recálculo</button>
                    <button type="button" id="btnCancelarEditar" class="btn-cancel">❌ Cancelar</button>
                </div>

                <div id="previewEditar"></div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DE EXCLUSÃO -->
<div class="modal" id="modalExcluirTurma" aria-hidden="true">
  <div class="modal__backdrop" data-close-excluir></div>
  <div class="modal__content" role="dialog" aria-modal="true" style="height:auto; max-width: 420px; border-top-color:#d9534f;">
    <div class="modal__header">
      <h2 style="color:#d9534f;">⚠️ Confirmar Exclusão</h2>
      <button class="modal__close" data-close-excluir aria-label="Fechar">×</button>
    </div>

    <div class="modal__body" style="text-align:center; display:flex; flex-direction:column; gap:14px;">
      <p>Tem certeza que deseja excluir a turma <strong id="nomeTurmaExcluir" style="color: var(--azul);"></strong>?</p>
      <p style="font-size: 13px; color:#666;">Esta ação remove também os encontros gerados dessa turma.</p>

      <form action="../PHP/excluirTurma.php" method="POST" style="display:flex; flex-direction:column; gap:10px;">
        <input type="hidden" name="id_turma" id="delete_turma_id">
        <button type="submit" class="btn-submit" style="background:#d9534f;">Sim, excluir</button>
        <button type="button" class="btn-cancel" data-close-excluir style="background:none; border:1px solid var(--borda);">Cancelar</button>
      </form>
    </div>
  </div>
</div>
<!-- MODAL: TURMAS ARQUIVADAS -->
<div class="modal modal-arquivadas" id="modalArquivadas" aria-hidden="true">
  <div class="modal__backdrop" data-close-arquivadas></div>

  <div class="modal__content modal__content--wide" role="dialog" aria-modal="true" aria-label="Turmas arquivadas">
    <div class="modal__header">
      <h2>Turmas arquivadas</h2> 
    <div class="arquivadas-actions">
      <button type="button" class="btn-arquivadas-clear" id="btnArquivadasClear" title="Apagar todas as arquivadas">
        Apagar tudo
      </button>
    <button class="modal__close" data-close-arquivadas aria-label="Fechar">×</button>
  </div>
    </div>

    <div class="modal__body">
      <p class="arquivadas-sub">
        Aqui ficam as turmas com <b>100% de progresso</b>, pra não poluir a tela principal
      </p>

      <div class="cards cards-arquivadas" id="listaTurmasArquivadas"></div>

      <div class="arquivadas-empty" id="arquivadasEmpty" style="display:none;">
        Nenhuma turma arquivada ainda.
      </div>
    </div>
  </div>
</div>


  <?php if ($toast_sucesso): ?>
    <div class="toast toast--success" data-toast><?= htmlspecialchars($toast_sucesso) ?></div>
  <?php endif; ?>
  <?php if ($toast_erro): ?>
    <div class="toast toast--error" data-toast><?= htmlspecialchars($toast_erro) ?></div>
  <?php endif; ?>

</body>
</html>
