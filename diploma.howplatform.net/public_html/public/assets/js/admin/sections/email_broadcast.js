/**
 * Email Broadcast Module (CI4) — Robust SPA-safe build
 * - Auto-detect API base: /admin/email_broadcast OR /admin/email-broadcast
 * - Matches routes you provided exactly
 * - Falls back to local templates if /templates endpoint missing
 * - Uses event delegation so listeners keep working with dynamic loads
 * - Arabic toasts + graceful error handling
 */

const EB_BUILD = "1.22.0";

/* ========================= CS & Utils ========================= */
const SIX_HOURS = 6 * 60 * 60 * 1000;
const PER_PAGE_DEFAULT = 20;

const LS = {
  TPL_LOCAL: "eb_tpl_local_v2",
  UCACHE: "eb_usercache_v2",
  UC_SINCE: "eb_uc_since",
  UC_LAST: "eb_uc_last",
  API_BASE: "eb_api_base_v1",
  TPL_SERVER_CACHE: "eb_tpl_server_cache_v1",
  TPL_SERVER_TS: "eb_tpl_server_cache_ts_v1",
  OX_SERVER_CACHE: "eb_ox_server_cache_v1",
  OX_SERVER_TS: "eb_ox_server_cache_ts_v1",
};

const STATE = {
  apiBase: null,                // resolved later
  hasTemplatesApi: false,       // probed later
  page: 1,
  perPage: PER_PAGE_DEFAULT,
  rows: [],
  total: 0,
  pages: 0,
  selectedIds: new Set(),
  meta: null,
  filters: {
    is_complete: "",
    gender: "",
    university_id: [],
    specialty_id: [],
    inactive_since: "",
    emailed_before: "",
    emailed_from: "",
    emailed_to: "",
    account_type_id: "",
  },
  // UI
  activeField: null,
  selection: { start: null, end: null },
};

const FILES = {
  selectedFile: null,
  selectedType: "image", // image | attachment
  list: [],
  attachments: new Set(),
};

const TEMPLATES = {
  source: "server", // server | local
  list: [],
  byId: {},
};

/* ========================= Helpers ========================= */
const qs = (s, r = document) => r.querySelector(s);
const qsa = (s, r = document) => [...r.querySelectorAll(s)];
const nowMs = () => Date.now();
const isFresh = (ts, ttl = SIX_HOURS) => (nowMs() - (Number(ts) || 0)) < ttl;
const esc = (s) => (s ?? "").toString().replace(/[&<>"']/g, m => ({
  '&': '&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
}[m]));

function toast(msg, type = "info") {
  const api = {
    success: (m) => (typeof showSuccess === "function" ? showSuccess(m, "تم", true) : console.log("[success]", m)),
    error:   (m) => (typeof showError   === "function" ? showError(m, "خطأ")      : console.error("[error]", m)),
    warning: (m) => (typeof showWarning === "function" ? showWarning(m, "تنبيه")   : console.warn("[warn]", m)),
    info:    (m) => (typeof showInfo    === "function" ? showInfo(m, "معلومة")     : console.log("[info]", m)),
  };
  return (api[type] || api.info)(msg);
}

function debounce(fn, wait = 300) {
  let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), wait); };
}

function getMeta(name){ return document.querySelector(`meta[name="${name}"]`)?.content || ""; }

/* ========================= HTTP Core ========================= */
async function httpJson(url, opts = {}, expectJson = true, timeoutMs = 15000) {
  const ac = new AbortController();
  const t = setTimeout(() => ac.abort(), timeoutMs);

  const headers = { "X-Requested-With": "XMLHttpRequest" };
  const isJson = opts && opts.body && typeof opts.body === "object" && !(opts.body instanceof FormData);

  const csrfName = getMeta("csrf-token-name"); // optional but we update from response
  const csrfHash = getMeta("csrf-token-value");
  if (csrfHash) headers["X-CSRF-TOKEN"] = csrfHash;
  if (isJson) headers["Content-Type"] = "application/json";

  try {
    const res = await fetch(url, { credentials:"same-origin", headers, signal: ac.signal, ...opts, ...(isJson ? { body: JSON.stringify(opts.body) } : {}) });
    let data = null;
    if (expectJson) {
      try { data = await res.json(); } catch { throw new Error(`HTTP ${res.status} (no JSON)`); }
      // update CSRF tokens if present
      if (data?.csrf?.name && data?.csrf?.hash) {
        const nameMeta = document.querySelector('meta[name="csrf-token-name"]');
        const valMeta  = document.querySelector('meta[name="csrf-token-value"]');
        nameMeta?.setAttribute("content", data.csrf.name);
        valMeta?.setAttribute("content", data.csrf.hash);
      }
      if (!res.ok || data?.success === false) {
        throw new Error(data?.message || `HTTP ${res.status}`);
      }
      return data;
    } else {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.text();
    }
  } finally {
    clearTimeout(t);
  }
}

function httpUpload(url, formData, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    const csrfHash = getMeta("csrf-token-value");
    if (csrfHash) xhr.setRequestHeader("X-CSRF-TOKEN", csrfHash);
    if (xhr.upload && typeof onProgress === "function") {
      xhr.upload.onprogress = (e) => { if (e.lengthComputable) onProgress(e.loaded, e.total); };
    }
    xhr.onreadystatechange = () => {
      if (xhr.readyState !== 4) return;
      let json = null;
      try {
        json = JSON.parse(xhr.responseText || "{}");
        if (json?.csrf?.hash && json?.csrf?.name) {
          document.querySelector('meta[name="csrf-token-name"]')?.setAttribute("content", json.csrf.name);
          document.querySelector('meta[name="csrf-token-value"]')?.setAttribute("content", json.csrf.hash);
        }
        if (xhr.status >= 200 && xhr.status < 300 && json?.success !== false) {
          resolve(json);
        } else reject(new Error(json?.message || `HTTP ${xhr.status}`));
      } catch {
        reject(new Error(`HTTP ${xhr.status} (no JSON)`));
      }
    };
    xhr.onerror = () => reject(new Error("Network error"));
    xhr.send(formData);
  });
}

