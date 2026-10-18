<!-- =========================================================
     Task Types Options — NotebookLM flat redesign (full-width)
========================================================= -->

<section id="taskTypesPage" class="ttp">

  <!-- Header (flat) -->
  <header class="ttp-head">
    <div class="ttp-head__main">
      <div class="ttp-head__icon"><i class="fas fa-tags" aria-hidden="true"></i></div>
      <div class="ttp-head__text">
        <h2>إدارة أنواع المهام</h2>
        <p>إضافة أنواع المهام وحذفها للتحكّم بتصنيفات المهام والواجبات بمرونة</p>
      </div>
    </div>
    <div class="ttp-head__count dashboard-card">
      <span class="count"><?= esc($typesCount) ?></span>
      <span class="ttp-head__count-lbl">نوع مسجّل</span>
    </div>
  </header>

  <!-- Add bar (compact, full width) — .panel-section مطلوب لـ task_type_options.js -->
  <div class="ttp-add panel-section">
    <label class="ttp-add__label" for="typeName"><i class="fas fa-plus-circle"></i> إضافة تصنيف جديد</label>
    <div class="ttp-add__row">
      <div class="ttp-input">
        <i class="fas fa-tag" aria-hidden="true"></i>
        <input id="typeName" type="text" placeholder="مثال: مشروع تقييمي، واجب منزلي…" autocomplete="off">
      </div>
      <button type="button" class="ttp-btn"><i class="fas fa-save"></i> حفظ التصنيف</button>
    </div>
  </div>

  <!-- Types grid (full width) -->
  <div class="ttp-list-head"><i class="fas fa-list"></i> قائمة التصنيفات المسجّلة</div>

  <ul id="typesList" class="tt-grid">
    <?php foreach ($types as $t): ?>
      <li class="tt-card" data-id="<?= (int) $t['id'] ?>">
        <span class="tt-id" title="معرّف النوع">#<?= (int) $t['id'] ?></span>
        <span class="tt-name"><?= esc($t['name']) ?></span>
        <button data-id="<?= (int) $t['id'] ?>" class="tt-del" title="حذف">
          <i class="fas fa-trash"></i>
        </button>
      </li>
    <?php endforeach; ?>
  </ul>
  <div class="tt-empty" id="ttEmpty" <?= empty($types) ? '' : 'style="display:none"' ?>>
    <i class="fas fa-inbox"></i> لا توجد أنواع مهام مسجّلة بعد.
  </div>

  <div class="ttp-foot">© <span id="currentYear"></span> منصة كيف التعليمية — جميع الحقوق محفوظة</div>
</section>

<style>
/* ===== أنواع المهام — تصميم فلات بعرض كامل (NotebookLM) ===== */
#taskTypesPage{ display:flex; flex-direction:column; gap:14.4px; }
#taskTypesPage *{ box-sizing:border-box; }

/* الهيدر — سطح فلات بلا تدرّج */
#taskTypesPage .ttp-head{
  display:flex; align-items:center; justify-content:space-between; gap:12.8px; flex-wrap:wrap;
  background:var(--surface-0); border:0.8px solid var(--border-color);
  border-radius:var(--border-radius); padding:14.4px 17.6px;
}
#taskTypesPage .ttp-head__main{ display:flex; align-items:center; gap:11.2px; }
#taskTypesPage .ttp-head__icon{
  width:38.4px; height:38.4px; flex:0 0 auto; border-radius:11.2px;
  background:var(--primary-50); color:var(--primary); border:0.8px solid var(--primary-300);
  display:grid; place-items:center; font-size:1.25rem;
}
#taskTypesPage .ttp-head__text h2{ margin:0; font-size:1.3rem; font-weight:800; color:var(--text-strong); }
#taskTypesPage .ttp-head__text p{ margin:3.2px 0 0; font-size:.86rem; color:var(--text-muted); }

/* عدّاد الإجمالي — بطاقة مدمجة */
#taskTypesPage .ttp-head__count{
  display:flex; flex-direction:column; align-items:center; gap:1.6px;
  background:var(--surface-1); border:0.8px solid var(--border-color);
  border-radius:11.2px; padding:8px 16px; min-width:76.8px;
}
#taskTypesPage .ttp-head__count .count{ font-size:1.7rem; font-weight:800; color:var(--primary-700); line-height:1; }
#taskTypesPage .ttp-head__count-lbl{ font-size:.78rem; color:var(--text-muted); }

/* شريط الإضافة */
#taskTypesPage .ttp-add{
  background:var(--surface-0); border:0.8px solid var(--border-color);
  border-radius:var(--border-radius); padding:12.8px 16px;
}
#taskTypesPage .ttp-add__label{
  display:flex; align-items:center; gap:6.4px; font-weight:700; color:var(--text-strong); margin-bottom:9.6px;
}
#taskTypesPage .ttp-add__label i{ color:var(--primary); }
#taskTypesPage .ttp-add__row{ display:flex; gap:9.6px; flex-wrap:wrap; }
#taskTypesPage .ttp-input{
  flex:1 1 256px; display:flex; align-items:center; gap:6.4px;
  background:var(--surface-1); border:0.8px solid var(--border-color); border-radius:8px; padding:0 9.6px;
  transition:border-color .15s ease, box-shadow .15s ease;
}
#taskTypesPage .ttp-input:focus-within{ border-color:var(--primary-300); box-shadow:var(--focus-ring); }
#taskTypesPage .ttp-input i{ color:var(--text-muted); font-size:.9rem; }
#taskTypesPage .ttp-input input{
  flex:1 1 auto; border:none; outline:none; background:transparent;
  padding:8.8px 0; font-size:.95rem; color:var(--text); font-family:inherit;
}
#taskTypesPage .ttp-btn{
  flex:0 0 auto; display:inline-flex; align-items:center; gap:6.4px;
  background:var(--primary-600); color:var(--text-inverse);
  border:none; border-radius:8px; padding:0 16px; font-weight:700; font-size:.92rem;
  cursor:pointer; transition:background .15s ease, transform .15s ease;
}
#taskTypesPage .ttp-btn:hover{ background:var(--primary-700); transform:translateY(-0.8px); }
#taskTypesPage .ttp-btn:disabled{ opacity:.6; cursor:not-allowed; transform:none; }
#taskTypesPage .ttp-btn:focus-visible{ outline:none; box-shadow:var(--focus-ring); }

