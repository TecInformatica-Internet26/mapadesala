(() => {
  /* =====================================================
     ELEMENTOS (HTML)
  ===================================================== */
  const palco = document.getElementById("container-salas");
  const subtituloEl = document.getElementById("subtitulo-mapa");

  const botoesView = Array.from(document.querySelectorAll(".botao-periodo"));
  const botaoFiltro = document.getElementById("botao-filtro");
  const botaoUsuario = document.getElementById("botao-usuario");

  const inputDataMapa = document.getElementById("mapa-data");

  const botaoMenu = document.getElementById("botao-menu");
  const overlayMobile = document.querySelector(".sobreposicao-mobile");
  const relogioEl = document.getElementById("relogio-lateral");

  /* =====================================================
     CONSTANTES
  ===================================================== */
  const ORDEM_VIEWS = ["day", "week", "month"];

  const TURNOS = [
    { id: "matutino", rotulo: "Manhã", range: [6, 12] },
    { id: "vespertino", rotulo: "Tarde", range: [12, 18] },
    { id: "noturno", rotulo: "Noite", range: [18, 23] },
  ];

  /* =====================================================
     ESTADO GLOBAL
  ===================================================== */
  const appState = {
    view: "day",
    date: toISODate(new Date()),

    // mês
    monthCursorISO: null,      // YYYY-MM-01 (mês visível)
    monthSelectedDate: null,   // YYYY-MM-DD (dia selecionado)

    data: null,                // { salas, agendamentos }
    idx: null,                 // Map slotKey -> agendamento

    filtros: {
      status: "all", // all | livre | ocupada
      professor: "",
      turnos: { matutino: true, vespertino: true, noturno: true },
    },
  };

  /* =====================================================
     MOCK (troca por fetch depois)
  ===================================================== */
  const mockAPI = {
    salas: [
      { id: 1, nome: "Sala 01", tipo: "Sala" },
      { id: 2, nome: "Sala 02", tipo: "Sala" },
      { id: 3, nome: "Sala 03", tipo: "Lab" },
      { id: 4, nome: "Sala 04", tipo: "Lab" },
      { id: 5, nome: "Sala 05", tipo: "Sala" },
      { id: 6, nome: "Sala 06", tipo: "Sala" },
      { id: 7, nome: "Sala 07", tipo: "Lab" },
      { id: 8, nome: "Sala 08", tipo: "Sala" },
      { id: 9, nome: "Sala 09", tipo: "Sala" },
      { id: 10, nome: "Sala 10", tipo: "Lab" },
      { id: 11, nome: "Sala 11", tipo: "Sala" },
      { id: 12, nome: "Sala 12", tipo: "Sala" },
    ],
    agendamentos: [
      { salaId: 1, data: shiftISO(toISODate(new Date()), 0), turno: "matutino", professor: "Carlos", curso: "Informática", codigoTurma: "TI-01" },
      { salaId: 1, data: shiftISO(toISODate(new Date()), 0), turno: "noturno", professor: "Ana", curso: "Redes", codigoTurma: "RD-02" },
      { salaId: 3, data: shiftISO(toISODate(new Date()), 0), turno: "vespertino", professor: "Marcos", curso: "Design", codigoTurma: "DS-03" },
    ],
  };

  async function carregarDados() {
    // Quando for backend:
    // const r = await fetch("../api/mapa_salas.php?json=1");
    // if (!r.ok) throw new Error("Falha ao carregar backend");
    // return await r.json();
    return deepClone(mockAPI);
  }

  /* =====================================================
     INDEX
  ===================================================== */
  function buildIndex(agendamentos) {
    const map = new Map();
    for (const a of agendamentos) map.set(slotKey(a.salaId, a.data, a.turno), a);
    return map;
  }

  function getBooking(salaId, dataISO, turno) {
    return appState.idx.get(slotKey(salaId, dataISO, turno)) || null;
  }

  /* =====================================================
     "AGORA" (só marcador)
  ===================================================== */
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
    if (baseDateISO !== hojeISO) return false;

    const nowId = getTurnoNowId(new Date());
    const order = ["matutino", "vespertino", "noturno"];
    return order.indexOf(turnoId) < order.indexOf(nowId);
  }

  function isNowTurno(turnoId, baseDateISO) {
    const hojeISO = toISODate(new Date());
    if (baseDateISO !== hojeISO) return false;
    return getTurnoNowId(new Date()) === turnoId;
  }

  /* =====================================================
     FILTROS
  ===================================================== */
  function norm(s) {
    return String(s || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();
  }

  function enabledTurnosCount() {
    return Object.values(appState.filtros.turnos).filter(Boolean).length || 0;
  }

  function professorMatches(booking) {
    const q = norm(appState.filtros.professor);
    if (!q) return true;
    if (!booking) return false;
    return norm(booking.professor).includes(q);
  }

  function slotVisible(booking, turnoId) {
    const f = appState.filtros;

    if (!f.turnos[turnoId]) return false;

    const hasProf = !!norm(f.professor);
    if (hasProf && f.status === "livre") return false;

    if (hasProf) return !!booking && professorMatches(booking);

    if (f.status === "ocupada") return !!booking;
    if (f.status === "livre") return !booking;
    return true;
  }

  function isFiltrosPadrao() {
    const f = appState.filtros;
    return (
      f.status === "all" &&
      !norm(f.professor) &&
      f.turnos.matutino &&
      f.turnos.vespertino &&
      f.turnos.noturno
    );
  }

  function atualizarIndicadorFiltro() {
    botaoFiltro?.classList.toggle("tem-filtro", !isFiltrosPadrao());
  }

  /* =====================================================
     MODAL: FILTROS
  ===================================================== */
  let modalFiltrosEl = null;

  function abrirModalFiltros() {
    if (!modalFiltrosEl) modalFiltrosEl = criarModalFiltros();
    sincronizarModalComEstado(modalFiltrosEl);

    modalFiltrosEl.classList.add("aberto");
    document.body.style.overflow = "hidden";

    const primeiro = modalFiltrosEl.querySelector("input,button");
    if (primeiro) primeiro.focus();
  }

  function fecharModalFiltros() {
    if (!modalFiltrosEl) return;
    modalFiltrosEl.classList.remove("aberto");
    document.body.style.overflow = "";
  }

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

  function sincronizarModalComEstado(overlay) {
    const f = appState.filtros;

    overlay.querySelectorAll('input[name="status"]').forEach((r) => {
      r.checked = (r.value === f.status);
    });

    overlay.querySelector('input[name="professor"]').value = f.professor || "";

    overlay.querySelectorAll('input[name="turno"]').forEach((c) => {
      c.checked = !!f.turnos[c.value];
    });
  }

  function sincronizarEstadoComModal(overlay) {
    const status = overlay.querySelector('input[name="status"]:checked')?.value || "all";
    const professor = overlay.querySelector('input[name="professor"]')?.value || "";

    const turnos = { matutino: false, vespertino: false, noturno: false };
    overlay.querySelectorAll('input[name="turno"]').forEach((c) => {
      turnos[c.value] = !!c.checked;
    });

    appState.filtros = { status, professor, turnos };

    if (!Object.values(appState.filtros.turnos).some(Boolean)) {
      appState.filtros.turnos.matutino = true;
    }
  }

  /* =====================================================
     MENU: USUÁRIO / LOGOFF
  ===================================================== */
  let menuUsuarioEl = null;

  function criarMenuUsuario() {
    const pop = document.createElement("div");
    pop.className = "menu-usuario";
    pop.innerHTML = `<button type="button" data-acao="sair">Sair</button>`;
    document.body.appendChild(pop);

    pop.addEventListener("click", (e) => {
      const acao = e.target.closest("[data-acao]")?.dataset.acao;
      if (acao === "sair") fazerLogout();
    });

    return pop;
  }

  function abrirFecharMenuUsuario() {
    if (!menuUsuarioEl) menuUsuarioEl = criarMenuUsuario();

    const abriu = menuUsuarioEl.classList.toggle("aberto");
    botaoUsuario?.setAttribute("aria-expanded", abriu ? "true" : "false");

    if (abriu) {
      document.addEventListener("click", fecharMenuUsuarioAoClicarFora, { capture: true });
    } else {
      document.removeEventListener("click", fecharMenuUsuarioAoClicarFora, { capture: true });
    }
  }

  function fecharMenuUsuarioAoClicarFora(e) {
    if (!menuUsuarioEl?.classList.contains("aberto")) return;
    if (menuUsuarioEl.contains(e.target)) return;
    if (botaoUsuario && botaoUsuario.contains(e.target)) return;

    menuUsuarioEl.classList.remove("aberto");
    botaoUsuario?.setAttribute("aria-expanded", "false");
    document.removeEventListener("click", fecharMenuUsuarioAoClicarFora, { capture: true });
  }

  function fazerLogout() {
    window.dispatchEvent(new CustomEvent("mapaSalas:logout"));
    location.reload();
  }

  /* =====================================================
     RENDER: DIA
  ===================================================== */
  function renderDay(dateISO) {
    const cards = appState.data.salas.map((s) => {
      const linhas = TURNOS
        .filter(t => appState.filtros.turnos[t.id])
        .map((t) => {
          const booking = getBooking(s.id, dateISO, t.id);
          if (!slotVisible(booking, t.id)) return "";

          const statusClass = booking ? "status--ocupada" : "status--livre";
          const badge = booking
            ? `<span class="selo ocupada"><span class="ponto"></span>Ocupada</span>`
            : `<span class="selo livre"><span class="ponto"></span>Livre</span>`;

          const meta = booking
            ? `${escapeHTML(booking.professor)} • ${escapeHTML(booking.curso)}`
            : "Sem agendamento";

          const passado = isPastTurno(t.id, dateISO) ? " estado-passado" : "";
          const agora = isNowTurno(t.id, dateISO) ? " estado-agora" : "";

          return `
            <div class="linha-turno ${statusClass}${passado}${agora}">
              <span class="etiqueta-turno">${t.rotulo}</span>
              ${badge}
              <div class="info-turno" title="${escapeHTML(meta)}">${escapeHTML(meta)}</div>
            </div>
          `;
        })
        .filter(Boolean)
        .join("");

      if (!linhas) return "";

      return `
        <div class="card-sala">
          <div class="cabecalho-sala">
            <h2 class="nome-sala">${escapeHTML(s.nome)}</h2>
            <span class="tipo-sala">${escapeHTML(s.tipo || "Sala")}</span>
          </div>
          <div class="lista-turnos">${linhas}</div>
        </div>
      `;
    }).filter(Boolean).join("");

    return cards
      ? `<div class="grade-salas">${cards}</div>`
      : `<div class="vazio-painel">Nenhum resultado com os filtros atuais.</div>`;
  }

  /* =====================================================
     RENDER: SEMANA
  ===================================================== */
  function renderWeek(dateISO) {
    const inicio = startOfWeekISO(dateISO);
    const dias = Array.from({ length: 7 }, (_, i) => shiftISO(inicio, i));
    const dow = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];

    const rows = appState.data.salas.map((s) => {
      let temMatch = false;

      const diasHTML = dias.map((d) => {
        const dt = new Date(d + "T00:00:00");
        const diaNum = dt.getDate();
        const diaDow = dow[dt.getDay()];

        const bars = TURNOS
          .filter(t => appState.filtros.turnos[t.id])
          .map((t) => {
            const booking = getBooking(s.id, d, t.id);

            const cls =
              appState.filtros.status === "ocupada" ? (booking ? "ocupada" : "apagado") :
              appState.filtros.status === "livre" ? (!booking ? "livre" : "apagado") :
              (booking ? "ocupada" : "livre");

            if (cls !== "apagado") temMatch = true;

            return `<span class="barra-turno-semana ${cls}" title="${t.rotulo}"></span>`;
          })
          .join("");

        return `
          <div class="card-dia-semana">
            <div class="topo-dia-semana">
              <span class="numero-dia-semana">${diaNum}</span>
              <span class="sigla-dia-semana">${diaDow}</span>
            </div>
            <div class="barras-turnos-semana">${bars}</div>
          </div>
        `;
      }).join("");

      if (!isFiltrosPadrao() && !temMatch) return "";

      return `
        <div class="linha-semana">
          <div class="cabecalho-linha-semana">
            <h2 class="nome-sala">${escapeHTML(s.nome)}</h2>
            <span class="tipo-sala">${escapeHTML(s.tipo || "Sala")}</span>
          </div>
          <div class="grade-dias-semana">${diasHTML}</div>
        </div>
      `;
    }).filter(Boolean).join("");

    return rows
      ? `<div class="lista-semana">${rows}</div>`
      : `<div class="vazio-painel">Nenhum resultado com os filtros atuais.</div>`;
  }

  /* =====================================================
     FERIADOS (BrasilAPI) - cache por ano
  ===================================================== */
  const feriadosCache = new Map(); // year -> Map(dateISO -> {name,type})

  async function ensureFeriadosBR(ano) {
    if (feriadosCache.has(ano)) return feriadosCache.get(ano);

    const url = `https://brasilapi.com.br/api/feriados/v1/${ano}`;
    const r = await fetch(url);
    if (!r.ok) throw new Error("Falha ao carregar feriados (BrasilAPI)");

    const arr = await r.json();
    const map = new Map();
    for (const h of arr) map.set(h.date, { name: h.name, type: h.type });

    feriadosCache.set(ano, map);
    return map;
  }

  function getFeriado(dateISO) {
    const ano = Number(dateISO.slice(0, 4));
    return feriadosCache.get(ano)?.get(dateISO) || null;
  }

  /* =====================================================
     RENDER: MÊS + PAINEL DIREITO
  ===================================================== */
  function renderPainelDia(dateISO) {
    const salasById = new Map(appState.data.salas.map(s => [s.id, s]));
    const ag = appState.data.agendamentos.filter(a => a.data === dateISO);

    if (!ag.length) {
      return `
        <div class="data-painel">${formatDateBR(dateISO)}</div>
        <div class="subtitulo-painel">0 agendamentos</div>
        <div class="vazio-painel">Nenhum agendamento para este dia.</div>
      `;
    }

    const order = { matutino: 0, vespertino: 1, noturno: 2 };
    ag.sort((a,b) => (order[a.turno] ?? 9) - (order[b.turno] ?? 9));

    const itens = ag.map(a => {
      const sala = salasById.get(a.salaId) || { nome: `Sala ${a.salaId}`, tipo:"Sala" };
      return `
        <div class="item-painel">
          <div class="topo-item-painel">
            <span class="etiqueta-turno">${turnoRotulo(a.turno)}</span>
            <span class="sala-item-painel" title="${escapeHTML(sala.nome)}">${escapeHTML(sala.nome)} • ${escapeHTML(sala.tipo||"Sala")}</span>
          </div>
          <div class="professor-item-painel">${escapeHTML(a.professor || "—")}</div>
          <div class="meta-item-painel">${escapeHTML(a.curso || "—")}${a.codigoTurma ? ` • ${escapeHTML(a.codigoTurma)}` : ""}</div>
        </div>
      `;
    }).join("");

    return `
      <div class="data-painel">${formatDateBR(dateISO)}</div>
      <div class="subtitulo-painel">${ag.length} agendamento(s)</div>
      <div class="lista-painel">${itens}</div>
    `;
  }

  function renderMonth(dateISO) {
    if (!appState.monthCursorISO) appState.monthCursorISO = startOfMonthISO(dateISO);
    if (!appState.monthSelectedDate) appState.monthSelectedDate = dateISO;

    const first = appState.monthCursorISO;
    const { gridDays } = monthGrid(first);

    ensureFeriadosBR(Number(first.slice(0, 4))).catch(() => {});
    const titulo = new Date(first + "T00:00:00").toLocaleString("pt-BR", { month:"long", year:"numeric" });

    const head = ["Dom","Seg","Ter","Qua","Qui","Sex","Sáb"].map(x => `<div>${x}</div>`).join("");

    const cells = gridDays.map(d => {
      const fora = d.slice(0,7) !== first.slice(0,7) ? " fora-mes" : "";
      const sel = d === appState.monthSelectedDate ? " selecionado" : "";

      const dayNum = new Date(d + "T00:00:00").getDate();
      const feriado = getFeriado(d);

      if (feriado) {
        return `
          <button type="button" class="celula-dia feriado${fora}" disabled>
            <div class="numero-dia">${dayNum}</div>
            <div class="etiqueta-feriado" title="${escapeHTML(feriado.name)}">${escapeHTML(feriado.name)}</div>
          </button>
        `;
      }

      const total = appState.data.salas.length * (enabledTurnosCount() || 1);
      let ocupados = 0;
      for (const a of appState.data.agendamentos) {
        if (a.data === d && appState.filtros.turnos[a.turno]) ocupados++;
      }
      const pct = total ? Math.round((ocupados / total) * 100) : 0;

      return `
        <button type="button" class="celula-dia${fora}${sel}" data-date="${d}">
          <div class="numero-dia">${dayNum}</div>
          <div class="barra-ocupacao" style="--busy-pct:${pct}"></div>
          <div class="texto-ocupacao">${ocupados}/${total} ocupados</div>
        </button>
      `;
    }).join("");

    return `
      <div class="mes-dividido">
        <div class="mes">
          <div class="navegacao-mes">
            <button class="botao-navegacao-mes" type="button" data-act="month-prev">‹</button>
            <div class="titulo-navegacao-mes">${escapeHTML(titulo)}</div>
            <button class="botao-navegacao-mes" type="button" data-act="month-next">›</button>
          </div>

          <div class="cabecalho-dias-mes">${head}</div>
          <div class="grade-dias-mes">${cells}</div>
        </div>

        <aside class="painel-dia" data-role="painel-dia">
          ${renderPainelDia(appState.monthSelectedDate)}
        </aside>
      </div>
    `;
  }

  function render(view, dateISO) {
    if (view === "day") return renderDay(dateISO);
    if (view === "week") return renderWeek(dateISO);
    if (view === "month") return renderMonth(dateISO);
    return `<div></div>`;
  }

  /* =====================================================
     SUBTÍTULO
  ===================================================== */
  function setSubtitle(view, dateISO) {
    const dt = new Date(dateISO + "T00:00:00");
    const month = dt.toLocaleString("pt-BR", { month: "long" });
    const year = dt.getFullYear();
    const day = dt.getDate();

    if (view === "day") subtituloEl.textContent = `Hoje • ${day} de ${capitalize(month)} de ${year}`;
    if (view === "week") subtituloEl.textContent = `Semana • a partir de ${formatDateBR(startOfWeekISO(dateISO))}`;
    if (view === "month") subtituloEl.textContent = `Mês • ${capitalize(month)} de ${year}`;
  }

  /* =====================================================
     ALTURA DO PALCO
  ===================================================== */
  function measureViewHeight(viewEl) {
    const child = viewEl.firstElementChild;
    const h1 = viewEl.scrollHeight || 0;
    const h2 = Math.ceil(viewEl.getBoundingClientRect().height || 0);
    const h3 = child ? Math.ceil(child.getBoundingClientRect().height || 0) : 0;
    return Math.max(h1, h2, h3, 120);
  }

  function setStageHeight(viewEl, animate = true) {
    const h = measureViewHeight(viewEl);
    if (!animate) palco.style.transition = "none";
    palco.style.height = h + "px";
    if (!animate) {
      palco.offsetHeight;
      palco.style.transition = "";
    }
  }

  function rerenderCurrent() {
    const current = palco.querySelector(".tela");
    if (!current) return;

    current.innerHTML = render(appState.view, appState.date);
    setSubtitle(appState.view, appState.date);
    requestAnimationFrame(() => setStageHeight(current, true));
  }

  /* =====================================================
     TROCA DE VIEW (animação)
  ===================================================== */
  function viewIndex(v) { return ORDEM_VIEWS.indexOf(v); }

  function setActiveButton(view) {
    botoesView.forEach((b) => {
      const active = b.dataset.view === view;
      b.classList.toggle("ativo", active);
      b.setAttribute("aria-selected", active ? "true" : "false");
    });
  }

  function switchView(nextView) {
    const prevView = appState.view;
    if (nextView === prevView) return;

    if (nextView === "month") {
      appState.monthCursorISO = startOfMonthISO(appState.date);
      appState.monthSelectedDate = appState.date;
      ensureFeriadosBR(Number(appState.monthCursorISO.slice(0, 4))).catch(() => {});
    }

    const forward = viewIndex(nextView) > viewIndex(prevView);
    const enterClass = forward ? "entra-da-direita" : "entra-da-esquerda";
    const exitClass = forward ? "sai-para-esquerda" : "sai-para-direita";

    const from = palco.querySelector(".tela");
    const to = document.createElement("div");
    to.className = `tela ${enterClass}`;
    to.innerHTML = render(nextView, appState.date);
    palco.appendChild(to);

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        setStageHeight(to, true);
        if (from) from.classList.add(exitClass);
        to.classList.add("tela-ativa");
        to.classList.remove(enterClass);
      });
    });

    const onEnd = (e) => {
      if (e.propertyName !== "transform") return;
      to.removeEventListener("transitionend", onEnd);
      if (from && from.parentNode) from.remove();
      to.classList.add("tela-ativa");
    };
    to.addEventListener("transitionend", onEnd);

    appState.view = nextView;
    setActiveButton(nextView);
    setSubtitle(nextView, appState.date);
  }

  function setBaseDateISO(nextISO) {
    if (!nextISO) return;
    appState.date = nextISO;

    // Mantém estado do mês coerente quando estiver no mês
    if (appState.view === "month") {
      appState.monthCursorISO = startOfMonthISO(appState.date);
      appState.monthSelectedDate = appState.date;
      ensureFeriadosBR(Number(appState.monthCursorISO.slice(0, 4))).catch(() => {});
    }

    setSubtitle(appState.view, appState.date);
    rerenderCurrent();
  }

  function mountFirst() {
    palco.innerHTML = "";
    const first = document.createElement("div");
    first.className = "tela tela-ativa";
    first.innerHTML = render(appState.view, appState.date);
    palco.appendChild(first);

    setActiveButton(appState.view);
    setSubtitle(appState.view, appState.date);
    requestAnimationFrame(() => setStageHeight(first, false));
  }

  /* =====================================================
     MENU MOBILE (sidebar)
  ===================================================== */
  function setMenuLateralAberto(open) {
    document.body.classList.toggle("menu-lateral-aberto", open);
    botaoMenu?.setAttribute("aria-expanded", open ? "true" : "false");
  }

  /* =====================================================
     EVENTOS
  ===================================================== */
  function bindEvents() {
    // troca view
    botoesView.forEach((btn) =>
      btn.addEventListener("click", () => switchView(btn.dataset.view))
    );

    // data base do mapa
    inputDataMapa?.addEventListener("change", () => {
      const iso = inputDataMapa.value;
      setBaseDateISO(iso);
    });

    // abrir filtros
    botaoFiltro?.addEventListener("click", abrirModalFiltros);

    // menu usuário (sair)
    botaoUsuario?.addEventListener("click", (e) => {
      e.stopPropagation();
      abrirFecharMenuUsuario();
    });

    // menu lateral mobile
    botaoMenu?.addEventListener("click", (e) => {
      e.stopPropagation();
      setMenuLateralAberto(!document.body.classList.contains("menu-lateral-aberto"));
    });
    overlayMobile?.addEventListener("click", () => setMenuLateralAberto(false));

    // ESC fecha: sidebar + modal + menu usuário
    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      setMenuLateralAberto(false);
      fecharModalFiltros();
      if (menuUsuarioEl?.classList.contains("aberto")) {
        menuUsuarioEl.classList.remove("aberto");
        botaoUsuario?.setAttribute("aria-expanded", "false");
      }
    });

    // clique dentro do palco (mês)
    palco.addEventListener("click", async (e) => {
      if (appState.view !== "month") return;

      const act = e.target.closest("[data-act]")?.dataset.act;
      if (act === "month-prev" || act === "month-next") {
        const delta = act === "month-prev" ? -1 : 1;
        appState.monthCursorISO = addMonthsISO(appState.monthCursorISO, delta);
        await ensureFeriadosBR(Number(appState.monthCursorISO.slice(0, 4))).catch(() => {});
        if (appState.monthSelectedDate.slice(0,7) !== appState.monthCursorISO.slice(0,7)) {
          appState.monthSelectedDate = appState.monthCursorISO;
        }
        rerenderCurrent();
        return;
      }

      const cell = e.target.closest(".celula-dia[data-date]");
      if (!cell || cell.disabled) return;

      const d = cell.dataset.date;
      await ensureFeriadosBR(Number(d.slice(0, 4))).catch(() => {});
      appState.monthSelectedDate = d;

      const current = palco.querySelector(".tela");
      if (!current) return;

      const prev = current.querySelector(".celula-dia.selecionado");
      if (prev) prev.classList.remove("selecionado");
      cell.classList.add("selecionado");
      cell.blur();

      const painel = current.querySelector('[data-role="painel-dia"]');
      if (painel) painel.innerHTML = renderPainelDia(d);

      setStageHeight(current, true);
    });

    window.addEventListener("resize", () => {
      const current = palco.querySelector(".tela");
      if (!current) return;
      setStageHeight(current, false);
    });

    setInterval(() => {
      if (appState.view === "day") rerenderCurrent();
    }, 60_000);
  }

  /* =====================================================
     RELÓGIO
  ===================================================== */
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

  /* =====================================================
     INIT
  ===================================================== */
  async function init() {
    appState.data = await carregarDados();
    appState.idx = buildIndex(appState.data.agendamentos);

    await ensureFeriadosBR(new Date().getFullYear()).catch(() => {});
    atualizarIndicadorFiltro();

    // sincroniza o calendário com a data base
    if (inputDataMapa) inputDataMapa.value = appState.date;

    mountFirst();
    bindEvents();
    startClock();
  }

  /* =====================================================
     UTILS
  ===================================================== */
  function slotKey(salaId, dataISO, turno) {
    return `${salaId}|${dataISO}|${turno}`;
  }

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

  function escapeHTML(str) {
    return String(str ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function capitalize(s) {
    const str = String(s || "");
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function formatDateBR(dateISO) {
    const [y, m, d] = dateISO.split("-");
    return `${d}/${m}/${y}`;
  }

  function turnoRotulo(turnoId) {
    return TURNOS.find(t => t.id === turnoId)?.rotulo || turnoId;
  }

  function deepClone(obj) {
    if (typeof structuredClone === "function") return structuredClone(obj);
    return JSON.parse(JSON.stringify(obj));
  }

  init();
})();