/* ========================= API base auto-detect ========================= */
async function detectApiBase() {
  // cached?
  const cached = localStorage.getItem(LS.API_BASE);
  if (cached) return cached;

  // Candidates based on your router naming
  const cands = ["/admin/email_broadcast", "/admin/email-broadcast"];
  for (const base of cands) {
    try {
      // probe filters endpoint (GET)
      await httpJson(`${base}/filters`, {}, true, 5000);
      localStorage.setItem(LS.API_BASE, base);
      return base;
    } catch { /* try next */ }
  }
  // If nothing responded OK, keep first as default but don't cache
  return cands[0];
}

async function checkTemplatesApi(base) {
  try {
    const j = await httpJson(`${base}/templates`, {}, true, 5000);
    return Array.isArray(j?.data) || Array.isArray(j);
  } catch { return false; }
}

/* ========================= User Cache (local) ========================= */
function ucLoad() {
  try { return JSON.parse(localStorage.getItem(LS.UCACHE) || "") || { byId:{}, byEmail:{} }; }
  catch { return { byId:{}, byEmail:{} }; }
}
function ucSave(cache) { localStorage.setItem(LS.UCACHE, JSON.stringify(cache || { byId:{}, byEmail:{} })); }
function ucGetSinceISO() { return localStorage.getItem(LS.UC_SINCE) || ""; }
function ucSetSinceISO(iso) {
  localStorage.setItem(LS.UC_SINCE, iso || "");
  localStorage.setItem(LS.UC_LAST, String(Date.now()));
}
function ucShouldRefresh(intervalMs = SIX_HOURS) {
  const last = Number(localStorage.getItem(LS.UC_LAST) || 0);
  return (Date.now() - last) >= intervalMs;
}
function ucUpsert(u) {
  if (!u) return;
  const c = ucLoad();
  const id = (typeof u.id === "number" || typeof u.id === "string") ? String(u.id) : null;
  const email = String(u.email || "").toLowerCase();
  const rec = { id: id, name: u.name || "", email: u.email || "", avatar: u.avatar || u.profile_image_url || null };
  if (id) c.byId[id] = rec;
  if (email) c.byEmail[email] = rec;
  ucSave(c);
}

/* ========================= Tokens/Insertion ========================= */
function isTokenTarget(el){ return !!(el && (el.matches?.('#mail-subject, #mail-preheader, #mail-body'))); }
function trackActiveField(el){ if (!isTokenTarget(el)) return; STATE.activeField = el; updateSel(el); }
function updateSel(el){
  try {
    STATE.selection.start = (typeof el.selectionStart === "number") ? el.selectionStart : null;
    STATE.selection.end   = (typeof el.selectionEnd   === "number") ? el.selectionEnd   : null;
  } catch {}
}
function getInsertTarget(){
  const ae = document.activeElement;
  if (isTokenTarget(ae)) return ae;
  const fallback = qs('#mail-subject') || qs('#mail-preheader') || qs('#mail-body');
  return (fallback && isTokenTarget(fallback)) ? fallback : null;
}
function insertAtCursor(el, text){
  if (!el) return;
  el.focus();
  let start = (el === STATE.activeField && Number.isInteger(STATE.selection.start)) ? STATE.selection.start : (el.selectionStart ?? null);
  let end   = (el === STATE.activeField && Number.isInteger(STATE.selection.end))   ? STATE.selection.end   : (el.selectionEnd   ?? null);
  if (start == null || end == null) start = end = (el.value?.length ?? 0);
  const before = (el.value || "").slice(0, start);
  const after  = (el.value || "").slice(end);
  el.value = before + text + after;
  const pos = start + text.length;
  if (typeof el.setSelectionRange === "function") el.setSelectionRange(pos, pos);
  STATE.activeField = el;
  STATE.selection.start = STATE.selection.end = pos;
}
function insertTokenToActive(tok){ const el = getInsertTarget(); if (el) insertAtCursor(el, tok); }

/* ========================= UI Bits ========================= */
function updateCounters(){
  const selCountEl = qs("#selectedCount");
  const totalCountEl = qs("#totalCount");
  if (selCountEl) selCountEl.textContent = `${STATE.selectedIds.size}`;
  if (totalCountEl) totalCountEl.textContent = `${STATE.total}`;
}
function toggleNoResults(){
  const nr = qs("#noResults");
  const tb = qs("#recipientsTable tbody");
  if (!nr || !tb) return;
  nr.style.display = tb.children.length > 0 ? "none" : "";
}
function fillMultiSelect(sel, items){
  if (!sel) return;
  sel.innerHTML = "";
  (items || []).forEach(it => {
    const opt = document.createElement("option");
    opt.value = it.id;
    opt.textContent = it.name;
    sel.appendChild(opt);
  });
}
function readMultiSelect(sel){ return sel ? qsa("option:checked", sel).map(o => o.value) : []; }

