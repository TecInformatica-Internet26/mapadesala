<?php
require "../PHP/conexao.php";
session_start();

/* 🔒 Apenas admin (id 1) */
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_usuario'] != 1) {
    header("Location: ../../index.html");
    exit;
}

/* ===============================
   AÇÕES (EDITAR / EXCLUIR)
================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* EDITAR USUÁRIO */
    if (isset($_POST['acao']) && $_POST['acao'] === 'editar') {
        $id    = $_POST['id_usuario'];
        $email = $_POST['email'];
        $senha = $_POST['senha'];

        if (!empty($senha)) {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conexao->prepare(
                "UPDATE usuarios SET email=?, senha=? WHERE id_usuario=?"
            );
            $stmt->bind_param("ssi", $email, $hash, $id);
        } else {
            $stmt = $conexao->prepare(
                "UPDATE usuarios SET email=? WHERE id_usuario=?"
            );
            $stmt->bind_param("si", $email, $id);
        }

        $stmt->execute();
        header("Location: adm.php");
        exit;
    }

    /* EXCLUIR USUÁRIO */
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
        $id = $_POST['id_usuario'];

        $stmt = $conexao->prepare(
            "DELETE FROM usuarios WHERE id_usuario=?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();

        header("Location: adm.php");
        exit;
    }
}

/* ===============================
   LISTAGEM
================================ */

$sql = "SELECT id_usuario, email FROM usuarios WHERE id_usuario != 1";
$result = $conexao->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Administração - Senac MA</title>
<link rel="icon" type="image/x-icon" href="../IMG/favicon.png">

<link rel="stylesheet" href="../CSS/adm.css">
<link rel="stylesheet" href="../CSS/padrao.css">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="../JS/padrao.js" defer></script>
</head>

<body>

<!-- ================= TOPBAR PADRÃO ================= -->
<header class="barra-topo">
    <button class="botao-menu" id="botao-menu">☰</button>

    <div class="logo-topo">
        <img src="../IMG/senac_logo_branco.png" alt="Senac">
    </div>

    <button class="botao-usuario" id="botao-usuario"><img src="../IMG/usuarioIcon.png" alt="Usuário"></button>
</header>

<!-- ================= SIDEBAR PADRÃO ================= -->
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

        <li class="item-nav"><a href="creditos.php" class="conteudo-barra-lateral">Créditos</a></li>
        </ul>
    </nav>

    <div class="rodape-lateral">
        <div class="relogio-lateral" id="relogio-lateral">--:--</div>
        <div class="creditos-lateral">
            Desenvolvido pela Turma Técnico de Informática para a Internet
        </div>
    </div>
</aside>

<!-- Overlay mobile -->
<div class="sobreposicao-mobile"></div>

<!-- ================= CONTEÚDO ================= -->
<main class="conteudo-principal">

    <section class="pagina-adm">

        <header class="cabecalho-pagina">
            <h1>Usuários</h1>
        </header>

        <div class="cards-adm">
            <?php while ($u = $result->fetch_assoc()): ?>
                    <div class="card-adm">

    <!-- TOPO: BADGE + BOTÕES -->
    <div class="card-topo">
        <span class="badge-admin">Usuário</span>

        <div class="card-acoes">
            <button class="btn-delete btn-icon "
                onclick="abrirExcluir(
                    <?= $u['id_usuario'] ?>,
                    '<?= htmlspecialchars($u['email']) ?>'
                )"><img src="../IMG/lixeiraIcon.png" alt="excluir"></button>

            <button class="btn-edit btn-icon"
                onclick="abrirEditar(
                    <?= $u['id_usuario'] ?>,
                    '<?= htmlspecialchars($u['email']) ?>'
                )"><img src="../IMG/lapisIcon.png" alt="editar"></button>
        </div>
    </div>

    <!-- EMAIL -->
    <h3 class="email-usuario">
        <?= htmlspecialchars($u['email']) ?>
    </h3>

</div>



            <?php endwhile; ?>
        </div>

    </section>
</main>

<!-- ================= MODAL EDITAR ================= -->
<div class="modal" id="modalEditar">
  <div class="modal-backdrop" onclick="fecharModais()"></div>

  <div class="modal-card">
    <button class="modal-fechar" onclick="fecharModais()">×</button>

    <h2 class="modal-titulo">Editar Usuário</h2>

    <form method="POST" action="../PHP/admEditar.php">
      <input type="hidden" name="id_usuario" id="editId">

      <div class="campo">
        <label>E-mail</label>
        <input type="email" name="email" id="editEmail" required>
      </div>

      <div class="campo">
        <label>Nova senha</label>
        <input type="password" name="senha">
        <small>Deixe em branco para não alterar</small>
      </div>

      <button class="btn-primario">Salvar Alterações</button>
    </form>
  </div>
</div>


<!-- ================= MODAL EXCLUIR ================= -->
<div class="modal" id="modalExcluir">
  <div class="modal-backdrop" onclick="fecharModais()"></div>

  <div class="modal-card">
    <button class="modal-fechar" onclick="fecharModais()">×</button>

    <h2 class="modal-titulo perigo">Excluir Usuário</h2>

    <form method="POST" action="../PHP/admDeletar.php">
      <input type="hidden" name="id_usuario" id="deleteId">

      <p class="texto-confirmacao">
        Deseja excluir o usuário:
        <strong id="deleteEmail"></strong>?
      </p>

      <button class="btn-primario perigo">Excluir</button>
    </form>
  </div>
</div>


<script src="../JS/adm.js"></script>
</body>
</html>
