(() => {
  const modalCriar = document.getElementById("meuModal");
  const modalEditar = document.getElementById("modalEditar");
  const modalExcluir = document.getElementById("modalExcluir");

  const btnAbrir = document.getElementById("btnAbrir");

  function setOpen(modalEl, open) {
    if (!modalEl) return;
    modalEl.classList.toggle("is-open", open);
    modalEl.setAttribute("aria-hidden", open ? "false" : "true");
    document.body.classList.toggle("no-scroll", open);
  }

  function abrirModalCriar() { setOpen(modalCriar, true); }
  function fecharModalCriar() { setOpen(modalCriar, false); btnAbrir?.focus(); }

  // Expostas pro onclick no HTML:
  window.abrirModalEdicao = function (botao) {
    // Preenche os dados
    document.getElementById("edit_id").value = botao.getAttribute("data-id") || "";
    document.getElementById("edit_nome").value = botao.getAttribute("data-nome") || "";
    document.getElementById("edit_capacidade").value = botao.getAttribute("data-capacidade") || "";
    setOpen(modalEditar, true);
  };

  window.abrirModalExcluir = function (botao) {
    document.getElementById("delete_id").value = botao.getAttribute("data-id") || "";
    document.getElementById("nomeSalaExcluir").innerText = botao.getAttribute("data-nome") || "";
    setOpen(modalExcluir, true);
  };

  function fecharModalEditar() { setOpen(modalEditar, false); }
  function fecharModalExcluir() { setOpen(modalExcluir, false); }

  btnAbrir?.addEventListener("click", abrirModalCriar);

  // Fechar pelos botões/backdrop (data-close)
  document.addEventListener("click", (e) => {
    const close = e.target.closest("[data-close]");
    if (!close) return;

    if (modalCriar?.classList.contains("is-open") && modalCriar.contains(close)) fecharModalCriar();
    if (modalEditar?.classList.contains("is-open") && modalEditar.contains(close)) fecharModalEditar();
    if (modalExcluir?.classList.contains("is-open") && modalExcluir.contains(close)) fecharModalExcluir();
  });

  // ESC fecha o que estiver aberto
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (modalExcluir?.classList.contains("is-open")) fecharModalExcluir();
    else if (modalEditar?.classList.contains("is-open")) fecharModalEditar();
    else if (modalCriar?.classList.contains("is-open")) fecharModalCriar();
  });
})();

// =========================
// FILTROS (modal padrão)
// =========================
(() => {
  const pagina = 'salas';
  const lista = document.getElementById('listaSalas');
  if (!lista) return;

  let overlay = null;
  let estado = { q: '' };

  function getCards() {
    return Array.from(lista.querySelectorAll('.card'));
  }

  function aplicarFiltros() {
    const q = (estado.q || '').trim().toLowerCase();
    const cards = getCards();

    cards.forEach((card) => {
      if (!q) {
        card.style.display = '';
        return;
      }
      const texto = (card.innerText || '').toLowerCase();
      card.style.display = texto.includes(q) ? '' : 'none';
    });
  }

  function criarOverlay() {
    // Usa o modal "namespaced" de filtros (evita conflito com .modal dos modais de CRUD)
    const el = document.createElement('div');
    el.className = 'sobreposicao-modal-filtros';
    el.setAttribute('aria-hidden', 'true');

    el.innerHTML = `
      <div class="modal-filtros" role="dialog" aria-modal="true" aria-label="Filtros">
        <div class="cabecalho-modal">
          <div class="titulo-modal">Filtros</div>
          <button type="button" class="fechar-modal" data-acao="fechar" aria-label="Fechar">×</button>
        </div>
        <div class="corpo-modal">
          <div class="campo">
            <div class="rotulo">Buscar</div>
            <input class="entrada" id="filtroTexto" type="search" placeholder="Nome da sala, capacidade..." />
          </div>
          <div class="linha-acoes">
            <button type="button" class="botao-secundario" data-acao="limpar">Limpar</button>
            <button type="button" class="botao-primario" data-acao="aplicar">Aplicar</button>
          </div>
        </div>
      </div>
    `;

    el.addEventListener('click', (e) => {
      if (e.target === el) fechar();
      const acao = e.target.closest('[data-acao]')?.dataset.acao;
      if (!acao) return;

      if (acao === 'fechar') fechar();
      if (acao === 'limpar') {
        estado.q = '';
        aplicarFiltros();
        fechar();
      }
      if (acao === 'aplicar') {
        const inp = el.querySelector('#filtroTexto');
        estado.q = inp?.value || '';
        aplicarFiltros();
        fechar();
      }
    });

    el.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') fechar();
      if (e.key === 'Enter') {
        const inp = el.querySelector('#filtroTexto');
        estado.q = inp?.value || '';
        aplicarFiltros();
        fechar();
      }
    });

    document.body.appendChild(el);
    return el;
  }

  function abrir() {
    if (!overlay) overlay = criarOverlay();
    overlay.classList.add('aberto');
    const inp = overlay.querySelector('#filtroTexto');
    if (inp) {
      inp.value = estado.q || '';
      setTimeout(() => inp.focus(), 0);
    }
  }

  function fechar() {
    overlay?.classList.remove('aberto');
  }

  window.addEventListener('app:abrir-filtros', (e) => {
    if (e?.detail?.pagina !== pagina) return;
    abrir();
  });
})();
