<section id="usersPage" class="mu2">

  <!-- ===== Header ===== -->
  <header class="mu2-head">
    <div class="mu2-head__main">
      <div class="mu2-head__icon"><i class="fas fa-user-cog" aria-hidden="true"></i></div>
      <div>
        <h2>إدارة الحسابات</h2>
        <p>لوحة احترافية لمراجعة المستخدمين ونشاطهم وإدارة بياناتهم</p>
      </div>
    </div>
    <button type="button" class="mu2-btn mu2-btn--ghost" id="btnRefreshAll"><i class="fas fa-rotate"></i> تحديث</button>
  </header>

  <!-- ===== KPIs ===== -->
  <div class="mu2-kpis" id="muKpis">
    <div class="mu2-kpi"><span class="mu2-kpi__v" id="kTotal">0</span><span class="mu2-kpi__l"><i class="fas fa-users"></i> إجمالي</span></div>
    <div class="mu2-kpi"><span class="mu2-kpi__v" id="kStudents">0</span><span class="mu2-kpi__l"><i class="fas fa-user-graduate"></i> طلاب</span></div>
    <div class="mu2-kpi"><span class="mu2-kpi__v" id="kTeachers">0</span><span class="mu2-kpi__l"><i class="fas fa-chalkboard-teacher"></i> معلمون</span></div>
    <div class="mu2-kpi"><span class="mu2-kpi__v" id="kAdmins">0</span><span class="mu2-kpi__l"><i class="fas fa-user-shield"></i> أدمن</span></div>
    <div class="mu2-kpi"><span class="mu2-kpi__v" id="kActive30">0</span><span class="mu2-kpi__l"><i class="fas fa-bolt"></i> نشط (30ي)</span></div>
    <div class="mu2-kpi"><span class="mu2-kpi__v" id="kNew30">0</span><span class="mu2-kpi__l"><i class="fas fa-user-plus"></i> جدد (30ي)</span></div>
    <div class="mu2-kpi"><span class="mu2-kpi__v" id="kCompleted">0</span><span class="mu2-kpi__l"><i class="fas fa-clipboard-check"></i> مكتمل</span></div>
    <div class="mu2-kpi"><span class="mu2-kpi__v" id="kPremium">0</span><span class="mu2-kpi__l"><i class="fas fa-crown"></i> مميّز</span></div>
    <div class="mu2-kpi mu2-kpi--warn"><span class="mu2-kpi__v" id="kScheduled">0</span><span class="mu2-kpi__l"><i class="fas fa-clock"></i> مجدول للحذف</span></div>
  </div>

  <!-- ===== Charts ===== -->
  <div class="mu2-charts">
    <div class="mu2-card mu2-chart"><div class="mu2-card__h"><i class="fas fa-chart-line"></i> التسجيلات (12 شهراً)</div><div class="mu2-card__b"><canvas id="chartReg" height="150"></canvas></div></div>
    <div class="mu2-card mu2-chart"><div class="mu2-card__h"><i class="fas fa-chart-pie"></i> توزيع الأدوار</div><div class="mu2-card__b" style="display:flex;justify-content:center;"><canvas id="chartRoles" height="150"></canvas></div></div>
    <div class="mu2-card mu2-chart"><div class="mu2-card__h"><i class="fas fa-right-to-bracket"></i> الدخول اليومي (30ي)</div><div class="mu2-card__b"><canvas id="chartLogins" height="150"></canvas></div></div>
  </div>

  <!-- ===== Filters ===== -->
  <div class="mu2-card">
    <div class="mu2-card__h"><i class="fas fa-filter"></i> الفلاتر</div>
    <div class="mu2-card__b">
      <div class="mu2-filters">
        <label class="mu2-fld"><span>الدور</span><select id="fRole"></select></label>
        <label class="mu2-fld"><span>اكتمال الملف</span><select id="fComplete"><option value="">الكل</option><option value="1">مكتمل</option><option value="0">غير مكتمل</option></select></label>
        <label class="mu2-fld"><span>النشاط</span><select id="fActivity">
          <option value="">الكل</option>
          <option value="active_7">نشط خلال 7 أيام</option>
          <option value="active_30">نشط خلال 30 يوم</option>
          <option value="inactive_30">خامل منذ 30+ يوم</option>
          <option value="inactive_90">خامل منذ 90+ يوم</option>
          <option value="never">لم يدخل قط</option>
        </select></label>
        <label class="mu2-fld"><span>تسجيل من</span><input type="date" id="fRegFrom"></label>
        <label class="mu2-fld"><span>تسجيل إلى</span><input type="date" id="fRegTo"></label>
        <label class="mu2-fld"><span>الاسم</span><input type="text" id="fUsername" placeholder="بحث بالاسم"></label>
        <label class="mu2-fld"><span>البريد</span><input type="text" id="fEmail" placeholder="بحث بالبريد"></label>
        <label class="mu2-fld"><span>الهاتف</span><input type="text" id="fPhone" placeholder="بحث بالهاتف"></label>
        <label class="mu2-fld"><span>الترتيب</span><select id="fSort">
          <option value="">الأحدث إضافة</option>
          <option value="created_desc">الأحدث تسجيلاً</option>
          <option value="created_asc">الأقدم تسجيلاً</option>
          <option value="login_desc">آخر دخول (الأحدث)</option>
          <option value="seen_desc">آخر نشاط (الأحدث)</option>
        </select></label>
        <label class="mu2-fld mu2-fld--chk"><input type="checkbox" id="fScheduled"><span>المجدولون للحذف فقط</span></label>
        <button type="button" class="mu2-btn mu2-btn--ghost" id="btnResetFilters"><i class="fas fa-eraser"></i> مسح</button>
      </div>
    </div>
  </div>

  <!-- ===== Bulk action bar ===== -->
  <div class="mu2-bulk" id="bulkBar" hidden>
    <span class="mu2-bulk__count"><i class="fas fa-check-double"></i> <b id="bulkCount">0</b> محدد</span>
    <div class="mu2-bulk__actions">
      <button type="button" class="mu2-btn mu2-btn--warn" id="btnBulkNotify"><i class="fas fa-bell"></i> إشعار حذف خلال 30 يوم</button>
      <button type="button" class="mu2-btn mu2-btn--ghost" id="btnBulkCancel"><i class="fas fa-ban"></i> إلغاء الجدولة</button>
      <button type="button" class="mu2-btn mu2-btn--ghost" id="btnBulkExport"><i class="fas fa-file-csv"></i> تصدير CSV</button>
      <button type="button" class="mu2-btn mu2-btn--danger" id="btnBulkDelete"><i class="fas fa-trash"></i> حذف نهائي</button>
    </div>
  </div>

  <!-- ===== Table ===== -->
  <div class="mu2-card">
    <div class="mu2-card__h"><i class="fas fa-list"></i> قائمة المستخدمين</div>
    <div class="mu2-card__b mu2-tablewrap">
      <table class="mu2-table">
        <thead>
          <tr>
            <th class="mu2-chkcol"><input type="checkbox" id="selectAll" title="تحديد الكل"></th>
            <th>المستخدم</th>
            <th>الهاتف</th>
            <th>الدور</th>
            <th>تاريخ التسجيل</th>
            <th>آخر نشاط</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody id="usersTbody"></tbody>
      </table>
    </div>
    <div class="mu2-pager">
      <button class="mu2-pg" id="firstPageBtn" title="الأولى"><i class="fas fa-angle-double-right"></i></button>
      <button class="mu2-pg" id="prevPageBtn" title="السابق"><i class="fas fa-angle-right"></i></button>
      <span class="mu2-pginfo" id="paginationInfo">صفحة 1 من 1</span>
      <button class="mu2-pg" id="nextPageBtn" title="التالي"><i class="fas fa-angle-left"></i></button>
      <button class="mu2-pg" id="lastPageBtn" title="الأخيرة"><i class="fas fa-angle-double-left"></i></button>
    </div>
  </div>

  <p class="mu2-foot">© <span id="currentYear"></span> منصة كيف التعليمية — جميع الحقوق محفوظة</p>

  <!-- ===== Activity slide-over ===== -->
  <div class="mu2-drawer" id="activityPanel" hidden>
    <div class="mu2-drawer__scrim" data-close-drawer></div>
    <aside class="mu2-drawer__panel">
      <div class="mu2-drawer__head">
        <h3><i class="fas fa-chart-simple"></i> سجل النشاط</h3>
        <button type="button" class="mu2-btn mu2-btn--ghost" id="activityClose" data-close-drawer><i class="fas fa-times"></i></button>
      </div>
      <div class="mu2-drawer__body" id="activityBody"></div>
    </aside>
  </div>