function renderTable(){
  const tbody = qs("#recipientsTable tbody");
  const pag   = qs("#pagination");
  const chkAll= qs("#chk-all");
  if (!tbody) return;

  tbody.innerHTML = "";
  STATE.rows.forEach(r => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td><input type="checkbox" class="chk-row" data-id="${esc(r.id)}" ${STATE.selectedIds.has(r.id) ? "checked" : ""}></td>
      <td>${esc(r.name)}</td>
      <td>${esc(r.email)}</td>
      <td>${esc(r.phone_number || "")}</td>
      <td>${esc(r.university_name || "")}</td>
      <td>${esc(r.specialty_name || "")}</td>
      <td>${r.gender === "female" ? "أنثى" : r.gender === "male" ? "ذكر" : ""}</td>
      <td>${r.is_complete ? "نعم" : "لا"}</td>
      <td>${esc(r.last_login_at || "—")}</td>
      <td><button type="button" class="btn btn-light preview" data-id="${esc(r.id)}"><i class="fas fa-eye"></i> معاينة</button></td>
    `;
    tbody.appendChild(tr);
  });

  if (pag) {
    const infoEl = qs("#paginationInfo");
    const firstEl= qs("#firstPageBtn");
    const prevEl = qs("#prevPageBtn");
    const nextEl = qs("#nextPageBtn");
    const lastEl = qs("#lastPageBtn");
    const totalPages = Math.max(STATE.pages || 0, 1);
    const curPage = Math.min(Math.max(STATE.page, 1), totalPages);
    if (infoEl) infoEl.textContent = `صفحة ${curPage} من ${totalPages}`;
    const atFirst = curPage <= 1, atLast = curPage >= totalPages;
    if (firstEl) firstEl.disabled = atFirst;
    if (prevEl)  prevEl.disabled  = atFirst;
    if (nextEl)  nextEl.disabled  = atLast;
    if (lastEl)  lastEl.disabled  = atLast;
  }

  if (chkAll) chkAll.checked = STATE.rows.length > 0 && STATE.rows.every(r => STATE.selectedIds.has(r.id));
  updateCounters(); toggleNoResults();
}

/* ========================= Read Filters & Loaders ========================= */
function readFilters(){
  STATE.filters.is_complete     = qs("#f-is-complete")?.value || "";
  STATE.filters.gender          = qs("#f-gender")?.value || "";
  STATE.filters.university_id   = readMultiSelect(qs("#f-university"));
  STATE.filters.specialty_id    = readMultiSelect(qs("#f-specialty"));
  STATE.filters.inactive_since  = qs("#f-inactive-since")?.value || "";
  STATE.filters.emailed_before  = qs("#f-emailed-before")?.value || "";
  STATE.filters.emailed_from    = qs("#f-emailed-from")?.value || "";
  STATE.filters.emailed_to      = qs("#f-emailed-to")?.value || "";
  STATE.filters.account_type_id = qs("#f-account-type")?.value || "";
}

async function loadMeta(){
  const j = await httpJson(`${STATE.apiBase}/filters`);
  if (!j?.success && !j?.data) throw new Error("فشل تحميل ميتا الفلاتر");
  STATE.meta = j.data || j;

  const toks = STATE.meta.supportedTokens || [];
  const tokensList = qs("#tokensList");
  if (tokensList) tokensList.innerHTML = toks.map(t => `<span class="eb-chip" data-token="${t}">${t}</span>`).join("");

  fillMultiSelect(qs("#f-university"), STATE.meta.universities || []);
  fillMultiSelect(qs("#f-specialty"), STATE.meta.specialties || []);
  fillMultiSelect(qs("#f-account-type"), STATE.meta.accountTypes || []);

  const cc = qs("#completeCounts");
  if (cc && STATE.meta.counts) cc.textContent = `مكتمل: ${STATE.meta.counts.complete} — غير مكتمل: ${STATE.meta.counts.incomplete}`;
}

async function loadList(){
  readFilters();
  const f = STATE.filters;
  const params = new URLSearchParams();
  if (f.is_complete !== "") params.set("is_complete", f.is_complete);
  if (f.gender !== "") params.set("gender", f.gender);
  if (f.university_id?.length) params.set("university_id", f.university_id.join(","));
  if (f.specialty_id?.length)  params.set("specialty_id",  f.specialty_id.join(","));
  if (f.inactive_since) params.set("inactive_since", f.inactive_since);
  if (f.account_type_id !== "") params.set("account_type_id", f.account_type_id);
  if (f.emailed_from) params.set("emailed_from", f.emailed_from);
  if (f.emailed_to)   params.set("emailed_to",   f.emailed_to);

  const eb = (f.emailed_before || "").toString().toLowerCase();
  if (["0","1","yes","no"].includes(eb)) params.set("emailed_before", eb === "yes" ? "1" : eb === "no" ? "0" : eb);

  params.set("page", String(STATE.page));
  params.set("per_page", String(STATE.perPage));

  const j = await httpJson(`${STATE.apiBase}/recipients?${params.toString()}`);
  if (!j?.success && !j?.data) throw new Error("فشل تحميل القائمة");

  const rows = (j.data?.rows ?? j.rows ?? []);
  STATE.total = (j.data?.total ?? j.total ?? 0);
  STATE.pages = (j.data?.pages ?? j.pages ?? 0);
  STATE.rows  = rows;

  rows.forEach(u => ucUpsert({ id:u.id, name:u.name, email:u.email, avatar: u.avatar || u.profile_image_url || null }));
  renderTable();
}

/* ========================= Preview & Send ========================= */
async function previewFull(userId){
  const subject = qs("#mail-subject")?.value.trim() || "";
  const pre     = qs("#mail-preheader")?.value.trim() || "";
  const body    = qs("#mail-body")?.value.trim() || "";
  if (!subject || !body) return toast("أدخل الموضوع ونص الرسالة للمعاينة", "error");

  const win = window.open("", "_blank", "width=920,height=720");
  if (!win) return toast("المتصفح منع النافذة المنبثقة…", "error");
  win.document.write(`<html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>معاينة</title></head><body style="font-family:Tahoma,Arial,sans-serif;line-height:1.8;padding:16px"><div style="text-align:center;color:#777;margin-top:80px;font-size:18px">جاري تحضير المعاينة…</div></body></html>`);
  win.document.close();

  try {
    const j = await httpJson(`${STATE.apiBase}/preview`, { method:"POST", body:{ subject, preheader:pre, body_template: body, user_id: userId }});
    const html = j?.html || "";
    if (!html) throw new Error(j?.message || "تعذّر إنشاء المعاينة");
    win.document.open(); win.document.write(html); win.document.close();
  } catch (e) {
    win.close();
    toast(e.message || "تعذّر إنشاء المعاينة", "error");
  }
}

async function sendTest(){
  const testEmail = qs("#test-email")?.value.trim() || "";
  const subject   = qs("#mail-subject")?.value.trim() || "";
  const pre       = qs("#mail-preheader")?.value.trim() || "";
  const body      = qs("#mail-body")?.value.trim() || "";
  const dryRun    = !!qs("#dry-run")?.checked;

  if (!testEmail) return toast("أدخل بريد تجربة", "error");
  if (!subject || !body) return toast("أدخل الموضوع ونص الرسالة", "error");

  await httpJson(`${STATE.apiBase}/send`, {
    method: "POST",
    body: { subject, preheader: pre, body_template: body, test_email: testEmail, dry_run: dryRun, file_ids: [...FILES.attachments] }
  });
  toast("تم معالجة رسالة التجربة", "success");
  openOutboxModal();
}

async function sendNow(){
  const subject = qs("#mail-subject")?.value.trim() || "";
  const pre     = qs("#mail-preheader")?.value.trim() || "";
  const body    = qs("#mail-body")?.value.trim() || "";
  const dryRun  = !!qs("#dry-run")?.checked;
  if (!subject || !body) return toast("أدخل الموضوع ونص الرسالة", "error");
  const ids = [...STATE.selectedIds];
  if (!ids.length) return toast("اختر مستلمين أولًا", "error");

  const wrap = qs("#sendProgress");
  const bar  = qs("#sendBar");
  const sts  = qs("#sendStatus");

  const doSend = async () => {
    try {
      if (wrap) wrap.style.display = "";
      if (sts)  sts.textContent = `جاري الإرسال (${ids.length} مستلم)...`;
      if (bar)  bar.style.width = "0%";

      const j = await httpJson(`${STATE.apiBase}/send`, {
        method: "POST",
        body: { subject, preheader: pre, body_template: body, user_ids: ids, dry_run: dryRun, file_ids: [...FILES.attachments] }
      });

      const res  = j.results || [];
      const tot  = res.length;
      const sent = res.filter(r => r.status === "sent").length;
      const pct  = tot ? Math.round((sent / tot) * 100) : 0;
      if (bar) bar.style.width = pct + "%";
      if (sts) sts.textContent = `اكتمل الإرسال: نجح ${sent} / فشل ${tot - sent}`;

      if (tot && sent === 0) toast("فشل الإرسال لجميع المستلمين", "error");
      else if (tot && sent < tot) toast(`تمت بعض الإرسالات (${sent}) وفشلت (${tot - sent})`, "warning");
      else toast("تم الإرسال بنجاح", "success");

      openOutboxModal();
    } catch (e) {
      toast(e.message || "فشل الإرسال", "error");
    }
  };

  if (typeof showConfirm === "function") {
    showConfirm({ title:"تأكيد الإرسال", text:`سيتم إرسال ${ids.length} رسالة${dryRun?" (وضع المعاينة فقط)":""}. هل أنت متأكد؟`, confirmText:"نعم، أرسل", cancelText:"إلغاء", onConfirm: doSend });
  } else {
    if (confirm(`سيتم إرسال ${ids.length} رسالة${dryRun?" (وضع المعاينة فقط)":""}. هل أنت متأكد؟`)) doSend();
  }
}

/* ========================= Templates ========================= */
function loadTemplatesFromLS(){
  try {
    const map = JSON.parse(localStorage.getItem(LS.TPL_LOCAL) || "") || {};
    // ensure preheader key
    let changed = false;
    Object.keys(map).forEach(k => { if (map[k] && typeof map[k] === "object" && !("preheader" in map[k])) { map[k].preheader = ""; changed = true; }});
    if (changed) localStorage.setItem(LS.TPL_LOCAL, JSON.stringify(map));
    return map;
  } catch { return {}; }
}

function refreshTemplatesSelect(){
  const sel = qs("#template-select");
  if (!sel) return;
  sel.innerHTML = `<option value="">— اختيار قالب محفوظ —</option>`;

  if (TEMPLATES.source === "server") {
    TEMPLATES.list
      .slice()
      .sort((a,b)=> String(a.name).localeCompare(String(b.name), "ar"))
      .forEach(t => {
        const opt = document.createElement("option");
        opt.value = `srv:${t.id}`;
        opt.textContent = t.name;
        sel.appendChild(opt);
      });
  } else {
    const map = loadTemplatesFromLS();
    Object.keys(map).sort().forEach(name => {
      const opt = document.createElement("option");
      opt.value = `loc:${name}`;
      opt.textContent = name;
      sel.appendChild(opt);
    });
  }
}

async function loadTemplatesWithCache(force = false){
  if (!STATE.hasTemplatesApi) {
    TEMPLATES.source = "local";
    refreshTemplatesSelect();
    return;
  }

  if (!force) {
    const ts = Number(localStorage.getItem(LS.TPL_SERVER_TS) || 0);
    const cached = (()=>{ try { return JSON.parse(localStorage.getItem(LS.TPL_SERVER_CACHE)||"") || null; } catch {return null;} })();
    if (Array.isArray(cached) && isFresh(ts)) {
      TEMPLATES.source = "server";
      TEMPLATES.list = cached.slice();
      TEMPLATES.byId = Object.fromEntries(cached.map(r => [r.id, r]));
      refreshTemplatesSelect();
      return;
    }
  }

  try {
    const j = await httpJson(`${STATE.apiBase}/templates`);
    const rows = j?.data || j;
    if (!Array.isArray(rows)) throw new Error("bad templates payload");
    localStorage.setItem(LS.TPL_SERVER_CACHE, JSON.stringify(rows));
    localStorage.setItem(LS.TPL_SERVER_TS, String(nowMs()));
    TEMPLATES.source = "server";
    TEMPLATES.list = rows.slice();
    TEMPLATES.byId = Object.fromEntries(rows.map(r => [r.id, r]));
    refreshTemplatesSelect();
  } catch (e) {
    TEMPLATES.source = "local";
    refreshTemplatesSelect();
  }
}

async function saveCurrentAsTemplate(){
  const name = qs("#template-name")?.value.trim();
  const subject = qs("#mail-subject")?.value.trim() || "";
  const body = qs("#mail-body")?.value.trim() || "";
  const pre  = qs("#mail-preheader")?.value.trim() || "";
  if (!name) return toast("أدخل اسم القالب", "error");

  if (STATE.hasTemplatesApi) {
    try {
      await httpJson(`${STATE.apiBase}/templates`, { method:"POST", body:{ name, subject, preheader: pre, body_template: body, is_shared: 0 }});
      await loadTemplatesWithCache(true);
      const sel = qs("#template-select");
      if (sel) {
        const found = TEMPLATES.list.find(t => String(t.name) === name);
        sel.value = found ? `srv:${found.id}` : "";
      }
      toast("تم حفظ القالب (سيرفر)", "success");
      return;
    } catch {
      // fallthrough to local
    }
  }

  const map = loadTemplatesFromLS();
  map[name] = { subject, preheader: pre, body };
  localStorage.setItem(LS.TPL_LOCAL, JSON.stringify(map));
  TEMPLATES.source = "local";
  refreshTemplatesSelect();
  const sel = qs("#template-select"); if (sel) sel.value = `loc:${name}`;
  toast("تم حفظ القالب محليًا", "success");
}

function applySelectedTemplate(){
  const sel = qs("#template-select");
  if (!sel || !sel.value) return toast("اختر قالبًا لتطبيقه", "error");
  const [src, key] = sel.value.split(":", 2);
  const subj = qs("#mail-subject");
  const body = qs("#mail-body");
  const pre  = qs("#mail-preheader");

  if (src === "srv" && TEMPLATES.source === "server") {
    const t = TEMPLATES.byId[Number(key)];
    if (!t) return toast("القالب غير موجود", "error");
    if (subj) subj.value = t.subject || "";
    if (body) body.value = t.body_template || "";
    if (pre)  pre.value  = ("preheader" in t ? (t.preheader || "") : "");
    toast("تم تطبيق القالب (سيرفر)", "success");
    return;
  }

  const map = loadTemplatesFromLS();
  const tpl = map[key];
  if (!tpl) return toast("القالب غير موجود", "error");
  if (subj) subj.value = tpl.subject || "";
  if (body) body.value = tpl.body || "";
  if (pre)  pre.value  = ("preheader" in tpl ? (tpl.preheader || "") : "");
  toast("تم تطبيق القالب", "success");
}

async function deleteSelectedTemplate(){
  const sel = qs("#template-select");
  if (!sel || !sel.value) return toast("اختر قالبًا لحذفه", "error");
  const [src, key] = sel.value.split(":", 2);

  if (src === "srv" && STATE.hasTemplatesApi) {
    try {
      await httpJson(`${STATE.apiBase}/templates/${encodeURIComponent(key)}`, { method:"DELETE" });
      await loadTemplatesWithCache(true);
      toast("تم حذف القالب (سيرفر)", "success");
      return;
    } catch (e) { return toast(e.message || "تعذّر حذف القالب", "error"); }
  }

  const map = loadTemplatesFromLS();
  delete map[key];
  localStorage.setItem(LS.TPL_LOCAL, JSON.stringify(map));
  refreshTemplatesSelect();
  toast("تم حذف القالب", "success");
}

/* ========================= Files ========================= */
function updateFileInputAccept(){
  const inp = qs("#eb-file-input");
  if (!inp) return;
  if (FILES.selectedType === "image") inp.setAttribute("accept","image/*");
  else inp.setAttribute("accept","image/*,application/pdf,.doc,.docx,.xls,.xlsx,.zip,.txt");
}
function filesSetSelectedFile(file){
  FILES.selectedFile = file || null;
  const btn = qs("#eb-btn-upload");
  const barWrap = qs("#eb-upload-progress");
  const bar = qs("#eb-upload-bar");
  if (btn) btn.disabled = !FILES.selectedFile;
  if (barWrap) barWrap.style.display = "none";
  if (bar) bar.style.width = "0%";

  const dz = qs("#eb-dropzone");
  if (dz) {
    dz.querySelector(".dz-name")?.remove?.();
    if (FILES.selectedFile) {
      const n = document.createElement("div");
      n.className = "dz-name";
      n.style.cssText = "margin-top:6px;color:#334155;font-size:.9rem;";
      n.textContent = `مختار: ${FILES.selectedFile.name} — ${(FILES.selectedFile.size/1024).toFixed(1)} KB`;
      dz.appendChild(n);
    }
  }
}
function filesRenderList(){
  const ul = qs("#eb-files-list");
  const empty = qs("#eb-files-empty");
  if (!ul) return;
  ul.innerHTML = "";
  const rows = FILES.list || [];
  if (!rows.length){ if (empty) empty.style.display = ""; return; }
  if (empty) empty.style.display = "none";

  rows.forEach(f => {
    const li = document.createElement("li");
    li.className = "eb-file-item";
    li.dataset.id   = f.id;
    li.dataset.type = f.file_type || (f.mime?.startsWith("image/") ? "image":"attachment");
    li.dataset.url  = f.public_url || f.url || "";
    li.style.cssText = "display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-bottom:1px solid #eee";
    const left = document.createElement("div");
    left.style.minWidth = "0";
    left.innerHTML = `<div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(f.original_name || f.stored_name || f.file_name || ('#'+f.id))}</div>
      <div class="sm" style="color:#64748b">${esc(f.mime || f.mime_type || "")} · ${(li.dataset.type==="image"?"صورة":"مرفق")}</div>`;
    const right = document.createElement("div");
    right.className = "actions"; right.style.display="flex"; right.style.gap="8px";
    const b1 = document.createElement("button"); b1.className="btn btn-xs insert-inline"; b1.type="button"; b1.textContent="إدراج كصورة";
    const b2 = document.createElement("button"); b2.className="btn btn-xs add-attach";    b2.type="button"; b2.textContent="إضافة كمرفق";
    if (li.dataset.type !== "image"){ b1.disabled = true; b1.title = "يدعم الصور فقط"; }
    right.appendChild(b1); right.appendChild(b2);
    li.appendChild(left); li.appendChild(right);
    ul.appendChild(li);
  });
}
function filesRenderAttachmentsChips(){
  const wrap = qs("#eb-attachments-chips");
  const empty= qs("#eb-no-attachments");
  if (!wrap) return;
  wrap.innerHTML = "";
  const ids = [...FILES.attachments];
  if (!ids.length){ if (empty) empty.style.display = ""; return; }
  if (empty) empty.style.display = "none";
  const byId = new Map(FILES.list.map(f => [Number(f.id), f]));
  ids.forEach(id => {
    const f = byId.get(Number(id));
    const name = f?.original_name || f?.file_name || `#${id}`;
    const chip = document.createElement("span");
    chip.className = "chip"; chip.dataset.id = String(id);
    chip.style.cssText = "background:#eef2ff;color:#1e40af;padding:6px 10px;border-radius:999px;display:inline-flex;gap:8px;align-items:center;";
    chip.innerHTML = `${esc(name)} <button type="button" class="eb-attach-remove" data-id="${id}" style="border:0;background:transparent;cursor:pointer;font-weight:700">&times;</button>`;
    wrap.appendChild(chip);
  });
}
async function filesRefreshList(){
  try {
    const j = await httpJson(`${STATE.apiBase}/files/list`);
    FILES.list = Array.isArray(j?.data) ? j.data : (Array.isArray(j)? j : []);
    filesRenderList(); filesRenderAttachmentsChips();
  } catch (e) {
    console.warn("[files] list failed", e);
    toast(e.message || "تعذّر تحميل قائمة الملفات", "warning");
  }
}
function insertImageAtCursor(ta, url, alt=""){
  if (!ta || !url) return;
  ta.focus();
  const s = ta.selectionStart ?? 0, e = ta.selectionEnd ?? 0;
  const before = ta.value.slice(0,s), after = ta.value.slice(e);
  const tag = `<img src="${esc(url)}" alt="${esc(alt)}" />`;
  ta.value = before + tag + after;
  const pos = (before + tag).length; ta.setSelectionRange(pos, pos);
}
async function uploadSelectedFile(){
  if (!FILES.selectedFile) return;
  const typeSel = qs("#eb-file-type");
  FILES.selectedType = (typeSel?.value || "image");

  const fd = new FormData();
  fd.append("file", FILES.selectedFile);
  fd.append("type", FILES.selectedType);

  const progWrap = qs("#eb-upload-progress");
  const bar = qs("#eb-upload-bar");
  if (progWrap) progWrap.style.display = "";
  if (bar) bar.style.width = "0%";

  try {
    const j = await httpUpload(`${STATE.apiBase}/files/upload`, fd, (loaded,total) => {
      if (bar && total) bar.style.width = `${Math.round((loaded/total)*100)}%`;
    });
    toast("تم رفع الملف بنجاح", "success");
    filesSetSelectedFile(null);
    await filesRefreshList();
    const data = j?.data;
    if (data && (data.type === "image" || (data.mime || "").startsWith("image/")) && (data.url || data.public_url)) {
      insertImageAtCursor(qs("#mail-body"), data.url || data.public_url, data.name || data.original_name || "");
    }
  } catch (e) {
    toast(e.message || "تعذّر رفع الملف", "error");
  } finally {
    if (progWrap) progWrap.style.display = "none";
    if (bar) bar.style.width = "0%";
  }
}
function addAttachmentId(id){ if (!id) return; FILES.attachments.add(Number(id)); filesRenderAttachmentsChips(); }
function removeAttachmentId(id){ FILES.attachments.delete(Number(id)); filesRenderAttachmentsChips(); }