/* عنوان القائمة */
#taskTypesPage .ttp-list-head{
  display:flex; align-items:center; gap:6.4px; font-weight:700; color:var(--text-strong);
  font-size:1rem; margin-top:1.6px;
}
#taskTypesPage .ttp-list-head i{ color:var(--primary); }

/* شبكة البطاقات — تملأ العرض */
#taskTypesPage .tt-grid{
  list-style:none; margin:0; padding:0;
  display:grid; grid-template-columns:repeat(auto-fill, minmax(192px, 1fr)); gap:9.6px;
}
#taskTypesPage .tt-card{
  display:flex; align-items:center; gap:8px;
  background:var(--surface-0); border:0.8px solid var(--border-color);
  border-radius:9.6px; padding:8.8px 10.4px;
  transition:border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}
#taskTypesPage .tt-card:hover{ border-color:var(--primary-300); box-shadow:var(--shadow-sm); transform:translateY(-1.6px); }
#taskTypesPage .tt-id{
  flex:0 0 auto; font-weight:800; font-size:.76rem; line-height:1;
  color:var(--primary-700); background:var(--primary-50);
  border:0.8px solid var(--primary-300); border-radius:799.2px;
  padding:3.2px 7.2px; letter-spacing:.02em; direction:ltr;
}
#taskTypesPage .tt-name{
  flex:1 1 auto; min-width:0; font-weight:600; color:var(--text-strong); font-size:.95rem;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
#taskTypesPage .tt-del{
  flex:0 0 auto; width:25.6px; height:25.6px; border-radius:7.2px;
  border:0.8px solid var(--border-color); background:var(--surface-1);
  color:var(--text-muted); cursor:pointer; display:grid; place-items:center;
  transition:background .15s ease, color .15s ease, border-color .15s ease;
}
#taskTypesPage .tt-del:hover{ background:rgba(239,68,68,.10); color:#ef4444; border-color:#ef4444; }
#taskTypesPage .tt-del:focus-visible{ outline:none; box-shadow:var(--focus-ring); }

#taskTypesPage .tt-empty{
  padding:14.4px; text-align:center; color:var(--text-muted); font-size:.92rem;
  background:var(--surface-1); border:0.8px dashed var(--border-color); border-radius:9.6px;
}

#taskTypesPage .ttp-foot{
  margin-top:4.8px; padding-top:9.6px; border-top:0.8px solid var(--border-color);
  text-align:center; color:var(--text-muted); font-size:.82rem;
}

/* وضع داكن */
html[data-theme="dark"] #taskTypesPage .ttp-head,
html[data-theme="dark"] #taskTypesPage .ttp-add,
html[data-theme="dark"] #taskTypesPage .tt-card{ background:var(--surface-0) !important; border-color:var(--border-color) !important; }
html[data-theme="dark"] #taskTypesPage .ttp-head__text h2,
html[data-theme="dark"] #taskTypesPage .tt-name{ color:var(--text-strong) !important; }
html[data-theme="dark"] #taskTypesPage .tt-id{ color:var(--primary-500) !important; }

@media (max-width:640px){
  #taskTypesPage .ttp-add__row{ flex-direction:column; align-items:stretch; }
  #taskTypesPage .ttp-btn{ justify-content:center; padding:8.8px 16px; }
}
</style>

<script>
  (function(){ var y = document.getElementById("currentYear"); if (y) y.textContent = new Date().getFullYear(); })();

  /* توحيد أي عنصر يُضاف لاحقًا عبر JS (appendLi) إلى نفس بطاقة المعرّف،
     ليظهر الـ id فورًا دون انتظار إعادة التحميل — منطق داخل الـ view فقط. */
  (function(){
    var list = document.getElementById('typesList');
    if (!list || typeof MutationObserver === 'undefined') return;
    var emptyBox = document.getElementById('ttEmpty');

    function decorate(li){
      if (!li || li.nodeType !== 1 || li.tagName !== 'LI') return;
      if (li.classList.contains('tt-card')) return;       // مُنسّق مسبقًا (server-rendered)
      var btn = li.querySelector('button[data-id]');
      if (!btn) return;                                    // ليس عنصر نوع
      var id = btn.getAttribute('data-id') || '';
      var nameEl = li.querySelector('span');
      var name = nameEl ? nameEl.textContent : '';
      li.classList.add('tt-card');
      li.setAttribute('data-id', id);
      li.innerHTML =
        '<span class="tt-id" title="معرّف النوع">#' + id + '</span>' +
        '<span class="tt-name"></span>' +
        '<button data-id="' + id + '" class="tt-del" title="حذف"><i class="fas fa-trash"></i></button>';
      li.querySelector('.tt-name').textContent = name;     // آمن ضد XSS
      if (emptyBox) emptyBox.style.display = 'none';
    }

    Array.prototype.forEach.call(list.children, decorate);
    new MutationObserver(function(muts){
      muts.forEach(function(m){ Array.prototype.forEach.call(m.addedNodes, decorate); });
    }).observe(list, { childList: true });
  })();
</script>