</section>

<style>
/* ===== إدارة الحسابات — تصميم فلات (NotebookLM) ===== */
#usersPage{ display:flex; flex-direction:column; gap:16px; }
#usersPage *{ box-sizing:border-box; }
#usersPage .mu2-head{ display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;
  background:var(--surface-0); border:1px solid var(--border-color); border-radius:var(--border-radius); padding:16px 20px; }
#usersPage .mu2-head__main{ display:flex; align-items:center; gap:14px; }
#usersPage .mu2-head__icon{ width:46px;height:46px;flex:0 0 auto;border-radius:13px;background:var(--primary-50);color:var(--primary);
  border:1px solid var(--primary-300);display:grid;place-items:center;font-size:1.2rem; }
#usersPage .mu2-head h2{ margin:0;font-size:1.25rem;font-weight:800;color:var(--text-strong); }
#usersPage .mu2-head p{ margin:3px 0 0;font-size:.85rem;color:var(--text-muted); }

/* KPIs */
#usersPage .mu2-kpis{ display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; }
#usersPage .mu2-kpi{ background:var(--surface-0);border:1px solid var(--border-color);border-radius:12px;padding:12px 14px;
  display:flex;flex-direction:column;gap:4px; }
#usersPage .mu2-kpi__v{ font-size:1.5rem;font-weight:800;color:var(--text-strong);line-height:1; }
#usersPage .mu2-kpi__l{ font-size:.76rem;color:var(--text-muted);display:flex;align-items:center;gap:5px; }
#usersPage .mu2-kpi__l i{ color:var(--primary); }
#usersPage .mu2-kpi--warn .mu2-kpi__v{ color:#ef4444; }
#usersPage .mu2-kpi--warn .mu2-kpi__l i{ color:#ef4444; }

