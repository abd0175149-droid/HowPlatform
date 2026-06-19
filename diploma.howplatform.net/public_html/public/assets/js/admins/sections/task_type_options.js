/* global Swal, showWarning, showSuccess, showError, showConfirm */

// نقطة الأساس حسب الراوتر:
// $routes->group('admins' ...)
//   $routes->group('api' ...)
//     $routes->group('task-type' ...)
const API_BASE = '/admins/api/task-type';

/*==============================================================
 | addTap: منع النداء المزدوج (pointerup + click)
 *=============================================================*/


/*==============================================================
 | init — نقطة الدخول
 *=============================================================*/
export function init() {
  loadCount();
  bindAddForm();
  bindDeleteBtns();

  const currentYear = document.getElementById('currentYear');
  if (currentYear) currentYear.textContent = new Date().getFullYear();
}

/*==============================================================
 | عدّاد البطاقة
 *=============================================================*/
async function loadCount() {
  try {
    const r = await fetch(`${API_BASE}/summary`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store'
    });
    if (!r.ok) return;
    const { count = 0 } = await r.json();
    const box = document.querySelector('.dashboard-card .count');
    if (box) box.textContent = count;
  } catch {
    // صامت: لا نزعج المستخدم
  }
}

/*==============================================================
 | إضافة نوع مهمة
 *=============================================================*/
function bindAddForm() {
  const input = document.getElementById('typeName');
  const btn   = input?.closest('.panel-section')?.querySelector('button');
  if (!btn) return;

  btn.addEventListener('click', async (e) => {
    e.preventDefault();
    // حماية إضافية (مع أن disabled يمنع النقر، لكن لزيادة التأكيد)
    if (btn.disabled) return;
    
    const name = (input?.value || '').trim();
    if (!name) return showWarning('يرجى إدخال اسم لنوع المهمة');

    btn.disabled = true;
    try {
      const r = await fetch(`${API_BASE}/create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ name })
      });

      if (!r.ok) return handleError(r);

      const type = await r.json(); // { id, name }
      appendLi(type);
      if (input) input.value = '';
      loadCount();
      showSuccess('أُضيف النوع بنجاح', undefined, true);
    } finally {
      btn.disabled = false;
    }
  });
}

/*==============================================================
 | حذف نوع مهمة
 *=============================================================*/
function bindDeleteBtns() {
  // تفويض أحداث الحذف على القائمة
  document.getElementById('typesList')?.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-id]');
    if (btn) deleteType(btn.dataset.id, btn);
  });
}

async function deleteType(id, btnEl) {
  await showConfirm({
    title: 'حذف نوع؟',
    text: 'لن يمكن التراجع عن هذه العملية.',
    confirmText: 'نعم، احذف',
    cancelText : 'إلغاء',
    onConfirm: async () => {
      const r = await fetch(`${API_BASE}/delete/${encodeURIComponent(id)}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' }
      });
      if (!r.ok) return handleError(r);

      // إزالة صف النوع من الواجهة
      btnEl?.parentElement?.remove();
      loadCount();
      showSuccess('حُذف النوع', undefined, true);
    }
  });
}

/*==============================================================
 | إضافة عنصر للقائمة
 *=============================================================*/
function appendLi({ id, name }) {
  const ul = document.getElementById('typesList');
  if (!ul) return;

  ul.insertAdjacentHTML('beforeend', `
    <li>
      <span>${escapeHtml(name)}</span>
      <button data-id="${String(id)}" class="link-danger" title="حذف">
        <i class="fas fa-trash"></i>
      </button>
    </li>
  `);
}

/*==============================================================
 | معالجة أخطاء HTTP (JSON موحّد)
 *=============================================================*/
async function handleError(res) {
  let msg = `HTTP ${res.status}`;
  try {
    const j = await res.json();
    if (typeof j?.error === 'string') msg = j.error;
    else if (j?.message) msg = j.message;
    else msg = Object.values(j.errors || j.messages || j).join('<br>') || msg;
  } catch {
    // تجاهل تحويل JSON الفاشل
  }
  showError(msg);
}

/*==============================================================
 | حراسة XSS بسيطة
 *=============================================================*/
function escapeHtml(s = '') {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
