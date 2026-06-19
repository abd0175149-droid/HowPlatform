/* global Swal, showWarning, showSuccess, showError, showConfirm */

/* ملاحظة الراوتر المتوقّع:
  /admins/api/UniversityOptions/summary                         → UniversityOptions::summary
  /admins/api/UniversityOptions/universities/store              → UniversityOptions::createUniversity
  /admins/api/UniversityOptions/universities/show/{id}          → UniversityOptions::show/$1
  /admins/api/UniversityOptions/universities/delete/{id}        → UniversityOptions::delete/$1
  /admins/api/UniversityOptions/specialties/store               → UniversityOptions::createSpecialty
  /admins/api/UniversityOptions/specialties/link/{uniId}        → UniversityOptions::linkSpecialties/$1
  /admins/api/UniversityOptions/specialties/unlink/{uniId}/{id} → UniversityOptions::unlinkSpecialty/$1/$2
  /admins/api/UniversityOptions/specialties/delete/{id}         → UniversityOptions::deleteSpecialty/$1
*/

const API_BASE = '/admins/api/UniversityOptions';

/*══════════════════════════════════════════════════════════════
 |                               init
 ══════════════════════════════════════════════════════════════*/
export function init() {
  loadCounts();
  bindUniversityForm();
  bindSpecialtyForm();
  bindLinkForm();

  refreshUniversitiesList();
  refreshSpecialtiesList();
  bindUniversityCards();

  const currentYear = document.getElementById('currentYear');
  if (currentYear) currentYear.textContent = new Date().getFullYear();

  // عند تغيير الجامعة ► نحدّث قائمة الربط وعلامات التخصّص
  document.addEventListener('change', (e) => {
    if (e.target && e.target.id === 'uniSelect') {
      refreshLinkedSpecs(e.target.value);
    }
  });
}

/*════════════════════════════ 1) Counters ═════════════════════*/
async function loadCounts() {
  try {
    const r = await fetch(`${API_BASE}/summary`, { headers: { Accept: 'application/json' } });
    if (!r.ok) return;
    const d = await r.json();
    const stat1 = document.querySelector('.mu-stats .mu-card:nth-child(1) .mu-value');
    const stat2 = document.querySelector('.mu-stats .mu-card:nth-child(2) .mu-value');
    if (stat1) stat1.textContent = d.universities ?? 0;
    if (stat2) stat2.textContent = d.specialties ?? 0;
  } catch {}
}

/*════════════════════ 2) إضافة جامعة ════════════════════*/
function bindUniversityForm() {
  const nameInp = document.getElementById('uniName');
  const dipSel  = document.getElementById('diplomaTypeSelect');
  const btn     = nameInp?.closest('.upg-card')?.querySelector('button');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const payload = {
      name: (nameInp?.value || '').trim(),
      diploma_type_id: dipSel?.value || ''
    };
    if (!payload.name || !payload.diploma_type_id) {
      return showWarning('أدخل اسم الجامعة واختر نوع الدبلوم');
    }

    btn.disabled = true;
    try {
      const r = await fetch(`${API_BASE}/universities/store`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload)
      });
      if (!r.ok) return handle(r);

      const uni = await r.json(); // {id,name, diploma, specialties:[]}
      appendUniToSelects(uni);
      appendUniLi(uni);
      appendUniCard(uni);

      if (nameInp) nameInp.value = '';
      if (dipSel)  dipSel.value  = '';

      loadCounts();
      refreshUniversitiesList();
      showSuccess('أُضيفت الجامعة بنجاح', undefined, true);
    } finally {
      btn.disabled = false;
    }
  });
}

/*════════════════════ 3) إضافة تخصّص ════════════════════*/
function bindSpecialtyForm() {
  const input = document.getElementById('specName');
  const btn   = input?.closest('.upg-card')?.querySelector('button');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const name = (input?.value || '').trim();
    if (!name) return showWarning('أدخل اسم التخصص');

    btn.disabled = true;
    try {
      const r = await fetch(`${API_BASE}/specialties/store`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ name }),
      });
      if (!r.ok) return handle(r);

      const spec = await r.json(); // {id,name}

      // أضِف checkbox جديداً
      const wrapper = document.getElementById('specCheckboxes');
      if (wrapper) {
        wrapper.insertAdjacentHTML(
          'beforeend',
          `<div>
             <input type="checkbox" id="spec${spec.id}" value="${spec.id}">
             <label for="spec${spec.id}">${escapeHtml(spec.name)}</label>
           </div>`
        );
      }

      if (input) input.value = '';
      loadCounts();
      refreshSpecialtiesList();
      showSuccess('أُضيف التخصّص بنجاح', undefined, true);
    } finally {
      btn.disabled = false;
    }
  });
}