/* Cards */
#usersPage .mu2-card{ background:var(--surface-0);border:1px solid var(--border-color);border-radius:var(--border-radius);overflow:hidden; }
#usersPage .mu2-card__h{ display:flex;align-items:center;gap:8px;padding:13px 18px;border-bottom:1px solid var(--border-color);
  font-weight:700;color:var(--text-strong);font-size:.95rem;background:var(--surface-1); }
#usersPage .mu2-card__h i{ color:var(--primary); }
#usersPage .mu2-card__b{ padding:16px 18px; }

#usersPage .mu2-charts{ display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; align-items:start; }
#usersPage .mu2-chart .mu2-card__b{ min-height:170px; }

/* Filters */
#usersPage .mu2-filters{ display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; align-items:end; }
#usersPage .mu2-fld{ display:flex;flex-direction:column;gap:5px;font-size:.8rem;color:var(--text-muted); }
#usersPage .mu2-fld>span{ font-weight:600; }
#usersPage .mu2-fld input[type=text],#usersPage .mu2-fld input[type=date],#usersPage .mu2-fld select{
  border:1px solid var(--border-color);background:var(--surface-1);border-radius:9px;padding:9px 11px;color:var(--text);font-size:.9rem;font-family:inherit;outline:none; }
#usersPage .mu2-fld input:focus,#usersPage .mu2-fld select:focus{ border-color:var(--primary-300);box-shadow:var(--focus-ring); }
#usersPage .mu2-fld--chk{ flex-direction:row;align-items:center;gap:8px;font-size:.85rem;color:var(--text); }