/* ========================= Link Insertion ========================= */
function htmlAttr(s=""){ return (s+"").replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/</g,"&lt;"); }
function htmlText(s=""){ return (s+"").replace(/&/g,"&amp;").replace(/</g,"&lt;"); }
function normalizeUrl(u=""){ const t=u.trim(); if(!t) return ""; if(/^(https?:|mailto:|tel:)/i.test(t)) return t; return "https://"+t.replace(/^\/+/,""); }
function isLikelyUrl(u=""){ return /^(https?:\/\/|mailto:|tel:).+/i.test((u||"").trim()); }
function insertLinkAtCursor(ta, url, label){
  if (!ta) return;
  ta.focus();
  const s = ta.selectionStart ?? 0, e = ta.selectionEnd ?? 0;
  const before = ta.value.slice(0,s), after = ta.value.slice(e);
  const finalLabel = (label?.trim() || ta.value.slice(s,e) || url);
  const a = `<a href="${htmlAttr(url)}" target="_blank" rel="noopener">${htmlText(finalLabel)}</a>`;
  ta.value = before + a + after;
  const pos = (before + a).length; ta.setSelectionRange(pos, pos);
}
function handleInsertLink(){
  const urlInp = qs("#link-url");
  const lblInp = qs("#link-label");
  const ta = qs("#mail-body");
  if (!urlInp || !ta) return;
  const url = normalizeUrl(urlInp.value || "");
  const label = (lblInp?.value || "").trim();
  if (!url) return toast("أدخل رابطًا أولًا", "error");
  if (!isLikelyUrl(url)) return toast("صيغة الرابط غير صحيحة", "error");
  insertLinkAtCursor(ta, url, label);
  urlInp.value = ""; if (lblInp) lblInp.value = ""; ta.focus();
}

