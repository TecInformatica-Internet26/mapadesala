(() => {
  "use strict";

  if (!window.MapaSalaAPI || !window.MapaSalaRender) {
    console.error("MapaSalaAPI/MapaSalaRender não carregaram. Verifique a ordem dos scripts no HTML.");
    return;
  }

  const palco = document.getElementById("container-salas");
  const subtituloEl = document.getElementById("subtitulo-mapa");

  const botoesView = Array.from(document.querySelectorAll(".botao-periodo"));
  const botaoFiltro = document.getElementById("botao-filtro");

  const botaoMenu = document.getElementById("botao-menu");
  const overlayMobile = document.querySelector(".sobreposicao-mobile");
  const relogioEl = document.getElementById("relogio-lateral");
  const inputData = document.getElementById("mapa-data");

  const ORDEM_VIEWS = ["day", "week", "month", "year"];

  const TURNOS = [
    { id: "matutino", rotulo: "Manhã", range: [6, 12] },
    { id: "vespertino", rotulo: "Tarde", range: [12, 18] },
    { id: "noturno", rotulo: "Noite", range: [18, 23] },
  ];

  const appState = {
    view: "day",
    date: toISODate(new Date()),
    monthCursorISO: null,
    monthSelectedDate: null,
    yearCursor: new Date().getFullYear(),
    data: { 
      salas: [], 
      agendamentos: [],
      feriados: []
    },
    idx: new Map(),
    feriadosIdx: new Map(),

    filtros: {
      status: "all",
      professor: "",
      salaId: "all", // ✅ NOVO
      turnos: { matutino: true, vespertino: true, noturno: true },
    },
  };

  // ======== utils datas ========
  function toISODate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  }

  function shiftISO(dateISO, days) {
    const d = new Date(dateISO + "T00:00:00");
    d.setDate(d.getDate() + days);
    return toISODate(d);
  }

  function startOfWeekISO(dateISO) {
    const d = new Date(dateISO + "T00:00:00");
    d.setDate(d.getDate() - d.getDay());
    return toISODate(d);
  }

  function startOfMonthISO(dateISO) {
    const d = new Date(dateISO + "T00:00:00");
    d.setDate(1);
    return toISODate(d);
  }

  function startOfYearISO(dateISO) {
    const d = new Date(dateISO + "T00:00:00");
    d.setMonth(0, 1);
    return toISODate(d);
  }

  function addYearsISO(dateISO, delta) {
    const d = new Date(dateISO + "T00:00:00");
    d.setFullYear(d.getFullYear() + delta);
    return toISODate(d);
  }

  function addMonthsISO(monthISO, delta) {
    const d = new Date(monthISO + "T00:00:00");
    d.setMonth(d.getMonth() + delta);
    d.setDate(1);
    return toISODate(d);
  }

  function monthGrid(monthFirstISO) {
    const first = new Date(monthFirstISO + "T00:00:00");
    const gridStart = new Date(first);
    gridStart.setDate(first.getDate() - first.getDay());
    const gridStartISO = toISODate(gridStart);
    const gridDays = Array.from({ length: 42 }, (_, i) => shiftISO(gridStartISO, i));
    return { gridDays };
  }

  const util = { toISODate, shiftISO, startOfWeekISO, startOfMonthISO, startOfYearISO, addMonthsISO, addYearsISO, monthGrid };

  // ======== index ========
  function slotKey(salaId, dataISO, turno) {
    return `${salaId}|${dataISO}|${turno}`;
  }

  function buildIndex(agendamentos, feriados) {
    const map = new Map();
    for (const a of agendamentos) map.set(slotKey(a.salaId, a.data, a.turno), a);
    
    // Criar índice de feriados
    const feriadosMap = new Map();
    for (const f of feriados) feriadosMap.set(f.data, f);
    
    return { agendamentos: map, feriados: feriadosMap };
  }

  function getBooking(salaId, dataISO, turno) {
    return appState.idx.get(slotKey(salaId, dataISO, turno)) || null;
  }

  function isFeriado(dateISO) {
    return appState.feriadosIdx.has(dateISO);
  }

  function getFeriadoInfo(dateISO) {
    return appState.feriadosIdx.get(dateISO) || null;
  }

  // ======== agora marcador ========
  function getTurnoNowId(date = new Date()) {
    const h = date.getHours();
    for (const t of TURNOS) {
      const [ini, fim] = t.range;
      if (h >= ini && h < fim) return t.id;
    }
    return null;
  }

  function isPastTurno(turnoId, baseDateISO) {
    const hojeISO = toISODate(new Date());
    
    // Se a data for futura, NÃO é passado
    if (baseDateISO > hojeISO) return false;
    
    // Se for passado, sempre é passado
    if (baseDateISO < hojeISO) return true;
    
    // Se for hoje, verifica o turno atual
    const nowId = getTurnoNowId(new Date());
    const order = ["matutino", "vespertino", "noturno"];
    return order.indexOf(turnoId) < order.indexOf(nowId);
  }

  function isNowTurno(turnoId, baseDateISO) {
    const hojeISO = toISODate(new Date());
    
    // Se não for hoje, não é "agora"
    if (baseDateISO !== hojeISO) return false;
    
    // Se for hoje, verifica se é o turno atual
    return getTurnoNowId(new Date()) === turnoId;
  }

  // ======== filtros ========
  function norm(s) {
    return String(s || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();
  }

  function isFiltrosPadrao() {
    const f = appState.filtros;
    return (
      f.status === "all" &&
      !String(f.professor || "").trim() &&
      (String(f.salaId || "all") === "all") &&
      f.turnos.matutino &&
      f.turnos.vespertino &&
      f.turnos.noturno
    );
  }


  function atualizarIndicadorFiltro() {
    if (!botaoFiltro) return;
    botaoFiltro.classList.toggle("tem-filtro", !isFiltrosPadrao());
  }

  // ======== modal filtros ========
  let modalFiltrosEl = null;

  function criarModalFiltros() {
    const overlay = document.createElement("div");
    overlay.className = "sobreposicao-modal";
    overlay.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true" aria-label="Filtros">
        <div class="cabecalho-modal">
          <div class="titulo-modal">Filtros</div>
          <button class="fechar-modal" type="button" data-acao="fechar">✕</button>
        </div>

        <div class="corpo-modal">
          <div class="campo">
            <div class="rotulo">Status da sala</div>
            <div class="linha">
              <label class="pilula"><input type="radio" name="status" value="all"> Todos</label>
              <label class="pilula"><input type="radio" name="status" value="livre"> Só livres</label>
              <label class="pilula"><input type="radio" name="status" value="ocupada"> Só ocupadas</label>
            </div>
          </div>

          <div class="campo">
            <div class="rotulo">Sala</div>
            <select class="entrada" name="sala">
              <option value="all">Todas</option>
            </select>
          </div>

          <div class="campo">
            <div class="rotulo">Professor</div>
            <input class="entrada" type="text" name="professor" placeholder="Ex: Carlos" />
          </div>

          <div class="campo">
            <div class="rotulo">Turno</div>
            <div class="linha">
              <label class="pilula"><input type="checkbox" name="turno" value="matutino"> Manhã</label>
              <label class="pilula"><input type="checkbox" name="turno" value="vespertino"> Tarde</label>
              <label class="pilula"><input type="checkbox" name="turno" value="noturno"> Noite</label>
            </div>
          </div>
        </div>

        <div class="acoes-modal">
          <button class="botao" type="button" data-acao="limpar">Limpar</button>
          <button class="botao primario" type="button" data-acao="aplicar">Aplicar</button>
        </div>
      </div>
    `;

    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) fecharModalFiltros();
    });

    overlay.addEventListener("keydown", (e) => {
      if (e.key === "Escape") fecharModalFiltros();
    });

    overlay.addEventListener("click", (e) => {
      const acao = e.target.closest("[data-acao]")?.dataset.acao;
      if (!acao) return;

      if (acao === "fechar") fecharModalFiltros();

      if (acao === "limpar") {
        appState.filtros = {
          status: "all",
          professor: "",
          salaId: "all",
          turnos: { matutino: true, vespertino: true, noturno: true },
        };
        atualizarIndicadorFiltro();
        sincronizarModalComEstado(overlay);
        rerenderCurrent();
      }

      if (acao === "aplicar") {
        sincronizarEstadoComModal(overlay);
        atualizarIndicadorFiltro();
        rerenderCurrent();
        fecharModalFiltros();
      }
    });

    // não deixa desmarcar todos os turnos
    overlay.addEventListener("change", (e) => {
      const t = e.target;
      if (t?.name !== "turno") return;

      const checks = overlay.querySelectorAll('input[name="turno"]');
      let on = 0;
      checks.forEach((c) => { if (c.checked) on++; });
      if (on === 0) t.checked = true;
    });

    document.body.appendChild(overlay);
    return overlay;
  }
  function preencherSelectSalas(overlay) {
  const select = overlay.querySelector('select[name="sala"]');
  if (!select) return;

  // remove opções antigas (dinâmicas)
  select.querySelectorAll("option[data-dinamico]").forEach((o) => o.remove());

  const salas = Array.isArray(appState.data?.salas) ? appState.data.salas : [];
  for (const s of salas) {
    const opt = document.createElement("option");
    opt.value = String(s.id);
    opt.textContent = s.nome;
    opt.dataset.dinamico = "1";
    select.appendChild(opt);
  }
}


  function sincronizarModalComEstado(overlay) {
    const f = appState.filtros;

    overlay.querySelectorAll('input[name="status"]').forEach((r) => {
      r.checked = (r.value === f.status);
    });

    const selectSala = overlay.querySelector('select[name="sala"]');
    if (selectSala) selectSala.value = String(f.salaId ?? "all");

    overlay.querySelector('input[name="professor"]').value = f.professor || "";

    overlay.querySelectorAll('input[name="turno"]').forEach((c) => {
      c.checked = !!f.turnos[c.value];
    });
  }


  function sincronizarEstadoComModal(overlay) {
    const status = overlay.querySelector('input[name="status"]:checked')?.value || "all";
    const professor = overlay.querySelector('input[name="professor"]')?.value || "";
    const salaId = overlay.querySelector('select[name="sala"]')?.value || "all";

    const turnos = { matutino: false, vespertino: false, noturno: false };
    overlay.querySelectorAll('input[name="turno"]').forEach((c) => {
      turnos[c.value] = !!c.checked;
    });

    appState.filtros = { status, professor, salaId, turnos };

    if (!Object.values(appState.filtros.turnos).some(Boolean)) {
      appState.filtros.turnos.matutino = true;
    }
  }


  function abrirModalFiltros() {
    if (!modalFiltrosEl) modalFiltrosEl = criarModalFiltros();
    preencherSelectSalas(modalFiltrosEl);
    sincronizarModalComEstado(modalFiltrosEl);

    modalFiltrosEl.classList.add("aberto");
    document.body.style.overflow = "hidden";
  }

  function fecharModalFiltros() {
    if (!modalFiltrosEl) return;
    modalFiltrosEl.classList.remove("aberto");
    document.body.style.overflow = "";
  }

  // ======== API -> state ========
  async function refreshData() {
    const data = await window.MapaSalaAPI.ensureDataForState(appState, util);
    appState.data = data;
    const indices = buildIndex(data.agendamentos, data.feriados);
    appState.idx = indices.agendamentos;
    appState.feriadosIdx = indices.feriados;
  }

  // ======== render pipeline ========
  function getDeps() {
    return { 
      TURNOS, 
      getBooking, 
      isPastTurno, 
      isNowTurno, 
      norm,
      isFeriado,
      getFeriadoInfo
    };
  }

  function rerenderCurrent() {
    const current = palco.querySelector(".tela");
    if (!current) return;

    current.innerHTML = window.MapaSalaRender.renderHTML(appState, util, getDeps());
    window.MapaSalaRender.setSubtitle(subtituloEl, appState, util);
    requestAnimationFrame(() => window.MapaSalaRender.setStageHeight(palco, current, true));
  }

  function mountFirst() {
    const html = window.MapaSalaRender.renderHTML(appState, util, getDeps());
    window.MapaSalaRender.mountFirst(palco, html);
    window.MapaSalaRender.setSubtitle(subtituloEl, appState, util);
  }

  // ======== view switch ========
  function viewIndex(v) { return ORDEM_VIEWS.indexOf(v); }

  function setActiveButton(view) {
    botoesView.forEach((b) => {
      const active = b.dataset.view === view;
      b.classList.toggle("ativo", active);
      b.setAttribute("aria-selected", active ? "true" : "false");
    });
  }

  async function switchView(nextView) {
    const prevView = appState.view;
    if (nextView === prevView) return;

    appState.view = nextView;

    if (nextView === "month") {
      appState.monthCursorISO = startOfMonthISO(appState.date);
      appState.monthSelectedDate = appState.date;
    }

    if (nextView === "year") {
      appState.yearCursor = new Date(appState.date + "T00:00:00").getFullYear();
    }

    await refreshData();

    const html = window.MapaSalaRender.renderHTML(appState, util, getDeps());
    const direction = viewIndex(nextView) > viewIndex(prevView) ? "forward" : "backward";
    window.MapaSalaRender.animateSwap(palco, html, direction);

    setActiveButton(nextView);
    window.MapaSalaRender.setSubtitle(subtituloEl, appState, util);
  }

  async function applyDate(nextISO) {
    if (!nextISO || nextISO === appState.date) {
      // ainda assim garante que input esteja coerente
      if (inputData && inputData.value !== appState.date) inputData.value = appState.date;
      return;
    }

    appState.date = nextISO;

    if (appState.view === "month") {
      appState.monthCursorISO = startOfMonthISO(appState.date);
      appState.monthSelectedDate = appState.date;
    }

    if (appState.view === "year") {
      appState.yearCursor = new Date(appState.date + "T00:00:00").getFullYear();
    }

    if (inputData) inputData.value = appState.date;

    await refreshData();
    rerenderCurrent();
    window.MapaSalaRender.setSubtitle(subtituloEl, appState, util);
  }

  // ======== menu mobile ========
  function setMenuLateralAberto(open) {
    document.body.classList.toggle("menu-lateral-aberto", open);
    botaoMenu?.setAttribute("aria-expanded", open ? "true" : "false");
  }

  // ======== eventos ========
  function bindEvents() {
    botoesView.forEach((btn) => btn.addEventListener("click", () => switchView(btn.dataset.view)));

    // data selecionada (base para dia/semana/mês/ano)
    inputData?.addEventListener("change", (e) => {
      const v = e.target?.value;
      if (v) applyDate(v);
    });

    botaoFiltro?.addEventListener("click", abrirModalFiltros);

    botaoMenu?.addEventListener("click", (e) => {
      e.stopPropagation();
      setMenuLateralAberto(!document.body.classList.contains("menu-lateral-aberto"));
    });
    overlayMobile?.addEventListener("click", () => setMenuLateralAberto(false));

    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      setMenuLateralAberto(false);
      fecharModalFiltros();
    });

    // mês: prev/next + selecionar dia
    palco.addEventListener("click", async (e) => {
      if (appState.view !== "month") return;

      const act = e.target.closest("[data-act]")?.dataset.act;

      if (act === "month-prev" || act === "month-next") {
        const delta = act === "month-prev" ? -1 : 1;

        appState.monthCursorISO = addMonthsISO(appState.monthCursorISO, delta);

        // por padrão, seleciona o 1º dia do mês e sincroniza base date
        appState.monthSelectedDate = appState.monthCursorISO;
        appState.date = appState.monthSelectedDate;
        if (inputData) inputData.value = appState.date;

        await refreshData();

        if (appState.monthSelectedDate?.slice(0, 7) !== appState.monthCursorISO.slice(0, 7)) {
          appState.monthSelectedDate = appState.monthCursorISO;
        }

        rerenderCurrent();
        return;
      }

      const cell = e.target.closest(".celula-dia[data-date]");
      if (!cell || cell.classList.contains("feriado")) return;

      appState.monthSelectedDate = cell.dataset.date;

      // sincroniza base date + input + subtítulo
      appState.date = appState.monthSelectedDate;
      if (inputData) inputData.value = appState.date;
      window.MapaSalaRender.setSubtitle(subtituloEl, appState, util);

      const current = palco.querySelector(".tela");
      if (!current) return;

      const prev = current.querySelector(".celula-dia.selecionado");
      if (prev) prev.classList.remove("selecionado");
      cell.classList.add("selecionado");
      cell.blur();

      const painel = current.querySelector('[data-role="painel-dia"]');
      if (painel) painel.innerHTML = window.MapaSalaRender.renderPainelDia(appState, getDeps(), appState.monthSelectedDate);

      window.MapaSalaRender.setStageHeight(palco, current, true);
    });

    // ano: navegação + abrir mês
    palco.addEventListener("click", async (e) => {
      if (appState.view !== "year") return;

      const act = e.target.closest("[data-act]")?.dataset.act;
      if (!act) return;

      if (act === "year-prev" || act === "year-next") {
        const delta = act === "year-prev" ? -1 : 1;

        // move o ano mantendo dia/mês se possível
        appState.date = addYearsISO(appState.date, delta);
        appState.yearCursor = new Date(appState.date + "T00:00:00").getFullYear();
        if (inputData) inputData.value = appState.date;

        await refreshData();
        rerenderCurrent();
        window.MapaSalaRender.setSubtitle(subtituloEl, appState, util);
        return;
      }

      if (act === "year-month") {
        const month = e.target.closest("[data-month]")?.dataset.month;
        if (!month) return;
        // abre o mês no 1º dia
        await applyDate(`${month}-01`);
        await switchView("month");
      }
    });

    window.addEventListener("resize", () => {
      const current = palco.querySelector(".tela");
      if (!current) return;
      window.MapaSalaRender.setStageHeight(palco, current, false);
    });

    setInterval(() => {
      if (appState.view === "day") rerenderCurrent();
    }, 60_000);
  }

  // ======== relógio ========
  function startClock() {
    if (!relogioEl) return;

    const tick = () => {
      const d = new Date();
      const hh = String(d.getHours()).padStart(2, "0");
      const mm = String(d.getMinutes()).padStart(2, "0");
      relogioEl.textContent = `${hh}:${mm}`;
    };

    tick();
    setInterval(tick, 15_000);
  }

  // ======== init ========
  async function init() {
    if (!palco) return;

    try {
      atualizarIndicadorFiltro();
      await refreshData();
      mountFirst();
      bindEvents();
      startClock();
      setActiveButton(appState.view);

      // input data começa em hoje (e fica pronto pro usuário)
      if (inputData) inputData.value = appState.date;
    } catch (err) {
      console.error(err);
      palco.innerHTML = `<div class="vazio-painel">Erro ao carregar mapa: ${window.MapaSalaRender.escapeHTML(err.message)}</div>`;
    }
  }

  init();
})();