/* Buttons */
#usersPage .mu2-btn{ display:inline-flex;align-items:center;gap:7px;border:1px solid var(--border-color);background:var(--surface-1);
  color:var(--text);border-radius:10px;padding:9px 14px;font-weight:700;font-size:.86rem;cursor:pointer;transition:all .15s ease;white-space:nowrap; }
#usersPage .mu2-btn:hover{ border-color:var(--primary-300);transform:translateY(-1px); }
#usersPage .mu2-btn--ghost{ background:var(--surface-1); }
#usersPage .mu2-btn--danger{ background:#ef4444;color:#fff;border-color:#ef4444; }
#usersPage .mu2-btn--danger:hover{ background:#dc2626; }
#usersPage .mu2-btn--warn{ background:var(--accent);color:var(--text-inverse);border-color:var(--accent); }

/* Bulk bar */
#usersPage .mu2-bulk{ position:sticky;top:8px;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
  background:var(--primary-50);border:1px solid var(--primary-300);border-radius:12px;padding:10px 16px; }
#usersPage .mu2-bulk__count{ font-weight:700;color:var(--primary-700); }
#usersPage .mu2-bulk__actions{ display:flex;gap:8px;flex-wrap:wrap; }

/* Table */
#usersPage .mu2-tablewrap{ padding:0;overflow:auto; }
#usersPage .mu2-table{ width:100%;border-collapse:collapse;font-size:.88rem; }
#usersPage .mu2-table th{ text-align:start;padding:11px 14px;background:var(--surface-1);color:var(--text-strong);font-weight:700;
  border-bottom:1px solid var(--border-color);white-space:nowrap; }
#usersPage .mu2-table td{ padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text);vertical-align:middle; }
#usersPage .mu2-table tbody tr:hover{ background:var(--primary-50); }
#usersPage .mu2-table tr.is-sel{ background:var(--primary-50); }
#usersPage .mu2-chkcol{ width:38px;text-align:center; }
#usersPage .mu2-uc{ display:flex;align-items:center;gap:10px;min-width:0; }
#usersPage .mu2-uc img{ width:38px;height:38px;border-radius:50%;object-fit:cover;border:1px solid var(--border-color);flex:0 0 auto; }
#usersPage .mu2-uc__t{ min-width:0; }
#usersPage .mu2-uc__n{ font-weight:700;color:var(--text-strong);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px; }
#usersPage .mu2-uc__e{ font-size:.76rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px; }
#usersPage .mu2-rolechip{ display:inline-block;padding:2px 9px;border-radius:999px;font-size:.74rem;font-weight:700;
  background:var(--surface-2);color:var(--text);border:1px solid var(--border-color); }
#usersPage .mu2-badges{ display:flex;gap:5px;flex-wrap:wrap; }
#usersPage .mu2-bdg{ font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid; }
#usersPage .mu2-bdg--prem{ background:rgba(253,158,2,.12);color:#b45309;border-color:rgba(253,158,2,.4); }
#usersPage .mu2-bdg--inc{ background:var(--surface-2);color:var(--text-muted);border-color:var(--border-color); }
#usersPage .mu2-bdg--del{ background:rgba(239,68,68,.12);color:#ef4444;border-color:rgba(239,68,68,.4); }
#usersPage .mu2-bdg--idle{ background:rgba(245,158,11,.1);color:#b45309;border-color:rgba(245,158,11,.35); }
#usersPage .mu2-act{ display:inline-flex;gap:4px; }
#usersPage .mu2-iconbtn{ width:30px;height:30px;border-radius:8px;border:1px solid var(--border-color);background:var(--surface-1);
  color:var(--text-muted);cursor:pointer;display:grid;place-items:center;transition:all .15s ease; }
