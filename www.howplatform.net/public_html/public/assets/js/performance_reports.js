// ========== Helpers ==========
const $  = (s) => document.querySelector(s);
const $$ = (s, root = document) => Array.from(root.querySelectorAll(s));

const swalSafe  = (window.Swal ? Swal : null);
const swalToast = swalSafe
  ? Swal.mixin({
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 2600,
      timerProgressBar: true,
      showCloseButton: true,
    })
  : null;

function toast(message, type = "info") {
  if (swalToast)
    return swalToast.fire({ icon: type, title: String(message ?? "") });
  alert(String(message ?? ""));
}

function escapeHtml(str) {
  str = String(str ?? "");
  return str
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

async function fetchJson(url) {
  const res = await fetch(url, { headers: { Accept: "application/json" } });
  const txt = await res.text();
  if (!res.ok) {
    let msg = txt;
    try {
      msg = JSON.parse(txt).message || msg;
    } catch {}
    toast(`خطأ ${res.status}: ${msg}`, "error");
    return null;
  }
  try {
    return JSON.parse(txt);
  } catch {
    toast("استجابة غير صالحة من الخادم.", "error");
    return null;
  }
}

function debounce(fn, ms = 320) {
  let t;
  return (...a) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...a), ms);
  };
}

// ========== DOM ==========
const weekFilter   = $("#weekFilter");
const taskFilter   = $("#taskFilter");
const groupSelect  = $("#groupSelect");
const statusSelect = $("#statusSelect");
const studentSearch = $("#studentSearch");
const gradeMinInp  = $("#gradeMin");
const gradeMaxInp  = $("#gradeMax");

const tbody   = $("#reportBody");
const aggBody = $("#aggBody");

const pageSizeSel = $("#pageSizeSel");
const pgPrev = $("#pgPrev");
const pgNext = $("#pgNext");
const pgNums = $("#pgNums");
const pageInfo = $("#pageInfo");

const printBtn       = $("#printPDF");
const exportExcelBtn = $("#exportExcel");

// ========== State ==========
let PAGE_SIZE = 10;
let CURRENT_PAGE = 1;
let ALL_ROWS = []; // {student, week_label, task_title, status, status_ar, grade}

// ========== Query Builder ==========
function buildQuery() {
  const params = new URLSearchParams();
  const week  = weekFilter ? weekFilter.value : "";
  const task  = taskFilter ? taskFilter.value : "";
  const group = groupSelect ? groupSelect.value : "";
  const stat  = statusSelect ? statusSelect.value : "";
  const q     = studentSearch ? studentSearch.value.trim() : "";
  const gmin  = gradeMinInp ? gradeMinInp.value : "";
  const gmax  = gradeMaxInp ? gradeMaxInp.value : "";

  if (week) params.set("week_id", week);
  if (task) params.set("task_id", task);
  if (group) params.set("group_id", group);
  if (stat) params.set("status", stat);
  if (q) params.set("q", q);
  if (gmin) params.set("grade_min", gmin);
  if (gmax) params.set("grade_max", gmax);

  return params;
}

// ========== Meta loaders ==========
async function loadWeeks() {
  const weeks = await fetchJson("/api/performance/weeks");
  if (!Array.isArray(weeks)) return;
  weekFilter.innerHTML =
    `<option value="">الكل</option>` +
    weeks
      .map(
        (w) =>
          `<option value="${w.id}">أسبوع ${w.week_number} — ${escapeHtml(
            w.title || ""
          )}</option>`
      )
      .join("");
}

async function loadGroups() {
  const groups = await fetchJson("/api/performance/groups");
  if (!Array.isArray(groups)) return;
  groupSelect.innerHTML =
    `<option value="">الكل</option>` +
    groups
      .map(
        (g) => `<option value="${g.id}">${escapeHtml(g.name || "")}</option>`
      )
      .join("");
}