/*══════════════ 4) ربط تخصّصات بجامعة ═════════════════*/
function bindLinkForm() {
  const btn = document.querySelector('#specCheckboxes')?.closest('.upg-card')?.querySelector('button');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const uniId   = document.getElementById('uniSelect')?.value || '';
    const specIds = Array.from(document.querySelectorAll('#specCheckboxes input:checked')).map(i => i.value);

    if (!uniId || !specIds.length) {
      showWarning('اختر جامعة وتخصّصًا واحدًا على الأقل');
      return;
    }

    btn.disabled = true;
    try {
      const r = await fetch(`${API_BASE}/specialties/link/${uniId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ specialty_ids: specIds })
      });

      if (r.ok) {
        refreshLinkedSpecs(uniId);
        showSuccess('تمّ الربط بنجاح', undefined, true);
      } else if (r.status === 409) {
        const { duplicates = [] } = await r.json().catch(() => ({}));
        // عرض أسماء بدل IDs إن أمكن
        const pretty = duplicates.map(id => getSpecNameById(id)).join(', ');
        showWarning(pretty ? `(${pretty}) مرتبطة مسبقًا` : 'بعض التخصّصات مرتبطة مسبقًا');
      } else {
        await handle(r);
      }
    } finally {
      btn.disabled = false;
    }
  });
}

/*════════════════════ 5) قوائم الحذف (UL) ═════════════════*/
function refreshUniversitiesList() {
  const ul = document.getElementById('universitiesList');
  if (!ul) return;

  ul.innerHTML = '';
  document.querySelectorAll('#uniSelect option[value]').forEach(o => {
    if (!o.value) return;
    ul.insertAdjacentHTML('beforeend', liTpl(o.value, o.textContent));
  });
  ul.querySelectorAll('button[data-id]').forEach(b => b.addEventListener('click', () => delUni(b.dataset.id)));
}

function refreshSpecialtiesList() {
  const ul = document.getElementById('specialtiesList');
  if (!ul) return;

  ul.innerHTML = '';
  document.querySelectorAll('#specCheckboxes input[type="checkbox"]').forEach(chk => {
    const name = chk.nextElementSibling ? chk.nextElementSibling.textContent : '';
    ul.insertAdjacentHTML('beforeend', liTpl(chk.value, name));
  });
  ul.querySelectorAll('button[data-id]').forEach(b => b.addEventListener('click', () => delSpec(b.dataset.id)));
}

/*═══════════════ 6) التخصّصات المرتبطة بجامعة ═════════════*/
async function refreshLinkedSpecs(uniId) {
  const ul = document.getElementById('linkedSpecsList');
  if (!ul) return;

  // إن لم تُحدَّد جامعة — نظّف كل شيء
  if (!uniId) {
    ul.innerHTML = '';
    document.querySelectorAll('#specCheckboxes input[type="checkbox"]').forEach(chk => (chk.checked = false));
    return;
  }

  const r = await fetch(`${API_BASE}/universities/show/${uniId}`, { headers: { Accept: 'application/json' } });
  if (!r.ok) {
    ul.innerHTML = '';
    document.querySelectorAll('#specCheckboxes input[type="checkbox"]').forEach(chk => (chk.checked = false));
    return;
  }

  const { specialties = [] } = await r.json();
  ul.innerHTML = '';

  specialties.forEach((s) => ul.insertAdjacentHTML('beforeend', liTpl(s.id, s.name)));
  ul.querySelectorAll('button[data-id]').forEach((b) => b.addEventListener('click', () => unlinkSpec(uniId, b.dataset.id)));

  // تعليم الـ checkboxes المرتبطة
  const ids = new Set(specialties.map((s) => String(s.id)));
  document.querySelectorAll('#specCheckboxes input[type="checkbox"]').forEach((chk) => {
    chk.checked = ids.has(chk.value);
  });
}

/*════════════════════ 7) حذف / إلغاء ربط ═════════════════*/
async function delUni(id) {
  await showConfirm({
    title: 'حذف جامعة؟',
    confirmText: 'نعم، احذف',
    cancelText: 'إلغاء',
    onConfirm: async () => {
      const r = await fetch(`${API_BASE}/universities/delete/${id}`, { method: 'DELETE' });
      if (!r.ok) return handle(r);  // لو FK يمنع الحذف، ما نغيّر الواجهة

      // إزالة من selectات
      const esc = escSel(id);
      document.querySelectorAll(`select#uniSelect option[value="${esc}"]`).forEach(o => o.remove());
      document.querySelectorAll(`select#universityForSpecs option[value="${esc}"]`).forEach(o => o.remove());

      // تنظيف كروت الواجهة
      document
        .getElementById('uniCards')
        ?.querySelectorAll(`.uni-card .delete-btn[data-id="${esc}"]`)
        .forEach(btn => btn.closest('.uni-card')?.remove());

      // تنظيف قائمة الحذف
      document
        .getElementById('universitiesList')
        ?.querySelectorAll(`button[data-id="${esc}"]`)
        .forEach(b => b.closest('li')?.remove());

      // لو هي الجامعة المختارة حاليًا — نظف قائمة الربط
      const currentUni = document.getElementById('uniSelect')?.value;
      if (currentUni === String(id)) {
        document.getElementById('uniSelect').value = '';
        await refreshLinkedSpecs(''); // يفرّغ القوائم والـ checks
      }

      loadCounts();
      showSuccess('حُذفت الجامعة', undefined, true);
    }
  });
}