/* ========================= Outbox Modal (basic) ========================= */
function openOutboxModal(){ const ov = qs("#outboxModal"); if (ov){ ov.style.display="flex"; document.body.style.overflow="hidden"; } }
function closeOutboxModal(){ const ov = qs("#outboxModal"); if (ov){ ov.style.display="none"; document.body.style.overflow=""; } }

/* ========================= Event Delegation ========================= */
function withinRoot(node){ const root = qs("#emailBroadcastPage"); return !!(root && node && root.contains(node)); }
function on(event, selector, handler, opts){
  document.addEventListener(event, (e) => {
    const target = e.target.closest(selector);
    if (!target) return;
    if (!withinRoot(target) && !selector.startsWith("#outbox")) return;
    handler(e, target);
  }, opts || false);
}

// track active field
document.addEventListener("focusin", (e)=>{ if (isTokenTarget(e.target)) trackActiveField(e.target); }, true);
document.addEventListener("click",   (e)=>{ if (isTokenTarget(e.target)) trackActiveField(e.target); }, true);
document.addEventListener("keyup",   (e)=>{ if (isTokenTarget(e.target)) updateSel(e.target); }, true);
document.addEventListener("mouseup", (e)=>{ if (isTokenTarget(e.target)) updateSel(e.target); }, true);
document.addEventListener("input",   (e)=>{ if (isTokenTarget(e.target)) updateSel(e.target); }, true);

