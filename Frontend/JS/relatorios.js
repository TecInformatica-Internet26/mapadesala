// Frontend/JS/relatorios.js

const elTipo = document.getElementById("tipo");
const elProfessor = document.getElementById("professor");
const elSala = document.getElementById("sala");
const elCampoProfessor = document.getElementById("campo-professor");
const elCampoSala = document.getElementById("campo-sala");

const btnGerar = document.getElementById("btn-gerar");
const btnPdf = document.getElementById("btn-pdf");
const elBaseDate = document.getElementById("base-date");


const elStatus = document.getElementById("status");
const elTitulo = document.getElementById("titulo-relatorio");
const elTabelaWrap = document.getElementById("tabela-wrap");

function ymd(d) {
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

// Range atual selecionado pelos chips.
// Padrão: "today" (a data escolhida no calendário vira a base para dia/semana/mês)
let currentRange = "today"; // today | week | month

function getBaseDay() {
  // data base escolhida pelo usuário (YYYY-MM-DD)
  const v = elBaseDate && elBaseDate.value;
  if (v && /^\d{4}-\d{2}-\d{2}$/.test(v)) {
    const [y, m, d] = v.split("-").map(Number);
    return new Date(y, m - 1, d);
  }
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function getRange(kind) {
  const day = getBaseDay();

  if (kind === "today") {
    const v = ymd(day);
    return { start: v, end: v };
  }

  if (kind === "week") {
    const dow = day.getDay(); // 0 dom ... 6 sab
    const diffToMon = (dow + 6) % 7;
    const startD = new Date(day);
    startD.setDate(day.getDate() - diffToMon);
    const endD = new Date(startD);
    endD.setDate(startD.getDate() + 6);
    return { start: ymd(startD), end: ymd(endD) };
  }

  // month
  const startD = new Date(day.getFullYear(), day.getMonth(), 1);
  const endD = new Date(day.getFullYear(), day.getMonth() + 1, 0);
  return { start: ymd(startD), end: ymd(endD) };
}

function formatDateBR(isoYmd) {
  if (!isoYmd || typeof isoYmd !== "string") return "";
  // aceita YYYY-MM-DD
  const m = isoYmd.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return isoYmd;
  return `${m[3]}/${m[2]}/${m[1]}`;
}

function setRange(kind) {
  currentRange = kind;
  document.querySelectorAll(".chip").forEach((c) => {
    c.classList.toggle("ativo", c.dataset.range === kind);
  });
}

// compat: versões anteriores chamavam setActiveRange
function setActiveRange(kind) {
  setRange(kind);
}

function updateCamposVisiveis() {
  const tipo = elTipo.value;
  const precisaProfessor = tipo === "turmas_prof";
  const precisaSala = tipo === "agenda_sala" || tipo === "disponibilidade_sala" || tipo === "ocupacao_sala";

  elCampoProfessor.style.display = precisaProfessor ? "block" : "none";
  elCampoSala.style.display = precisaSala ? "block" : "none";

  // Ocupação de sala: sala é opcional
  if (tipo === "ocupacao_sala") {
    elCampoSala.querySelector("label").textContent = "Sala (opcional)";
  } else {
    elCampoSala.querySelector("label").textContent = "Sala";
  }

}

function setStatus(msg, kind = "info") {
  elStatus.textContent = msg;
  elStatus.dataset.kind = kind;
}

function makeTable(columns, rows) {
  const table = document.createElement("table");
  table.className = "tabela";
  const thead = document.createElement("thead");
  const trh = document.createElement("tr");
  for (const c of columns) {
    const th = document.createElement("th");
    th.textContent = c.label;
    trh.appendChild(th);
  }
  thead.appendChild(trh);
  table.appendChild(thead);

  const tbody = document.createElement("tbody");
  if (!rows.length) {
    const tr = document.createElement("tr");
    const td = document.createElement("td");
    td.colSpan = columns.length;
    td.className = "vazio";
    td.textContent = "Sem dados para o período.";
    tr.appendChild(td);
    tbody.appendChild(tr);
  } else {
    for (const r of rows) {
      const tr = document.createElement("tr");
      for (const c of columns) {
        const td = document.createElement("td");
        const v = r[c.key];
        const val = v === null || v === undefined ? "" : v;
        td.textContent = c.format ? c.format(val, r) : String(val);
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    }
  }
  table.appendChild(tbody);
  return table;
}


function pivotAgendaPorData(rows) {
  const turnosOrder = ["matutino", "vespertino", "noturno"];
  const map = new Map();
  for (const r of rows) {
    const d = r.data;
    if (!map.has(d)) {
      map.set(d, { data: d, matutino: "", vespertino: "", noturno: "" });
    }
    const row = map.get(d);
    const cell = `${r.cod_turma ? r.cod_turma + " - " : ""}${r.turma || ""}${r.professor ? " (" + r.professor + ")" : ""}`.trim();
    const key = (r.turno || "").toLowerCase();
    if (key.includes("mat")) row.matutino = row.matutino ? row.matutino + " / " + cell : cell;
    else if (key.includes("ves")) row.vespertino = row.vespertino ? row.vespertino + " / " + cell : cell;
    else if (key.includes("not")) row.noturno = row.noturno ? row.noturno + " / " + cell : cell;
  }
  const out = Array.from(map.values());
  out.sort((a,b)=>a.data.localeCompare(b.data));
  return out;
}

function eachDateInclusive(startYmd, endYmd, fn) {
  const [sy, sm, sd] = startYmd.split("-").map(Number);
  const [ey, em, ed] = endYmd.split("-").map(Number);
  let d = new Date(sy, sm - 1, sd);
  const end = new Date(ey, em - 1, ed);
  while (d <= end) {
    fn(ymd(d));
    d.setDate(d.getDate() + 1);
  }
}

function buildDisponibilidade(rowsOcupado, start, end) {
  const turnos = ["manha", "tarde", "noite"];
  const labels = { manha: "matutino", tarde: "vespertino", noite: "noturno" };

  const ocupado = new Map();
  for (const r of rowsOcupado) {
    const key = `${r.data}|${r.turno_raw}`;
    ocupado.set(key, true);
  }

  const livres = [];
  eachDateInclusive(start, end, (d) => {
    for (const t of turnos) {
      const key = `${d}|${t}`;
      if (!ocupado.has(key)) {
        livres.push({ data: d, turno: labels[t] });
      }
    }
  });
  return livres;
}

async function gerar() {
  const tipo = elTipo.value;
  const { start, end } = getRange(currentRange);
  const id_professor = elProfessor.value;
  const id_sala = elSala.value;

  // por padrão, só habilita PDF quando o relatório suportar
  if (btnPdf) btnPdf.disabled = true;

  if (tipo === "turmas_prof" && !id_professor) {
    setStatus("Selecione um professor.", "warn");
    return;
  }
  if ((tipo === "agenda_sala" || tipo === "disponibilidade_sala") && !id_sala) {
    setStatus("Selecione uma sala.", "warn");
    return;
  }

  const params = new URLSearchParams({ tipo, start, end });
  if (id_professor) params.set("id_professor", id_professor);
  if (id_sala) params.set("id_sala", id_sala);

  setStatus("Gerando...", "info");
  elTabelaWrap.innerHTML = "";


  try {
    const resp = await fetch(`../api/relatorios.php?${params.toString()}`);
    const data = await resp.json();
    if (!data.ok) throw new Error(data.error || "Falha ao gerar");

    const rows = data.rows || [];
    const meta = data.meta || {};

    // Títulos
    const titles = {
      turmas_prof: "Turmas do professor",
      ocupacao_sala: "Ocupação de sala",
      agenda_sala: "Agenda da sala",
      disponibilidade_sala: "Disponibilidade da sala",
    };
    const tituloFormatado = `${titles[tipo] || "Relatório"} (${formatDateBR(meta.start)} a ${formatDateBR(meta.end)})`;
    elTitulo.textContent = tituloFormatado;

    if (tipo === "turmas_prof") {
      const cols = [
        { key: "professor", label: "Professor" },
        { key: "cod_turma", label: "Código" },
        { key: "nome_turma", label: "Turma" },
        { key: "turno", label: "Turno" },
        { key: "primeiro_encontro", label: "1º encontro", format: formatDateBR },
        { key: "ultimo_encontro", label: "Último", format: formatDateBR },
        { key: "encontros", label: "Encontros" },
        { key: "horas_total", label: "Horas" },
      ];
      elTabelaWrap.appendChild(makeTable(cols, rows));
      setStatus(`${rows.length} turma(s) no período.`, "ok");
      return;
    }

    if (tipo === "ocupacao_sala") {
      const cols = [
        { key: "sala", label: "Sala" },
        { key: "turno", label: "Turno" },
        { key: "turnos_ocupados", label: "Turnos ocupados" },
        { key: "horas_ocupadas", label: "Horas" },
      ];
      elTabelaWrap.appendChild(makeTable(cols, rows));
      setStatus(`${rows.length} linha(s).`, "ok");
      return;
    }

    if (tipo === "agenda_sala") {
      // Turnos em linha: Matutino | Vespertino | Noturno
      const pivot = pivotAgendaPorData(rows);
      const cols = [
        { key: "data", label: "Data", format: formatDateBR },
        { key: "matutino", label: "Matutino" },
        { key: "vespertino", label: "Vespertino" },
        { key: "noturno", label: "Noturno" },
      ];
      elTabelaWrap.appendChild(makeTable(cols, pivot));
      setStatus(`${rows.length} encontro(s) no período.`, "ok");
      if (btnPdf) btnPdf.disabled = false;
      return;
    }

    if (tipo === "disponibilidade_sala") {
      // Compacto: 1 linha por dia, com Matutino | Vespertino | Noturno
      const pivotOcup = pivotAgendaPorData(rows);

      // mapa por data para preencher todos os dias do range
      const map = new Map();
      for (const r of pivotOcup) map.set(r.data, r);

      const compact = [];
      eachDateInclusive(start, end, (d) => {
        const base = map.get(d) || { data: d, matutino: "", vespertino: "", noturno: "" };
        compact.push({
          data: d,
          matutino: base.matutino || "Disponível",
          vespertino: base.vespertino || "Disponível",
          noturno: base.noturno || "Disponível",
        });
      });

      const cols = [
        { key: "data", label: "Data", format: formatDateBR },
        { key: "matutino", label: "Matutino" },
        { key: "vespertino", label: "Vespertino" },
        { key: "noturno", label: "Noturno" },
      ];
      elTabelaWrap.appendChild(makeTable(cols, compact));

      const totalDias = compact.length;
      const ocupados = rows.length;
      setStatus(`Dias: ${totalDias} | Encontros ocupando turnos: ${ocupados}`, "ok");
      if (btnPdf) btnPdf.disabled = false;
      return;
    }

  } catch (e) {
    setStatus(e.message || "Erro ao gerar", "err");
    if (btnPdf) btnPdf.disabled = true;
  }
}


function salvarPdfAtual() {
  if (!elTabelaWrap || !elTabelaWrap.innerHTML.trim()) {
    setStatus("Gere um relatório antes de salvar como PDF.", "warn");
    return;
  }

  // Imprime/gera PDF SEM abrir outra aba: usa um iframe invisível.
  // A caixa de impressão/PDF ainda é do navegador, mas a página atual não navega.
  const iframe = document.createElement("iframe");
  iframe.setAttribute("aria-hidden", "true");
  iframe.style.position = "fixed";
  iframe.style.right = "0";
  iframe.style.bottom = "0";
  iframe.style.width = "0";
  iframe.style.height = "0";
  iframe.style.border = "0";
  document.body.appendChild(iframe);

  const win = iframe.contentWindow;
  const doc = win?.document;
  if (!win || !doc) {
    iframe.remove();
    setStatus("Não foi possível preparar a impressão.", "err");
    return;
  }

  const title = (elTitulo && elTitulo.textContent) ? elTitulo.textContent : "Relatório";
  const tableHtml = elTabelaWrap.innerHTML;

  // Observação: remover a URL do cabeçalho/rodapé é controlado pelo navegador.
  // No Chrome/Edge: desmarque "Cabeçalhos e rodapés" ao imprimir para não aparecer.
  const html = `<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>${escapeHtml(title)}</title>
  <style>
    :root{ color-scheme: light; }
    body{ font-family: Montserrat, Arial, sans-serif; padding: 24px; }
    h1{ font-size: 18px; margin: 0 0 16px; }
    table{ width: 100%; border-collapse: collapse; }
    th, td{ padding: 10px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; font-size: 12px; }
    th{ text-align: left; font-weight: 700; }
    @page { margin: 12mm; }
    @media print{
      body{ padding: 0; }
      a{ color: inherit; text-decoration: none; }
    }
  </style>
</head>
<body>
  <h1>${escapeHtml(title)}</h1>
  ${tableHtml}
</body>
</html>`;

  doc.open();
  doc.write(html);
  doc.close();

  const cleanup = () => setTimeout(() => iframe.remove(), 250);
  win.addEventListener("afterprint", cleanup, { once: true });
  // Fallback: remove mesmo assim.
  setTimeout(cleanup, 3000);

  // Dispara o print
  win.focus();
  setTimeout(() => win.print(), 80);
}

function escapeHtml(str) {
  return String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

// EVENTOS
document.addEventListener("DOMContentLoaded", () => {
  updateCamposVisiveis();
  setActiveRange(currentRange);

  // default: hoje
  if (elBaseDate && !elBaseDate.value) {
    const now = new Date();
    elBaseDate.value = ymd(new Date(now.getFullYear(), now.getMonth(), now.getDate()));
  }

  if (elBaseDate) {
    elBaseDate.addEventListener("change", () => {
      // mudou a data base -> invalida PDF até gerar de novo
      if (btnPdf) btnPdf.disabled = true;
    });
  }

  elTipo.addEventListener("change", () => {
    updateCamposVisiveis();
    if (btnPdf) btnPdf.disabled = true;
  });

  btnGerar.addEventListener("click", (e) => {
    e.preventDefault();
    gerar();
  });

  if (btnPdf) {
    btnPdf.addEventListener("click", (e) => {
      e.preventDefault();
      salvarPdfAtual();
    });
  }

  document.querySelectorAll(".chip[data-range]").forEach((c) => {
    c.addEventListener("click", () => {
      currentRange = c.dataset.range;
      setActiveRange(currentRange);
      if (btnPdf) btnPdf.disabled = true;
    });
  });
});