async function delSpec(id) {
  await showConfirm({
    title: 'حذف تخصّص؟',
    confirmText: 'نعم، احذف',
    cancelText: 'إلغاء',
    onConfirm: async () => {
      const r = await fetch(`${API_BASE}/specialties/delete/${id}`, { method: 'DELETE' });
      if (!r.ok) return handle(r);

      // 1) احذف الـ checkbox من قائمة الاختيار
      const wrapper = document.getElementById('specCheckboxes');
      const chkDiv = wrapper?.querySelector(`input[value="${escSel(id)}"]`)?.closest('div');
      chkDiv?.remove();

      // 2) احذف من قائمة الحذف (UL)
      document
        .getElementById('specialtiesList')
        ?.querySelectorAll(`button[data-id="${escSel(id)}"]`)
        .forEach(b => b.closest('li')?.remove());

      // 3) إن كان التخصص ظاهرًا ضمن “المرتبطة بالجامعة المختارة” — أعِد تحميلها
      const uniId = document.getElementById('uniSelect')?.value || '';
      if (uniId) await refreshLinkedSpecs(uniId);

      // 4) حدث العدادات
      loadCounts();

      showSuccess('حُذِف التخصّص نهائيًا', undefined, true);
    }
  });
}

async function unlinkSpec(uniId, specId) {
  await showConfirm({
    title: 'إلغاء الربط؟',
    confirmText: 'نعم، أزل الربط',
    cancelText: 'إلغاء',
    onConfirm: async () => {
      const r = await fetch(`${API_BASE}/specialties/unlink/${uniId}/${specId}`, { method: 'DELETE' });
      if (!r.ok) return handle(r);

      refreshLinkedSpecs(uniId);
      showSuccess('أُزيل الربط', undefined, true);
    }
  });
}

/*════════════════════ 8) كروت الجامعات السُفلى ════════════*/
function bindUniversityCards() {
  // استمع للنقر على أزرار الحذف داخل حاوية كروت الجامعات
  document.getElementById('uniCards')?.addEventListener('click', (e) => {
    const del = e.target.closest('.delete-btn');
    if (del) delUni(del.dataset.id);
  });
}

/*══════════════ 9) Helpers ══════════════*/
function liTpl(id, txt) {
  return `<li>
            <span>${escapeHtml(txt || '')}</span>
            <button data-id="${String(id)}" class="link-danger" title="حذف">
              <i class="fas fa-trash"></i>
            </button>
          </li>`;
}

function appendUniToSelects({ id, name }) {
  const o = new Option(name, id);
  document.getElementById('uniSelect')?.appendChild(o.cloneNode(true));
  document.getElementById('universityForSpecs')?.appendChild(o); // إن وُجد Select إضافي
}

function appendUniLi(uni) {
  document.getElementById('universitiesList')?.insertAdjacentHTML('beforeend', liTpl(uni.id, uni.name));
}

function appendUniCard(uni) {
  document.getElementById('uniCards')?.insertAdjacentHTML(
    'beforeend',
    `
      <div class="year-card uni-card">
        <div class="year-header">
          <div class="year-title"><i class="fas fa-university"></i> ${escapeHtml(uni.name)}</div>
        </div>
        <div class="actions">
          <button class="action-btn-years delete-btn" data-id="${String(uni.id)}">
            <i class="fas fa-trash"></i> حذف
          </button>
        </div>
      </div>
    `
  );
}

function getSpecNameById(id) {
  const label = document.querySelector(`#specCheckboxes input[value="${escSel(id)}"] + label`);
  return label ? label.textContent.trim() : String(id);
}

function escapeHtml(s = '') {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// هروب آمن لمحددات CSS عند البحث داخل DOM
function escSel(v) {
  const s = String(v);
  if (window.CSS && typeof CSS.escape === 'function') return CSS.escape(s);
  // fallback بسيط يغطي أشهر الحالات
  return s.replace(/([!"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~])/g, '\\$1');
}

/*════════════════ 10) Error handler ════════════════*/
async function handle(res) {
  let msg = `HTTP ${res.status}`;
  try {
    const txt = await res.text();
    try {
      const j = JSON.parse(txt);
      msg = Object.values(j.errors || j.messages || j).join('<br>') || j.message || msg;
    } catch {
      msg = txt || msg;
    }
  } catch {}
  showError(msg);
}