// tokens
on("mousedown", "#tokensList .eb-chip", (e)=> e.preventDefault());
on("click", "#tokensList .eb-chip", (_e, chip)=> { const t = chip.dataset.token || chip.textContent.trim(); if (t) insertTokenToActive(t); });

// link
on("click", "#btn-insert-link", ()=> handleInsertLink());
on("keydown", "#link-url",  (e)=>{ if (e.key === "Enter"){ e.preventDefault(); handleInsertLink(); }});
on("keydown", "#link-label",(e)=>{ if (e.key === "Enter"){ e.preventDefault(); handleInsertLink(); }});

// outbox modal
on("click", "#btn-open-outbox", ()=> openOutboxModal());
on("click", "#outboxClose", ()=> closeOutboxModal());
document.addEventListener("click", (e)=>{ const ov=qs("#outboxModal"); if (ov && e.target === ov) closeOutboxModal(); });
document.addEventListener("keydown", (e)=>{ const ov=qs("#outboxModal"); if (e.key === "Escape" && ov && ov.style.display !== "none") closeOutboxModal(); });

// targets overlay (simple open/close)
on("click", "#btn-open-targets", ()=> qs("#targetsOverlay")?.classList.add("active"));
on("click", "#btn-close-targets", ()=> qs("#targetsOverlay")?.classList.remove("active"));
document.addEventListener("click", (e)=>{ const ov=qs("#targetsOverlay"); if (ov && e.target === ov) ov.classList.remove("active"); });
document.addEventListener("keydown", (e)=>{ if (e.key === "Escape") qs("#targetsOverlay")?.classList.remove("active"); });

