<section id="aiToolsPage" class="upg-page" dir="rtl">
  <header class="ap-head">
    <div class="ap-head__title">
      <div class="ap-head__icon"><i class="fas fa-robot" aria-hidden="true"></i></div>
      <h2>إدارة أدوات الذكاء الاصطناعي</h2>
      <div class="ap-head__sub">تعديل بيانات الأدوات وإسنادها لأنواع المهام (الجامعات/المساقات).</div>
    </div>
  </header>

  <!-- التبويبات -->
  <div class="ait-tabs" style="display:flex;gap:8px;margin:14px 0;">
    <button type="button" class="ait-tab is-active" data-tab="tools"
      style="padding:9px 18px;border:none;border-radius:999px;cursor:pointer;font-weight:700;background:var(--primary-600);color:var(--text-inverse);">
      <i class="fas fa-toolbox"></i> الأدوات
    </button>
    <button type="button" class="ait-tab" data-tab="placements"
      style="padding:9px 18px;border:none;border-radius:999px;cursor:pointer;font-weight:700;background:var(--surface-2);color:var(--text-strong);">
      <i class="fas fa-link"></i> الإسناد
    </button>
  </div>

  <!-- ========== تبويب الأدوات ========== -->
  <div id="aitToolsPanel" class="ait-panel">
    <div class="upg-card panel-section">
      <div class="upg-card__body" style="overflow-x:auto;">
        <table class="ait-table" style="width:100%;border-collapse:collapse;text-align:right;font-size:0.92rem;">
          <thead>
            <tr style="background:var(--surface-1);color:var(--text-strong);">
              <th style="padding:10px;">المفتاح</th>
              <th style="padding:10px;">العنوان</th>
              <th style="padding:10px;">المزوّد / النموذج</th>
              <th style="padding:10px;">التكلفة</th>
              <th style="padding:10px;">الحالة</th>
              <th style="padding:10px;">النطاق</th>
              <th style="padding:10px;">إجراءات</th>
            </tr>
          </thead>
          <tbody id="aiToolsTbody">
            <tr><td colspan="7" style="padding:18px;text-align:center;color:var(--text-muted);">جارٍ التحميل…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ========== تبويب الإسناد ========== -->
  <div id="aitPlacementsPanel" class="ait-panel" style="display:none;">
    <div class="upg-card panel-section">
      <div class="upg-card__head" style="display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;"><i class="fas fa-link"></i> إسناد الأدوات لأنواع المهام</h3>
        <button type="button" id="btnAddPlacement" class="btn btn-primary"
          style="padding:8px 16px;border:none;border-radius:999px;background:var(--primary-600);color:var(--text-inverse);cursor:pointer;font-weight:700;">
          <i class="fas fa-plus-circle"></i> إضافة إسناد
        </button>
      </div>
      <div class="upg-card__body" style="overflow-x:auto;">
        <table class="ait-table" style="width:100%;border-collapse:collapse;text-align:right;font-size:0.92rem;">
          <thead>
            <tr style="background:var(--surface-1);color:var(--text-strong);">
              <th style="padding:10px;">الأداة</th>
              <th style="padding:10px;">نوع المهمة</th>
              <th style="padding:10px;">الجامعة</th>
              <th style="padding:10px;">المساق</th>
              <th style="padding:10px;">نص الزر</th>
              <th style="padding:10px;">الأيقونة</th>
              <th style="padding:10px;">الترتيب</th>
              <th style="padding:10px;">الحالة</th>
              <th style="padding:10px;">إجراءات</th>
            </tr>
          </thead>
          <tbody id="aiPlacementsTbody">
            <tr><td colspan="9" style="padding:18px;text-align:center;color:var(--text-muted);">جارٍ التحميل…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ========== قائمة جانبية: تفاصيل/تعديل أداة ========== -->
  <div id="aiToolDrawer" class="aitd-drawer" hidden>
    <div class="aitd-drawer__scrim" data-close-drawer></div>
    <aside class="aitd-drawer__panel">
      <div class="aitd-drawer__head">
        <h3><i class="fas fa-robot"></i> <span id="aitDrawerTitle">تفاصيل الأداة</span></h3>
        <button type="button" class="aitd-close" data-close-drawer aria-label="إغلاق"><i class="fas fa-times"></i></button>
      </div>
      <div class="aitd-drawer__body" id="aitDrawerBody">
        <div style="text-align:center;color:var(--text-muted);padding:30px;">جارٍ التحميل…</div>
      </div>
    </aside>
  </div>

  <!-- ========== مودال إضافة/تعديل إسناد ========== -->
  <div id="aiPlacementModal" class="ait-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--surface-0);max-width:640px;width:100%;max-height:92vh;overflow:auto;border-radius:var(--border-radius);border:1px solid var(--border-color);box-shadow:var(--shadow-lg);direction:rtl;">
      <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border-color);">
        <h3 id="placementModalTitle" style="margin:0;font-size:1.05rem;color:var(--text-strong);"><i class="fas fa-link"></i> إضافة إسناد</h3>
        <button type="button" class="ait-close" data-close="aiPlacementModal" style="background:var(--surface-2);color:var(--text-strong);border:none;border-radius:8px;padding:7px 14px;cursor:pointer;">إغلاق</button>
      </div>
      <div style="padding:18px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <input type="hidden" id="pf_id">
        <div style="grid-column:1 / -1;">
          <label style="font-weight:700;font-size:0.85rem;">الأداة</label>
          <select id="pf_type_key" class="form-control" style="width:100%;padding:8px;border:1px solid var(--border-color);border-radius:8px;"></select>
        </div>
        <div style="grid-column:1 / -1;">
          <label style="font-weight:700;font-size:0.85rem;">نوع المهمة</label>
          <select id="pf_task_type_id" class="form-control" style="width:100%;padding:8px;border:1px solid var(--border-color);border-radius:8px;"></select>
        </div>
        <div>
          <label style="font-weight:700;font-size:0.85rem;">معرّف الجامعة (0 = الكل)</label>
          <input id="pf_university_id" type="number" min="0" value="0" class="form-control" style="width:100%;padding:8px;border:1px solid var(--border-color);border-radius:8px;">
        </div>
        <div>
          <label style="font-weight:700;font-size:0.85rem;">معرّف المساق (0 = الكل)</label>
          <input id="pf_course_id" type="number" min="0" value="0" class="form-control" style="width:100%;padding:8px;border:1px solid var(--border-color);border-radius:8px;">
        </div>
        <div>
          <label style="font-weight:700;font-size:0.85rem;">أدنى مستوى اشتراك</label>
          <input id="pf_min_subscription_level" type="number" min="0" value="0" class="form-control" style="width:100%;padding:8px;border:1px solid var(--border-color);border-radius:8px;">
        </div>
        <div>
          <label style="font-weight:700;font-size:0.85rem;">ترتيب الزر</label>
          <input id="pf_button_order" type="number" value="0" class="form-control" style="width:100%;padding:8px;border:1px solid var(--border-color);border-radius:8px;">
        </div>
        <div>
          <label style="font-weight:700;font-size:0.85rem;">نص الزر (اختياري)</label>
          <input id="pf_button_label" type="text" class="form-control" style="width:100%;padding:8px;border:1px solid var(--border-color);border-radius:8px;" placeholder="افتراضي: عنوان الأداة">
        </div>
        <div>
          <label style="font-weight:700;font-size:0.85rem;">أيقونة الزر (اختياري)</label>
          <input id="pf_button_icon" type="text" class="form-control" style="width:100%;padding:8px;border:1px solid var(--border-color);border-radius:8px;" placeholder="مثال: fa-image">
        </div>
        <div style="display:flex;align-items:center;gap:8px;"><input id="pf_is_enabled" type="checkbox" checked><label for="pf_is_enabled" style="font-weight:700;font-size:0.85rem;">مُفعّل</label></div>
      </div>
      <div style="padding:14px 18px;border-top:1px solid var(--border-color);text-align:left;">
        <button type="button" id="btnSavePlacement" class="btn btn-primary" style="padding:9px 20px;border:none;border-radius:999px;background:var(--primary-600);color:var(--text-inverse);cursor:pointer;font-weight:700;"><i class="fas fa-save"></i> حفظ</button>
      </div>
    </div>
  </div>

  <!-- ========== أنماط القائمة الجانبية (drawer) ========== -->
  <style>
  #aiToolsPage .aitd-drawer{ position:fixed;inset:0;z-index:99999; }
  #aiToolsPage .aitd-drawer[hidden]{ display:none; }
  #aiToolsPage .aitd-drawer__scrim{ position:absolute;inset:0;background:rgba(0,0,0,.45); }
  #aiToolsPage .aitd-drawer__panel{ position:absolute;top:0;bottom:0;inset-inline-start:0;width:min(560px,96vw);
    background:var(--surface-0);border-inline-end:1px solid var(--border-color);box-shadow:var(--shadow-lg);
    display:flex;flex-direction:column;animation:aitdSlide .2s ease;direction:rtl; }
  @keyframes aitdSlide{ from{ transform:translateX(-14px);opacity:.5;} to{ transform:none;opacity:1;} }
  #aiToolsPage .aitd-drawer__head{ display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding:14px 18px;border-bottom:1px solid var(--border-color);position:sticky;top:0;background:var(--surface-0);z-index:2; }
  #aiToolsPage .aitd-drawer__head h3{ margin:0;font-size:1.05rem;color:var(--text-strong);display:flex;align-items:center;gap:9px;min-width:0; }
  #aiToolsPage .aitd-drawer__head h3 i{ color:var(--primary); }
  #aiToolsPage .aitd-drawer__head #aitDrawerTitle{ white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
  #aiToolsPage .aitd-close{ flex:0 0 auto;width:34px;height:34px;border-radius:9px;border:1px solid var(--border-color);
    background:var(--surface-1);color:var(--text-muted);cursor:pointer;display:grid;place-items:center; }
  #aiToolsPage .aitd-close:hover{ border-color:var(--primary-300);color:var(--primary-700); }
  #aiToolsPage .aitd-drawer__body{ padding:16px 18px;overflow:auto; }

  /* أقسام */
  #aiToolsPage .aitd-sec{ border:1px solid var(--border-color);border-radius:12px;margin-bottom:14px;overflow:hidden;background:var(--surface-0); }
  #aiToolsPage .aitd-sec__h{ display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--surface-1);
    border-bottom:1px solid var(--border-color);font-weight:700;font-size:.9rem;color:var(--text-strong); }
  #aiToolsPage .aitd-sec__h i{ color:var(--primary); }
  #aiToolsPage .aitd-sec__b{ padding:14px; }

  /* شبكة معلومات (label/value) */
  #aiToolsPage .aitd-grid{ display:grid;grid-template-columns:1fr 1fr;gap:12px; }
  #aiToolsPage .aitd-kv{ display:flex;flex-direction:column;gap:3px;min-width:0; }
  #aiToolsPage .aitd-kv--full{ grid-column:1 / -1; }
  #aiToolsPage .aitd-kv__l{ font-size:.74rem;color:var(--text-muted);font-weight:600;display:flex;align-items:center;gap:5px; }
  #aiToolsPage .aitd-kv__v{ font-size:.9rem;color:var(--text-strong);word-break:break-word; }
  #aiToolsPage .aitd-mono{ font-family:monospace;direction:ltr;text-align:left;font-size:.82rem; }

  /* شارات */
  #aiToolsPage .aitd-chip{ display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:.76rem;font-weight:700;border:1px solid; }
  #aiToolsPage .aitd-chip--on{ background:rgba(21,128,61,.1);color:#15803d;border-color:rgba(21,128,61,.35); }
  #aiToolsPage .aitd-chip--off{ background:rgba(185,28,28,.08);color:#b91c1c;border-color:rgba(185,28,28,.3); }
  #aiToolsPage .aitd-chip--admin{ background:var(--primary-50);color:var(--primary-700);border-color:var(--primary-300); }
  #aiToolsPage .aitd-chip--muted{ background:var(--surface-2);color:var(--text-muted);border-color:var(--border-color); }

  /* أيقونة معاينة */
  #aiToolsPage .aitd-iconprev{ width:42px;height:42px;border-radius:11px;background:var(--primary-50);border:1px solid var(--primary-300);
    color:var(--primary-700);display:grid;place-items:center;font-size:1.15rem;flex:0 0 auto; }

  /* صندوق التوجيه الأساسي */
  #aiToolsPage .aitd-prompt{ max-height:260px;overflow:auto;white-space:pre-wrap;word-break:break-word;
    background:var(--surface-1);border:1px solid var(--border-color);border-radius:9px;padding:12px;
    font-size:.82rem;line-height:1.7;color:var(--text); }

  /* عناصر الإسناد داخل الدروير */
  #aiToolsPage .aitd-pl{ border:1px solid var(--border-color);border-radius:10px;padding:10px 12px;margin-bottom:9px;background:var(--surface-1); }
  #aiToolsPage .aitd-pl:last-child{ margin-bottom:0; }
  #aiToolsPage .aitd-pl__top{ display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:6px; }
  #aiToolsPage .aitd-pl__meta{ display:flex;gap:6px;flex-wrap:wrap;font-size:.78rem; }
  #aiToolsPage .aitd-pltag{ display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:8px;
    background:var(--surface-2);border:1px solid var(--border-color);color:var(--text); }
  #aiToolsPage .aitd-pltag i{ color:var(--primary);font-size:.72rem; }

  /* حقول فورم الطالب (ملخّص) */
  #aiToolsPage .aitd-fld{ display:inline-flex;align-items:center;gap:6px;padding:3px 9px;border-radius:8px;margin:0 0 6px 6px;
    background:var(--surface-2);border:1px solid var(--border-color);font-size:.78rem;color:var(--text); }
  #aiToolsPage .aitd-fld .req{ color:#b91c1c;font-weight:800; }

  /* نموذج التعديل داخل الدروير */
  #aiToolsPage .aitd-form{ display:grid;grid-template-columns:1fr 1fr;gap:12px; }
  #aiToolsPage .aitd-f--full{ grid-column:1 / -1; }
  #aiToolsPage .aitd-form label{ font-weight:700;font-size:.82rem;color:var(--text-strong);display:block;margin-bottom:4px; }
  #aiToolsPage .aitd-form input[type=text],#aiToolsPage .aitd-form input[type=number],
  #aiToolsPage .aitd-form select,#aiToolsPage .aitd-form textarea{ width:100%;padding:8px 10px;border:1px solid var(--border-color);
    border-radius:8px;background:var(--surface-1);color:var(--text);font-family:inherit;font-size:.88rem;outline:none; }
  #aiToolsPage .aitd-form input:focus,#aiToolsPage .aitd-form select:focus,#aiToolsPage .aitd-form textarea:focus{ border-color:var(--primary-300);box-shadow:var(--focus-ring); }
  #aiToolsPage .aitd-chk{ display:flex;align-items:center;gap:8px;font-size:.84rem;font-weight:700;color:var(--text); }
  #aiToolsPage .aitd-drawer__foot{ display:flex;justify-content:flex-start;gap:10px;padding:14px 18px;border-top:1px solid var(--border-color);
    position:sticky;bottom:0;background:var(--surface-0); }
  #aiToolsPage .aitd-savebtn{ padding:9px 22px;border:none;border-radius:999px;background:var(--primary-600);color:var(--text-inverse);cursor:pointer;font-weight:700; }
  #aiToolsPage .aitd-savebtn:hover{ background:var(--primary-700); }
  #aiToolsPage .aitd-empty{ color:var(--text-muted);font-size:.85rem;padding:6px 0; }
  @media (max-width:520px){ #aiToolsPage .aitd-grid,#aiToolsPage .aitd-form{ grid-template-columns:1fr; } }
  </style>
</section>
