/* =========================================================================
 *  Admin: Manage Users (Merged Sources)
 *  - Base data from default.diploma_users
 *  - Enrichment (name/email/avatar) from remote.users
 * ========================================================================= */
/* global Swal */
export function init () {
  const $ = (id) => document.getElementById(id);

  // ===== عناصر الجدول والفلاتر =====
  const tableBody       = $("users-table-body");
  const roleFilter      = $("role-filter");
  const usernameSearch  = $("username-search"); // من remote.users.name
  const phoneSearch     = $("phone-search");    // من default.diploma_users.phone_number
  const emailSearch     = $("email-search");    // من remote.users.email
  const completeFilter  = $("complete-filter"); // 👈 جديد: فلترة اكتمال الملف (du.is_complete)

  // ===== عناصر الباجينيشن =====
  const firstPageBtn    = $("firstPageBtn");
  const prevPageBtn     = $("prevPageBtn");
  const nextPageBtn     = $("nextPageBtn");
  const lastPageBtn     = $("lastPageBtn");
  const paginationInfo  = $("paginationInfo");

  // ===== كروت الإحصاءات =====
  const statStudentsEl = $("statStudents");
  const statTeachersEl = $("statTeachers");
  const statAdminsEl   = $("statAdmins");

  // ===== إعدادات عامة =====
  const API = {
    LIST:  "/manage_users/list",
    OPTS:  "/manage_users/options",
    STATS: "/manage_users/stats",
    UPDATE:"/manage_users/update_account",
    DEL:   "/manage_users/delete",
  };

  // CSRF (اختياري إن كان مفعل في CI4)
  const CSRF = readCsrf();

  let debounceTimer;

  // ===== القوائم المنسدلة (خيارات) =====
  let rolesOptions       = [];
  let universityOptions  = [];
  let yearOptions        = [];
  let specialtyOptions   = [];

  // ===== حالة الباجينيشن =====
  const pagination = {
    page: 1,
    perPage: 10,
    total: 0,
    totalPages: 1,
  };

  // ================== نقطة البدء ==================
  (async () => {
    await fetchDropdownOptions();   // جلب القوائم
    renderRoleFilter();             // ملء DDL الأدوار
    wirePaginationButtons();        // ربط أزرار الباجينيشن
    fetchUsers();                   // جلب أول صفحة
    await fetchUsersStats();
  })();

  // ================== الفلاتر ==================
  function renderRoleFilter() {
    let html = `<option value="">الكل</option>` +
      rolesOptions.map(r => `<option value="${escapeHtml(r.id)}">${escapeHtml(r.name)}</option>`).join("");
    roleFilter.innerHTML = html;
  }

  roleFilter.addEventListener('change', () => {
    pagination.page = 1;            // الرجوع للصفحة الأولى عند تغيير الفلتر
    fetchUsersWithFilter();
  });

  // 👇 جديد: الاستماع لتغيير فلترة اكتمال الملف
  if (completeFilter) {
    completeFilter.addEventListener('change', () => {
      pagination.page = 1;
      fetchUsersWithFilter();
    });
  }

  [ usernameSearch, phoneSearch, emailSearch ].forEach(inputEl => {
    inputEl.addEventListener('input', () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        pagination.page = 1;        // الرجوع للصفحة الأولى عند البحث
        fetchUsersWithFilter();
      }, 700);
    });
  });

  // ================== إحصاءات ==================
  async function fetchUsersStats() {
    try {
      const res = await safeFetch(API.STATS);
      const j = await res.json();

      if (j?.success && j?.data) {
        const { students = 0, teachers = 0, admins = 0 } = j.data;

        if (statStudentsEl) statStudentsEl.setAttribute('data-target', students);
        if (statTeachersEl) statTeachersEl.setAttribute('data-target', teachers);
        if (statAdminsEl)   statAdminsEl.setAttribute('data-target', admins);

        runCounters({ students, teachers, admins });

        document.querySelectorAll('.mu-card.is-loading')
          .forEach(c => c.classList.remove('is-loading'));
      } else {
        if (statStudentsEl) statStudentsEl.textContent = '0';
        if (statTeachersEl) statTeachersEl.textContent = '0';
        if (statAdminsEl)   statAdminsEl.textContent   = '0';
      }
    } catch {
      if (statStudentsEl) statStudentsEl.textContent = '—';
      if (statTeachersEl) statTeachersEl.textContent = '—';
      if (statAdminsEl)   statAdminsEl.textContent   = '—';
    }
  }

  // عدّاد متحرك من 0 → الهدف
  function animateValue(el, to, duration = 1000){
    if (!el) return;

    const latinFormatter =
      (typeof Intl !== 'undefined' && Intl.NumberFormat)
        ? new Intl.NumberFormat('en-US', { maximumFractionDigits: 0, useGrouping: true })
        : null;

    const from = 0;
    const start = performance.now();

    const formatLatin = (n) => {
      const x = Math.floor(n);
      if (latinFormatter) return latinFormatter.format(x);
      return String(x).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    function frame(now){
      const p = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - p, 3); // easeOutCubic
      el.textContent = formatLatin(from + (to - from) * eased);
      if (p < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  function runCounters({students=0, teachers=0, admins=0} = {}){
    animateValue(statStudentsEl, Number(students)||0, 1200);
    animateValue(statTeachersEl, Number(teachers)||0, 1350);
    animateValue(statAdminsEl,   Number(admins)||0,   1500);
  }

  // ================== أزرار الباجينيشن ==================
  function wirePaginationButtons() {
    if (!firstPageBtn || !prevPageBtn || !nextPageBtn || !lastPageBtn) return;

    firstPageBtn.addEventListener('click', () => goToPage(1));
    prevPageBtn.addEventListener('click',  () => goToPage(pagination.page - 1));
    nextPageBtn.addEventListener('click',  () => goToPage(pagination.page + 1));
    lastPageBtn.addEventListener('click',  () => goToPage(pagination.totalPages));
  }

  function goToPage(targetPage) {
    if (!targetPage || targetPage < 1) return;
    if (pagination.totalPages && targetPage > pagination.totalPages) return;
    if (targetPage === pagination.page) return;

    pagination.page = targetPage;
    fetchUsersWithFilter();
  }

  function updatePaginationUI() {
    if (paginationInfo) {
      const totalPages = pagination.totalPages || 1;
      paginationInfo.textContent = `صفحة ${pagination.page} من ${totalPages}`;
    }

    const disablePrev = pagination.page <= 1;
    const disableNext = pagination.page >= (pagination.totalPages || 1);

    if (firstPageBtn) firstPageBtn.disabled = disablePrev;
    if (prevPageBtn)  prevPageBtn.disabled  = disablePrev;
    if (nextPageBtn)  nextPageBtn.disabled  = disableNext;
    if (lastPageBtn)  lastPageBtn.disabled  = disableNext;
  }

  // ================== طلبات القوائم المنسدلة ==================
  async function fetchDropdownOptions () {
    try {
      const res  = await safeFetch(API.OPTS);
      const json = await res.json();

      rolesOptions      = json.roles        ?? [];
      universityOptions = json.universities ?? [];
      yearOptions       = json.years        ?? [];
      specialtyOptions  = json.specialties  ?? [];
    } catch {
      showError("فشل تحميل القوائم المنسدلة");
    }
  }

  // ================== جلب المستخدمين ==================
  function buildQueryParams() {
    const params = new URLSearchParams({
      role_id : roleFilter?.value || '',
      username: (usernameSearch?.value || '').trim(), // فلتر remote.users.name
      phone   : (phoneSearch?.value || '').trim(),    // فلتر diploma_users.phone_number
      email   : (emailSearch?.value || '').trim(),    // فلتر remote.users.email
      complete: (completeFilter?.value ?? ''),        // 👈 جديد: '1' أو '0' أو ''
      page    : String(pagination.page),
      per_page: String(pagination.perPage),
    });
    return params;
  }

  function fetchUsers () {
    pagination.page = 1;
    fetchUsersWithFilter();
  }

  function fetchUsersWithFilter() {
    tableBody.innerHTML =
      `<tr><td colspan="10" style="text-align:center">جاري التحميل…</td></tr>`;

    const params = buildQueryParams();

    safeFetch(`${API.LIST}?${params.toString()}`)
      .then(r => r.json())
      .then(j => {
        const users = (j.data && j.data.users) ? j.data.users : (j.users || []);
        const p     = (j.data && j.data.pagination) ? j.data.pagination : null;

        if (p) {
          pagination.page       = Number(p.page)        || pagination.page;
          pagination.perPage    = Number(p.per_page)    || pagination.perPage;
          pagination.total      = Number(p.total)       || 0;
          pagination.totalPages = Number(p.total_pages) || 1;
        } else {
          pagination.total      = users.length;
          pagination.totalPages = 1;
          pagination.page       = 1;
        }

        renderUsers(users);
        updatePaginationUI();
      })
      .catch(() => {
        tableBody.innerHTML =
          `<tr><td colspan="10" style="text-align:center;color:#a00">فشل تحميل البيانات</td></tr>`;
      });
  }

  // ================== بناء الجدول ==================
  function renderUsers (list) {
    if (!list || !list.length) {
      tableBody.innerHTML =
        `<tr><td colspan="10" style="text-align:center">لا يوجد مستخدمون</td></tr>`;
      return;
    }

    tableBody.innerHTML = list.map(u => {
      const safeName  = escapeHtml(u.name ?? '—'); // قد لا يوجد سجل في remote
      const safeEmail = escapeHtml(u.email ?? '-');
      const safePhone = escapeHtml(u.phone_number ?? '-');

      return `
      <tr data-id="${escapeHtml(u.id)}">
        <!-- الصورة -->
        <td>${renderImage(u.profile_image_url, u.gender)}</td>

        <!-- بيانات أساسيّة -->
        <td>${safeName}</td>
        <td>${safePhone}</td>
        <td>${safeEmail}</td>

        <!-- الحقول القابلة للتعديل (من default) -->
        <td>${buildSelect('role',       u.role_id)}</td>
        <td>${buildSelect('university', u.university_id)}</td>
        <td>${buildSelect('year',       u.academic_year_id)}</td>
        <td>${buildSelect('specialty',  u.specialty_id)}</td>

        <!-- الإجراءات -->
        <td class="action-buttons-user">
          <button class="action-btn-user edit-btn-user"          title="تعديل"><i class="fas fa-edit"></i></button>
          <button class="action-btn-user save-btn-user d-none"   title="حفظ"><i class="fas fa-check"></i></button>
          <button class="action-btn-user cancel-btn-user d-none" title="إلغاء"><i class="fas fa-times"></i></button>
          <button class="action-btn-user delete-btn-user"        title="حذف"><i class="fas fa-trash-alt"></i></button>
        </td>
      </tr>`;
    }).join("");

    bindUserActions();
  }

  // ================== مساعدات ==================
  /** Fallback للصورة حسب الجنس */
  function renderImage (url, genderOrOptions) {
    const isString = typeof genderOrOptions === 'string';
    const opts = isString ? { gender: genderOrOptions } : (genderOrOptions || {});

    const gender = (opts.gender === 'female') ? 'female' : 'male';
    const size   = Number(opts.size ?? 48);
    const high   = (opts.priority === 'high');
    const version= opts.version ?? null;
    const alt    = opts.alt ?? 'User avatar';

    // ✅ فallback متوافق مع منصتك
    const fallback = (gender === 'female')
      ? '/public/uploads/profile/default_female.jpg'
      : '/public/uploads/profile/default_male.jpg';

    let src = (url && String(url).trim()) ? String(url).trim() : fallback;

    if (version && !/^https?:\/\//i.test(src) && !/^\/\//.test(src)) {
      const sep = src.includes('?') ? '&' : '?';
      src = `${src}${sep}v=${encodeURIComponent(version)}`;
    }

    const attrs = [
      `src="${escapeHtml(src)}"`,
      `alt="${escapeHtml(alt)}"`,
      `class="user-avatar"`,
      `width="${size}"`,
      `height="${size}"`,
      `decoding="async"`
    ];

    if (high) {
      attrs.push(`fetchpriority="high"`);
    } else {
      attrs.push(`loading="lazy"`);
    }

    const onErr = `this.onerror=null;this.src='${escapeHtml(fallback)}';`;
    attrs.push(`onerror="${onErr}"`);

    return `<img ${attrs.join(' ')} />`;
  }

  function escapeHtml (s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  // إضافة خيار "غير محدد" ودعم null بشكل صريح
  function buildSelect(type, selected) {
    const dataMap = {
      role       : rolesOptions,
      university : universityOptions,
      year       : yearOptions,
      specialty  : specialtyOptions
    };

    const cls = `select-${type}`;
    const isNullish = (selected === null || selected === undefined || selected === '' || Number(selected) === 0);

    const opts =
      `<option value="" ${isNullish ? 'selected' : ''}>غير محدد</option>` +
      (dataMap[type] || [])
        .map(o => {
          const sel = String(o.id) === String(selected) ? 'selected' : '';
          return `<option value="${escapeHtml(o.id)}" ${sel}>${escapeHtml(o.name)}</option>`;
        })
        .join("");

    return `<select class="form-control ${cls}" disabled>${opts}</select>`;
  }

  // ================== ربط الأحداث (تعديل/حذف) ==================
  function bindUserActions () {
    bindEdit();
    bindDelete();
  }

  // ——— زر ✏️ ———
  function bindEdit () {
    document.querySelectorAll(".edit-btn-user").forEach(btn => {
      btn.addEventListener("click", () => startEdit(btn.closest("tr")));
    });
  }

  function startEdit (row) {
    row.querySelectorAll("select").forEach(s => s.disabled = false);

    row.querySelector(".edit-btn-user").classList.add("d-none");
    row.querySelector(".delete-btn-user").classList.add("d-none");
    row.querySelector(".save-btn-user").classList.remove("d-none");
    row.querySelector(".cancel-btn-user").classList.remove("d-none");

    row.querySelector(".save-btn-user")  .onclick = () => saveChanges(row);
    row.querySelector(".cancel-btn-user").onclick = () => fetchUsersWithFilter();
  }

  // ——— زر ✅ ———
  async function saveChanges (row) {
    const id = Number(row.dataset.id);
    const toNullInt = (v) => (v === '' || v === 'null' || v === null || v === undefined) ? null : Number(v);

    const payload = {
      user_id         : id, // هذا هو users.id = diploma_users.user_id
      role_id         : toNullInt(row.querySelector(".select-role")?.value),
      university_id   : toNullInt(row.querySelector(".select-university")?.value),
      academic_year_id: toNullInt(row.querySelector(".select-year")?.value),
      specialty_id    : toNullInt(row.querySelector(".select-specialty")?.value)
    };

    const saveBtn   = row.querySelector(".save-btn-user");
    const cancelBtn = row.querySelector(".cancel-btn-user");

    try {
      if (saveBtn)   saveBtn.disabled   = true;
      if (cancelBtn) cancelBtn.disabled = true;

      const res = await safeFetch(API.UPDATE, {
        method : "POST",
        headers: { "Content-Type": "application/json" },
        body   : JSON.stringify(payload)
      });

      if (!res.ok) {
        const text = await res.text().catch(() => '');
        throw new Error(text || `HTTP ${res.status}`);
      }

      const j = await res.json();
      if (j?.success) {
        showSuccess("حُفظت التغييرات", undefined, true);
        fetchUsersWithFilter();
      } else {
        throw new Error(j?.message || "لم يتم الحفظ");
      }
    } catch (e) {
      showError(e.message || "فشل الاتصال بالخادم");
    } finally {
      if (saveBtn)   saveBtn.disabled   = false;
      if (cancelBtn) cancelBtn.disabled = false;
    }
  }

  // ——— زر 🗑️ ———
  function bindDelete() {
    document.querySelectorAll(".delete-btn-user").forEach(btn => {
      btn.addEventListener("click", () => {
        const row  = btn.closest("tr");
        const id   = row.dataset.id;
        const name = row.children[1].textContent || 'المستخدم';

        showConfirm({
          title: `حذف ${name}?`,
          text: "لا يمكن التراجع عن هذه العملية.",
          confirmText: "نعم، احذفه",
          cancelText: "إلغاء",
          onConfirm: async () => {
            try {
              const res = await safeFetch(API.DEL, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ user_id: id })
              });

              const j = await res.json();
              if (j.success) {
                showSuccess("حُذف المستخدم", undefined, true);
                fetchUsersWithFilter();
              } else {
                throw new Error(j.message || "لم يُحذف");
              }
            } catch {
              showError("فشل الاتصال بالخادم");
            }
          }
        });
      });
    });
  }

  // ================== أدوات مشتركة ==================
  function readCsrf() {
    // لو CI4 CSRF مفعّل، ضع Meta: <meta name="csrf-token" content="...">
    const meta = document.querySelector('meta[name="csrf-token"]');
    const token = meta?.getAttribute('content') || null;
    return token ? { header: 'X-CSRF-TOKEN', token } : null;
  }

  function safeFetch(url, opts = {}) {
    const headers = {
      "X-Requested-With": "XMLHttpRequest",
      ...(opts.headers || {})
    };
    if (CSRF && !('X-CSRF-TOKEN' in headers)) {
      headers[CSRF.header] = CSRF.token;
    }
    return fetch(url, { ...opts, headers });
  }

  function showError(msg = "حدث خطأ") {
    if (typeof Swal?.fire === 'function') {
      Swal.fire({ icon: 'error', title: 'خطأ', text: String(msg), confirmButtonText: 'حسنًا' });
    } else {
      alert(`خطأ: ${msg}`);
    }
  }

  function showSuccess(msg = "تم بنجاح", sub = '', toast = false) {
    if (typeof Swal?.fire === 'function') {
      if (toast) {
        Swal.fire({ icon: 'success', title: msg, text: sub, toast: true, position: 'top-end', timer: 1600, showConfirmButton: false });
      } else {
        Swal.fire({ icon: 'success', title: 'نجاح', text: msg, confirmButtonText: 'حسنًا' });
      }
    } else {
      console.log('SUCCESS:', msg);
    }
  }

  function showConfirm({ title='تأكيد', text='', confirmText='موافق', cancelText='إلغاء', onConfirm=()=>{} } = {}) {
    if (typeof Swal?.fire === 'function') {
      Swal.fire({
        icon: 'warning',
        title, text,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText
      }).then(r => { if (r.isConfirmed) onConfirm(); });
    } else {
      if (confirm(`${title}\n${text}`)) onConfirm();
    }
  }
}