// table selection
on("change", "#recipientsTable tbody input.chk-row", (_e, chk)=>{
  const id = chk.dataset.id; if (chk.checked) STATE.selectedIds.add(id); else STATE.selectedIds.delete(id); updateCounters();
});
on("click", "#recipientsTable tbody button.preview", (_e, btn)=> { const id = btn.dataset.id; if (id) previewFull(id).catch(console.error); });
on("change", "#chk-all", (_e, m)=>{ if (m.checked) STATE.rows.forEach(r=> STATE.selectedIds.add(String(r.id))); else STATE.rows.forEach(r=> STATE.selectedIds.delete(String(r.id))); renderTable(); updateCounters(); });
on("click", "#btn-select-all", ()=> { STATE.rows.forEach(r=> STATE.selectedIds.add(String(r.id))); renderTable(); });
on("click", "#btn-clear-all",  ()=> { STATE.rows.forEach(r=> STATE.selectedIds.delete(String(r.id))); renderTable(); });

// files UI
on("change", "#eb-file-type", (_e, sel)=> { FILES.selectedType = sel.value || "image"; updateFileInputAccept(); });
on("click",  "#eb-dropzone", ()=> qs("#eb-file-input")?.click());
on("change", "#eb-file-input", (_e, inp)=>{ const f = inp.files && inp.files[0] ? inp.files[0] : null; filesSetSelectedFile(f); const btn=qs("#eb-btn-upload"); if (btn) btn.disabled = !FILES.selectedFile; });
on("click",  "#eb-btn-clear-file", ()=>{ const inp=qs("#eb-file-input"); if (inp) inp.value=""; filesSetSelectedFile(null); });
on("click",  "#eb-btn-upload", ()=> uploadSelectedFile().catch(console.error));
on("click",  "#btn-refresh-files", ()=> filesRefreshList().catch(console.error));
on("click",  "#eb-files-list .insert-inline", (_e, btn)=>{ const li = btn.closest(".eb-file-item"); if (!li) return; if ((li.dataset.type||"attachment") !== "image") return; const url = li.dataset.url || ""; const name = li.querySelector("div div")?.textContent || ""; insertImageAtCursor(qs("#mail-body"), url, name || ""); });
on("click",  "#eb-files-list .add-attach", (_e, btn)=>{ const li=btn.closest(".eb-file-item"); if (!li) return; const id = Number(li.dataset.id||0); if(!id) return; addAttachmentId(id); });
on("click",  "#eb-attachments-chips .eb-attach-remove", (_e, b)=>{ const id = Number(b.dataset.id||0); if(!id) return; removeAttachmentId(id); });

// filters auto-apply
const triggerAuto = debounce(()=>{ STATE.page = 1; loadList().catch(console.error); }, 300);
["#f-is-complete","#f-gender","#f-university","#f-specialty","#f-emailed-before","#f-account-type"].forEach(sel => on("change", sel, triggerAuto));
["#f-inactive-since","#f-emailed-from","#f-emailed-to"].forEach(sel => { on("change", sel, triggerAuto); on("input", sel, triggerAuto); });

// pagination
on("click", "#firstPageBtn", ()=>{ STATE.page=1; loadList().catch(console.error); });
on("click", "#prevPageBtn",  ()=>{ STATE.page=Math.max(1, STATE.page-1); loadList().catch(console.error); });
on("click", "#nextPageBtn",  ()=>{ STATE.page=Math.min(STATE.pages||1, STATE.page+1); loadList().catch(console.error); });
on("click", "#lastPageBtn",  ()=>{ STATE.page=STATE.pages||1; loadList().catch(console.error); });

