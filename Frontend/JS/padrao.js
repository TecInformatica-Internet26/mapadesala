(() => {
  "use strict";

  // =====================================================
  // Elementos padrão
  // =====================================================
  const botaoMenu = document.getElementById("botao-menu");
  const overlayMobile = document.querySelector(".sobreposicao-mobile");
  const relogioEl = document.getElementById("relogio-lateral");
  const botaoUsuario = document.getElementById("botao-usuario");
  const navLista = document.querySelector(".nav-lateral ul");

  // =====================================================
  // Helpers
  // =====================================================
  const paginaAtual = (() => {
    const p = (window.location.pathname || "").toLowerCase();
    if (p.includes("mapadesala")) return "mapa";
    if (p.includes("salas.php")) return "salas";
    if (p.includes("turmas.php")) return "turmas";
    if (p.includes("professores.php")) return "professores";
    if (p.includes("adm.php")) return "adm";
    return "outra";
  })();

  const isMapaDeSalas = paginaAtual === "mapa" || !!document.getElementById("container-salas");

  async function obterSessao() {
    try {
      const resp = await fetch("../PHP/session_info.php", { credentials: "same-origin" });
      if (!resp.ok) return { logado: false, papel: "user" };
      const data = await resp.json();
      return { logado: !!data?.logado, papel: data?.papel === "admin" ? "admin" : "user" };
    } catch {
      return { logado: false, papel: "user" };
    }
  }

  // =====================================================
  // Toast (mensagens vindas do PHP via session)
  // =====================================================
  const toastEl = document.querySelector("[data-toast]");
  if (toastEl) {
    requestAnimationFrame(() => toastEl.classList.add("is-show"));
    setTimeout(() => {
      toastEl.classList.remove("is-show");
      setTimeout(() => toastEl.remove(), 250);
    }, 3200);
  }

  // =====================================================
  // Menu lateral (mobile)
  // =====================================================
  function setMenuLateralAberto(open) {
    document.body.classList.toggle("menu-lateral-aberto", open);
    botaoMenu?.setAttribute("aria-expanded", open ? "true" : "false");
  }

  // No mapa, o core pode ter handlers próprios — então não duplicamos
  if (!isMapaDeSalas) {
    botaoMenu?.addEventListener("click", (e) => {
      e.stopPropagation();
      setMenuLateralAberto(!document.body.classList.contains("menu-lateral-aberto"));
    });

    overlayMobile?.addEventListener("click", () => setMenuLateralAberto(false));
  }

  // =====================================================
  // Relógio (sidebar)
  // =====================================================
  function iniciarRelogio() {
    if (!relogioEl) return;
    const tick = () => {
      const d = new Date();
      const hh = String(d.getHours()).padStart(2, "0");
      const mm = String(d.getMinutes()).padStart(2, "0");
      relogioEl.textContent = `${hh}:${mm}`;
    };
    tick();
    setInterval(tick, 15000);
  }

  if (!isMapaDeSalas) iniciarRelogio();

  // =====================================================
  // Menu usuário (popover simples) + logout
  // =====================================================
  let menuUsuarioEl = null;

  function fazerLogout() {
    window.dispatchEvent(new CustomEvent("app:logout"));
    window.location.href = "../PHP/logout.php";
  }

  function criarMenuUsuario() {
    const pop = document.createElement("div");
    pop.className = "menu-usuario";
    pop.innerHTML = `
      <div class="menu-usuario__cabecalho">
        <div class="menu-usuario__papel" id="menuUsuarioPapel">Usuário</div>
      </div>
      <button type="button" class="menu-usuario__sair" data-acao="sair"><img src="../IMG/sairIcon.png" alt="Sair">Sair</button>
    `;
    document.body.appendChild(pop);

    obterSessao().then((s) => {
      const el = pop.querySelector("#menuUsuarioPapel");
      if (!el) return;
      el.textContent = s.papel === "admin" ? "Administrador" : "Usuário";
    });

    pop.addEventListener("click", (e) => {
      const acao = e.target.closest("[data-acao]")?.dataset.acao;
      if (acao === "sair") fazerLogout();
    });
    return pop;
  }

  function fecharMenuUsuarioAoClicarFora(e) {
    if (!menuUsuarioEl?.classList.contains("aberto")) return;
    if (menuUsuarioEl.contains(e.target)) return;
    if (botaoUsuario && botaoUsuario.contains(e.target)) return;
    menuUsuarioEl.classList.remove("aberto");
    botaoUsuario?.setAttribute("aria-expanded", "false");
    document.removeEventListener("click", fecharMenuUsuarioAoClicarFora, { capture: true });
  }

  function toggleMenuUsuario() {
    if (!menuUsuarioEl) menuUsuarioEl = criarMenuUsuario();
    const abriu = menuUsuarioEl.classList.toggle("aberto");
    botaoUsuario?.setAttribute("aria-expanded", abriu ? "true" : "false");
    if (abriu) document.addEventListener("click", fecharMenuUsuarioAoClicarFora, { capture: true });
    else document.removeEventListener("click", fecharMenuUsuarioAoClicarFora, { capture: true });
  }

  botaoUsuario?.addEventListener("click", (e) => {
    e.stopPropagation();
    toggleMenuUsuario();
  });

  
  // =====================================================
  // Filtros: só emite um evento padrão (as páginas que quiserem usam)
  // =====================================================
  const FILTRO_BTN_SELECTOR = [
    "[data-abrir-filtros]",
    "#botao-filtro",
    ".botao-filtro",
    ".btn-icon.btn-filter",
    ".btn-icon.btn-filtro",
  ].join(",");

  if (!isMapaDeSalas) {
    document.addEventListener(
      "click",
      (e) => {
        const btn = e.target.closest(FILTRO_BTN_SELECTOR);
        if (!btn) return;
        e.preventDefault();
        window.dispatchEvent(new CustomEvent("app:abrir-filtros", { detail: { pagina: paginaAtual } }));
      },
      { capture: true }
    );
  }
})()