async function loadTasksForWeek() {
  taskFilter.disabled = true;
  taskFilter.innerHTML = `<option value="">…تحميل</option>`;
  const week = weekFilter.value;
  if (!week) {
    taskFilter.innerHTML = `<option value="">الكل</option>`;
    taskFilter.disabled = false;
    return;
  }
  const ts = (await fetchJson(
    `/api/performance/tasks?week_id=${encodeURIComponent(week)}`
  )) || [];
  const current = taskFilter.value;
  taskFilter.innerHTML =
    `<option value="">الكل</option>` +
    ts
      .map(
        (t) =>
          `<option value="${t.id}">${escapeHtml(
            t.title || `مهمة #${t.id}`
          )}</option>`
      )
      .join("");
  if (current && ts.some((t) => String(t.id) === String(current))) {
    taskFilter.value = current;
  }
  taskFilter.disabled = false;
}

// ========== Table ==========
async function loadReportTable() {
  const qs = buildQuery().toString();
  const data = await fetchJson(`/api/performance/reports/summary?${qs}`);
  if (!Array.isArray(data)) return;
  ALL_ROWS = data;
  CURRENT_PAGE = 1;
  renderTablePage();
}

function renderTablePage() {
  const total = Array.isArray(ALL_ROWS) ? ALL_ROWS.length : 0;
  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  if (CURRENT_PAGE > totalPages) CURRENT_PAGE = totalPages;

  const start = (CURRENT_PAGE - 1) * PAGE_SIZE;
  const end = Math.min(total, start + PAGE_SIZE);
  const rows = ALL_ROWS.slice(start, end);

  tbody.innerHTML =
    rows
      .map((r) => {
        const gradeVal =
          r && r.grade != null ? String(r.grade).trim() : "";
        const gradeCell =
          gradeVal === "" ? "-" : escapeHtml(gradeVal);
        return (
          "<tr>" +
          `<td>${escapeHtml(r.student || "")}</td>` +
          `<td>${escapeHtml(r.week_label || "")}</td>` +
          `<td>${escapeHtml(r.task_title || "")}</td>` +
          `<td>${escapeHtml(r.status_ar || "")}</td>` +
          `<td>${gradeCell}</td>` +
          "</tr>"
        );
      })
      .join("") || "<tr><td colspan='5'>لا توجد نتائج</td></tr>";

  renderPageNumbers(totalPages);
  pageInfo.textContent =
    total === 0
      ? "لا توجد نتائج"
      : `عرض ${start + 1}–${end} من ${total}`;
}

function renderPageNumbers(totalPages) {
  pgNums.innerHTML = "";
  const makeBtn = (p, l = p) => {
    const b = document.createElement("button");
    b.textContent = String(l);
    b.className = p === CURRENT_PAGE ? "active" : "";
    b.onclick = () => {
      CURRENT_PAGE = p;
      renderTablePage();
    };
    return b;
  };
  if (totalPages <= 7) {
    for (let p = 1; p <= totalPages; p++) pgNums.appendChild(makeBtn(p));
  } else {
    const dots = () => {
      const s = document.createElement("span");
      s.textContent = "…";
      s.style.padding = "0 .4rem";
      pgNums.appendChild(s);
    };
    pgNums.appendChild(makeBtn(1));
    if (CURRENT_PAGE > 3) dots();
    for (
      let p = Math.max(2, CURRENT_PAGE - 1);
      p <= Math.min(totalPages - 1, CURRENT_PAGE + 1);
      p++
    )
      pgNums.appendChild(makeBtn(p));
    if (CURRENT_PAGE < totalPages - 2) dots();
    pgNums.appendChild(makeBtn(totalPages));
  }
  pgPrev.disabled = CURRENT_PAGE <= 1;
  pgNext.disabled = CURRENT_PAGE >= totalPages;
  pgPrev.onclick = () => {
    if (CURRENT_PAGE > 1) {
      CURRENT_PAGE--;
      renderTablePage();
    }
  };
  pgNext.onclick = () => {
    if (CURRENT_PAGE < totalPages) {
      CURRENT_PAGE++;
      renderTablePage();
    }
  };
}

// ========== Aggregates ==========
async function loadAggregates() {
  const qs = buildQuery().toString();
  const data = await fetchJson(`/api/performance/reports/aggregates?${qs}`);
  if (!Array.isArray(data)) return;
  aggBody.innerHTML =
    data
      .map(
        (r) =>
          `<tr>
            <td style="text-align:right">${escapeHtml(
              r.task_title || "مهمة #" + r.task_id
            )}</td>
            <td>${escapeHtml(r.week_label || "")}</td>
            <td>${r.students_cnt}</td>
            <td class="status-on_time">${r.on_time_cnt}</td>
            <td class="status-late">${r.late_cnt}</td>
            <td class="status-not_submitted">${r.not_submitted_cnt}</td>
            <td>${r.graded_cnt}</td>
            <td>${r.need_grade_cnt}</td>
          </tr>`
      )
      .join("") || "<tr><td colspan='8'>لا توجد بيانات</td></tr>";
}

// ========== Print (hidden IFrame) ==========
function openPrint() {
  const url = `/api/performance/reports/print?auto=1&${buildQuery().toString()}`;
  const iframe = document.createElement("iframe");
  iframe.style.position = "fixed";
  iframe.style.right = "-10000px";
  iframe.style.width = "1px";
  iframe.style.height = "1px";
  iframe.onload = () => {
    try {
      iframe.contentWindow.focus();
    } catch {}
    setTimeout(() => iframe.remove(), 4000);
  };
  iframe.src = url;
  document.body.appendChild(iframe);
}
if (printBtn) printBtn.addEventListener("click", openPrint);

// ========== Excel ==========
function exportExcel() {
  const url = `/api/performance/reports/export/excel?${buildQuery().toString()}`;
  window.open(url, "_self");
}
if (exportExcelBtn) exportExcelBtn.addEventListener("click", exportExcel);

// ========== Events ==========
async function onWeekChange() {
  await loadTasksForWeek();
  await loadReportTable();
  await loadAggregates();
}
if (weekFilter) weekFilter.addEventListener("change", onWeekChange);

if (taskFilter)
  taskFilter.addEventListener("change", () => {
    loadReportTable();
    loadAggregates();
  });

[groupSelect, statusSelect, gradeMinInp, gradeMaxInp].forEach((el) => {
  if (el)
    el.addEventListener("change", () => {
      loadReportTable();
      loadAggregates();
    });
});

if (studentSearch)
  studentSearch.addEventListener(
    "input",
    debounce(() => {
      loadReportTable();
      loadAggregates();
    }, 320)
  );

if (pageSizeSel)
  pageSizeSel.addEventListener("change", () => {
    PAGE_SIZE = parseInt(pageSizeSel.value, 10) || 10;
    CURRENT_PAGE = 1;
    renderTablePage();
  });

// ========== Init ==========
document.addEventListener("DOMContentLoaded", async () => {
  await loadWeeks();
  await loadGroups();
  await loadTasksForWeek();
  await loadReportTable();
  await loadAggregates();
});