#usersPage .mu2-iconbtn:hover{ border-color:var(--primary-300);color:var(--primary-700); }
#usersPage .mu2-iconbtn.del:hover{ border-color:#ef4444;color:#ef4444;background:rgba(239,68,68,.08); }
#usersPage .mu2-dim{ color:var(--text-muted);font-size:.8rem; }

/* Pager */
#usersPage .mu2-pager{ display:flex;align-items:center;justify-content:center;gap:8px;padding:12px; }
#usersPage .mu2-pg{ width:34px;height:34px;border-radius:9px;border:1px solid var(--border-color);background:var(--surface-1);
  color:var(--text);cursor:pointer; }
#usersPage .mu2-pg:hover:not(:disabled){ border-color:var(--primary-300);color:var(--primary-700); }
#usersPage .mu2-pg:disabled{ opacity:.45;cursor:not-allowed; }
#usersPage .mu2-pginfo{ font-size:.82rem;color:var(--text-muted);min-width:110px;text-align:center; }
#usersPage .mu2-foot{ text-align:center;color:var(--text-muted);font-size:.8rem;padding:4px 0; }

/* Drawer */
#usersPage .mu2-drawer{ position:fixed;inset:0;z-index:9999; }
#usersPage .mu2-drawer__scrim{ position:absolute;inset:0;background:rgba(0,0,0,.4); }
#usersPage .mu2-drawer__panel{ position:absolute;top:0;bottom:0;inset-inline-start:0;width:min(440px,92vw);background:var(--surface-0);
  border-inline-end:1px solid var(--border-color);box-shadow:var(--shadow-lg);display:flex;flex-direction:column;animation:mu2slide .2s ease; }
@keyframes mu2slide{ from{ transform:translateX(-12px);opacity:.6;} to{ transform:none;opacity:1;} }
#usersPage .mu2-drawer__head{ display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-color); }
#usersPage .mu2-drawer__head h3{ margin:0;font-size:1.05rem;color:var(--text-strong);display:flex;align-items:center;gap:8px; }
#usersPage .mu2-drawer__head i{ color:var(--primary); }
#usersPage .mu2-drawer__body{ padding:18px;overflow:auto; }
#usersPage .mu2-actsec{ margin-bottom:18px; }
#usersPage .mu2-actsec h4{ margin:0 0 8px;font-size:.85rem;color:var(--text-muted);font-weight:700; }
#usersPage .mu2-actrow{ display:flex;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px dashed var(--border-color);font-size:.85rem; }
#usersPage .mu2-actrow b{ color:var(--text-strong); }
#usersPage .mu2-dev{ border:1px solid var(--border-color);border-radius:10px;padding:9px 11px;margin-bottom:8px;font-size:.82rem;background:var(--surface-1); }
#usersPage .mu2-dev .d1{ font-weight:700;color:var(--text-strong); }
#usersPage .mu2-dev .d2{ color:var(--text-muted);font-size:.76rem; }

html[data-theme="dark"] #usersPage .mu2-head,
html[data-theme="dark"] #usersPage .mu2-kpi,
html[data-theme="dark"] #usersPage .mu2-card{ background:var(--surface-0)!important;border-color:var(--border-color)!important; }
html[data-theme="dark"] #usersPage .mu2-kpi__v,
html[data-theme="dark"] #usersPage .mu2-uc__n{ color:var(--text-strong)!important; }

@media (max-width:640px){
  #usersPage .mu2-table th:nth-child(3),#usersPage .mu2-table td:nth-child(3),
  #usersPage .mu2-table th:nth-child(5),#usersPage .mu2-table td:nth-child(5){ display:none; }
}
</style>

<script>document.getElementById("currentYear").textContent = new Date().getFullYear();</script>
