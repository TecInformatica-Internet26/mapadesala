(() => {
  "use strict";

  const API_URL = "../api/mapa_salas.php";

  let cache = {
    start: null,
    end: null,
    salas: [],
    agendamentos: [],
    feriados: [],
  };

  function rangeCovers(cacheStart, cacheEnd, needStart, needEnd) {
    if (!cacheStart || !cacheEnd) return false;
    return cacheStart <= needStart && cacheEnd >= needEnd;
  }

  async function fetchRange(startISO, endISO) {
    const url = `${API_URL}?start=${encodeURIComponent(startISO)}&end=${encodeURIComponent(endISO)}`;

    const r = await fetch(url, { headers: { Accept: "application/json" } });
    const j = await r.json().catch(() => null);

    if (!r.ok || !j || j.ok === false) {
      throw new Error(j?.error || "Falha ao carregar dados do backend");
    }

    return {
      salas: Array.isArray(j.salas) ? j.salas : [],
      agendamentos: Array.isArray(j.agendamentos) ? j.agendamentos : [],
      feriados: Array.isArray(j.feriados) ? j.feriados : [],
    };
  }

  function getNeededRangeForView(state, util) {
    if (state.view === "day") {
      return { start: state.date, end: state.date };
    }

    if (state.view === "week") {
      const ini = util.startOfWeekISO(state.date);
      const fim = util.shiftISO(ini, 6);
      return { start: ini, end: fim };
    }

    if (state.view === "year") {
      const ini = util.startOfYearISO(state.date);
      const fim = util.shiftISO(util.addYearsISO(ini, 1), -1);
      return { start: ini, end: fim };
    }

    // month
    const first = state.monthCursorISO || util.startOfMonthISO(state.date);
    const { gridDays } = util.monthGrid(first);
    return { start: gridDays[0], end: gridDays[gridDays.length - 1] };
  }

  async function ensureDataForState(state, util) {
    const { start, end } = getNeededRangeForView(state, util);

    // cache cobre o necessário
    if (rangeCovers(cache.start, cache.end, start, end)) {
      return { 
        salas: cache.salas, 
        agendamentos: cache.agendamentos,
        feriados: cache.feriados
      };
    }

    // buffer para evitar fetch toda hora
    const startBuf = util.shiftISO(start, -35);
    const endBuf = util.shiftISO(end, 35);

    const data = await fetchRange(startBuf, endBuf);

    cache = {
      start: startBuf,
      end: endBuf,
      salas: data.salas,
      agendamentos: data.agendamentos,
      feriados: data.feriados,
    };

    return data;
  }

  function clearCache() {
    cache = { 
      start: null, 
      end: null, 
      salas: [], 
      agendamentos: [],
      feriados: []
    };
  }

  window.MapaSalaAPI = {
    ensureDataForState,
    clearCache,
  };
})();