// apply/reset
on("click", "#btn-apply", ()=>{ STATE.page=1; loadList().catch(console.error); });
on("click", "#btn-reset", ()=>{
  const setVal = (sel,v="")=>{ const el=qs(sel); if (el) el.value=v; };
  setVal("#f-is-complete"); setVal("#f-gender"); setVal("#f-inactive-since"); setVal("#f-emailed-before"); setVal("#f-emailed-from"); setVal("#f-emailed-to"); setVal("#f-account-type");
  qsa("#f-university option").forEach(o=> o.selected=false);
  qsa("#f-specialty option").forEach(o=> o.selected=false);
  STATE.selectedIds.clear(); STATE.page=1; loadList().catch(console.error);
});

// send & test
on("click", "#btn-send-test", ()=> sendTest().catch(console.error));
on("click", "#btn-send",      ()=> sendNow().catch(console.error));

// inline preview
on("click", "#btn-preview", async ()=>{
  const subject = (qs("#mail-subject")?.value || "").trim();
  const pre     = (qs("#mail-preheader")?.value || "").trim();
  const body    = (qs("#mail-body")?.value || "").trim();
  if (!subject || !body) return toast("أدخل الموضوع ونص الرسالة أولًا", "error");
  const checked = document.querySelector('#recipientsTable tbody input.chk-row:checked');
  const userId  = checked?.dataset?.id || null;
  if (!userId) return toast("اختر مستلمًا واحدًا على الأقل", "error");
  const pane = qs("#previewPane"); const frame = qs("#previewFrame");
  if (!pane || !frame) return;
  try {
    const j = await httpJson(`${STATE.apiBase}/preview`, { method:"POST", body:{ subject, preheader: pre, body_template: body, user_id: userId }});
    const html = j?.html || ""; if (!html) throw new Error(j?.message || "تعذّر إنشاء المعاينة");
    pane.style.display=""; const d = frame.contentWindow.document; d.open(); d.write(html); d.close();
  } catch(e){ toast(e.message || "تعذّر إنشاء المعاينة", "error"); }
});
on("click", "#btn-preview-clear", ()=>{ const pane=qs("#previewPane"); const frame=qs("#previewFrame"); if (pane) pane.style.display="none"; const d=frame?.contentWindow?.document; if (d){ d.open(); d.write(""); d.close(); }});

// templates controls
on("click", "#btn-save-tpl",    ()=> saveCurrentAsTemplate());
on("click", "#btn-apply-tpl",   ()=> applySelectedTemplate());
on("click", "#btn-delete-tpl",  ()=> deleteSelectedTemplate());
on("click", "#btn-refresh-tpl", ()=> { loadTemplatesWithCache(true).then(()=> toast("تم تحديث كاش القوالب", "success")).catch(()=> toast("تعذّر تحديث القوالب", "error")); });

// users cache manual refresh
on("click", "#btn-refresh-uc", ()=> { refreshUsersCache(true).then(()=> toast("تم تحديث كاش المستخدمين", "success")).catch(()=> toast("تعذّر تحديث كاش المستخدمين", "error")); });

/* ========================= Users Cache Refresh ========================= */
async function refreshUsersCache(force = false){
  try {
    if (!force && !ucShouldRefresh()) return;
    const since = ucGetSinceISO();
    const params = new URLSearchParams({ page:"1", per_page:"200" });
    if (since) params.set("since", since);
    const j = await httpJson(`${STATE.apiBase}/recipients?${params.toString()}`);
    const users = j?.data?.rows || j?.rows || j?.data?.users || j?.users || [];
    users.forEach(ucUpsert);
    ucSetSinceISO(new Date().toISOString());
  } catch (e) {
    console.warn("[users-cache] failed:", e);
  }
}

/* ========================= Mount / Init ========================= */
async function mountEmailBroadcast(root){
  if (!root) return;

  // resolve API base & features
  STATE.apiBase = await detectApiBase();
  STATE.hasTemplatesApi = await checkTemplatesApi(STATE.apiBase);

  try { await loadMeta(); } catch (e){ console.error(e); toast("حدث خطأ أثناء تحميل الفلاتر", "error"); }
  try { await loadList(); } catch (e){ console.error(e); }

  const bodyTa = qs("#mail-body"); if (bodyTa){ bodyTa.style.width = "800px"; bodyTa.style.height = "400px"; }

  await loadTemplatesWithCache().catch(()=> refreshTemplatesSelect());
  await filesRefreshList().catch(()=>{});
  updateCounters();

  try { await refreshUsersCache(true); } catch {}
}

function startAutoMount(){
  const tryMount = ()=> {
    const root = qs("#emailBroadcastPage");
    if (root && !root._ebMounted) {
      root._ebMounted = true;
      mountEmailBroadcast(root).catch(console.error);
    }
    if (!root) {
      // section removed, allow re-mount later
      const prev = document.querySelector("[data-eb-mounted]");
      if (prev) prev.removeAttribute("data-eb-mounted");
    } else {
      root.setAttribute("data-eb-mounted","1");
    }
  };
  tryMount();
  const obs = new MutationObserver(()=> tryMount());
  obs.observe(document.documentElement, { childList:true, subtree:true });
}

/* exported init (for your router loader) */
export async function init(){
  try { await waitForElement("#emailBroadcastPage", 6000); } catch {}
  startAutoMount();
}
// also expose as global in case your loader calls it globally
window.emailBroadcastInit = init;

function waitForElement(selector, timeout = 8000){
  return new Promise((resolve, reject)=>{
    const found = qs(selector); if (found) return resolve(found);
    const obs = new MutationObserver(()=>{ const el = qs(selector); if (el){ obs.disconnect(); resolve(el);} });
    obs.observe(document.documentElement, { childList:true, subtree:true });
    setTimeout(()=>{ obs.disconnect(); reject(new Error(`Timeout waiting for ${selector}`)); }, timeout);
  });
}

console.log(`[EmailBroadcast ${EB_BUILD}] loaded.`);
