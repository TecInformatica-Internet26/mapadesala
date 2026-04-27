(() => {
  "use strict";

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

  function setSubtitle(subtituloEl, state, util) {
    if (!subtituloEl) return;

    const dt = new Date(state.date + "T00:00:00");
    const month = dt.toLocaleString("pt-BR", { month: "long" });
    const year = dt.getFullYear();
    const day = dt.getDate();

    if (state.view === "day") {
      const d0 = new Date();
      const hojeISO = `${d0.getFullYear()}-${String(d0.getMonth() + 1).padStart(2, "0")}-${String(d0.getDate()).padStart(2, "0")}`;
      const prefix = (state.date === hojeISO) ? "Hoje" : "Dia";
      subtituloEl.textContent = `${prefix} • ${day} de ${capitalize(month)} de ${year}`;
    }
    if (state.view === "week") subtituloEl.textContent = `Semana • a partir de ${formatDateBR(util.startOfWeekISO(state.date))}`;
    if (state.view === "month") subtituloEl.textContent = `Mês • ${capitalize(month)} de ${year}`;
    if (state.view === "year") subtituloEl.textContent = `Ano • ${year}`;
  }

  function measureViewHeight(viewEl) {
    const child = viewEl.firstElementChild;
    const h1 = viewEl.scrollHeight || 0;
    const h2 = Math.ceil(viewEl.getBoundingClientRect().height || 0);
    const h3 = child ? Math.ceil(child.getBoundingClientRect().height || 0) : 0;
    return Math.max(h1, h2, h3, 120);
  }

  function setStageHeight(palco, viewEl, animate = true) {
    if (!palco || !viewEl) return;
    const h = measureViewHeight(viewEl);

    if (!animate) palco.style.transition = "none";
    palco.style.height = h + "px";
    if (!animate) {
      palco.offsetHeight;
      palco.style.transition = "";
    }
  }

  function turnoRotulo(turnoId, TURNOS) {
    return TURNOS.find((t) => t.id === turnoId)?.rotulo || turnoId;
  }

  function enabledTurnosCount(state) {
    return Object.values(state.filtros.turnos).filter(Boolean).length || 0;
  }

  function getSalasVisiveis(state) {
    const salaId = String(state.filtros?.salaId ?? "all");
    const salas = Array.isArray(state.data?.salas) ? state.data.salas : [];

    if (salaId === "all") return salas;
    return salas.filter((s) => String(s.id) === salaId);
  }

  function isFiltrosPadrao(state) {
    const f = state.filtros;
    return (
      f.status === "all" &&
      String(f.salaId ?? "all") === "all" &&
      !String(f.professor || "").trim() &&
      f.turnos.matutino &&
      f.turnos.vespertino &&
      f.turnos.noturno
    );
  }

  function slotVisible(state, booking, turnoId, normFn) {
    const f = state.filtros;

    if (!f.turnos[turnoId]) return false;

    const q = normFn(f.professor);
    const hasProf = !!q;

    if (hasProf && f.status === "livre") return false;

    if (hasProf) {
      if (!booking) return false;
      return normFn(booking.professor).includes(q);
    }

    if (f.status === "ocupada") return !!booking;
    if (f.status === "livre") return !booking;
    return true;
  }

  // =======================
  // DAY
  // =======================
  function renderDay(state, util, deps) {
    const { TURNOS, getBooking, isPastTurno, isNowTurno, norm, isFeriado, getFeriadoInfo } = deps;

    const isTodayFeriado = isFeriado(state.date);
    const feriadoInfo = getFeriadoInfo(state.date);

    if (isTodayFeriado && feriadoInfo) {
      return `
        <div class="vazio-painel feriado-info">
          <div style="font-size: 20px; margin-bottom: 10px;">🎉 ${escapeHTML(feriadoInfo.nome)}</div>
          <div>Hoje é feriado - não há aulas programadas</div>
        </div>
      `;
    }

    const cards = getSalasVisiveis(state)
      .map((s) => {
        const linhas = TURNOS
          .filter((t) => state.filtros.turnos[t.id])
          .map((t) => {
            const booking = getBooking(s.id, state.date, t.id);
            if (!slotVisible(state, booking, t.id, norm)) return "";

            const statusClass = booking ? "status--ocupada" : "status--livre";
            const badge = booking
              ? `<span class="selo ocupada"><span class="ponto"></span>Ocupada</span>`
              : `<span class="selo livre"><span class="ponto"></span>Livre</span>`;

            const meta = booking
              ? `${escapeHTML(booking.professor)} • ${escapeHTML(booking.curso)}`
              : "Sem agendamento";

            const passado = isPastTurno(t.id, state.date) ? " estado-passado" : "";
            const agora = isNowTurno(t.id, state.date) ? " estado-agora" : "";

            return `
              <div class="linha-turno ${statusClass}${passado}${agora}">
                <span class="etiqueta-turno">${escapeHTML(t.rotulo)}</span>
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
      })
      .filter(Boolean)
      .join("");

    return cards
      ? `<div class="grade-salas">${cards}</div>`
      : `<div class="vazio-painel">Nenhum resultado com os filtros atuais.</div>`;
  }

  // =======================
  // WEEK
  // =======================
  function renderWeek(state, util, deps) {
    const { TURNOS, getBooking, isFeriado } = deps;

    const inicio = util.startOfWeekISO(state.date);
    const dias = Array.from({ length: 7 }, (_, i) => util.shiftISO(inicio, i));
    const dow = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];

    const rows = getSalasVisiveis(state)
      .map((s) => {
        let temMatch = false;

        const diasHTML = dias
          .map((d) => {
            const dt = new Date(d + "T00:00:00");
            const diaNum = dt.getDate();
            const diaDow = dow[dt.getDay()];
            const ehFeriado = isFeriado(d);

            const bars = TURNOS
              .filter((t) => state.filtros.turnos[t.id])
              .map((t) => {
                const booking = getBooking(s.id, d, t.id);

                const cls = ehFeriado ? "apagado" :
                  state.filtros.status === "ocupada"
                    ? booking ? "ocupada" : "apagado"
                    : state.filtros.status === "livre"
                      ? !booking ? "livre" : "apagado"
                      : booking ? "ocupada" : "livre";

                if (cls !== "apagado") temMatch = true;

                return `<span class="barra-turno-semana ${cls}" title="${escapeHTML(t.rotulo)}${ehFeriado ? " (Feriado)" : ""}"></span>`;
              })
              .join("");

            return `
              <div class="card-dia-semana ${ehFeriado ? "dia-feriado" : ""}">
                <div class="topo-dia-semana">
                  <span class="numero-dia-semana">${diaNum}</span>
                  <span class="sigla-dia-semana">${diaDow}${ehFeriado ? " 🎉" : ""}</span>
                </div>
                <div class="barras-turnos-semana">${bars}</div>
              </div>
            `;
          })
          .join("");

        if (!isFiltrosPadrao(state) && !temMatch) return "";

        return `
          <div class="linha-semana">
            <div class="cabecalho-linha-semana">
              <h2 class="nome-sala">${escapeHTML(s.nome)}</h2>
              <span class="tipo-sala">${escapeHTML(s.tipo || "Sala")}</span>
            </div>
            <div class="grade-dias-semana">${diasHTML}</div>
          </div>
        `;
      })
      .filter(Boolean)
      .join("");

    return rows
      ? `<div class="lista-semana">${rows}</div>`
      : `<div class="vazio-painel">Nenhum resultado com os filtros atuais.</div>`;
  }

  // =======================
  // MONTH
  // =======================
  function renderPainelDia(state, deps, dateISO) {
    const { TURNOS, isFeriado, getFeriadoInfo } = deps;

    if (isFeriado(dateISO)) {
      const feriadoInfo = getFeriadoInfo(dateISO);
      return `
        <div class="data-painel">${formatDateBR(dateISO)}</div>
        <div class="subtitulo-painel">🎉 Feriado</div>
        <div class="vazio-painel feriado-info">
          <div style="font-size: 18px; margin-bottom: 8px;">${escapeHTML(feriadoInfo?.nome || "Feriado")}</div>
          <div>Não há aulas programadas para este dia</div>
        </div>
      `;
    }

    const salasById = new Map(state.data.salas.map((s) => [s.id, s]));

    const salaIdFiltro = String(state.filtros?.salaId ?? "all");
    const ag = state.data.agendamentos.filter((a) => {
      if (a.data !== dateISO) return false;
      if (salaIdFiltro === "all") return true;
      return String(a.salaId) === salaIdFiltro;
    });

    if (!ag.length) {
      return `
        <div class="data-painel">${formatDateBR(dateISO)}</div>
        <div class="subtitulo-painel">0 agendamentos</div>
        <div class="vazio-painel">Nenhum agendamento para este dia.</div>
      `;
    }

    const order = { matutino: 0, vespertino: 1, noturno: 2 };
    ag.sort((a, b) => (order[a.turno] ?? 9) - (order[b.turno] ?? 9));

    const itens = ag
      .map((a) => {
        const sala = salasById.get(a.salaId) || { nome: `Sala ${a.salaId}`, tipo: "Sala" };
        return `
          <div class="item-painel">
            <div class="topo-item-painel">
              <span class="etiqueta-turno">${escapeHTML(turnoRotulo(a.turno, TURNOS))}</span>
              <span class="sala-item-painel" title="${escapeHTML(sala.nome)}">${escapeHTML(sala.nome)} • ${escapeHTML(sala.tipo || "Sala")}</span>
            </div>
            <div class="professor-item-painel">${escapeHTML(a.professor || "—")}</div>
            <div class="meta-item-painel">${escapeHTML(a.curso || "—")}${a.codigoTurma ? ` • ${escapeHTML(a.codigoTurma)}` : ""}</div>
          </div>
        `;
      })
      .join("");

    return `
      <div class="data-painel">${formatDateBR(dateISO)}</div>
      <div class="subtitulo-painel">${ag.length} agendamento(s)</div>
      <div class="lista-painel">${itens}</div>
    `;
  }

  function renderMonth(state, util, deps) {
    const { TURNOS, isFeriado, getFeriadoInfo } = deps;

    if (!state.monthCursorISO) state.monthCursorISO = util.startOfMonthISO(state.date);
    if (!state.monthSelectedDate) state.monthSelectedDate = state.date;

    const first = state.monthCursorISO;
    const { gridDays } = util.monthGrid(first);

    const titulo = new Date(first + "T00:00:00").toLocaleString("pt-BR", { month: "long", year: "numeric" });
    const head = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"].map((x) => `<div>${x}</div>`).join("");

    // ✅ Sala selecionada (quando o filtro de sala estiver ativo)
    const salaSelecionadaId = String(state.filtros?.salaId ?? "all");
    const salaSelecionada =
      salaSelecionadaId === "all"
        ? null
        : (Array.isArray(state.data?.salas) ? state.data.salas : []).find((s) => String(s.id) === salaSelecionadaId);

    // Identificar salas físicas (não externas)
    const salasFisicasIds = new Set();
    state.data.salas.forEach((sala) => {
      const nome = String(sala.nome || "").toLowerCase();
      const tipo = String(sala.tipo || "").toLowerCase();

      if (!nome.includes("externo") && !nome.includes("externa") && !tipo.includes("externo")) {
        salasFisicasIds.add(sala.id);
      }
    });
    const totalSalasFisicas = salasFisicasIds.size;

    function contarOcupacaoPorTurno(dataISO) {
      const ocupacaoPorTurno = { matutino: 0, vespertino: 0, noturno: 0 };
      const slotsOcupados = new Set();

      for (const a of state.data.agendamentos) {
        if (a.data === dataISO && salasFisicasIds.has(a.salaId)) {
          const slotKey = `${a.salaId}|${a.turno}`;
          if (!slotsOcupados.has(slotKey)) {
            slotsOcupados.add(slotKey);
            ocupacaoPorTurno[a.turno]++;
          }
        }
      }
      return ocupacaoPorTurno;
    }

    function getCorTurno(ocupados, totalSalas) {
      const pct = totalSalas ? (ocupados / totalSalas) * 100 : 0;
      if (pct >= 70) return "alta";
      if (pct >= 30) return "media";
      return "baixa";
    }

    const rotuloTurnoCurto = (t) =>
      t === "matutino" ? "Manhã" : t === "vespertino" ? "Tarde" : "Noite";

    const cells = gridDays
      .map((d) => {
        const fora = d.slice(0, 7) !== first.slice(0, 7) ? " fora-mes" : "";
        const sel = d === state.monthSelectedDate ? " selecionado" : "";

        const dayNum = new Date(d + "T00:00:00").getDate();

        // Feriado (sempre desabilita)
        if (isFeriado(d)) {
          const feriadoInfo = getFeriadoInfo(d);
          return `
            <button type="button" class="celula-dia${fora}${sel} feriado" data-date="${d}" disabled>
              <div class="numero-dia">${dayNum}</div>
              <div class="etiqueta-feriado" title="${feriadoInfo ? escapeHTML(feriadoInfo.nome) : "Feriado"}">
                🎉 ${feriadoInfo ? escapeHTML(feriadoInfo.nome) : "Feriado"}
              </div>
            </button>
          `;
        }

        // ✅ MODO: SALA ESPECÍFICA
        if (salaSelecionada) {
          const turnos = ["matutino", "vespertino", "noturno"];

          const bars = turnos
            .map((t) => {
              const habilitado = !!state.filtros.turnos[t];

              const booking = (state.data.agendamentos || []).find(
                (a) =>
                  String(a.salaId) === String(salaSelecionada.id) &&
                  a.data === d &&
                  a.turno === t
              );

              const clsBase = booking ? "ocupada" : "livre";
              const cls = habilitado ? clsBase : "apagado";

              const statusTxt = booking ? "Ocupada" : "Livre";

              // ✅ inclui turno na classe (matutino/vespertino/noturno)
              return `<div class="mini-barra ${t} ${cls}" data-turno="${t}" title="${rotuloTurnoCurto(t)}: ${statusTxt}"></div>`;
            })
            .join("");

          return `
            <button type="button" class="celula-dia${fora}${sel}" data-date="${d}">
              <div class="numero-dia">${dayNum}</div>
              <div class="mini-barras-turnos sala-especifica">
                ${bars}
              </div>
            </button>
          `;
        }

        // ✅ MODO: GERAL (todas as salas físicas)
        const ocupacao = contarOcupacaoPorTurno(d);

        const corManha = getCorTurno(ocupacao.matutino, totalSalasFisicas);
        const corTarde = getCorTurno(ocupacao.vespertino, totalSalasFisicas);
        const corNoite = getCorTurno(ocupacao.noturno, totalSalasFisicas);

        const pctManha = totalSalasFisicas ? Math.round((ocupacao.matutino / totalSalasFisicas) * 100) : 0;
        const pctTarde = totalSalasFisicas ? Math.round((ocupacao.vespertino / totalSalasFisicas) * 100) : 0;
        const pctNoite = totalSalasFisicas ? Math.round((ocupacao.noturno / totalSalasFisicas) * 100) : 0;

        const miniBarras = `
          <div class="mini-barras-turnos">
            <div class="mini-barra matutino ${corManha}" data-turno="matutino" data-ocupados="${ocupacao.matutino}" data-total="${totalSalasFisicas}" data-pct="${pctManha}"></div>
            <div class="mini-barra vespertino ${corTarde}" data-turno="vespertino" data-ocupados="${ocupacao.vespertino}" data-total="${totalSalasFisicas}" data-pct="${pctTarde}"></div>
            <div class="mini-barra noturno ${corNoite}" data-turno="noturno" data-ocupados="${ocupacao.noturno}" data-total="${totalSalasFisicas}" data-pct="${pctNoite}"></div>
          </div>
        `;

        const contadores = `
          <div class="contadores-turnos">
            <div class="contador-turno matutino" data-cor="${corManha}">
              <span class="contador-numero">${ocupacao.matutino}</span>
              <span class="contador-porcento">${pctManha}%</span>
            </div>
            <div class="contador-turno vespertino" data-cor="${corTarde}">
              <span class="contador-numero">${ocupacao.vespertino}</span>
              <span class="contador-porcento">${pctTarde}%</span>
            </div>
            <div class="contador-turno noturno" data-cor="${corNoite}">
              <span class="contador-numero">${ocupacao.noturno}</span>
              <span class="contador-porcento">${pctNoite}%</span>
            </div>
          </div>
        `;

        return `
          <button type="button" class="celula-dia${fora}${sel}" data-date="${d}"
            title="Manhã: ${ocupacao.matutino}/${totalSalasFisicas} (${pctManha}%) | Tarde: ${ocupacao.vespertino}/${totalSalasFisicas} (${pctTarde}%) | Noite: ${ocupacao.noturno}/${totalSalasFisicas} (${pctNoite}%)">
            <div class="numero-dia">${dayNum}</div>
            ${miniBarras}
            ${contadores}
          </button>
        `;
      })
      .join("");

    const legenda = salaSelecionada
      ? `
        <div class="legenda-ocupacao legenda-sala-especifica">
          <div class="item-legenda">
            <span class="bolinha-legenda livre"></span>
            Livre
          </div>
          <div class="item-legenda">
            <span class="bolinha-legenda ocupada"></span>
            Ocupada
          </div>
          <div class="item-legenda">
            <span class="bolinha-legenda apagado"></span>
            Turno desativado
          </div>
          <div class="item-legenda" style="margin-left: auto;">
            <span style="font-size: 14px; color: #6b7280;">
              Sala: ${escapeHTML(salaSelecionada.nome)}
            </span>
          </div>
        </div>
      `
      : `
        <div class="legenda-ocupacao">
          <div class="item-legenda">
            <span class="bolinha-legenda baixa"></span>
            Baixa (0-29%)
          </div>
          <div class="item-legenda">
            <span class="bolinha-legenda media"></span>
            Média (30-69%)
          </div>
          <div class="item-legenda">
            <span class="bolinha-legenda alta"></span>
            Alta (70-100%)
          </div>
          <div class="item-legenda" style="margin-left: auto;">
            <span style="font-size: 10px; color: #6b7280;">
              ${totalSalasFisicas} salas físicas
            </span>
          </div>
        </div>
      `;

    return `
      <div class="mes-dividido">
        <div class="mes">
          <div class="navegacao-mes">
            <button class="botao-navegacao-mes" type="button" data-act="month-prev">‹</button>
            <div class="titulo-navegacao-mes">${escapeHTML(capitalize(titulo))}</div>
            <button class="botao-navegacao-mes" type="button" data-act="month-next">›</button>
          </div>

          <div class="cabecalho-dias-mes">${head}</div>
          <div class="grade-dias-mes">${cells}</div>
          ${legenda}
        </div>

        <aside class="painel-dia" data-role="painel-dia">
          ${renderPainelDia(state, deps, state.monthSelectedDate)}
        </aside>
      </div>
    `;
  }

  // =======================
  // YEAR
  // =======================
  function renderYear(state, util, deps) {
    const year = new Date(state.date + "T00:00:00").getFullYear();
    const meses = Array.from({ length: 12 }, (_, i) => {
      const d = new Date(year, i, 1);
      const mesISO = `${year}-${String(i + 1).padStart(2, "0")}`;
      const nome = d.toLocaleString("pt-BR", { month: "long" });
      return { mesISO, nome: capitalize(nome) };
    });

    // filtros relevantes pra contagem
    const salaIdFiltro = String(state.filtros?.salaId ?? "all");
    const qProf = (deps?.norm ? deps.norm(state.filtros?.professor) : String(state.filtros?.professor || "").toLowerCase());
    const turnosAtivos = state.filtros?.turnos || { matutino: true, vespertino: true, noturno: true };

    const ag = Array.isArray(state.data?.agendamentos) ? state.data.agendamentos : [];

    function contaMes(mesISO) {
      // conta agendamentos (ocupadas) do mês, respeitando sala/professor/turnos
      let total = 0;
      for (const a of ag) {
        if (!a?.data || String(a.data).slice(0, 7) !== mesISO) continue;
        if (salaIdFiltro !== "all" && String(a.salaId) !== salaIdFiltro) continue;
        if (!turnosAtivos[a.turno]) continue;
        if (qProf) {
          const ap = deps?.norm ? deps.norm(a.professor) : String(a.professor || "").toLowerCase();
          if (!ap.includes(qProf)) continue;
        }
        total++;
      }
      return total;
    }

    const cards = meses
      .map(({ mesISO, nome }) => {
        const qtd = contaMes(mesISO);
        return `
          <button type="button" class="card-mes-ano" data-act="year-month" data-month="${mesISO}" aria-label="Abrir ${escapeHTML(nome)}">
            <div class="titulo-mes-ano">${escapeHTML(nome)}</div>
            <div class="meta-mes-ano">${qtd} agendamento(s)</div>
          </button>
        `;
      })
      .join("");

    return `
      <div class="ano">
        <div class="navegacao-ano">
          <button class="botao-navegacao-mes" type="button" data-act="year-prev" aria-label="Ano anterior">‹</button>
          <div class="titulo-navegacao-mes">${year}</div>
          <button class="botao-navegacao-mes" type="button" data-act="year-next" aria-label="Próximo ano">›</button>
        </div>

        <div class="grade-ano">
          ${cards}
        </div>

        <div class="nota-ano">Clique em um mês para abrir a visualização mensal.</div>
      </div>
    `;
  }

  function renderHTML(state, util, deps) {
    if (state.view === "day") return renderDay(state, util, deps);
    if (state.view === "week") return renderWeek(state, util, deps);
    if (state.view === "year") return renderYear(state, util, deps);
    return renderMonth(state, util, deps);
  }

  function animateSwap(palco, html, direction) {
    const from = palco.querySelector(".tela");
    const to = document.createElement("div");

    const enterClass = direction === "forward" ? "entra-da-direita" : "entra-da-esquerda";
    const exitClass = direction === "forward" ? "sai-para-esquerda" : "sai-para-direita";

    to.className = `tela ${enterClass}`;
    to.innerHTML = html;
    palco.appendChild(to);

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        setStageHeight(palco, to, true);
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
  }

  function mountFirst(palco, html) {
    palco.innerHTML = "";
    const first = document.createElement("div");
    first.className = "tela tela-ativa";
    first.innerHTML = html;
    palco.appendChild(first);
    requestAnimationFrame(() => setStageHeight(palco, first, false));
  }

  window.MapaSalaRender = {
    escapeHTML,
    renderHTML,
    mountFirst,
    animateSwap,
    setStageHeight,
    setSubtitle,
    renderPainelDia,
  };
})();
