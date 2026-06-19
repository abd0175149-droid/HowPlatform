import AI_TOOLS_COMMON from './ai_tools_common.js';

// ضمان تعيين window.AI_TOOLS_COMMON للتوافق مع الكود الحالي
if (typeof window !== 'undefined' && !window.AI_TOOLS_COMMON) {
  window.AI_TOOLS_COMMON = AI_TOOLS_COMMON;
}

const API_BASE = '/students/ai-tools';
const STYLE_ID = 'ai-tools-factory-style';

function ensureStyles() {
  if (document.getElementById(STYLE_ID)) return;
  const css = `
  .ai-tool-modal-open{overflow:hidden;}
  .ai-tool-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);display:none;z-index:9999;align-items:center;justify-content:center;padding:20px;}
  .ai-tools-panel{display:flex;flex-direction:column;gap:16px;padding:4px 0;}
  .ai-tools-empty{display:flex;gap:12px;align-items:center;padding:12px;border:1px dashed var(--border-color,#d7dbe1);border-radius:12px;background:var(--surface-1,#f5f7fb);color:var(--text-muted,#4b5563);}
  .ai-tools-empty i{font-size:1.4rem;color:var(--primary-500,#219ebc);}
  .ai-tool-card{border:1px solid var(--border-color,#e6e6e6);border-radius:16px;padding:18px;display:flex;flex-direction:column;gap:14px;background:var(--surface-0,#fff);box-shadow:0 2px 8px rgba(0,0,0,0.04),inset 0 0 0 1px rgba(34,197,246,.05);transition:all 0.3s ease;margin-bottom:12px;color:var(--text,#0f172a);}
  .ai-tool-card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;}
  .ai-tool-title{font-size:1rem;font-weight:700;margin:0;color:var(--text,#126782);}
  .ai-tool-desc{margin:4px 0 0;color:var(--text-muted,#555);font-size:.9rem;}
  .ai-tool-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
  .ai-tool-status{display:none;padding:8px 14px;border-radius:12px;font-weight:600;font-size:.875rem;background:var(--surface-1,#eef2ff);color:var(--text-strong,#023047);border:1px solid var(--border-color,#c7d2fe);box-shadow:0 2px 4px rgba(0,0,0,0.05);transition:all 0.3s ease;}
  .ai-tool-status.show{display:inline-flex;align-items:center;gap:8px;animation:slideInStatus 0.3s ease-out;}
  .ai-tool-status.processing{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);color:#d97706;border-color:#fcd34d;box-shadow:0 2px 8px rgba(217,119,6,0.2);}
  .ai-tool-status.pending{background:linear-gradient(135deg,#dbeafe 0%,#bfdbfe 100%);color:#2563eb;border-color:#93c5fd;box-shadow:0 2px 8px rgba(37,99,235,0.2);}
  .ai-tool-status.done{background:linear-gradient(135deg,#d1fae5 0%,#a7f3d0 100%);color:#059669;border-color:#6ee7b7;box-shadow:0 2px 8px rgba(5,150,105,0.2);}
  .ai-tool-status.failed{background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%);color:#dc2626;border-color:#fca5a5;box-shadow:0 2px 8px rgba(220,38,38,0.2);}
  .ai-tool-status .status-spinner{display:inline-block;width:14px;height:14px;border:2.5px solid currentColor;border-top-color:transparent;border-right-color:transparent;border-radius:50%;animation:spin 0.9s linear infinite;}
  @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
  .ai-tool-status .status-pulse{display:inline-block;width:10px;height:10px;background:currentColor;border-radius:50%;animation:pulseStatus 2s ease-in-out infinite;box-shadow:0 0 0 0 currentColor;}
  @keyframes pulseStatus{0%,100%{opacity:1;transform:scale(1);box-shadow:0 0 0 0 currentColor;}50%{opacity:0.7;transform:scale(0.95);box-shadow:0 0 0 4px transparent;}}
  @keyframes slideInStatus{from{opacity:0;transform:translateY(-5px) scale(0.95);}to{opacity:1;transform:translateY(0) scale(1);}}
  .ai-tool-status.processing .status-spinner,.ai-tool-status.pending .status-spinner{animation:spin 0.8s linear infinite;}
  .btn-primary:disabled{opacity:0.6;cursor:not-allowed;background:#94a3b8;border-color:#94a3b8;}
  .ai-tool-processing .td-card{pointer-events:none;opacity:0.7;}
  .ai-tool-processing .td-card:hover{transform:none;box-shadow:none;}
  .ai-tool-modal-backdrop.show{display:flex;}
  .ai-tool-modal{background:var(--surface-0,#fff);color:var(--text,#023047);border-radius:16px;border:1px solid var(--border-color,#e1e5e9);box-shadow:0 24px 60px rgba(15,23,42,.18);width:min(940px,94vw);max-height:90vh;overflow:auto;display:flex;flex-direction:column;}
  .ai-tool-modal header,.ai-tool-modal footer{padding:16px 20px;border-bottom:1px solid var(--border-color,#e1e5e9);display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--surface-0,#fff);}
  .ai-tool-modal footer{border-top:1px solid var(--border-strong,#e1e5e9);border-bottom:none;justify-content:flex-start;gap:10px;}
  .ai-tool-modal .ai-modal-title{font-size:1.05rem;font-weight:700;margin:0;display:flex;align-items:center;gap:8px;color:var(--text-strong,#023047);}
  .ai-tool-modal .ai-modal-close{border:1px solid var(--border-color,#d7dbe1);border-radius:10px;background:var(--surface-0,#fff);color:var(--text);padding:8px 12px;cursor:pointer;transition:.2s;}
  .ai-tool-modal .ai-modal-close:hover{background:var(--surface-1,#f5f7fb);}
  .ai-tool-modal .ai-modal-content{padding:16px 20px;}
  .ai-tool-form{display:flex;flex-direction:column;gap:18px;}
  .ai-form-section{display:flex;flex-direction:column;gap:12px;}
  .ai-form-section h4{margin:0;font-size:1rem;color:var(--text-strong,#023047);}
  .ai-form-section p{margin:0;color:var(--text-muted,#555);font-size:.9rem;}
  .ai-grid{display:grid;gap:14px;}
  .ai-grid.ai-grid-1{grid-template-columns:1fr;}
  .ai-grid.ai-grid-2{grid-template-columns:repeat(auto-fit,minmax(240px,1fr));}
  .ai-grid.ai-grid-chips{grid-template-columns:1fr;}
  .ai-field{display:flex;flex-direction:column;gap:6px;}
  .ai-field.full{grid-column:1 / -1;}
  .ai-field label{font-weight:600;color:var(--text-strong,#023047);font-size:.95rem;}
  .ai-field input,
  .ai-field select,
  .ai-field textarea{border:1px solid var(--border-color,#dcdfe4);border-radius:12px;padding:10px 12px;font-size:.95rem;background:var(--surface-0,#fff);color:var(--text,#111);}
  .ai-field textarea{min-height:90px;resize:vertical;}
  .ai-field input:focus,
  .ai-field select:focus,
  .ai-field textarea:focus{outline:none;box-shadow:0 0 0 3px rgba(33,158,188,.2);border-color:var(--primary-500,#219ebc);}
  .ai-field small{color:var(--text-muted,#5f6d7e);}
  .ai-field-error{color:#dc2626;font-size:.85rem;display:none;}
  .ai-field.has-error .ai-field-error{display:block;}
  .ai-switch{display:inline-flex;align-items:center;gap:10px;cursor:pointer;}
  .ai-switch input{display:none;}
  .ai-switch .track{width:46px;height:26px;border-radius:999px;background:#d9e2ec;border:1px solid #c6d3e0;position:relative;transition:.2s;}
  .ai-switch .thumb{position:absolute;width:20px;height:20px;border-radius:50%;background:#fff;top:50%;left:3px;transform:translateY(-50%);box-shadow:0 1px 3px rgba(0,0,0,.2);transition:.2s;}
  .ai-switch input:checked + .track{background:#219ebc;border-color:#219ebc;}
  .ai-switch input:checked + .track .thumb{left:23px;}
  .ai-chips{display:flex;flex-wrap:wrap;gap:8px;}
  .ai-chip{border:1px solid var(--border-color,#dfe7f3);border-radius:20px;padding:8px 12px;cursor:pointer;background:var(--surface-1,#f8fafc);color:var(--text);font-weight:600;font-size:.9rem;transition:.2s;}
  .ai-chip.active{background:var(--primary-500,#219ebc);color:#fff;border-color:var(--primary-500,#219ebc);}
  .ai-chip.deny{animation:chipDeny .25s;}
  @keyframes chipDeny{0%,100%{transform:translateX(0);}25%{transform:translateX(-3px);}75%{transform:translateX(3px);}}
  .ai-tool-modal footer .ai-hint{color:var(--text-muted,#5f738c);font-size:.9rem;}
  .ai-modal-backdrop-close{position:absolute;inset:0;}
  .ai-result-content{max-height:60vh;overflow:auto;}
  .ai-result-field{margin-bottom:12px;padding:10px 14px;border-radius:12px;background:var(--surface-1,#f7f9fc);border:1px solid var(--border-color,#e4e7ec);}
  .ai-result-field h5{margin:0 0 6px;font-size:.95rem;color:var(--text-strong,#0a3247);}
  .ai-result-field p{margin:0;color:var(--text,#1f2933);line-height:1.7;}
  .ai-result-tags{display:flex;flex-wrap:wrap;gap:6px;}
  .ai-result-chip{background:#e0f2fe;color:#0369a1;padding:4px 10px;border-radius:999px;font-size:.85rem;}
  .ai-tool-access-badge{align-self:flex-start;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;border-radius:999px;padding:4px 10px;font-size:.85rem;font-weight:600;}
  .ai-copy-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;font-size:.9rem;}
  .ai-tool-delete-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:10px 16px;
    font-size:.9rem;
    font-weight:600;
    background:#e53935; /* الأحمر الأساسي */
    color:#fff;
    border:1px solid #e53935;
    border-radius:12px;
    cursor:pointer;
    transition:all 0.2s ease;
    white-space:nowrap;
    min-height:38px;
    box-shadow:0 2px 4px rgba(229,57,53,0.22); /* الظل */
}

.ai-tool-delete-btn:hover{
    background:#d32f2f !importent; /* أغمق للهوفر */
    border-color:#d32f2f !importent;
    transform:translateY(-1px) !importent;
    box-shadow:0 4px 12px rgba(211,47,47,0.35) !importent;
}

.ai-tool-delete-btn:active{
    transform:translateY(0) !importent;
    box-shadow:0 2px 6px rgba(211,47,47,0.28)!importent;
}

.ai-tool-delete-btn:disabled{
    opacity:0.6 !importent;
    cursor:not-allowed !importent;
    background:#ef5350; /* حالة disabled لون أخف */
    border-color:#ef5350;
    box-shadow:0 2px 4px rgba(239,83,80,0.22);
}

.ai-tool-delete-btn:focus{
    outline:2px solid rgba(229,57,53,0.45); /* تركيز واضح */
    outline-offset:2px;
}

.ai-tool-delete-btn:focus:not(:focus-visible){
    outline:none;
}

.ai-tool-delete-btn i{
    font-size:0.875rem;
    transition:transform 0.2s ease;
}

.ai-tool-delete-btn:hover i{
    transform:scale(1.1);
}

  .ai-matrix-table{width:100%;border-collapse:collapse;margin-top:10px;font-size:.9rem;direction:rtl;}
  .ai-matrix-table thead th{background:var(--primary-900,#0a3247);color:#fff;padding:10px;border:1px solid var(--border-color,#d9e2ec);font-weight:600;text-align:right;}
  .ai-matrix-table tbody td{border:1px solid var(--border-color,#d9e2ec);padding:10px;vertical-align:top;color:var(--text,#0f172a);background:var(--surface-0,#fff);text-align:right;white-space:pre-wrap;}
  .ai-matrix-table tbody tr:nth-child(even) td{background:var(--surface-1,#f8fafc);}
  
  /* Article Evaluator Styles */
  .article-section{background:var(--surface-0,#fff);border:1px solid var(--border-color,#e1e5e9);border-radius:12px;padding:16px;margin-bottom:16px;}
  .section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:2px solid var(--primary-500,#219ebc);padding-bottom:8px;}
  .section-title{font-size:1.1rem;font-weight:700;color:var(--text-strong,#0a3247);margin:0;padding:4px 8px;border-radius:6px;transition:background 0.2s;}
  .section-title:focus{background:var(--surface-1,#f5f7fb);outline:none;}
  .section-info{display:flex;gap:12px;align-items:center;font-size:0.9rem;color:var(--text-muted,#5f738c);}
  .word-count strong{color:var(--primary-500,#219ebc);font-weight:700;}
  .section-parts{display:flex;flex-direction:column;gap:12px;margin-top:12px;}
  .article-part{background:var(--surface-1,#f8fafc);border:1px solid var(--border-color,#e1e5e9);border-radius:8px;padding:12px;position:relative;}
  .part-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:8px;}
  .part-title{flex:1;padding:6px 10px;border:1px solid var(--border-color,#dcdfe4);border-radius:6px;font-size:0.95rem;font-weight:600;background:var(--surface-0,#fff);color:var(--text,#111);}
  .btn-remove-part{background:#ef5350;color:#fff;border:none;border-radius:6px;padding:6px 10px;cursor:pointer;transition:all 0.2s;}
  .btn-remove-part:hover{background:#d32f2f;transform:translateY(-1px);}
  .part-text{width:100%;padding:10px;border:1px solid var(--border-color,#dcdfe4);border-radius:6px;font-size:0.95rem;resize:vertical;min-height:120px;font-family:inherit;background:var(--surface-0,#fff);color:var(--text,#111);}
  .part-text:focus{outline:none;box-shadow:0 0 0 3px rgba(33,158,188,.2);border-color:var(--primary-500,#219ebc);}
  .part-info{display:flex;justify-content:flex-end;margin-top:6px;font-size:0.85rem;color:var(--text-muted,#5f738c);}
  .part-word-count strong{color:var(--primary-500,#219ebc);font-weight:600;}
  .section-actions{display:flex;justify-content:flex-end;margin-top:12px;}
  .btn-add-part{background:#219ebc;color:#fff;border:none;border-radius:8px;padding:8px 16px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s;}
  .btn-add-part:hover{background:#1a7a96;transform:translateY(-1px);box-shadow:0 4px 12px rgba(33,158,188,0.3);}
  
  /* Article Evaluator Result Styles */
  mark{display:inline;position:relative;}
  mark sup{position:absolute;top:-8px;right:-8px;background:#fff;border:2px solid #000;border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:bold;}
  .ai-matrix-table td div{margin-bottom:6px;}
  .ai-matrix-table td div:last-child{margin-bottom:0;}
  .ai-tool-jobs-list{margin-top:16px;padding-top:16px;border-top:1px solid var(--border-color,#e2e8f0);}
  .ai-tool-jobs-list-header{font-weight:600;margin-bottom:12px;color:var(--text-strong,#1e293b);font-size:0.9rem;display:flex;align-items:center;gap:8px;}
  .ai-tool-jobs-list-header i{color:var(--primary-500,#219ebc);font-size:1rem;}
  .ai-jobs-list-container{display:flex;flex-direction:column;gap:10px;}
  .ai-job-item{position:relative;padding:14px 16px;border:1px solid var(--border-color,#e2e8f0);border-radius:12px;background:var(--surface-0,#fff);color:var(--text,#111);cursor:pointer;transition:all 0.25s cubic-bezier(0.4,0,0.2,1);box-shadow:0 1px 3px rgba(0,0,0,0.05);}
  .ai-job-item:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.1);border-color:var(--primary-500,#219ebc);}
  .ai-job-item.active{border-color:var(--primary-500,#219ebc);background:var(--success-100,#f0f9ff);box-shadow:0 4px 16px rgba(33,158,188,0.15);}
  .ai-job-item-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px;}
  .ai-job-item-info{display:flex;flex-direction:column;gap:8px;flex:1;}
  .ai-job-item-title{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
  .ai-job-item-num{font-weight:700;color:var(--text,#111);font-size:0.95rem;}
  .ai-job-item-status{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;font-size:0.8rem;font-weight:600;white-space:nowrap;}
  .ai-job-item-status.done{background:var(--success-100,#d1fae5);color:var(--success-600,#059669);border:1px solid var(--success-500,#a7f3d0);}
  .ai-job-item-status.failed{background:var(--error-100,#fee2e2);color:var(--error-600,#dc2626);border:1px solid var(--error-500,#fecaca);}
  .ai-job-item-status.processing{background:var(--warning-100,#fef3c7);color:var(--warning-600,#d97706);border:1px solid var(--warning-500,#fde68a);animation:pulse 2s ease-in-out infinite;}
  .ai-job-item-status.pending{background:var(--info-100,#dbeafe);color:var(--info-600,#2563eb);border:1px solid var(--info-500,#bfdbfe);animation:pulse 2s ease-in-out infinite;}
  .ai-job-item-date{color:var(--text-muted,#64748b);font-size:0.85rem;display:flex;align-items:center;gap:6px;}
  .ai-job-item-date i{font-size:0.75rem;opacity:0.7;}
  .ai-job-item-actions{display:flex;gap:8px;flex-shrink:0;}
  .ai-job-item-actions .btn{transition:all 0.2s ease;}
  .ai-job-item-actions .btn:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,0.15);}
  @keyframes slideIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
  .ai-job-item{animation:slideIn 0.3s ease-out;}

  /* Dark mode specific tweaks for premium feel */
  [data-theme="dark"] .ai-job-item.active { background: rgba(33,158,188,0.15); border-color: var(--primary-400); color: #fff; }
  [data-theme="dark"] .ai-result-field { background: var(--surface-2); border-color: var(--border-color); }
  [data-theme="dark"] .ai-matrix-table thead th { background: #000; color: #fff; }
  [data-theme="dark"] .ai-tool-modal-backdrop { background: rgba(0,0,0,0.7); }
  `;
  const style = document.createElement('style');
  style.id = STYLE_ID;
  style.textContent = css;
  document.head.appendChild(style);
}

/**
 * دالة مساعدة للتنقل إلى section
 */
function navigateToSection(section) {
  // معالجة خاصّة لـ rewards - استخدام route مباشر
  if (section === 'rewards') {
    // استخدام loadSection إذا كان متوفراً، وإلا استخدام route مباشر
    if (typeof window.loadSection === 'function') {
      window.loadSection('rewards');
    } else {
      window.location.href = '/students/pages/rewards';
    }
    return;
  }

  if (window.StudentRouter && typeof window.StudentRouter.navigate === 'function') {
    window.StudentRouter.navigate(section);
  } else if (typeof window.loadSection === 'function') {
    window.loadSection(section);
  } else {
    // Fallback: استخدام hash navigation
    location.hash = '#/' + section.replace(/^#?\//, '');
  }
}

function notifyUser(type, message) {
  if (!message) return;
  if (window.Swal && typeof window.Swal.fire === 'function') {
    window.Swal.fire({
      icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'),
      text: message,
      confirmButtonText: 'حسناً',
    });
    return;
  }
  if (window.showToast && typeof window.showToast === 'function') {
    window.showToast(message);
    return;
  }
  alert(message);
}

async function fetchPlacements({ taskTypeId, universityId, courseId, taskId }) {
  if (!taskTypeId) return [];
  const qs = new URLSearchParams({ task_type_id: taskTypeId });
  if (universityId) qs.set('university_id', universityId);
  if (courseId) qs.set('course_id', courseId);
  if (taskId) qs.set('task_id', taskId);

  const res = await fetch(`${API_BASE}/placements?${qs.toString()}`, {
    credentials: 'include',
    headers: { 'Accept': 'application/json' },
  });
  const json = await res.json().catch(() => ({}));
  if (!json?.ok || !Array.isArray(json.placements)) {
    console.warn('[ai-tool-factory] Failed to fetch placements:', json);
    return [];
  }

  // التحقق من أن كل placement يحتوي على form
  const validPlacements = json.placements.map(placement => {
    if (!placement.form || !placement.form.sections || !Array.isArray(placement.form.sections)) {
      console.warn('[ai-tool-factory] Placement missing form config:', placement.type_key, placement);
    }
    return placement;
  });

  return validPlacements;
}

const textContent = (value) => (value ?? '').toString();

class FieldController {
  constructor(field) {
    this.field = field;
    this.listeners = [];
    this.visible = true;
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'ai-field';
    if (field.full) this.wrapper.classList.add('full');
    this.errorEl = document.createElement('small');
    this.errorEl.className = 'ai-field-error';
    this.build();
  }

  build() {
    const field = this.field;
    const label = document.createElement('label');
    label.textContent = field.label || field.key || field.name;
    this.wrapper.appendChild(label);

    let inputEl;
    switch (field.type) {
      case 'textarea':
        inputEl = document.createElement('textarea');
        inputEl.value = field.default ? String(field.default) : '';
        if (field.placeholder) inputEl.placeholder = field.placeholder;
        // دعم readonly في field config
        if (field.readonly) {
          inputEl.readOnly = true;
          inputEl.style.backgroundColor = '#f5f5f5';
          inputEl.style.cursor = 'not-allowed';
        }
        this.wrapper.appendChild(inputEl);
        this.inputEl = inputEl;
        inputEl.addEventListener('input', () => this.notify());
        break;
      case 'number':
        inputEl = document.createElement('input');
        inputEl.type = 'number';
        if (field.min !== undefined) inputEl.min = field.min;
        if (field.max !== undefined) inputEl.max = field.max;
        inputEl.value = field.default !== undefined ? field.default : '';
        this.wrapper.appendChild(inputEl);
        this.inputEl = inputEl;
        inputEl.addEventListener('input', () => this.notify());
        break;
      case 'select':
        inputEl = document.createElement('select');
        (field.options || []).forEach(opt => {
          const option = document.createElement('option');
          if (typeof opt === 'string') {
            option.value = opt;
            option.textContent = opt;
          } else {
            option.value = opt.value ?? opt.label ?? '';
            option.textContent = opt.label ?? opt.value ?? '';
          }
          inputEl.appendChild(option);
        });
        if (field.default !== undefined) inputEl.value = field.default;
        // دعم disabled في field config
        if (field.disabled) {
          inputEl.disabled = true;
        }
        this.wrapper.appendChild(inputEl);
        this.inputEl = inputEl;
        inputEl.addEventListener('change', () => this.notify());
        break;
      case 'toggle':
        this.wrapper.classList.add('ai-field-toggle');
        const switchLabel = document.createElement('label');
        switchLabel.className = 'ai-switch';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        if (field.default) checkbox.checked = true;
        const track = document.createElement('span');
        track.className = 'track';
        const thumb = document.createElement('span');
        thumb.className = 'thumb';
        track.appendChild(thumb);
        switchLabel.appendChild(checkbox);
        switchLabel.appendChild(track);
        switchLabel.appendChild(document.createTextNode(field.switch_label || ''));
        this.wrapper.appendChild(switchLabel);
        this.inputEl = checkbox;
        checkbox.addEventListener('change', () => this.notify());
        break;
      case 'chips':
        this.chipSet = new Set(Array.isArray(field.default) ? field.default : []);
        const chipsWrap = document.createElement('div');
        chipsWrap.className = 'ai-chips';
        (field.options || []).forEach(opt => {
          const value = typeof opt === 'string' ? opt : (opt.value ?? opt.label ?? '');
          const label = typeof opt === 'string' ? opt : (opt.label ?? opt.value ?? '');
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'ai-chip';
          btn.textContent = label;
          btn.dataset.value = value;
          if (this.chipSet.has(value)) btn.classList.add('active');
          btn.addEventListener('click', () => {
            if (this.chipSet.has(value)) {
              this.chipSet.delete(value);
              btn.classList.remove('active');
              this.notify();
              return;
            }
            if (field.max && this.chipSet.size >= field.max) {
              btn.classList.add('deny');
              setTimeout(() => btn.classList.remove('deny'), 300);
              notifyUser('info', `يمكن اختيار ${field.max} عناصر كحد أقصى.`);
              return;
            }
            this.chipSet.add(value);
            btn.classList.add('active');
            this.notify();
          });
          chipsWrap.appendChild(btn);
        });
        this.wrapper.appendChild(chipsWrap);
        break;
      case 'file':
        inputEl = document.createElement('input');
        inputEl.type = 'file';
        if (field.accept) inputEl.accept = field.accept;
        this.wrapper.appendChild(inputEl);
        this.inputEl = inputEl;
        inputEl.addEventListener('change', () => this.notify());
        break;
      case 'dynamic-list':
        this.dynamicListItems = Array.isArray(field.default) ? [...field.default] : [];
        const listContainer = document.createElement('div');
        listContainer.className = 'ai-dynamic-list';
        listContainer.style.cssText = 'display: flex; flex-direction: column; gap: 8px;';

        const itemsContainer = document.createElement('div');
        itemsContainer.className = 'ai-dynamic-list-items';
        itemsContainer.style.cssText = 'display: flex; flex-direction: column; gap: 8px;';

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'ai-btn ai-btn-add';
        addBtn.style.cssText = 'padding: 8px 16px; background: #28a745; color: #fff; border: none; border-radius: 8px; cursor: pointer; align-self: flex-start;';
        addBtn.innerHTML = '<i class="fa-solid fa-plus"></i> إضافة عنصر';
        addBtn.addEventListener('click', () => this.addDynamicListItem(''));

        listContainer.appendChild(itemsContainer);
        listContainer.appendChild(addBtn);
        this.wrapper.appendChild(listContainer);
        this.itemsContainer = itemsContainer;

        // إضافة العناصر الافتراضية
        this.dynamicListItems.forEach(item => this.addDynamicListItem(item));

        // إذا كان min_items محدد ولم يكن هناك عناصر، أضف عنصر واحد
        if (field.min_items && this.dynamicListItems.length === 0) {
          this.addDynamicListItem('');
        }
        break;
      default:
        inputEl = document.createElement('input');
        inputEl.type = 'text';
        if (field.placeholder) inputEl.placeholder = field.placeholder;
        if (field.default) inputEl.value = field.default;
        // دعم readonly في field config
        if (field.readonly) {
          inputEl.readOnly = true;
          inputEl.style.backgroundColor = '#f5f5f5';
          inputEl.style.cursor = 'not-allowed';
        }
        this.wrapper.appendChild(inputEl);
        this.inputEl = inputEl;
        inputEl.addEventListener('input', () => this.notify());
    }

    if (field.hint) {
      const hint = document.createElement('small');
      hint.textContent = field.hint;
      this.wrapper.appendChild(hint);
    }

    this.wrapper.appendChild(this.errorEl);
  }

  onChange(fn) {
    this.listeners.push(fn);
  }

  notify() {
    const value = this.getValue();
    this.listeners.forEach(fn => fn(value));
  }

  setVisible(visible) {
    this.visible = !!visible;
    this.wrapper.style.display = this.visible ? '' : 'none';
  }

  getValue() {
    if (this.field.type === 'chips') {
      return Array.from(this.chipSet || []);
    }
    if (this.field.type === 'dynamic-list') {
      return this.dynamicListItems || [];
    }
    if (!this.inputEl) return '';
    if (this.field.type === 'toggle') {
      return !!this.inputEl.checked;
    }
    if (this.field.type === 'number') {
      const val = this.inputEl.value;
      return val === '' ? '' : Number(val);
    }
    return this.inputEl.value ?? '';
  }

  addDynamicListItem(value = '') {
    if (!this.itemsContainer) return;

    const itemIndex = this.dynamicListItems.length;
    this.dynamicListItems.push(value);

    const itemRow = document.createElement('div');
    itemRow.className = 'ai-dynamic-list-item';
    itemRow.style.cssText = 'display: flex; gap: 8px; align-items: center;';

    const input = document.createElement('input');
    input.type = 'text';
    input.value = value;
    input.placeholder = this.field.placeholder || 'أدخل قيمة...';
    input.style.cssText = 'flex: 1; border: 1px solid #dcdfe4; border-radius: 8px; padding: 8px 12px;';
    input.addEventListener('input', () => {
      this.dynamicListItems[itemIndex] = input.value;
      this.notify();
    });

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'ai-btn-remove';
    removeBtn.style.cssText = 'padding: 8px 12px; background: #dc3545; color: #fff; border: none; border-radius: 8px; cursor: pointer;';
    removeBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
    removeBtn.addEventListener('click', () => {
      // التحقق من min_items
      if (this.field.min_items && this.dynamicListItems.length <= this.field.min_items) {
        notifyUser('info', `يجب أن يكون هناك على الأقل ${this.field.min_items} عنصر.`);
        return;
      }
      this.dynamicListItems.splice(itemIndex, 1);
      itemRow.remove();
      this.notify();
    });

    itemRow.appendChild(input);
    itemRow.appendChild(removeBtn);
    this.itemsContainer.appendChild(itemRow);
  }

  setError(message) {
    if (!message) {
      this.wrapper.classList.remove('has-error');
      this.errorEl.textContent = '';
      return;
    }
    this.wrapper.classList.add('has-error');
    this.errorEl.textContent = message;
  }

  reset() {
    this.setError('');
    const defaultValue = this.field.default;
    if (this.field.type === 'chips') {
      this.chipSet = new Set(Array.isArray(defaultValue) ? defaultValue : []);
      const btns = this.wrapper.querySelectorAll('.ai-chip');
      btns.forEach(btn => {
        const val = btn.dataset.value;
        if (this.chipSet.has(val)) {
          btn.classList.add('active');
        } else {
          btn.classList.remove('active');
        }
      });
    } else if (this.field.type === 'dynamic-list') {
      this.dynamicListItems = [];
      if (this.itemsContainer) {
        this.itemsContainer.innerHTML = '';
      }
      const defaultItems = Array.isArray(defaultValue) ? [...defaultValue] : [];
      defaultItems.forEach(item => this.addDynamicListItem(item));
      if (this.field.min_items && defaultItems.length === 0) {
        for (let i = 0; i < this.field.min_items; i++) {
          this.addDynamicListItem('');
        }
      }
    } else if (this.field.type === 'toggle') {
      if (this.inputEl) {
        this.inputEl.checked = !!defaultValue;
      }
    } else if (this.inputEl) {
      this.inputEl.value = defaultValue !== undefined ? defaultValue : '';
    }
    this.notify();
  }

  validate() {
    this.setError('');
    if (!this.visible) return true;
    const field = this.field;
    const value = this.getValue();

    if (field.required) {
      if (field.type === 'chips' && (!value || value.length === 0)) {
        this.setError('هذا الحقل مطلوب');
        return false;
      }
      if (field.type === 'dynamic-list') {
        const items = Array.isArray(value) ? value.filter(v => v && v.trim() !== '') : [];
        if (items.length === 0) {
          this.setError('يجب إضافة عنصر واحد على الأقل');
          return false;
        }
        if (field.min_items && items.length < field.min_items) {
          this.setError(`يجب إضافة ${field.min_items} عناصر على الأقل`);
          return false;
        }
        return true;
      }
      if (field.type !== 'chips' && value === '') {
        this.setError('هذا الحقل مطلوب');
        return false;
      }
      if (field.type === 'toggle' && field.required && value !== true) {
        this.setError('يجب تفعيل هذا الحقل');
        return false;
      }
    }

    if (typeof value === 'string') {
      if (field.minlength && value.length < field.minlength) {
        this.setError(`الحد الأدنى ${field.minlength} أحرف`);
        return false;
      }
      if (field.maxlength && value.length > field.maxlength) {
        this.setError(`الحد الأقصى ${field.maxlength} أحرف`);
        return false;
      }
    }

    if (typeof value === 'number' && value !== 0 && !Number.isNaN(value)) {
      if (field.min !== undefined && value < field.min) {
        this.setError(`القيمة يجب أن تكون ≥ ${field.min}`);
        return false;
      }
      if (field.max !== undefined && value > field.max) {
        this.setError(`القيمة يجب أن تكون ≤ ${field.max}`);
        return false;
      }
    }

    return true;
  }
}

function buildForm(formConfig = {}) {
  const form = document.createElement('form');
  form.className = 'ai-tool-form';
  form.noValidate = true;

  const controllers = {};
  const sections = Array.isArray(formConfig.sections) ? formConfig.sections : [];

  sections.forEach(section => {
    const sectionEl = document.createElement('div');
    sectionEl.className = 'ai-form-section';

    if (section.title) {
      const h = document.createElement('h4');
      h.textContent = section.title;
      sectionEl.appendChild(h);
    }
    if (section.description) {
      const p = document.createElement('p');
      p.textContent = section.description;
      sectionEl.appendChild(p);
    }

    const layout = section.layout || 'grid-2';
    const grid = document.createElement('div');
    grid.className = `ai-grid ${layout === 'grid-1' ? 'ai-grid-1' : (layout === 'chips' ? 'ai-grid-chips' : 'ai-grid-2')}`;

    (section.fields || []).forEach(field => {
      const ctrl = new FieldController(field);
      grid.appendChild(ctrl.wrapper);
      const fieldKey = field.key || field.name;
      if (fieldKey) {
          controllers[fieldKey] = ctrl;
      }
    });

    sectionEl.appendChild(grid);
    form.appendChild(sectionEl);
  });

  // شروط إظهار الحقول
  Object.values(controllers).forEach(ctrl => {
    const condition = ctrl.field.show_if;
    if (!condition) return;
    const dep = controllers[condition.field];
    if (!dep) return;
    const evaluate = (value) => {
      if (Array.isArray(value)) {
        const visible = value.includes(condition.value);
        ctrl.setVisible(visible);
      } else {
        ctrl.setVisible(value === condition.value);
      }
    };
    dep.onChange(evaluate);
    evaluate(dep.getValue());
  });

  return { form, controllers };
}

class AiToolInstance {
  constructor({ container, placement, context }) {
    this.container = container;
    this.placement = placement;
    this.context = context;
    this.typeKey = placement.type_key;
    this.timer = null;
    this.state = 'idle';
    this.jobsList = []; // قائمة جميع jobs
    this.currentJobId = null; // job الحالي المعروض
    this.buildCard();
    this.buildModal();
    this.refreshStatus({ silent: true });
    // تحديث قسم المعلومات بعد بناء Modal
    if (this.infoSection) {
      this.updateInfoSection();
    }
  }

  buildCard() {
    const card = document.createElement('div');
    card.className = 'ai-tool-card';

    const head = document.createElement('div');
    head.className = 'ai-tool-card-head';

    const titleWrap = document.createElement('div');
    const title = document.createElement('p');
    title.className = 'ai-tool-title';
    title.textContent = this.placement.title || this.placement.button?.label || 'أداة ذكاء اصطناعي';
    const desc = document.createElement('p');
    desc.className = 'ai-tool-desc';
    desc.textContent = this.placement.description || '';
    titleWrap.appendChild(title);
    if (desc.textContent) titleWrap.appendChild(desc);

    this.statusBadge = document.createElement('span');
    this.statusBadge.className = 'ai-tool-status';

    head.appendChild(titleWrap);
    head.appendChild(this.statusBadge);

    const actions = document.createElement('div');
    actions.className = 'ai-tool-actions';

    this.openBtn = document.createElement('button');
    this.openBtn.type = 'button';
    this.openBtn.className = 'btn btn-primary';
    const btnIcon = this.placement.button?.icon || this.placement.panel?.button?.icon;
    const btnLabel = this.placement.button?.label || this.placement.panel?.button?.label || 'تشغيل الأداة (AI)';
    this.openBtn.innerHTML = `${btnIcon ? `<i class="fa ${btnIcon}"></i>` : ''} ${btnLabel}`;
    this.openBtn.addEventListener('click', () => this.openModal());

    this.downloadBtn = document.createElement('a');
    this.downloadBtn.className = 'btn btn-secondary';
    this.downloadBtn.style.display = 'none';
    this.downloadBtn.textContent = this.placement.panel?.download_label || 'تنزيل الملف';
    this.downloadBtn.target = '_blank';
    this.downloadBtn.rel = 'noopener';

    this.resultBtn = document.createElement('button');
    this.resultBtn.type = 'button';
    this.resultBtn.className = 'btn btn-secondary ai-tool-result-btn';
    this.resultBtn.style.display = 'none';
    this.resultBtn.textContent = 'عرض النتيجة';
    this.resultBtn.addEventListener('click', () => this.showResult());

    // زر الحذف (فقط للأدمن في وضع الاستعراض)
    this.deleteBtn = document.createElement('button');
    this.deleteBtn.type = 'button';
    this.deleteBtn.className = 'ai-tool-delete-btn';
    this.deleteBtn.style.display = 'none';
    this.deleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i> حذف الطلب';
    this.deleteBtn.addEventListener('click', () => this.deleteJob());

    this.retryBtn = document.createElement('button');
    this.retryBtn.type = 'button';
    this.retryBtn.className = 'btn btn-secondary ai-tool-retry-btn';
    this.retryBtn.style.display = 'none';
    this.retryBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> إعادة التوليد';
    this.retryBtn.addEventListener('click', () => this.rebuildJob());

    this.rebuildFileBtn = document.createElement('button');
    this.rebuildFileBtn.type = 'button';
    this.rebuildFileBtn.className = 'btn btn-secondary ai-tool-rebuild-file-btn';
    this.rebuildFileBtn.style.display = 'none';
    this.rebuildFileBtn.innerHTML = '<i class="fa-solid fa-file-word"></i> إعادة بناء الملف';
    this.rebuildFileBtn.addEventListener('click', () => this.rebuildDocxJob());


    // زر رد على تعليق (لأداة المنتدى فقط)
    if (this.typeKey === 'forum_reply_v1') {
      this.commentReplyBtn = document.createElement('button');
      this.commentReplyBtn.type = 'button';
      this.commentReplyBtn.className = 'btn btn-primary ai-comment-reply-btn';
      this.commentReplyBtn.style.display = 'none';
      this.commentReplyBtn.innerHTML = '<i class="fa-solid fa-reply"></i> كتابة رد على إجابة أحد الزملاء';
      this.commentReplyBtn.addEventListener('click', () => this.openCommentReplyModal());
      actions.appendChild(this.commentReplyBtn);
    }

    // حاوية لأزرار التنزيل المتعددة
    this.downloadsContainer = document.createElement('div');
    this.downloadsContainer.className = 'ai-tool-downloads-container';
    this.downloadsContainer.style.cssText = 'display: none; gap: 8px; flex-wrap: wrap; align-items: center;';

    actions.appendChild(this.openBtn);
    actions.appendChild(this.downloadBtn);
    actions.appendChild(this.downloadsContainer);
    actions.appendChild(this.resultBtn);
    actions.appendChild(this.rebuildFileBtn);

    // زر الحذف يظهر بجانب زر النتيجة/التنزيل (فقط للأدمن)
    if (window.__IS_ADMIN_VIEWING_AS_STUDENT__ === true) {
      actions.appendChild(this.deleteBtn);
    }
    actions.appendChild(this.retryBtn);

    // قسم قائمة Jobs (سيتم بناؤه لاحقاً)
    this.jobsListContainer = document.createElement('div');
    this.jobsListContainer.className = 'ai-tool-jobs-list';
    this.jobsListContainer.style.cssText = 'margin-top: 16px; display: none;';

    card.appendChild(head);
    card.appendChild(this.jobsListContainer);
    card.appendChild(actions);

    // Badges للمعلومات
    const badgesContainer = document.createElement('div');
    badgesContainer.style.cssText = 'display: flex; flex-direction: column; gap: 8px; margin-top: 12px;';

    // Badge تكلفة النقاط
    if (this.placement.points_cost !== undefined) {
      const pointsBadge = document.createElement('small');
      pointsBadge.className = 'ai-tool-access-badge';
      pointsBadge.style.cssText = 'background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa;';
      const formattedCost = window.AI_TOOLS_COMMON?.formatPoints?.(this.placement.points_cost) || this.placement.points_cost.toString();
      pointsBadge.textContent = `${formattedCost} نقطة`;
      badgesContainer.appendChild(pointsBadge);
    }

    if ((this.placement.min_subscription_level || 0) > 0) {
      const badge = document.createElement('small');
      badge.className = 'ai-tool-access-badge';
      badge.textContent = 'يتطلب اشتراكاً مميزاً';
      badgesContainer.appendChild(badge);
    }

    // Badge الاستخدام المجاني (سيتم تحديثه لاحقاً)
    this.freeUsageBadge = document.createElement('small');
    this.freeUsageBadge.className = 'ai-tool-access-badge';
    this.freeUsageBadge.style.cssText = 'background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; display: none;';
    this.freeUsageBadge.textContent = 'استخدام مجاني متاح';
    badgesContainer.appendChild(this.freeUsageBadge);

    // Badge خصم النقاط (سيتم تحديثه لاحقاً)
    this.pointsDeductionBadge = document.createElement('small');
    this.pointsDeductionBadge.className = 'ai-tool-access-badge';
    this.pointsDeductionBadge.style.cssText = 'background: #fef3c7; color: #d97706; border: 1px solid #fde68a; display: none;';
    badgesContainer.appendChild(this.pointsDeductionBadge);

    if (badgesContainer.children.length > 0) {
      card.appendChild(badgesContainer);
    }

    this.container.appendChild(card);
    this.card = card;
  }

  async addSpecialtySelectorIfNeeded(formConfig) {
    try {
      const res = await fetch(`${API_BASE}/specialties`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });

      if (!res.ok) {
        console.warn('[ai-tool-factory] Failed to fetch specialties');
        return;
      }

      const data = await res.json();
      if (!data?.ok || !data.show_specialty_selector) {
        return; // لا حاجة لإضافة الحقل
      }

      // إضافة حقل اختيار التخصص في أول section
      const specialtyField = {
        key: 'selected_specialty_id',
        type: 'select',
        label: 'التخصص المستهدف',
        required: true,
        options: data.specialties.map(s => ({
          value: String(s.id),
          label: s.name
        }))
      };

      // إضافة الحقل في أول section من formConfig
      if (formConfig.sections && formConfig.sections.length > 0) {
        const firstSection = formConfig.sections[0];
        if (firstSection.fields) {
          firstSection.fields.unshift(specialtyField); // إضافة في البداية
        } else {
          firstSection.fields = [specialtyField];
        }
      }
    } catch (err) {
      console.warn('[ai-tool-factory] Error adding specialty selector:', err);
    }
  }

  buildFormFromConfig(formConfig, content, modal) {
    const { form, controllers } = buildForm(formConfig);
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      this.submitForm();
    });
    content.appendChild(form);
    this.formEl = form;
    this.controllers = controllers;

    // لأداة دراسة الحالة: إخفاء case_text وإظهار selected_case_index
    if (this.typeKey === 'case_study_v1') {
      const caseTextCtrl = this.controllers['case_text'];
      const caseSelectCtrl = this.controllers['selected_case_index'];
      if (caseTextCtrl) {
        caseTextCtrl.setVisible(false); // إخفاء حقل النص
      }
      if (caseSelectCtrl) {
        caseSelectCtrl.setVisible(true); // إظهار قائمة الاختيار
      }
    }

    // استعادة البيانات من localStorage عند فتح النموذج
    this.loadCachedData();

    // قسم معلومات النقاط والاشتراك
    this.infoSection = document.createElement('div');
    this.infoSection.className = 'ai-modal-info-section';
    this.infoSection.style.cssText = 'padding: 12px 16px; background: #f7f9fc; border-top: 1px solid #e4e7ec; border-bottom: 1px solid #e4e7ec; font-size: 0.9rem;';
    this.updateInfoSection();
    form.appendChild(this.infoSection);

    // إنشاء footer و submitBtn بعد إنشاء form
    const footer = document.createElement('footer');

    // زر تفريغ الحقول
    const resetBtn = document.createElement('button');
    resetBtn.type = 'button';
    resetBtn.className = 'btn btn-outline-danger';
    resetBtn.style.cssText = 'padding: 8px 14px; font-size: 0.9rem; border-radius: 10px; margin-left: 10px; display: inline-flex; align-items: center; gap: 6px;';
    resetBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> تفريغ الحقول';
    resetBtn.addEventListener('click', () => this.resetForm());
    footer.appendChild(resetBtn);

    const submitBtn = document.createElement('button');
    submitBtn.type = 'submit';
    submitBtn.className = 'btn btn-primary';
    submitBtn.textContent = this.placement.form?.submit_label || 'إرسال الطلب';
    footer.appendChild(submitBtn);
    const hint = document.createElement('span');
    hint.className = 'ai-hint';
    hint.textContent = 'يتم التنفيذ في الخلفية — يمكنك متابعة عملك بشكل طبيعي.';
    footer.appendChild(hint);
    form.appendChild(footer);
    this.submitBtn = submitBtn;
  }

  /**
   * إعداد منطق الحالات المتسلسلة لأداة دمج الحالة
   */
  setupCaseIntegrationLogic() {
    const CASE_CATEGORIES = {
      'صعوبات التعلم الأكاديمية': [
        'عُسر القراءة (Dyslexia)',
        'عُسر الكتابة (Dysgraphia)',
        'عُسر الحساب (Dyscalculia)',
        'صعوبات الفهم القرائي/الاستيعاب',
        'بطء التعلم (ليس إعاقة، لكن يحتاج تكييفات)'
      ],
      'اضطرابات التواصل واللغة': [
        'اضطرابات النطق (التلعثم/اللثغة…)',
        'اضطراب اللغة التعبيرية أو الاستقبالية',
        'تأخر لغوي',
        'صعوبات التواصل الاجتماعي (ضمن طيف التوحد أحيانًا)'
      ],
      'اضطرابات الانتباه والسلوك': [
        'اضطراب فرط الحركة وتشتت الانتباه (ADHD)',
        'صعوبات التنظيم الذاتي وضبط الانفعال',
        'مشكلات سلوكية (عند وجود خطة دعم سلوكي)'
      ],
      'الإعاقات الحسية': [
        'الإعاقة السمعية (ضعف سمع/صمم مع وسائل دعم)',
        'الإعاقة البصرية (ضعف بصر/كف بصري مع تكييفات)'
      ],
      'الإعاقات الحركية والصحية': [
        'إعاقة حركية (شلل دماغي خفيف/استخدام كرسي…)',
        'حالات صحية مزمنة (سكري، صرع، ربو…) مع خطة طبية مدرسية',
        'إصابات مؤقتة تؤثر على التعلم'
      ],
      'الإعاقة العقلية/النمائية': [
        'إعاقة عقلية بسيطة (مع أهداف واقعية وتكييفات)',
        'اضطرابات نمائية تؤثر على المهارات المعرفية'
      ],
      'اضطراب طيف التوحد (ASD)': [
        'من يحتاج دعمًا بسيطًا إلى متوسطًا داخل الصف، مع تكييفات واضحة وروتين ودعم تواصل'
      ],
      'الاضطرابات الانفعالية والنفسية': [
        'القلق، الاكتئاب (بشكل غير وصفي ومهني داخل المدرسة)',
        'صدمات/ضغوط نفسية',
        'مشكلات تقدير الذات والعزلة الاجتماعية'
      ],
      'الموهبة والتفوق': [
        'موهوبون أكاديميًا',
        'موهوبون في مجالات محددة (قيادة، فن، رياضة، STEM…)',
        '"موهوب مع صعوبة تعلم" (Twice-exceptional)'
      ],
      'فروق فردية مرتبطة بالنوع الاجتماعي والسياق': [
        'فجوات تحصيل بسبب تنميط جندري أو توقعات اجتماعية',
        'تحديات مرتبطة بالدافعية/اللغة/الثقافة/البيئة المنزلية',
        'طلاب يحتاجون دعمًا بسبب ظروف اجتماعية/اقتصادية'
      ]
    };

    const categoryCtrl = this.controllers['case_category'];
    const caseCtrl = this.controllers['case'];

    if (!categoryCtrl || !caseCtrl) {
      console.warn('[ai-tool-factory] Case integration: missing controllers');
      return;
    }

    // عند تغيير التصنيف، تحديث قائمة النوع
    categoryCtrl.onChange((category) => {
      const caseSelect = caseCtrl.inputEl;
      if (!caseSelect) return;

      // مسح الخيارات السابقة
      caseSelect.innerHTML = '<option value="">— اختر نوع الحالة —</option>';

      if (category && CASE_CATEGORIES[category]) {
        // تفعيل القائمة وإضافة الخيارات
        caseSelect.disabled = false;
        CASE_CATEGORIES[category].forEach(caseType => {
          const option = document.createElement('option');
          option.value = caseType;
          option.textContent = caseType;
          caseSelect.appendChild(option);
        });
      } else {
        // تعطيل القائمة إذا لم يتم اختيار تصنيف
        caseSelect.disabled = true;
      }
    });

    // تهيئة الحالة الأولية
    const initialCategory = categoryCtrl.getValue();
    if (initialCategory) {
      categoryCtrl.notify();
    } else {
      caseCtrl.inputEl.disabled = true;
    }
  }

  /**
   * إعداد منطق الخيارات المعتمدة لأداة الموقف التقييمي
   */
  setupAssessmentPositionLogic() {
    const ASSESSMENT_STRATEGIES = {
      'التقويم المعتمد على الأداء': [
        'قائمة الشطب',
        'سلم التقدير',
        'سلم التقدير اللفظي',
        'السجل الوصفي'
      ],
      'استراتيجية الملاحظة': [
        'قائمة الشطب',
        'سلم التقدير',
        'سجل سير التعلم',
        'السجل الوصفي' // Changed Anecdotal Records to available options in DB
      ],
      'استراتيجية التواصل': [
        'سلم التقدير',
        'قائمة الشطب',
        'أسئلة وإجابات',
        'مقابلة'
      ],
      'المراجعة للذات': [
        'يوميات الطالب',
        'ملف الطالب',
        'مقياس التقدير'
      ],
      'الورقة والقلم': [
        'اختبار قصير',
        'اختبار قياسي'
      ]
    };

    const strategyCtrl = this.controllers['assessment_strategy'];
    const toolCtrl = this.controllers['assessment_tool'];

    if (!strategyCtrl || !toolCtrl) {
        return; // Not all controllers present
    }

    // نحتفظ بالقيمة الأولية التي ربما تمت استعادتها من الكاش
    let initialTool = toolCtrl.getValue();

    strategyCtrl.onChange((strategy) => {
      console.log('[ai-tool-factory] Strategy changed to:', strategy);
      const toolSelect = toolCtrl.inputEl;
      if (!toolSelect) return;

      // نحتفظ بالقيمة الحالية قبل مسح الخيارات
      const currentTool = initialTool || toolSelect.value;
      initialTool = null; // نستخدم القيمة الأولية مرة واحدة فقط

      // مسح الخيارات السابقة
      toolSelect.innerHTML = '<option value="">دع الذكاء الاصطناعي يختار</option>';

      if (strategy && ASSESSMENT_STRATEGIES[strategy]) {
        toolSelect.disabled = false;
        ASSESSMENT_STRATEGIES[strategy].forEach(toolName => {
          const option = document.createElement('option');
          option.value = toolName;
          option.textContent = toolName;
          toolSelect.appendChild(option);
        });
      } else {
        // إذا اختار "دع الذكاء الاصطناعي يختار" أو كقيمة افتراضية
        console.log('[ai-tool-factory] Showing all tools');
        const allTools = [
            'قائمة الشطب', 'سلم التقدير', 'سلم التقدير اللفظي', 'السجل الوصفي',
            'سجل سير التعلم', 'أسئلة وإجابات', 'مقابلة', 'يوميات الطالب',
            'ملف الطالب', 'مقياس التقدير', 'اختبار قصير', 'اختبار قياسي'
        ];
        toolSelect.disabled = false;
        allTools.forEach(toolName => {
          const option = document.createElement('option');
          option.value = toolName;
          option.textContent = toolName;
          toolSelect.appendChild(option);
        });
      }

      // استعادة القيمة إن أمكن
      if (currentTool) {
        toolSelect.value = currentTool;
      }
    });

    // تهيئة الحالة الأولية
    strategyCtrl.notify(); // فرض التحديث لإظهار القائمة الصحيحة
  }

  /**
   * تحميل case_text من task_prompt لأداة دراسة الحالة
   */
  async loadCaseTextFromTask() {
    if (!this.context.taskId || this.context.taskId === '0') {
      return;
    }

    const caseTextCtrl = this.controllers['case_text'];
    if (!caseTextCtrl || !caseTextCtrl.inputEl) {
      return;
    }

    try {
      const res = await fetch(`${API_BASE}/get-task-prompt?task_id=${this.context.taskId}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'include'
      });
      const json = await res.json().catch(() => ({}));

      if (json.ok && json.task_prompt) {
        const taskPrompt = json.task_prompt.trim();

        // محاولة تحليل JSON
        try {
          const caseStudyData = JSON.parse(taskPrompt);
          if (caseStudyData && caseStudyData.type === 'case_study' && caseStudyData.cases && Array.isArray(caseStudyData.cases)) {
            // حفظ البيانات في instance للاستخدام لاحقاً
            this.caseStudyData = {
              ...caseStudyData,
              rawText: taskPrompt
            };

            // تحديث selected_case_index بالعناوين من البيانات
            const caseSelectCtrl = this.controllers['selected_case_index'];
            if (caseSelectCtrl && caseSelectCtrl.inputEl) {
              caseSelectCtrl.inputEl.innerHTML = '';

              if (caseStudyData.cases.length > 1) {
                // إضافة خيار "جميع الحالات" إذا كانت هناك حالات متعددة
                const allOption = document.createElement('option');
                allOption.value = '-1';
                allOption.textContent = '— جميع الحالات —';
                caseSelectCtrl.inputEl.appendChild(allOption);
              }

              // إضافة جميع الحالات (العناوين فقط)
              caseStudyData.cases.forEach((c, idx) => {
                const option = document.createElement('option');
                // استخدام index من البيانات إذا كان موجوداً، وإلا استخدام idx
                const caseIndex = c.index !== undefined ? String(c.index) : String(idx);
                option.value = caseIndex;
                // عرض العنوان فقط (title) - بدون النص
                option.textContent = c.title || `حالة ${idx + 1}`;
                caseSelectCtrl.inputEl.appendChild(option);
              });

              // إظهار الحقل دائماً (تم إخفاء case_text)
              caseSelectCtrl.setVisible(true);

              // تحديد أول حالة كقيمة افتراضية
              if (caseStudyData.cases.length > 0) {
                const firstCaseIndex = caseStudyData.cases[0].index !== undefined
                  ? String(caseStudyData.cases[0].index)
                  : '0';
                caseSelectCtrl.inputEl.value = firstCaseIndex;
                // تحديث case_text تلقائياً
                const firstCase = caseStudyData.cases[0];
                if (caseTextCtrl && caseTextCtrl.inputEl) {
                  caseTextCtrl.inputEl.value = firstCase.text || firstCase.content || '';
                }
              }
            }

            // استخدام أول حالة كنص افتراضي
            if (caseStudyData.cases.length > 0) {
              const firstCase = caseStudyData.cases[0];
              caseTextCtrl.inputEl.value = firstCase.text || firstCase.content || taskPrompt;
            } else {
              caseTextCtrl.inputEl.value = taskPrompt;
            }

            // حفظ المعايير في instance لإرسالها في payload (لا تظهر في الفورم)
            if (caseStudyData.criteria && Array.isArray(caseStudyData.criteria) && caseStudyData.criteria.length > 0) {
              this.caseStudyCriteria = caseStudyData.criteria;
            }
            return;
          }
        } catch (e) {
          // إذا لم يكن JSON، استخدمه كنص عادي
        }

        // حفظ النص العادي للاستخدام لاحقاً
        this.caseStudyData = {
          rawText: taskPrompt
        };

        // استخدام النص العادي
        caseTextCtrl.inputEl.value = taskPrompt;
      }
    } catch (e) {
      console.warn('[ai-tool-factory] Failed to load case text:', e);
    }
  }

  /**
   * إعداد منطق أداة دراسة الحالة (تحديث case_text عند تغيير selected_case_index)
   */
  setupCaseStudyLogic() {
    const caseSelectCtrl = this.controllers['selected_case_index'];
    const caseTextCtrl = this.controllers['case_text'];

    if (!caseSelectCtrl || !caseTextCtrl) {
      return;
    }

    // حفظ بيانات الحالات في instance
    if (!this.caseStudyData) {
      return; // لم يتم تحميل البيانات بعد
    }

    // عند تغيير selected_case_index، تحديث case_text
    caseSelectCtrl.onChange((selectedValue) => {
      if (!this.caseStudyData || !this.caseStudyData.cases) {
        return;
      }

      const selectedIndex = parseInt(selectedValue, 10);

      if (selectedIndex === -1) {
        // جميع الحالات - دمج جميع النصوص
        const allTexts = this.caseStudyData.cases.map(c => c.text || c.content || '').filter(t => t).join('\n\n---\n\n');
        caseTextCtrl.inputEl.value = allTexts || this.caseStudyData.rawText || '';
      } else {
        // البحث عن الحالة باستخدام index من البيانات
        let selectedCase = null;

        // محاولة البحث باستخدام index من البيانات (مطابقة مباشرة)
        selectedCase = this.caseStudyData.cases.find(c => c.index === selectedIndex);

        // إذا لم يتم العثور عليها، استخدم القيمة كفهرس في المصفوفة
        if (!selectedCase) {
          // إذا كانت القيمة رقم (من idx)، استخدمها مباشرة كفهرس
          if (selectedIndex >= 0 && selectedIndex < this.caseStudyData.cases.length) {
            selectedCase = this.caseStudyData.cases[selectedIndex];
          }
        }

        if (selectedCase) {
          // تحديث case_text بالنص المطابق للحالة المختارة
          caseTextCtrl.inputEl.value = selectedCase.text || selectedCase.content || '';
        } else {
          console.warn('[ai-tool-factory] Case not found for index:', selectedIndex);
        }
      }
    });
  }

  /**
   * تحديث قسم المعلومات في Modal
   */
  async updateInfoSection() {
    if (!this.infoSection) return;

    // إظهار loading state
    this.infoSection.innerHTML = `<div style="text-align: center; padding: 8px; color: #666; font-size: 0.85rem;">جاري جلب المعلومات...</div>`;
    this.infoSection.style.display = 'block';

    try {
      const pointsCost = this.placement.points_cost || 30;
      const userPoints = await (window.AI_TOOLS_COMMON?.getUserPoints?.() || Promise.resolve(0));
      const subscriptionStatus = await (window.AI_TOOLS_COMMON?.checkSubscriptionStatus?.() || Promise.resolve({ active: false, level: 0 }));

      const formattedCost = window.AI_TOOLS_COMMON?.formatPoints?.(pointsCost) || pointsCost.toString();
      const formattedUser = window.AI_TOOLS_COMMON?.formatPoints?.(userPoints) || userPoints.toString();

      let html = `<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">`;
      html += `<div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">`;
      html += `<span><strong>تكلفة الأداة:</strong> ${formattedCost} نقطة</span>`;
      html += `<span><strong>رصيدك الحالي:</strong> ${formattedUser} نقطة</span>`;

      if (subscriptionStatus.active) {
        html += `<span style="color: #059669;"><strong>✓</strong> لديك اشتراك فعال</span>`;
      }

      html += `</div>`;

      // روابط سريعة (كأزرار للتنقل إلى sections)
      html += `<div style="display: flex; gap: 10px;">`;
      html += `<button type="button" class="ai-info-link-btn" data-section="rewards" style="background: none; border: none; color: #219ebc; text-decoration: none; font-size: 0.85rem; cursor: pointer; padding: 0;">عرض رصيد النقاط</button>`;
      if (!subscriptionStatus.active) {
        html += `<button type="button" class="ai-info-link-btn" data-section="upgrade" style="background: none; border: none; color: #219ebc; text-decoration: none; font-size: 0.85rem; cursor: pointer; padding: 0;">الاشتراك الآن</button>`;
      }
      html += `</div>`;
      html += `</div>`;

      this.infoSection.innerHTML = html;
      this.infoSection.style.display = 'block';

      // إضافة event listeners للأزرار
      const linkButtons = this.infoSection.querySelectorAll('.ai-info-link-btn');
      linkButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const section = btn.getAttribute('data-section');
          if (section) {
            this.closeModal();
            navigateToSection(section);
          }
        });
      });
    } catch (err) {
      console.warn('[ai-tool-factory] Failed to update info section:', err);
      this.infoSection.innerHTML = `<div style="text-align: center; padding: 8px; color: #dc2626; font-size: 0.85rem;">حدث خطأ في جلب المعلومات</div>`;
      this.infoSection.style.display = 'block';
    }
  }

  getCacheKey() {
    return `ai_tool_${this.typeKey}_${this.context.taskId || 'default'}`;
  }

  saveCachedData(payload) {
    try {
      const cacheKey = this.getCacheKey();
      // حفظ جميع الحقول ما عدا type_key و task_id
      const dataToCache = {};
      Object.entries(payload).forEach(([key, value]) => {
        if (key !== 'type_key' && key !== 'task_id' && value !== '' && value !== undefined) {
          dataToCache[key] = value;
        }
      });
      localStorage.setItem(cacheKey, JSON.stringify(dataToCache));
    } catch (err) {
      console.warn('[ai-tools] Failed to save cache:', err);
    }
  }

  loadCachedData() {
    try {
      const cacheKey = this.getCacheKey();
      const cached = localStorage.getItem(cacheKey);
      if (!cached) return;

      const data = JSON.parse(cached);
      Object.entries(data).forEach(([key, value]) => {
        const ctrl = this.controllers[key];
        if (!ctrl) return;

        if (ctrl.field.type === 'chips' && Array.isArray(value)) {
          // استعادة chips
          ctrl.chipSet = new Set(value);
          // تحديث الواجهة
          const chipsWrap = ctrl.wrapper.querySelector('.ai-chips');
          if (chipsWrap) {
            Array.from(chipsWrap.children).forEach(btn => {
              // الحصول على قيمة الـ chip من options
              const chipText = btn.textContent.trim();
              const fieldOptions = ctrl.field.options || [];
              let chipValue = chipText;

              // البحث عن القيمة في options
              for (const opt of fieldOptions) {
                const optValue = typeof opt === 'string' ? opt : (opt.value ?? opt.label ?? '');
                const optLabel = typeof opt === 'string' ? opt : (opt.label ?? opt.value ?? '');
                if (optLabel === chipText || optValue === chipText) {
                  chipValue = optValue;
                  break;
                }
              }

              if (value.includes(chipValue)) {
                btn.classList.add('active');
              } else {
                btn.classList.remove('active');
              }
            });
          }
        } else if (ctrl.inputEl) {
          if (ctrl.field.type === 'toggle') {
            ctrl.inputEl.checked = !!value;
          } else if (ctrl.field.type === 'number') {
            ctrl.inputEl.value = value;
          } else {
            ctrl.inputEl.value = value;
          }
        }
      });
    } catch (err) {
      console.warn('[ai-tools] Failed to load cache:', err);
    }
  }

  buildModal() {

    ensureStyles();
    const root = document.getElementById('ai-tools-modals-root') || document.body;
    this.modalBackdrop = document.createElement('div');
    this.modalBackdrop.className = 'ai-tool-modal-backdrop';
    this.modalBackdrop.setAttribute('aria-hidden', 'true');

    const modal = document.createElement('div');
    modal.className = 'ai-tool-modal';

    const header = document.createElement('header');
    const title = document.createElement('div');
    title.className = 'ai-modal-title';
    title.innerHTML = `<i class="fa-solid fa-wand-magic-sparkles"></i> ${this.placement.form?.modal_title || 'نموذج الأداة'}`;
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ai-modal-close';
    closeBtn.textContent = 'إغلاق';
    closeBtn.addEventListener('click', () => this.closeModal());
    header.appendChild(title);
    header.appendChild(closeBtn);

    const content = document.createElement('div');
    content.className = 'ai-modal-content';

    // التحقق من وجود form config (استثناء الأدوات ذات الواجهة المخصصة بالكامل)
    const formConfig = this.placement.form || {};
    const cleanTypeKey = (this.typeKey || '').trim();
    const isFullyCustomUI = ['inquiry_plan_evaluator_v1', 'inquiry_plan_evaluator'].includes(cleanTypeKey);
    
    if (!isFullyCustomUI && (!formConfig.sections || !Array.isArray(formConfig.sections) || formConfig.sections.length === 0)) {
      console.warn('[ai-tool-factory] No form sections found for tool:', this.typeKey, 'placement:', this.placement);
      content.innerHTML = `
        <div style="padding: 20px; text-align: center; color: #666;">
          <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #f59e0b; margin-bottom: 10px;"></i>
          <p><strong>تعذر تحميل نموذج الأداة</strong></p>
          <p style="font-size: 0.9rem;">لم يتم العثور على إعدادات النموذج. يرجى التحقق من إعدادات الأداة في قاعدة البيانات.</p>
          <p style="font-size: 0.8rem; color: #999; margin-top: 10px; font-family: monospace;">Debug typeKey: ${this.typeKey}</p>
        </div>
      `;

      // إذا لم يكن form موجود، أضف footer مباشرة في modal
      const footer = document.createElement('footer');
      footer.style.borderTop = '1px solid #e1e5e9';
      footer.style.padding = '16px 20px';
      footer.style.display = 'flex';
      footer.style.justifyContent = 'flex-end';
      footer.style.alignItems = 'center';
      const closeBtnFooter = document.createElement('button');
      closeBtnFooter.type = 'button';
      closeBtnFooter.className = 'btn btn-secondary';
      closeBtnFooter.textContent = 'إغلاق';
      closeBtnFooter.addEventListener('click', () => this.closeModal());
      footer.appendChild(closeBtnFooter);
      modal.appendChild(footer);
    } else {
      // إخفاء حقول topic و context لأداة المنتدى (تُؤخذ تلقائياً من المهمة)
      if (this.typeKey === 'forum_reply_v1') {
        formConfig.sections = formConfig.sections.map(section => ({
          ...section,
          fields: section.fields.filter(field => field.key !== 'topic' && field.key !== 'context')
        }));
      }

      // لأدوات التأمل: إضافة حقل اختيار التخصص إذا كان تخصص المستخدم 22 أو 14
      if (this.typeKey === 'reflection_v1' || this.typeKey === 'weekly_reflection_v1') {
        // استدعاء async وإضافة الحقل ثم بناء النموذج
        (async () => {
          await this.addSpecialtySelectorIfNeeded(formConfig);
          this.buildFormFromConfig(formConfig, content, modal);
        })();
      } else if (this.typeKey === 'article_evaluator_v1') {
        // لأداة مقيم المقالات: استخدام الواجهة الديناميكية المخصصة
        this.buildArticleEvaluatorForm(formConfig, content, modal);
      } else if (cleanTypeKey === 'inquiry_plan_evaluator_v1' || cleanTypeKey === 'inquiry_plan_evaluator') {
        // لأداة مقيم المخطط الاستقصائي: الواجهة الديناميكية المخصصة
        this.buildInquiryPlanEvaluatorForm(formConfig, content, modal);
      } else if (this.typeKey === 'test_design_v1') {
        // لأداة تصميم الاختبار: واجهة ديناميكية لإضافة الدروس
        this.buildTestDesignForm(formConfig, content, modal);
      } else {
        this.buildFormFromConfig(formConfig, content, modal);
        // لأداة دمج الحالة: إضافة JavaScript للتعامل مع الحالات المتسلسلة
        // يجب استدعاؤه بعد buildFormFromConfig لأن controllers يتم إنشاؤها هناك
        if (this.typeKey === 'case_integration_v1') {
          // استخدام setTimeout لضمان أن controllers جاهزة
          setTimeout(() => {
            this.setupCaseIntegrationLogic();
          }, 0);
        }
        // لأداة دراسة الحالة: تحميل case_text من task_prompt تلقائياً
        if (this.typeKey === 'case_study_v1') {
          setTimeout(() => {
            this.loadCaseTextFromTask();
            this.setupCaseStudyLogic();
          }, 0);
        }
        // الموقف التقييمي
        if (this.typeKey === 'assessment_position_v1') {
          setTimeout(() => {
            this.setupAssessmentPositionLogic();
          }, 0);
        }
      }
    }

    modal.appendChild(header);
    modal.appendChild(content);
    this.modalBackdrop.appendChild(modal);
    root.appendChild(this.modalBackdrop);
  }

  async openModal() {
    if (this.modalBackdrop) {
      this.modalBackdrop.classList.add('show');
      this.modalBackdrop.setAttribute('aria-hidden', 'false');

      // لأداة دراسة الحالة: تحميل case_text من task_prompt عند فتح المودال
      if (this.typeKey === 'case_study_v1') {
        await this.loadCaseTextFromTask();
        this.setupCaseStudyLogic();
      }

      // تحديث قسم المعلومات عند فتح المودال
      if (this.infoSection) {
        this.updateInfoSection();
      }
      document.body.classList.add('ai-tool-modal-open');
    }
  }

  closeModal() {
    if (this.modalBackdrop) {
      this.modalBackdrop.classList.remove('show');
      this.modalBackdrop.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('ai-tool-modal-open');
    }
  }

  collectPayload() {
    // لأداة مقيم المقالات: استخدام الملف الخارجي
    if (this.typeKey === 'article_evaluator_v1' && this.articleEvaluatorModule) {
      return this.articleEvaluatorModule.collectPayload();
    }
    
    // لأداة مقيم المخطط الاستقصائي
    const cleanTypeKey = (this.typeKey || '').trim();
    if ((cleanTypeKey === 'inquiry_plan_evaluator_v1' || cleanTypeKey === 'inquiry_plan_evaluator') && this.inquiryPlanEvaluatorModule) {
      return this.inquiryPlanEvaluatorModule.collectPayload();
    }

    const payload = {};
    let valid = true;
    Object.entries(this.controllers || {}).forEach(([key, ctrl]) => {
      if (!ctrl.validate()) valid = false;
      const value = ctrl.getValue();
      // للـ dynamic-list، نرسل المصفوفة حتى لو كانت فارغة (لكن بعد التحقق من min_items)
      if (ctrl.field.type === 'dynamic-list') {
        const items = Array.isArray(value) ? value.filter(v => v && v.trim() !== '') : [];
        if (items.length > 0 || !ctrl.field.required) {
          payload[key] = items;
        }
      } else if (ctrl.visible && value !== '' && value !== undefined) {
        payload[key] = value;
      }
    });

    // لأداة دمج الحالة: دمج case_category و case في case واحد
    if (this.typeKey === 'case_integration_v1') {
      const category = payload['case_category'];
      const caseType = payload['case'];
      if (category && caseType) {
        payload['case'] = `${category} - ${caseType}`;
        delete payload['case_category'];
      }
    }

    // لأداة دراسة الحالة: إضافة المعايير من قاعدة البيانات (إذا كانت موجودة)
    if (this.typeKey === 'case_study_v1' && this.caseStudyCriteria && Array.isArray(this.caseStudyCriteria)) {
      payload['criteria'] = this.caseStudyCriteria;
    }

    // لأداة تصميم الاختبار: جمع بيانات الدروس من الـ UI الديناميكي
    if (this.typeKey === 'test_design_v1') {
      const lessonItems = document.querySelectorAll('.ai-td-lesson-item');
      const lessonsArr = [];
      lessonItems.forEach(item => {
        const title    = item.querySelector('.ai-td-lesson-title')?.value?.trim() || '';
        const sessions = item.querySelector('.ai-td-lesson-sessions')?.value?.trim() || '';
        const outcomes = item.querySelector('.ai-td-lesson-outcomes')?.value?.trim() || '';
        if (title) lessonsArr.push({ title, sessions, outcomes });
      });
      if (lessonsArr.length === 0) {
        valid = false;
        const addBtn = document.querySelector('.ai-td-add-lesson');
        if (addBtn) addBtn.style.outline = '2px solid red';
      }
      payload['lessons_input'] = JSON.stringify(lessonsArr);
    }

    return { payload, valid };
  }

  /**
   * UI ديناميكي لأداة تصميم الاختبار
   * يُظهر الحقول الثابتة + مبني لإضافة الدروس ديناميكياً
   */
  buildTestDesignForm(formConfig, content, modal) {
    // أنشئ form config بدون قسم الدروس (سيُبنى يدوياً)
    const staticConfig = {
      ...formConfig,
      sections: (formConfig.sections || []).filter(s =>
        !s.fields?.some(f => f.key === 'lessons_input')
      )
    };
    this.buildFormFromConfig(staticConfig, content, modal);

    // ==== أضف مبني الدروس الديناميكي ====
    const formEl = this.formEl;
    if (!formEl) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'ai-form-section ai-td-lessons-section';
    wrapper.style.cssText = 'margin-top:16px;';

    wrapper.innerHTML = `
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <strong style="font-size:0.95rem;">الدروس والنتاجات</strong>
        <button type="button" class="ai-td-add-lesson" style="
          background:#0ea5e9; color:#fff; border:none; border-radius:6px;
          padding:5px 12px; font-size:0.85rem; cursor:pointer; font-family:inherit;
        ">+ إضافة درس</button>
      </div>
      <div class="ai-td-lessons-list" style="display:flex; flex-direction:column; gap:10px;"></div>
      <p style="font-size:0.8rem; color:#6b7280; margin-top:8px;">
        النتاجات اختيارية — يكملها الذكاء الاصطناعي إن تُركت فارغة.
      </p>
    `;

    // إدراج الدروس قبل info section (تكلفة + رصيد) وقبل زر الإرسال
    if (this.infoSection && this.infoSection.parentNode === formEl) {
      formEl.insertBefore(wrapper, this.infoSection);
    } else {
      formEl.appendChild(wrapper);
    }

    const list   = wrapper.querySelector('.ai-td-lessons-list');
    const addBtn = wrapper.querySelector('.ai-td-add-lesson');

    const addLesson = (titleVal = '', sessionsVal = '', outcomesVal = '') => {
      const item = document.createElement('div');
      item.className = 'ai-td-lesson-item';
      item.style.cssText = 'background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px;';
      item.innerHTML = `
        <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
          <input type="text" class="ai-td-lesson-title" placeholder="عنوان الدرس *" value="${titleVal}"
            style="flex:1; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;
                   font-family:inherit; font-size:0.9rem; direction:rtl;" required>
          <input type="number" class="ai-td-lesson-sessions" placeholder="عدد الحصص" value="${sessionsVal}"
            min="1" style="width:110px; padding:6px 10px; border:1px solid #d1d5db;
                          border-radius:6px; font-family:inherit; font-size:0.9rem;">
          <button type="button" class="ai-td-remove-lesson"
            style="background:none; border:1px solid #f87171; color:#ef4444; border-radius:6px;
                   padding:4px 8px; cursor:pointer; flex-shrink:0;" title="حذف الدرس">✕</button>
        </div>
        <textarea class="ai-td-lesson-outcomes" placeholder="النتاجات التعليمية (اختياري)" rows="2"
          style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;
                 font-family:inherit; font-size:0.85rem; direction:rtl; resize:vertical; box-sizing:border-box;"
        >${outcomesVal}</textarea>
      `;
      item.querySelector('.ai-td-remove-lesson').addEventListener('click', () => item.remove());
      list.appendChild(item);
    };

    // درس واحد فارغ كنقطة بداية
    addLesson();

    addBtn.addEventListener('click', () => addLesson());
  }

  resetForm() {
    if (!this.formEl) return;

    // 1. إعادة ضبط controllers القياسية إذا وُجدت
    if (this.controllers) {
      Object.values(this.controllers).forEach(ctrl => {
        if (typeof ctrl.reset === 'function') {
          ctrl.reset();
        }
      });
    }

    // 2. إعادة ضبط المدخلات القياسية المتبقية (في حالة عدم استخدام controllers)
    const inputs = this.formEl.querySelectorAll('input, select, textarea');
    inputs.forEach(el => {
      // تجنب تغيير حقول معينة مثل أزرار أو حقول مخفية
      if (el.type === 'submit' || el.type === 'button') return;
      
      if (el.tagName === 'SELECT') {
        el.value = el.options[0]?.value || '';
      } else if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = false;
      } else {
        el.value = '';
      }
      
      // إطلاق أحداث لتنبيه المستمعين
      el.dispatchEvent(new Event('change', { bubbles: true }));
      el.dispatchEvent(new Event('input', { bubbles: true }));
    });

    // 3. دعم خاص للـ Chips الخاصة بالملفات المخصصة
    const customActiveChips = this.formEl.querySelectorAll('.ai-chips .ai-chip.active');
    customActiveChips.forEach(chip => {
      chip.classList.remove('active');
    });

    // 4. لأداة مقيم المقالات: إعادة ضبط الأقسام الديناميكية
    if (this.typeKey === 'article_evaluator_v1' && this.articleEvaluatorModule) {
      ['introduction', 'body', 'conclusion'].forEach(sectionKey => {
        const section = this.formEl.querySelector(`.article-section[data-section-key="${sectionKey}"]`);
        if (section) {
          const partsContainer = section.querySelector('.section-parts');
          if (partsContainer) {
            partsContainer.innerHTML = '';
            const partHTML = this.articleEvaluatorModule.buildPartHTML(sectionKey, 1);
            partsContainer.insertAdjacentHTML('beforeend', partHTML);
          }
          this.articleEvaluatorModule.updateSectionWordCount(this.formEl, sectionKey);
        }
      });
    }

    // 5. لأداة مقيم مخطط البحث الاستقصائي: إعادة ضبط الأجزاء الديناميكية للجدول
    const cleanTypeKey = (this.typeKey || '').trim();
    if ((cleanTypeKey === 'inquiry_plan_evaluator_v1' || cleanTypeKey === 'inquiry_plan_evaluator') && this.inquiryPlanEvaluatorModule) {
      const textareas = this.formEl.querySelectorAll('textarea[data-section]');
      textareas.forEach(ta => {
        ta.value = '';
        this.inquiryPlanEvaluatorModule.updateSectionWordCount(ta);
      });

      const tableContainer = this.formEl.querySelector('#table-rows-container');
      if (tableContainer) {
        tableContainer.innerHTML = '';
        this.inquiryPlanEvaluatorModule.addTableRow(this.formEl);
        this.inquiryPlanEvaluatorModule.addTableRow(this.formEl);
      }
    }

    // 6. لأداة تصميم الاختبار (test_design_v1): تفريغ قائمة الدروس
    if (this.typeKey === 'test_design_v1') {
      const lessonsList = this.formEl.querySelector('.ai-td-lessons-list');
      if (lessonsList) {
        lessonsList.innerHTML = '';
        const addBtn = this.formEl.querySelector('.ai-td-add-lesson');
        if (addBtn) {
          addBtn.click();
        }
      }
    }

    // إعلام المستخدم
    notifyUser('info', 'تم تفريغ حقول النموذج.');
  }

  async submitForm() {
    if (!this.context.taskId) {
      notifyUser('error', 'لا يمكن إرسال الطلب قبل تحميل تفاصيل المهمة.');
      return;
    }

    const { payload, valid } = this.collectPayload();
    if (!valid) {
      notifyUser('info', 'يرجى تصحيح الحقول المطلوبة قبل الإرسال.');
      return;
    }

    payload.type_key = this.typeKey;
    payload.task_id = this.context.taskId;

    // التحقق من الوصول قبل الإرسال
    try {
      const studentId = this.getStudentIdentifier();
      const qs = new URLSearchParams({
        type_key: this.typeKey,
        task_id: this.context.taskId,
      });
      if (studentId) {
        qs.set('student_id', studentId);
      }
      const accessRes = await fetch(`${API_BASE}/check-access?${qs.toString()}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const accessData = await accessRes.json();

      if (!accessData.ok || !accessData.can_access) {
        this.showAccessDeniedModal(
          accessData.reason || 'unknown',
          accessData.points_cost || 30,
          accessData.user_points || 0,
          accessData.has_active_plan || false
        );
        return;
      }

      // إذا كان سيتم خصم النقاط، عرض Modal التأكيد
      if (accessData.reason === 'points' && accessData.points_cost > 0) {
        const userPoints = accessData.user_points || await (window.AI_TOOLS_COMMON?.getUserPoints?.() || Promise.resolve(0));
        const confirmed = await this.showPointsConfirmationModal(accessData.points_cost, userPoints);
        if (!confirmed) {
          return; // المستخدم ألغى
        }
      }
    } catch (err) {
      console.warn('[ai-tools] Access check failed:', err);
      // المتابعة مع الإرسال حتى لو فشل التحقق (للتوافق مع الإصدارات القديمة)
    }

    // تعطيل الزر
    this.submitBtn.disabled = true;
    const originalText = this.submitBtn.textContent;
    this.submitBtn.textContent = 'جاري الإرسال...';

    // إضافة class لمنع التفاعل مع td-card
    document.body.classList.add('ai-tool-processing');

    try {
      // التحقق من وجود ملفات (للأدوات مثل smart_analyzer_v1)
      const hasFiles = Object.values(payload).some(v => v instanceof File);

      let res, json;

      if (hasFiles && this.typeKey === 'smart_analyzer_v1') {
        // استخدام FormData للملفات
        const formData = new FormData();
        formData.append('type_key', this.typeKey);
        formData.append('task_id', this.context.taskId);

        for (const [key, value] of Object.entries(payload)) {
          if (key === 'type_key' || key === 'task_id') continue;
          if (value instanceof File) {
            formData.append(key, value);
          } else {
            formData.append(key, value);
          }
        }

        res = await fetch(`${API_BASE}/analyze-file`, {
          method: 'POST',
          credentials: 'include',
          body: formData,
        });

        json = await res.json().catch(() => ({}));

        // معالجة الرد المباشر (done)
        if (res.status === 200 && json.ok && json.status === 'done') {
          this.closeModal();
          notifyUser('success', `تم التحليل بنجاح! تم استخراج ${json.units_count || 0} وحدة معرفية.`);
          this.refreshStatus({ withSpinner: false });
          return;
        }
      } else {
        // الطريقة العادية (JSON)
        res = await fetch(`${API_BASE}/request`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          credentials: 'include',
          body: JSON.stringify(payload),
        });
        json = await res.json().catch(() => ({}));
      }

      if (res.status === 200 && json.ok && json.status === 'accepted') {
        // حفظ البيانات في localStorage بعد إرسال النموذج بنجاح
        this.saveCachedData(payload);
        this.closeModal();
        notifyUser('success', 'تم استلام الطلب وسيتم معالجته في الخلفية.');
        // إعادة تعيين currentJobId للسماح بتحديثه في refreshStatus
        this.currentJobId = null;
        this.refreshStatus({ withSpinner: true });
        return;
      }

      if (res.status === 402 || (res.status === 400 && json.message && (json.message.includes('نقاط') || json.message.includes('اشتراك')))) {
        this.closeModal();
        this.showAccessDeniedModal(
          json.reason || 'unknown',
          json.points_cost || this.placement.points_cost || 30,
          json.user_points || 0,
          json.has_active_plan || false
        );
        return;
      }

      if (res.status === 409 && json.status === 'already_active') {
        this.closeModal();
        notifyUser('info', 'لديك طلب فعّال بالفعل لهذه المهمة.');
        this.refreshStatus({ withSpinner: true });
        return;
      }

      if (res.status === 200 && json.status === 'already_done' && json.download_url) {
        this.closeModal();
        this.applyState('done', 'تم الإنشاء سابقًا', json.download_url);
        notifyUser('info', 'الملف منشأ مسبقًا — تم إظهار زر التنزيل.');
        return;
      }

      if (res.status === 409 && json.status === 'failed_locked') {
        this.closeModal();
        notifyUser('error', 'توجد عملية سابقة تحتاج إلى مراجعة قبل إعادة الإرسال.');
        this.applyState('failed', json.message || 'فشل سابق');
        return;
      }

      notifyUser('error', json.message || 'تعذّر تنفيذ الطلب. حاول لاحقاً.');
    } catch (err) {
      console.error('[ai-tools] request error', err);
      notifyUser('error', 'حدث خطأ أثناء الاتصال بالخادم.');
    } finally {
      this.submitBtn.disabled = false;
      this.submitBtn.textContent = this.placement.form?.submit_label || 'إرسال الطلب';
      // إزالة class لمنع التفاعل
      document.body.classList.remove('ai-tool-processing');
    }
  }

  getStudentIdentifier() {
    // 1. محاولة الحصول عليه من مدخلات النموذج إذا كانت معبأة
    if (this.controllers) {
      const studentId = this.controllers.student_id?.getValue();
      if (studentId) return studentId;
      const studentNumber = this.controllers.student_number?.getValue();
      if (studentNumber) return studentNumber;
    }
    // 2. محاولة الحصول عليه من رابط الصفحة النشط
    const urlParams = new URLSearchParams(window.location.search || window.location.hash.split('?')[1] || '');
    const urlStudentId = urlParams.get('student_id') || urlParams.get('student_number') || urlParams.get('user_id') || urlParams.get('uid') || urlParams.get('student');
    if (urlStudentId) return urlStudentId;

    return null;
  }

  async refreshStatus(opts = {}) {
    if (!this.context.taskId) return;
    const studentId = this.getStudentIdentifier();
    const qs = new URLSearchParams({
      type_key: this.typeKey,
      task_id: this.context.taskId,
    });
    if (studentId) {
      qs.set('student_id', studentId);
    }
    try {
      const res = await fetch(`${API_BASE}/status?${qs.toString()}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });

      if (res.status === 402) {
        const json = await res.json().catch(() => ({}));
        this.applyState('locked', json?.message || 'الميزة تتطلب اشتراكاً مميزاً');
        this.openBtn.disabled = true;
        return;
      }

      const json = await res.json().catch(() => ({}));
      if (!json?.ok) {
        if (!opts.silent) notifyUser('error', json?.message || 'تعذّر التحقق من الحالة.');
        this.applyState('idle');
        return;
      }

      // جلب قائمة jobs
      await this.loadJobsList();

      // تحديث currentJobId ليتناسب مع الطالب الحالي إذا كان موجوداً في القائمة
      if (studentId && this.jobsList.length > 0) {
        const matchedJob = this.jobsList.find(job => {
          if (this.typeKey === 'inquiry_report_evaluator_v1') {
            return String(job.student_id) === String(studentId);
          } else {
            return String(job.student_number) === String(studentId);
          }
        });
        if (matchedJob) {
          this.currentJobId = matchedJob.id;
        } else {
          this.currentJobId = null;
        }
      } else if (this.jobsList.length > 0) {
        const latestJob = this.jobsList[0];
        if (!this.currentJobId || this.currentJobId !== latestJob.id) {
          this.currentJobId = latestJob.id;
        }
      }

      // بناء واجهة القائمة
      this.buildJobsListUI();

      // تطبيق حالة job الحالي
      const currentJob = this.jobsList.find(j => j.id === this.currentJobId);
      if (currentJob) {
        // إذا كان job مكتمل أو في حالة مراجعة، استخدم switchToJob
        if (currentJob.status === 'done' || currentJob.status === 'review') {
          this.switchToJob(this.currentJobId);
        } else {
          // إذا كان job موجود لكن لم يكتمل بعد
          this.applyState(
            currentJob.status === 'queued' ? 'pending' : currentJob.status,
            json.message || '',
            currentJob.download_url || null,
            this.currentJobId
          );
        }
      } else {
        this.applyState(json.state || 'idle', json.message || '', json.download_url || null, null, json.files || null);
      }

      // تحديث badges بناءً على معلومات النقاط
      if (json.points_cost !== undefined || json.is_free_usage !== undefined) {
        this.updateBadges(json.is_free_usage === false, json.points_cost);
      }

      // لأداة المنتدى: التحقق من إمكانية إظهار زر رد التعليقات بعد تحديث الحالة
      if (this.typeKey === 'forum_reply_v1' && json.state === 'done' && this.commentReplyBtn) {
        // تأخير بسيط للتأكد من أن applyState تم تنفيذه
        setTimeout(() => this.checkAndShowCommentReplyButton(), 500);
      }

      // لأداة التأمل/المنتدى: التحقق من حالة ردود التعليقات وإظهارها في الـ badge
      if ((this.typeKey === 'forum_reply_v1' || this.typeKey === 'reflection_v1' || this.typeKey === 'weekly_reflection_v1') && json.state === 'done') {
        this.updateCommentReplyStatus();
      }

      // auto-refresh للحالات النشطة: إذا كانت الحالة processing أو pending، جدول تحديث تلقائي
      const finalState = currentJob ? (currentJob.status === 'queued' ? 'pending' : currentJob.status) : (json.state || 'idle');
      if (finalState === 'processing' || finalState === 'pending') {
        // إذا لم يكن هناك timer نشط، أضف واحداً
        if (!this.timer) {
          this.timer = setTimeout(() => {
            this.refreshStatus({ silent: true });
          }, 15000);
        }
      }
    } catch (err) {
      console.warn('[ai-tools] status error', err);
      if (!opts.silent) notifyUser('error', 'تعذّر التحقق من حالة الأداة.');
    }
  }

  applyState(state, message = '', downloadUrl = null, jobId = null, files = null) {
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }

    this.state = state;
    if (jobId) {
      this.currentJobId = jobId;
    }
    const badge = this.statusBadge;
    if (badge) {
      // إزالة جميع فئات الحالة السابقة
      badge.classList.remove('processing', 'pending', 'done', 'failed');

      if (state === 'idle' || state === 'none') {
        badge.classList.remove('show');
        badge.textContent = '';
        badge.innerHTML = '';
      } else {
        badge.classList.add('show', state);
        const labels = this.placement.panel?.status_labels || {};
        const label = labels[state] || message || state;

        // إضافة أنيميشن للحالات النشطة
        if (state === 'processing' || state === 'pending') {
          badge.innerHTML = `<span class="status-spinner"></span> <span>${label}</span>`;
        } else if (state === 'done') {
          badge.innerHTML = `<i class="fa-solid fa-check-circle" style="font-size: 0.875rem;"></i> <span>${label}</span>`;
        } else if (state === 'failed') {
          badge.innerHTML = `<i class="fa-solid fa-xmark-circle" style="font-size: 0.875rem;"></i> <span>${label}</span>`;
        } else {
          badge.textContent = label;
        }
      }
    }

    this.downloadBtn.style.display = 'none';
    if (this.downloadsContainer) {
      this.downloadsContainer.style.display = 'none';
      this.downloadsContainer.innerHTML = '';
    }
    this.resultBtn.style.display = 'none';
    if (this.deleteBtn) {
      this.deleteBtn.style.display = 'none';
    }
    this.retryBtn.style.display = 'none';
    this.rebuildFileBtn.style.display = 'none';

    // توحيد معالجة الأزرار لجميع الأدوات
    switch (state) {
      case 'done':
        // السماح بإنشاء jobs جديدة - لا نعطل الزر
        this.openBtn.disabled = false;
        // إزالة class لمنع التفاعل
        document.body.classList.remove('ai-tool-processing');

        // منطق إظهار الأزرار حسب نوع النتيجة:
        // - لأداة مقيم المقالات → كلا الزرين معاً (عرض + تنزيل)
        // - للأدوات الأخرى:
        //   * إذا كان هناك ملف (downloadUrl) → فقط زر التنزيل
        //   * إذا لم يكن هناك ملف → فقط زر العرض

        if (this.typeKey === 'article_evaluator_v1' && downloadUrl) {
          // أداة مقيم المقالات: كلا الزرين معاً
          this.downloadBtn.href = downloadUrl;
          this.downloadBtn.style.display = 'inline-flex';
          this.resultBtn.style.display = 'inline-flex'; // إظهار زر العرض أيضاً
          // إظهار زر الحذف للأدمن
          if (window.__IS_ADMIN_VIEWING_AS_STUDENT__ === true && this.deleteBtn) {
            this.deleteBtn.style.display = 'inline-flex';
          }
        } else if (files && Array.isArray(files) && files.length > 0) {
          // أدوات ترجع عدة ملفات (مثل test_design_v1)
          this.downloadsContainer.style.display = 'flex';
          this.resultBtn.style.display = 'none';
          
          files.forEach(file => {
            const btn = document.createElement('a');
            btn.className = 'btn btn-secondary';
            btn.href = file.url;
            btn.target = '_blank';
            btn.rel = 'noopener';
            btn.innerHTML = `<i class="fa-solid fa-download"></i> ${file.name}`;
            this.downloadsContainer.appendChild(btn);
          });

          // إظهار زر الحذف للأدمن
          if (window.__IS_ADMIN_VIEWING_AS_STUDENT__ === true && this.deleteBtn) {
            this.deleteBtn.style.display = 'inline-flex';
          }
        } else if (downloadUrl) {
          // الأدوات الأخرى: يوجد ملف → فقط زر التنزيل
          this.downloadBtn.href = downloadUrl;
          this.downloadBtn.style.display = 'inline-flex';
          this.resultBtn.style.display = 'none';
          // إظهار زر الحذف للأدمن في وضع الاستعراض بجانب زر التنزيل
          if (window.__IS_ADMIN_VIEWING_AS_STUDENT__ === true && this.deleteBtn) {
            this.deleteBtn.style.display = 'inline-flex';
          }
        } else {
          // لا يوجد ملف → فقط زر العرض
          this.downloadBtn.style.display = 'none';
          this.resultBtn.style.display = 'inline-flex';
          // إظهار زر الحذف للأدمن في وضع الاستعراض بجانب زر النتيجة
          if (window.__IS_ADMIN_VIEWING_AS_STUDENT__ === true && this.deleteBtn) {
            this.deleteBtn.style.display = 'inline-flex';
          }
        }

        // إظهار زر إعادة التوليد إذا كانت المهمة تمتلك ID
        if (this.currentJobId) {
          // زر إعادة التوليد بالذكاء الاصطناعي للأدمن فقط
          if (window.__IS_ADMIN_VIEWING_AS_STUDENT__ === true) {
            this.retryBtn.style.display = 'inline-flex';
            this.retryBtn.className = 'btn btn-secondary ai-tool-retry-btn';
            this.retryBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> إعادة التوليد (AI)';
          }
          // زر إعادة بناء الملف للجميع
          this.rebuildFileBtn.style.display = 'inline-flex';
        }


        // لأداة المنتدى: إظهار زر "كتابة رد على إجابة أحد الزملاء" بعد إنشاء الرد الأول
        if (this.typeKey === 'forum_reply_v1' && this.commentReplyBtn) {
          this.checkAndShowCommentReplyButton();
        }
        break;

      case 'review': {
        // ✅ حالة المراجعة: إظهار زر مراجعة التقييم
        this.openBtn.disabled = false;
        document.body.classList.remove('ai-tool-processing');
        
        // إظهار زر المراجعة
        this.resultBtn.style.display = 'inline-flex';
        this.resultBtn.textContent = '📋 مراجعة التقييم';
        this.resultBtn.className = 'btn btn-primary ai-tool-result-btn';
        
        // ربط حدث الضغط بفتح مودال المراجعة
        const oldBtn = this.resultBtn;
        const newBtn = oldBtn.cloneNode(true);
        oldBtn.parentNode.replaceChild(newBtn, oldBtn);
        this.resultBtn = newBtn;
        this.resultBtn.addEventListener('click', () => {
          console.log('[ai-tools] Review button clicked, jobId:', this.currentJobId);
          this.showReviewModal();
        });
        
        if (badge) {
          badge.classList.add('show', 'review');
          badge.innerHTML = '<i class="fa-solid fa-clipboard-check" style="font-size:0.875rem;"></i> <span>بانتظار المراجعة</span>';
        }
        break;
      }
      case 'processing':
      case 'pending':
        this.openBtn.disabled = true;
        // إضافة class لمنع التفاعل مع td-card
        document.body.classList.add('ai-tool-processing');
        // auto-refresh كل 15 ثانية للحالات النشطة (بدلاً من 30)
        if (this.timer) {
          clearTimeout(this.timer);
        }
        this.timer = setTimeout(() => {
          this.refreshStatus({ silent: true });
        }, 15000);
        break;
      case 'failed':
        this.openBtn.disabled = false;
        // إزالة class لمنع التفاعل
        document.body.classList.remove('ai-tool-processing');
        if (this.currentJobId) {
          this.retryBtn.style.display = 'inline-flex';
          // تلوين الزر ليكون واضحاً بأنه لمحاولة إصلاح الفشل
          this.retryBtn.className = 'btn btn-primary ai-tool-retry-btn';
          this.retryBtn.innerHTML = '<span style="color:#f59e0b; margin-left:5px;"><i class="fa-solid fa-triangle-exclamation"></i></span> إعادة المحاولة';
        }

        break;
      case 'locked':
        this.openBtn.disabled = true;
        // إزالة class لمنع التفاعل
        document.body.classList.remove('ai-tool-processing');
        if (badge) {
          badge.classList.add('show');
          badge.textContent = message;
        }
        break;
      default:
        this.openBtn.disabled = false;
        // إزالة class لمنع التفاعل
        document.body.classList.remove('ai-tool-processing');
    }
  }

  async rebuildJob() {
    if (!this.currentJobId) return;
    if (this.retryBtn.disabled) return;
    
    this.retryBtn.disabled = true;
    const oldHtml = this.retryBtn.innerHTML;
    this.retryBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الإرسال…';

    try {
      const res = await fetch('/api/ai-jobs/rebuild', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ job_id: this.currentJobId }),
      });
      const data = await res.json();

      if (data.ok) {
        this.retryBtn.innerHTML = '<i class="fas fa-check"></i> تم الإرسال';
        setTimeout(() => {
          this.applyState('pending', 'جارٍ إعادة التوليد…');
        }, 1000);
      } else {
        notifyUser('error', data.message || 'حدث خطأ أثناء محاولة الإعادة.');
        this.retryBtn.disabled = false;
        this.retryBtn.innerHTML = oldHtml;
      }
    } catch (err) {
      console.error('[AiTools] rebuild error:', err);
      notifyUser('error', 'حدث خطأ في الاتصال بالخادم.');
      this.retryBtn.disabled = false;
      this.retryBtn.innerHTML = oldHtml;
    }
  }

  async rebuildDocxJob() {
    if (!this.currentJobId) return;
    
    // استخدام SweetAlert2 بدلاً من confirm العادي
    if (typeof Swal !== 'undefined') {
      const result = await Swal.fire({
        title: 'إعادة بناء الملف',
        text: 'هل أنت متأكد من إعادة بناء الملف؟ هذه العملية لن تستهلك من رصيدك وستعالج مشاكل تلف الملف.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'نعم، أعد البناء',
        cancelButtonText: 'إلغاء'
      });
      if (!result.isConfirmed) return;
    } else {
      if (!confirm('هل أنت متأكد من إعادة بناء الملف؟ هذه العملية لن تستهلك من رصيدك وستعالج مشاكل تلف الملف.')) {
        return;
      }
    }

    try {
      this.rebuildFileBtn.disabled = true;
      this.rebuildFileBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جارٍ البناء...';
      
      const response = await fetch('/api/ai-jobs/rebuild-file', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ job_id: this.currentJobId })
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw new Error(data.message || 'حدث خطأ أثناء إعادة بناء الملف');
      }

      // إعادة الزر لشكله الطبيعي
      this.rebuildFileBtn.disabled = false;
      this.rebuildFileBtn.innerHTML = '<i class="fa-solid fa-file-word"></i> إعادة بناء الملف';

      // استخدام SweetAlert2 بدلاً من alert
      if (typeof Swal !== 'undefined') {
        Swal.fire('نجاح', 'تم إعادة بناء الملف بنجاح! يمكنك الآن تنزيله.', 'success');
      } else {
        alert('تم إعادة بناء الملف بنجاح! يمكنك الآن تنزيله.');
      }
      
      this.refreshStatus();
    } catch (error) {
            this.rebuildFileBtn.disabled = false;
      this.rebuildFileBtn.innerHTML = '<i class="fa-solid fa-file-word"></i> إعادة بناء الملف';
    }
  }

  _renderRubricSection(container, rubricGrades) {
    if (!rubricGrades || rubricGrades.length === 0) return;
    container.innerHTML = `
      <h4 style="margin-bottom:15px; color:#1e293b; font-size:1.1rem;"><i class="fa-solid fa-list-check" style="margin-right:8px; color:#2563eb;"></i> مراجعة علامات المعايير</h4>
      <div style="overflow-x:auto; background:#fff; border-radius:8px; border:1px solid #e2e8f0;">
        <table style="width:100%; border-collapse:collapse; text-align:right;">
          <thead style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
            <tr>
              <th style="padding:12px; font-weight:600; color:#475569; width:40%;">المعيار</th>
              <th style="padding:12px; font-weight:600; color:#475569; width:20%;">العلامة</th>
              <th style="padding:12px; font-weight:600; color:#475569; width:40%;">المبرر</th>
            </tr>
          </thead>
          <tbody id="review-rubric-tbody"></tbody>
        </table>
      </div>
      <div style="margin-top:15px; text-align:left;">
        <button id="btn-regenerate-feedback" class="btn btn-sm btn-outline-primary">
          <i class="fa-solid fa-rotate-right"></i> إعادة توليد التغذية الراجعة بناءً على العلامات المعدّلة
        </button>
      </div>
    `;

    const tbody = container.querySelector('#review-rubric-tbody');
    const validGrades = ['D', 'M', 'GP', 'P', 'F'];
    const gradeLabels = {
      'D': 'ناجح بتميّز',
      'M': 'ناجح بتفوّق',
      'GP': 'ناجح بتقدير جيّد',
      'P': 'ناجح',
      'F': 'مُخفق'
    };

    rubricGrades.forEach(g => {
      const tr = document.createElement('tr');
      tr.style.borderBottom = '1px solid #f1f5f9';
      
      let optionsHtml = '';
      validGrades.forEach(v => {
        const sel = (v === g.grade_symbol) ? 'selected' : '';
        optionsHtml += `<option value="${v}" ${sel}>${gradeLabels[v]} (${v})</option>`;
      });

      tr.innerHTML = `
        <td style="padding:10px 12px; border:1px solid #e2e8f0; font-weight:500; color:#0f172a;">${g.criterion_name}</td>
        <td style="padding:10px 12px; border:1px solid #e2e8f0;">
          <select class="form-select form-select-sm grade-select" data-id="${g.criterion_id}" data-name="${g.criterion_name}">
            ${optionsHtml}
          </select>
        </td>
        <td style="padding:10px 12px; border:1px solid #e2e8f0; vertical-align:top;">
          <div style="font-size:0.85rem; color:#475569; line-height:1.6;" class="justification-text">${g.justification || '—'}</div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    const regenBtn = container.querySelector('#btn-regenerate-feedback');
    regenBtn.addEventListener('click', async () => {
      regenBtn.disabled = true;
      regenBtn.innerHTML = '<span class="status-spinner"></span> جاري التوليد...';
      
      const jobId = container.closest('.ai-job-item-card')?.dataset?.id;
      if (!jobId) {
        notifyUser('error', 'رقم المهمة غير معروف');
        regenBtn.disabled = false;
        return;
      }

      const modifiedGrades = this._collectReviewGrades(rubricGrades);
      try {
        const res = await fetch(`${API_BASE}/regenerate-feedback`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ job_id: jobId, rubric_grades: modifiedGrades }),
        });
        const data = await res.json();
        if (data.ok && data.feedback_report) {
          const feedbackSection = document.getElementById('review-feedback-section');
          this._renderFeedbackSection(feedbackSection, data.feedback_report);
          
          if (data.rubric_grades) {
            const tb = document.getElementById('review-rubric-tbody');
            if (tb) {
              data.rubric_grades.forEach(mg => {
                const sel = tb.querySelector(`select[data-id="${mg.criterion_id}"]`);
                if (sel) {
                  const justDiv = sel.closest('tr').querySelector('.justification-text');
                  if (justDiv) justDiv.textContent = mg.justification;
                }
              });
            }
          }
          
          notifyUser('success', 'تم إعادة توليد التغذية الراجعة والمبررات بنجاح');
        } else {
          notifyUser('error', data.message || 'فشل إعادة التوليد');
        }
      } catch (err) {
        notifyUser('error', 'حدث خطأ أثناء الاتصال');
      }
      regenBtn.disabled = false;
      regenBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> إعادة توليد التغذية الراجعة بناءً على العلامات المعدّلة';
    });

  }

  /**
   * ✅ مودال مراجعة التقييم — inquiry_report_evaluator_v1
   */
  getStudentIdentifierForJob(jobId) {
    if (!jobId) return null;
    const job = this.jobsList.find(j => j.id === jobId);
    if (job) {
      return job.student_id || job.student_number || null;
    }
    return null;
  }

  async showReviewModal() {
    console.log('[ai-tools] showReviewModal called, currentJobId:', this.currentJobId, 'typeKey:', this.typeKey);
    
    if (!this.currentJobId) {
      console.warn('[ai-tools] showReviewModal: no currentJobId');
      notifyUser('info', 'لا يوجد تقييم لمراجعته.');
      return;
    }

    // جلب بيانات التقييم من API
    const studentId = this.getStudentIdentifierForJob(this.currentJobId) || this.getStudentIdentifier();
    const qs = new URLSearchParams({ 
      type_key: this.typeKey, 
      task_id: this.context.taskId,
      job_id: this.currentJobId 
    });
    if (studentId) {
      qs.set('student_id', studentId);
    }
    try {
      const url = `${API_BASE}/status?${qs.toString()}`;
      console.log('[ai-tools] Fetching review data from:', url);
      
      const res = await fetch(url, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json().catch(() => ({}));
      console.log('[ai-tools] Status API response:', JSON.stringify(json).substring(0, 500));
      
      if (!json?.ok) {
        notifyUser('error', 'فشل في جلب بيانات التقييم: ' + (json?.message || 'خطأ غير محدد'));
        return;
      }
      
      if (json.state !== 'review') {
        console.warn('[ai-tools] State is not review:', json.state);
        notifyUser('info', 'التقييم ليس في حالة مراجعة (الحالة: ' + json.state + ')');
        return;
      }
      
      if (!json.evaluation_data) {
        console.warn('[ai-tools] No evaluation_data in response');
        notifyUser('info', 'لم يتم العثور على بيانات التقييم في الرد.');
        return;
      }
      
      const jobId = json.job_id || this.currentJobId;
      console.log('[ai-tools] Rendering review modal for job:', jobId);
      this._renderReviewModal(json.evaluation_data, jobId);
    } catch (err) {
      console.error('[ai-tools] review modal error', err);
      notifyUser('error', 'تعذّر تحميل بيانات التقييم: ' + err.message);
    }
  }

  _renderReviewModal(evalData, jobId) {
    ensureStyles();
    const gradeOptions = [
      { symbol: 'D', word: 'ناجح بتميّز' },
      { symbol: 'M', word: 'ناجح بتفوّق' },
      { symbol: 'GP', word: 'ناجح بتقدير جيّد' },
      { symbol: 'P', word: 'ناجح' },
      { symbol: 'F', word: 'راسب' },
    ];

    const root = document.getElementById('ai-tools-modals-root') || document.body;
    const backdrop = document.createElement('div');
    backdrop.className = 'ai-tool-modal-backdrop show';
    backdrop.setAttribute('aria-hidden', 'false');

    const modal = document.createElement('div');
    modal.className = 'ai-tool-modal';
    modal.style.maxWidth = '900px';

    // === Header ===
    const header = document.createElement('header');
    header.innerHTML = `
      <div class="ai-modal-title"><i class="fa-solid fa-clipboard-check"></i> مراجعة التقييم واعتماد النتائج</div>
      <button type="button" class="ai-modal-close" id="review-close-btn">إغلاق</button>
    `;

    // === Content ===
    const content = document.createElement('div');
    content.className = 'ai-modal-content';
    content.style.padding = '20px';

    // ── بيانات الطالب ──
    const si = evalData.student_info || {};
    content.innerHTML = `
      <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:14px 18px; margin-bottom:20px;">
        <strong>الطالب:</strong> ${si.student_name || '—'} | <strong>الرقم:</strong> ${si.student_id || '—'}
      </div>
    `;

    // ── التقدير الكلي ──
    const ov = evalData.overall_evaluation || {};
    const overallDiv = document.createElement('div');
    overallDiv.style.cssText = 'margin-bottom:20px; display:flex; align-items:center; gap:12px;';
    overallDiv.innerHTML = `
      <strong style="font-size:1rem;">التقدير الكلي:</strong>
      <select id="review-overall-grade" style="padding:8px 14px; border:1px solid #d1d5db; border-radius:8px; font-family:inherit; font-size:0.95rem;">
        ${gradeOptions.map(g => `<option value="${g.symbol}" ${g.symbol === ov.grade_symbol ? 'selected' : ''}>${g.symbol} — ${g.word}</option>`).join('')}
      </select>
    `;
    content.appendChild(overallDiv);

    // ── جدول المعايير ──
    const tableWrap = document.createElement('div');
    tableWrap.style.cssText = 'overflow-x:auto; margin-bottom:24px;';
    const table = document.createElement('table');
    table.style.cssText = 'width:100%; border-collapse:collapse; font-size:0.9rem;';
    table.innerHTML = `
      <thead>
        <tr style="background:#1e293b; color:#fff;">
          <th style="padding:10px 12px; text-align:right; border:1px solid #334155; width:30%;">المعيار</th>
          <th style="padding:10px 12px; text-align:center; border:1px solid #334155; width:18%;">التقدير</th>
          <th style="padding:10px 12px; text-align:right; border:1px solid #334155; width:52%;">المبرر</th>
        </tr>
      </thead>
      <tbody id="review-rubric-tbody"></tbody>
    `;
    tableWrap.appendChild(table);
    content.appendChild(tableWrap);

    const tbody = table.querySelector('#review-rubric-tbody');
    const rubricGrades = evalData.rubric_grades || [];
    rubricGrades.forEach((g, idx) => {
      const tr = document.createElement('tr');
      tr.style.cssText = idx % 2 === 0 ? 'background:#f8fafc;' : 'background:#fff;';
      tr.innerHTML = `
        <td style="padding:10px 12px; border:1px solid #e2e8f0; font-weight:500; vertical-align:top;">${g.criterion_name}</td>
        <td style="padding:10px 12px; border:1px solid #e2e8f0; text-align:center; vertical-align:top;">
          <select class="review-grade-select" data-idx="${idx}" style="padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-family:inherit; font-size:0.85rem; width:100%;">
            ${gradeOptions.map(opt => `<option value="${opt.symbol}" ${opt.symbol === g.grade_symbol ? 'selected' : ''}>${opt.symbol} — ${opt.word}</option>`).join('')}
          </select>
        </td>
        <td style="padding:10px 12px; border:1px solid #e2e8f0; vertical-align:top;">
          <div style="font-size:0.85rem; color:#475569; line-height:1.6;">${g.justification || '—'}</div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    // ── زر إعادة التوليد ──
    const regenBtn = document.createElement('button');
    regenBtn.type = 'button';
    regenBtn.className = 'btn btn-secondary';
    regenBtn.style.cssText = 'margin-bottom:20px;';
    regenBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> إعادة توليد التغذية الراجعة بناءً على العلامات المعدّلة';
    content.appendChild(regenBtn);

    // ── التغذية الراجعة ──
    const fb = evalData.feedback_report || {};
    const feedbackDiv = document.createElement('div');
    feedbackDiv.id = 'review-feedback-section';
    feedbackDiv.style.cssText = 'background:#fefce8; border:1px solid #fde68a; border-radius:10px; padding:18px; margin-bottom:20px;';
    this._renderFeedbackSection(feedbackDiv, fb);
    content.appendChild(feedbackDiv);

    // ── زر الاعتماد ──
    const approveBtn = document.createElement('button');
    approveBtn.type = 'button';
    approveBtn.className = 'btn btn-primary';
    approveBtn.style.cssText = 'width:100%; padding:14px; font-size:1.05rem; border-radius:10px; font-weight:600;';
    approveBtn.innerHTML = '<i class="fa-solid fa-check-double"></i> اعتماد التقييم وتوليد الملفات';
    content.appendChild(approveBtn);

    modal.appendChild(header);
    modal.appendChild(content);
    backdrop.appendChild(modal);
    root.appendChild(backdrop);

    // === Events ===
    header.querySelector('#review-close-btn').addEventListener('click', () => {
      backdrop.classList.remove('show');
      setTimeout(() => backdrop.remove(), 200);
    });

    // إعادة التوليد
    regenBtn.addEventListener('click', async () => {
      regenBtn.disabled = true;
      regenBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جارٍ إعادة التوليد...';

      const modifiedGrades = this._collectReviewGrades(rubricGrades);
      try {
        const res = await fetch(`${API_BASE}/regenerate-feedback`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ job_id: jobId, rubric_grades: modifiedGrades }),
        });
        const data = await res.json();
        if (data.ok && data.feedback_report) {
          const feedbackSection = document.getElementById('review-feedback-section');
          this._renderFeedbackSection(feedbackSection, data.feedback_report);
          notifyUser('success', 'تم إعادة توليد التغذية الراجعة بنجاح');
        } else {
          notifyUser('error', data.message || 'فشل إعادة التوليد');
        }
      } catch (err) {
        notifyUser('error', 'حدث خطأ أثناء الاتصال');
      }
      regenBtn.disabled = false;
      regenBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> إعادة توليد التغذية الراجعة بناءً على العلامات المعدّلة';
    });

    // الاعتماد
    approveBtn.addEventListener('click', async () => {
      approveBtn.disabled = true;
      approveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جارٍ بناء الملفات...';

      const modifiedGrades = this._collectReviewGrades(rubricGrades);
      const overallSelect = document.getElementById('review-overall-grade');
      const overallSymbol = overallSelect.value;
      const overallWord = gradeOptions.find(g => g.symbol === overallSymbol)?.word || '';

      // جمع التغذية الراجعة المعدّلة
      const feedbackSection = document.getElementById('review-feedback-section');
      const strengthInputs = feedbackSection.querySelectorAll('.review-strength-input');
      const devInputs = feedbackSection.querySelectorAll('.review-dev-input');
      const notesInput = feedbackSection.querySelector('.review-notes-input');

      const feedbackReport = {
        strengths: Array.from(strengthInputs).map(i => i.value.trim()).filter(v => v),
        areas_for_development: Array.from(devInputs).map(i => i.value.trim()).filter(v => v),
        general_notes: notesInput ? notesInput.value.trim() : '',
      };

      try {
        const res = await fetch(`${API_BASE}/approve-evaluation`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            job_id: jobId,
            rubric_grades: modifiedGrades,
            feedback_report: feedbackReport,
            overall_evaluation: { grade_symbol: overallSymbol, grade_word: overallWord },
          }),
        });
        const data = await res.json();
        if (data.ok) {
          backdrop.classList.remove('show');
          setTimeout(() => backdrop.remove(), 200);
          notifyUser('success', 'تم اعتماد التقييم وبناء الملفات بنجاح!');
          this.refreshStatus();
        } else {
          notifyUser('error', data.message || 'فشل الاعتماد');
          approveBtn.disabled = false;
          approveBtn.innerHTML = '<i class="fa-solid fa-check-double"></i> اعتماد التقييم وتوليد الملفات';
        }
      } catch (err) {
        notifyUser('error', 'حدث خطأ أثناء الاتصال');
        approveBtn.disabled = false;
        approveBtn.innerHTML = '<i class="fa-solid fa-check-double"></i> اعتماد التقييم وتوليد الملفات';
      }
    });
  }

  _renderFeedbackSection(container, fb) {
    const strengths = fb.strengths || [];
    const devPoints = fb.areas_for_development || [];
    const notes = fb.general_notes || '';

    container.innerHTML = `
      <h4 style="margin:0 0 14px; color:#92400e; font-size:1rem;">📝 التغذية الراجعة (قابلة للتعديل)</h4>
      <div style="margin-bottom:16px;">
        <strong style="color:#166534;">نقاط القوة:</strong>
        <div class="review-strengths-list" style="margin-top:8px; display:flex; flex-direction:column; gap:6px;">
          ${strengths.map(s => `<textarea class="review-strength-input" rows="2" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-family:inherit; font-size:0.85rem; direction:rtl; resize:vertical; box-sizing:border-box;">${s}</textarea>`).join('')}
        </div>
      </div>
      <div style="margin-bottom:16px;">
        <strong style="color:#991b1b;">مجالات التطوير:</strong>
        <div class="review-dev-list" style="margin-top:8px; display:flex; flex-direction:column; gap:6px;">
          ${devPoints.map(d => `<textarea class="review-dev-input" rows="2" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-family:inherit; font-size:0.85rem; direction:rtl; resize:vertical; box-sizing:border-box;">${d}</textarea>`).join('')}
        </div>
      </div>
      <div>
        <strong style="color:#1e40af;">ملاحظات عامة:</strong>
        <textarea class="review-notes-input" rows="3" style="width:100%; margin-top:8px; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-family:inherit; font-size:0.85rem; direction:rtl; resize:vertical; box-sizing:border-box;">${notes}</textarea>
      </div>
    `;
  }

  _collectReviewGrades(originalGrades) {
    const selects = document.querySelectorAll('.review-grade-select');
    const gradeWords = { D: 'ناجح بتميّز', M: 'ناجح بتفوّق', GP: 'ناجح بتقدير جيّد', P: 'ناجح', F: 'راسب' };
    return originalGrades.map((g, idx) => {
      const select = selects[idx];
      const symbol = select ? select.value : g.grade_symbol;
      return {
        ...g,
        grade_symbol: symbol,
        grade_word: gradeWords[symbol] || g.grade_word,
      };
    });
  }


  async showResult() {
    const studentId = this.getStudentIdentifierForJob(this.currentJobId) || this.getStudentIdentifier();
    const qs = new URLSearchParams({
      type_key: this.typeKey,
      task_id: this.context.taskId,
    });
    if (studentId) {
      qs.set('student_id', studentId);
    }
    if (this.currentJobId) {
      qs.set('job_id', this.currentJobId);
    }
    try {
      // جلب النتيجة الرئيسية
      const res = await fetch(`${API_BASE}/result?${qs.toString()}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json().catch(() => ({}));
      if (!json?.ok || json.state !== 'done') {
        notifyUser('info', json?.message || 'لم تكتمل النتيجة بعد.');
        return;
      }
      let data = json.result || {};
      // إذا كانت النتيجة نصاً (JSON string) — حوّلها لـ object
      if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch { /* keep as-is */ }
      }

      // لأداة المنتدى: جلب ردود التعليقات أيضاً
      let comments = [];
      if (this.typeKey === 'forum_reply_v1') {
        try {
          const commentsRes = await fetch(`${API_BASE}/comments?task_id=${this.context.taskId}`, {
            credentials: 'include',
            headers: { 'Accept': 'application/json' },
          });
          const commentsJson = await commentsRes.json().catch(() => ({}));
          if (commentsJson?.ok && Array.isArray(commentsJson.comments)) {
            comments = commentsJson.comments;
          }
        } catch (err) {
          console.warn('[ai-tools] Failed to fetch comments:', err);
        }
      }

      this.renderResultModal(data, comments);
    } catch (err) {
      console.error('[ai-tools] result error', err);
      notifyUser('error', 'تعذّر عرض النتيجة حالياً.');
    }
  }

  renderResultModal(result, comments = []) {
    ensureStyles();

    // معالجة خاصة لأداة مقيم المقالات
    if (this.typeKey === 'article_evaluator_v1') {
      this.renderArticleEvaluatorResult(result);
      return;
    }
    
    // معالجة خاصة لتقييم المخطط الاستقصائي
    const cleanTypeKey = (this.typeKey || '').trim();
    if (cleanTypeKey === 'inquiry_plan_evaluator_v1' || cleanTypeKey === 'inquiry_plan_evaluator') {
      this.renderInquiryPlanEvaluatorResult(result);
      return;
    }

    // معالجة خاصة لتقييم تقرير البحث الاستقصائي
    if (cleanTypeKey === 'inquiry_report_evaluator_v1') {
      this.renderInquiryReportEvaluatorResult(result);
      return;
    }

    const view = this.placement.result_view || {};
    const root = document.getElementById('ai-tools-modals-root') || document.body;
    const backdrop = document.createElement('div');
    backdrop.className = 'ai-tool-modal-backdrop show';
    backdrop.setAttribute('aria-hidden', 'false');

    const modal = document.createElement('div');
    modal.className = 'ai-tool-modal';

    const header = document.createElement('header');
    const title = document.createElement('div');
    title.className = 'ai-modal-title';
    title.innerHTML = `<i class="fa-solid fa-file-lines"></i> ${view.title || 'نتيجة الأداة'}`;

    const headerActions = document.createElement('div');
    headerActions.style.display = 'flex';
    headerActions.style.gap = '10px';
    headerActions.style.alignItems = 'center';

    // أزرار النسخ المنفصلة (لأداة المنتدى)
    if (this.typeKey === 'forum_reply_v1' && result.title && result.content) {
      const copyTitleBtn = document.createElement('button');
      copyTitleBtn.type = 'button';
      copyTitleBtn.className = 'btn btn-secondary ai-copy-btn';
      copyTitleBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ العنوان';
      copyTitleBtn.addEventListener('click', () => this.copyToClipboard(result.title || ''));
      headerActions.appendChild(copyTitleBtn);

      const copyContentBtn = document.createElement('button');
      copyContentBtn.type = 'button';
      copyContentBtn.className = 'btn btn-secondary ai-copy-btn';
      copyContentBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ النص';
      copyContentBtn.addEventListener('click', () => this.copyToClipboard(result.content || ''));
      headerActions.appendChild(copyContentBtn);
    } else if (this.typeKey === 'weekly_reflection_v1' && result.content) {
      // زر النسخ للتأمل الأسبوعي (يشمل المحتوى والمراجع)
      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.className = 'btn btn-secondary ai-copy-btn';
      copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ التأمل والمراجع';
      copyBtn.addEventListener('click', () => {
        let textToCopy = result.content || '';
        if (result.references && Array.isArray(result.references) && result.references.length > 0) {
          textToCopy += '\n\nالمراجع:\n';
          result.references.forEach((ref, index) => {
            textToCopy += `${index + 1}. ${ref}\n`;
          });
        }
        this.copyToClipboard(textToCopy);
      });
      headerActions.appendChild(copyBtn);
    } else {
      // زر النسخ العام
      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.className = 'btn btn-secondary ai-copy-btn';
      copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ الرد';
      copyBtn.addEventListener('click', () => this.copyResultToClipboard(result, view));
      headerActions.appendChild(copyBtn);
    }


    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ai-modal-close';
    closeBtn.textContent = 'إغلاق';
    closeBtn.addEventListener('click', () => {
      backdrop.classList.remove('show');
      backdrop.setAttribute('aria-hidden', 'true');
      setTimeout(() => backdrop.remove(), 200);
    });
    headerActions.appendChild(closeBtn);

    header.appendChild(title);
    header.appendChild(headerActions);

    const content = document.createElement('div');
    content.className = 'ai-modal-content';
    const body = document.createElement('div');
    body.className = 'ai-result-content';

    // لأداة التأمل الأسبوعي: عرض النص مباشرة بدون عناوين + قائمة المراجع
    if (this.typeKey === 'weekly_reflection_v1') {
      const content = result.content || result.description || result.text || '';
      if (content) {
        // عرض المحتوى
        const block = document.createElement('div');
        block.className = 'ai-result-field';
        block.style.marginBottom = '30px';
        const paragraph = document.createElement('div');
        paragraph.style.fontSize = '1rem';
        paragraph.style.lineHeight = '1.8';
        paragraph.style.color = '#1f2933';
        paragraph.style.whiteSpace = 'pre-wrap';
        paragraph.innerHTML = String(content).replace(/\n/g, '<br>');
        block.appendChild(paragraph);
        body.appendChild(block);

        // عرض قائمة المراجع إذا وُجدت
        const references = result.references;
        if (references && Array.isArray(references) && references.length > 0) {
          const refSection = document.createElement('div');
          refSection.className = 'ai-result-field';
          refSection.style.marginTop = '40px';
          refSection.style.paddingTop = '30px';
          refSection.style.borderTop = '2px solid #e1e5e9';

          const refTitle = document.createElement('h5');
          refTitle.textContent = 'المراجع';
          refTitle.style.marginBottom = '15px';
          refTitle.style.color = '#0a3247';
          refTitle.style.fontSize = '1.1rem';
          refSection.appendChild(refTitle);

          const refList = document.createElement('ol');
          refList.style.margin = '0';
          refList.style.paddingLeft = '25px';
          refList.style.listStyleType = 'decimal';
          refList.style.color = '#1f2933';
          refList.style.lineHeight = '1.8';

          references.forEach((ref, index) => {
            const refItem = document.createElement('li');
            refItem.style.marginBottom = '10px';
            refItem.textContent = String(ref);
            refList.appendChild(refItem);
          });

          refSection.appendChild(refList);
          body.appendChild(refSection);
        }
      } else {
        // إذا لم يكن هناك محتوى، عرض رسالة
        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'ai-result-field';
        emptyMsg.style.textAlign = 'center';
        emptyMsg.style.padding = '40px 20px';
        emptyMsg.style.color = '#666';
        emptyMsg.innerHTML = '<p>لم يتم العثور على محتوى التأمل.</p>';
        body.appendChild(emptyMsg);
      }
    } else {
      const fields = Array.isArray(view.fields) ? view.fields : Object.keys(result).map(key => ({ key, label: key }));
      // للأدوات الأخرى: العرض العادي مع عناوين
      fields.forEach(field => {
        if (!field.key) return;
        const value = result[field.key];

        // تخطي الحقول الفارغة أو غير الموجودة
        if (value === undefined || value === null || value === '') {
          return;
        }
        if (field.render === 'matrix' && Array.isArray(value) && value.length === 0) {
          return;
        }

        const block = document.createElement('div');
        block.className = 'ai-result-field';
        const label = document.createElement('h5');
        label.textContent = field.label || field.key;
        block.appendChild(label);

        if (field.render === 'matrix' && Array.isArray(value)) {
          const columns = Array.isArray(field.columns) && field.columns.length
            ? field.columns
            : Object.keys(value[0] || {}).map(key => ({ key, label: key }));
          const table = document.createElement('table');
          table.className = 'ai-matrix-table';
          const thead = document.createElement('thead');
          const headRow = document.createElement('tr');
          columns.forEach(col => {
            const th = document.createElement('th');
            th.textContent = col.label || col.key;
            headRow.appendChild(th);
          });
          thead.appendChild(headRow);
          table.appendChild(thead);

          const tbody = document.createElement('tbody');
          const formatMatrixCell = (cell) => {
            if (Array.isArray(cell)) {
              return cell.map(item => `<div>• ${textContent(item)}</div>`).join('') || '';
            }
            if (cell && typeof cell === 'object') {
              return Object.keys(cell).map(key => `<div>${textContent(cell[key])}</div>`).join('');
            }
            return textContent(cell);
          };

          value.forEach(row => {
            const tr = document.createElement('tr');
            columns.forEach(col => {
              const td = document.createElement('td');
              td.innerHTML = formatMatrixCell(row?.[col.key]);
              tr.appendChild(td);
            });
            tbody.appendChild(tr);
          });

          table.appendChild(tbody);
          block.appendChild(table);
          body.appendChild(block);
          return;
        }

        if (Array.isArray(value) && (field.render === 'chips' || typeof value[0] !== 'object')) {
          const tagWrap = document.createElement('div');
          tagWrap.className = 'ai-result-tags';
          value.forEach(item => {
            const chip = document.createElement('span');
            chip.className = 'ai-result-chip';
            chip.textContent = textContent(item);
            tagWrap.appendChild(chip);
          });
          block.appendChild(tagWrap);
        } else {
          const paragraph = document.createElement('p');
          paragraph.innerHTML = Array.isArray(value)
            ? value.map(v => `<div>${textContent(v)}</div>`).join('')
            : (field.render === 'richtext' ? String(value || '').replace(/\n/g, '<br>') : textContent(value));
          block.appendChild(paragraph);
        }
        body.appendChild(block);
      });
    }

    // لأداة المنتدى: عرض ردود التعليقات
    if (this.typeKey === 'forum_reply_v1' && comments.length > 0) {
      const commentsSection = document.createElement('div');
      commentsSection.className = 'ai-comments-section';
      commentsSection.style.marginTop = '30px';
      commentsSection.style.paddingTop = '30px';
      commentsSection.style.borderTop = '2px solid #e1e5e9';

      const commentsTitle = document.createElement('h4');
      commentsTitle.textContent = 'ردودك على تعليقات الزملاء';
      commentsTitle.style.marginBottom = '20px';
      commentsTitle.style.color = '#0a3247';
      commentsSection.appendChild(commentsTitle);

      comments.forEach((comment, index) => {
        const commentBlock = document.createElement('div');
        commentBlock.className = 'ai-comment-block';
        commentBlock.style.marginBottom = '25px';
        commentBlock.style.padding = '16px';
        commentBlock.style.border = '1px solid #e1e5e9';
        commentBlock.style.borderRadius = '12px';
        commentBlock.style.background = '#f8fafc';

        // التعليق الأصلي
        const originalComment = document.createElement('div');
        originalComment.className = 'ai-original-comment';
        originalComment.style.marginBottom = '12px';
        originalComment.style.padding = '12px';
        originalComment.style.background = '#fff';
        originalComment.style.borderRadius = '8px';
        originalComment.style.borderLeft = '3px solid #219ebc';
        const commentLabel = document.createElement('strong');
        commentLabel.textContent = 'تعليق الزميل:';
        commentLabel.style.display = 'block';
        commentLabel.style.marginBottom = '8px';
        commentLabel.style.color = '#219ebc';
        originalComment.appendChild(commentLabel);
        const commentText = document.createElement('p');
        commentText.textContent = comment.comment_text || '';
        commentText.style.margin = '0';
        commentText.style.color = '#1f2933';
        originalComment.appendChild(commentText);
        commentBlock.appendChild(originalComment);

        // الرد على التعليق
        if (comment.reply && comment.status === 'done') {
          const replyBlock = document.createElement('div');
          replyBlock.className = 'ai-comment-reply';
          replyBlock.style.padding = '12px';
          replyBlock.style.background = '#fff';
          replyBlock.style.borderRadius = '8px';
          replyBlock.style.borderLeft = '3px solid #10b981';

          const replyHeader = document.createElement('div');
          replyHeader.style.display = 'flex';
          replyHeader.style.justifyContent = 'space-between';
          replyHeader.style.alignItems = 'center';
          replyHeader.style.marginBottom = '8px';

          const replyLabel = document.createElement('strong');
          replyLabel.textContent = `ردك ${index + 1}:`;
          replyLabel.style.color = '#10b981';
          replyHeader.appendChild(replyLabel);

          const copyReplyBtn = document.createElement('button');
          copyReplyBtn.type = 'button';
          copyReplyBtn.className = 'btn btn-sm btn-secondary';
          copyReplyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ';
          copyReplyBtn.style.fontSize = '0.85rem';
          copyReplyBtn.style.padding = '4px 10px';
          const replyContent = comment.reply.content || '';
          copyReplyBtn.addEventListener('click', () => this.copyToClipboard(replyContent));
          replyHeader.appendChild(copyReplyBtn);

          replyBlock.appendChild(replyHeader);

          if (comment.reply.title) {
            const replyTitle = document.createElement('h6');
            replyTitle.textContent = comment.reply.title;
            replyTitle.style.margin = '0 0 8px 0';
            replyTitle.style.color = '#0a3247';
            replyBlock.appendChild(replyTitle);
          }

          const replyContentEl = document.createElement('p');
          replyContentEl.innerHTML = (comment.reply.content || '').replace(/\n/g, '<br>');
          replyContentEl.style.margin = '0';
          replyContentEl.style.color = '#1f2933';
          replyBlock.appendChild(replyContentEl);

          commentBlock.appendChild(replyBlock);
        } else if (comment.status === 'pending' || comment.status === 'processing') {
          const statusMsg = document.createElement('div');
          statusMsg.style.display = 'flex';
          statusMsg.style.alignItems = 'center';
          statusMsg.style.gap = '8px';
          statusMsg.style.color = '#f59e0b';
          statusMsg.style.fontStyle = 'italic';

          const spinner = document.createElement('span');
          spinner.className = 'status-spinner';
          spinner.style.width = '14px';
          spinner.style.height = '14px';
          spinner.style.borderWidth = '2px';

          const text = document.createElement('span');
          text.textContent = 'قيد المعالجة...';

          statusMsg.appendChild(spinner);
          statusMsg.appendChild(text);
          commentBlock.appendChild(statusMsg);
        }

        commentsSection.appendChild(commentBlock);
      });

      body.appendChild(commentsSection);
    }

    content.appendChild(body);
    modal.appendChild(header);
    modal.appendChild(content);
    backdrop.appendChild(modal);
    root.appendChild(backdrop);
  }

  copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => {
        notifyUser('success', 'تم النسخ إلى الحافظة بنجاح!');
      }).catch(() => {
        this.fallbackCopyToClipboard(text);
      });
    } else {
      this.fallbackCopyToClipboard(text);
    }
  }

  fallbackCopyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      notifyUser('success', 'تم النسخ إلى الحافظة بنجاح!');
    } catch (err) {
      notifyUser('error', 'تعذّر نسخ النص. يرجى نسخه يدوياً.');
    }
    document.body.removeChild(textarea);
  }

  async checkAndShowCommentReplyButton() {
    if (!this.commentReplyBtn) {
      console.warn('[ai-tools] commentReplyBtn not found');
      return;
    }

    try {
      // التحقق من أن الرد الأول تم إنشاؤه
      const statusRes = await fetch(`${API_BASE}/status?type_key=${this.typeKey}&task_id=${this.context.taskId}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const statusJson = await statusRes.json().catch(() => ({}));
      if (statusJson?.state !== 'done') {
        console.log('[ai-tools] Main reply not done yet, state:', statusJson?.state);
        return; // الرد الأول لم يكتمل بعد
      }

      // التحقق من إمكانية إنشاء رد جديد
      const res = await fetch(`${API_BASE}/comments?task_id=${this.context.taskId}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json().catch(() => ({}));
      console.log('[ai-tools] Comments check result:', json);

      if (json?.ok && json.can_create_new && this.commentReplyBtn) {
        this.commentReplyBtn.style.display = 'inline-flex';
        console.log('[ai-tools] Comment reply button shown');
      } else if (json?.ok && !json.can_create_new && this.commentReplyBtn) {
        this.commentReplyBtn.style.display = 'none'; // تم الوصول للحد الأقصى
        console.log('[ai-tools] Comment reply button hidden (max reached)');
      } else {
        console.warn('[ai-tools] Unexpected response or missing button:', { ok: json?.ok, can_create_new: json?.can_create_new, hasBtn: !!this.commentReplyBtn });
      }
    } catch (err) {
      console.error('[ai-tools] Failed to check comments:', err);
    }
  }

  openCommentReplyModal() {
    ensureStyles();
    const root = document.getElementById('ai-tools-modals-root') || document.body;
    const backdrop = document.createElement('div');
    backdrop.className = 'ai-tool-modal-backdrop show';
    backdrop.setAttribute('aria-hidden', 'false');

    const modal = document.createElement('div');
    modal.className = 'ai-tool-modal';

    const header = document.createElement('header');
    const title = document.createElement('div');
    title.className = 'ai-modal-title';
    title.innerHTML = '<i class="fa-solid fa-reply"></i> كتابة رد على إجابة أحد الزملاء';
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ai-modal-close';
    closeBtn.textContent = 'إغلاق';
    closeBtn.addEventListener('click', () => {
      backdrop.classList.remove('show');
      backdrop.setAttribute('aria-hidden', 'true');
      setTimeout(() => backdrop.remove(), 200);
    });
    header.appendChild(title);
    header.appendChild(closeBtn);

    const content = document.createElement('div');
    content.className = 'ai-modal-content';

    const form = document.createElement('form');
    form.className = 'ai-tool-form';
    form.noValidate = true;

    // حقل تعليق الزميل
    const commentSection = document.createElement('div');
    commentSection.className = 'ai-form-section';
    const commentLabel = document.createElement('label');
    commentLabel.textContent = 'تعليق الزميل الذي تريد الرد عليه *';
    commentLabel.style.fontWeight = '600';
    commentLabel.style.marginBottom = '8px';
    commentLabel.style.display = 'block';
    const commentTextarea = document.createElement('textarea');
    commentTextarea.name = 'comment_text';
    commentTextarea.required = true;
    commentTextarea.placeholder = 'الصق هنا نص تعليق الزميل الذي تريد الرد عليه...';
    commentTextarea.style.width = '100%';
    commentTextarea.style.minHeight = '120px';
    commentTextarea.style.padding = '12px';
    commentTextarea.style.border = '1px solid #dcdfe4';
    commentTextarea.style.borderRadius = '12px';
    commentSection.appendChild(commentLabel);
    commentSection.appendChild(commentTextarea);

    // نوع الرد
    const replyTypeSection = document.createElement('div');
    replyTypeSection.className = 'ai-form-section';
    const replyTypeLabel = document.createElement('label');
    replyTypeLabel.textContent = 'طبيعة الرد';
    replyTypeLabel.style.fontWeight = '600';
    replyTypeLabel.style.marginBottom = '8px';
    replyTypeLabel.style.display = 'block';
    const replyTypeSelect = document.createElement('select');
    replyTypeSelect.name = 'reply_type';
    replyTypeSelect.style.width = '100%';
    replyTypeSelect.style.padding = '12px';
    replyTypeSelect.style.border = '1px solid #dcdfe4';
    replyTypeSelect.style.borderRadius = '12px';
    [
      { value: 'agreement', label: 'موافقة' },
      { value: 'disagreement', label: 'اختلاف بالرأي' },
      { value: 'question', label: 'سؤال' },
      { value: 'suggestion', label: 'اقتراح' },
      { value: 'clarification', label: 'توضيح' },
      { value: 'addition', label: 'إضافة' },
    ].forEach(opt => {
      const option = document.createElement('option');
      option.value = opt.value;
      option.textContent = opt.label;
      replyTypeSelect.appendChild(option);
    });
    replyTypeSection.appendChild(replyTypeLabel);
    replyTypeSection.appendChild(replyTypeSelect);

    // تعليمات إضافية
    const instructionsSection = document.createElement('div');
    instructionsSection.className = 'ai-form-section';
    const instructionsLabel = document.createElement('label');
    instructionsLabel.textContent = 'تعليمات إضافية (اختياري)';
    instructionsLabel.style.fontWeight = '600';
    instructionsLabel.style.marginBottom = '8px';
    instructionsLabel.style.display = 'block';
    const instructionsTextarea = document.createElement('textarea');
    instructionsTextarea.name = 'reply_instructions';
    instructionsTextarea.placeholder = 'مثال: أريد أن يكون الرد داعماً ومحفزاً...';
    instructionsTextarea.style.width = '100%';
    instructionsTextarea.style.minHeight = '80px';
    instructionsTextarea.style.padding = '12px';
    instructionsTextarea.style.border = '1px solid #dcdfe4';
    instructionsTextarea.style.borderRadius = '12px';
    instructionsSection.appendChild(instructionsLabel);
    instructionsSection.appendChild(instructionsTextarea);

    form.appendChild(commentSection);
    form.appendChild(replyTypeSection);
    form.appendChild(instructionsSection);

    const footer = document.createElement('footer');
    const submitBtn = document.createElement('button');
    submitBtn.type = 'submit';
    submitBtn.className = 'btn btn-primary';
    submitBtn.textContent = 'توليد الرد';
    const hint = document.createElement('span');
    hint.className = 'ai-hint';
    hint.textContent = 'يمكنك إنشاء ردين فقط لكل مهمة.';
    footer.appendChild(submitBtn);
    footer.appendChild(hint);
    form.appendChild(footer);

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!commentTextarea.value.trim()) {
        notifyUser('error', 'يرجى إدخال تعليق الزميل');
        return;
      }

      // تعطيل الزر
      submitBtn.disabled = true;
      const originalCommentReplyText = submitBtn.textContent;
      submitBtn.textContent = 'جاري الإرسال...';

      // إضافة class لمنع التفاعل مع td-card
      document.body.classList.add('ai-tool-processing');

      try {
        const payload = {
          task_id: this.context.taskId,
          comment_text: commentTextarea.value.trim(),
          reply_type: replyTypeSelect.value,
          reply_instructions: instructionsTextarea.value.trim(),
        };

        const res = await fetch(`${API_BASE}/comment-reply`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          credentials: 'include',
          body: JSON.stringify(payload),
        });

        const json = await res.json().catch(() => ({}));
        if (res.status === 200 && json.ok) {
          notifyUser('success', 'تم استلام طلب الرد وسيتم معالجته في الخلفية.');
          backdrop.classList.remove('show');
          backdrop.setAttribute('aria-hidden', 'true');
          setTimeout(() => backdrop.remove(), 200);
          // تحديث الحالة لإظهار حالة رد التعليق
          this.refreshStatus({ withSpinner: true });
          // تحديث حالة ردود التعليقات بعد تأخير قصير
          setTimeout(() => this.updateCommentReplyStatus(), 1000);
        } else {
          notifyUser('error', json.message || 'تعذّر إرسال الطلب.');
        }
      } catch (err) {
        console.error('[ai-tools] comment reply error', err);
        notifyUser('error', 'حدث خطأ أثناء الاتصال بالخادم.');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'توليد الرد';
        // إزالة class لمنع التفاعل
        document.body.classList.remove('ai-tool-processing');
      }
    });

    content.appendChild(form);
    modal.appendChild(header);
    modal.appendChild(content);
    backdrop.appendChild(modal);
    root.appendChild(backdrop);
  }

  copyResultToClipboard(result, view) {
    // بناء نص قابل للنسخ من النتيجة
    const fields = Array.isArray(view.fields) ? view.fields : Object.keys(result).map(key => ({ key, label: key }));
    const parts = [];

    fields.forEach(field => {
      if (!field.key) return;
      const value = result[field.key];
      if (value === null || value === undefined || value === '') return;

      const label = field.label || field.key;
      let textValue = '';

      if (field.render === 'matrix' && Array.isArray(value)) {
        const columns = Array.isArray(field.columns) && field.columns.length
          ? field.columns
          : Object.keys(value[0] || {}).map(colKey => ({ key: colKey, label: colKey }));
        const formatMatrixCellText = (cell) => {
          if (Array.isArray(cell)) {
            return cell.map(item => `- ${textContent(item)}`).join('\n');
          }
          if (cell && typeof cell === 'object') {
            return Object.keys(cell).map(k => `${k}: ${textContent(cell[k])}`).join('; ');
          }
          return textContent(cell);
        };
        const rowsText = value.map(row => {
          return columns.map(col => `${col.label || col.key}: ${formatMatrixCellText(row?.[col.key])}`).join('\n');
        });
        textValue = rowsText.join('\n---\n');
      } else if (Array.isArray(value)) {
        textValue = value.map(v => textContent(v)).join('، ');
      } else {
        textValue = textContent(value);
      }

      if (textValue) {
        parts.push(`${label}:\n${textValue}`);
      }
    });

    const fullText = parts.join('\n\n');

    // نسخ إلى الحافظة
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(fullText).then(() => {
        notifyUser('success', 'تم نسخ الرد إلى الحافظة بنجاح!');
      }).catch(() => {
        this.fallbackCopyToClipboard(fullText);
      });
    } else {
      this.fallbackCopyToClipboard(fullText);
    }
  }

  fallbackCopyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      notifyUser('success', 'تم نسخ الرد إلى الحافظة بنجاح!');
    } catch (err) {
      notifyUser('error', 'تعذّر نسخ النص. يرجى نسخه يدوياً.');
    }
    document.body.removeChild(textarea);
  }

  async updateCommentReplyStatus() {
    // لأداة المنتدى: التحقق من حالة ردود التعليقات وإظهارها
    if (this.typeKey === 'forum_reply_v1') {
      try {
        const res = await fetch(`${API_BASE}/comments?task_id=${this.context.taskId}`, {
          credentials: 'include',
          headers: { 'Accept': 'application/json' },
        });
        const json = await res.json().catch(() => ({}));

        if (json?.ok && Array.isArray(json.comments)) {
          const pendingComments = json.comments.filter(c => c.status === 'pending' || c.status === 'processing');
          const doneComments = json.comments.filter(c => c.status === 'done');

          if (pendingComments.length > 0 && this.statusBadge) {
            // إظهار حالة ردود التعليقات المعلقة
            const badge = this.statusBadge;
            badge.classList.add('show', 'processing');
            badge.innerHTML = `<span class="status-spinner"></span> ${doneComments.length} رد جاهز، ${pendingComments.length} رد قيد المعالجة`;
          } else if (doneComments.length > 0 && this.statusBadge && this.state === 'done') {
            // تحديث الـ badge لإظهار عدد الردود الجاهزة
            const badge = this.statusBadge;
            badge.classList.add('show', 'done');
            badge.innerHTML = `${doneComments.length} رد جاهز على تعليقات الزملاء`;
          }
        }
      } catch (err) {
        console.warn('[ai-tools] Failed to update comment reply status:', err);
      }
    }
  }

  async retryJob() {
    if (!this.context.taskId) {
      notifyUser('error', 'لا يمكن إعادة المحاولة قبل تحميل تفاصيل المهمة.');
      return;
    }

    if (!this.retryBtn) {
      notifyUser('error', 'زر إعادة المحاولة غير متاح.');
      return;
    }

    const originalText = this.retryBtn.innerHTML;
    this.retryBtn.disabled = true;
    this.retryBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري إعادة المحاولة...';

    try {
      const res = await fetch(`${API_BASE}/retry`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          type_key: this.typeKey,
          task_id: this.context.taskId,
        }),
      });

      const json = await res.json().catch(() => ({}));

      if (res.status === 200 && json.ok && json.status === 'retried') {
        notifyUser('success', 'تم تغيير الحالة إلى معلق — سيتم معالجة المهمة مرة أخرى.');
        this.refreshStatus({ withSpinner: true });
        return;
      }

      notifyUser('error', json.message || 'تعذّر إعادة المحاولة. حاول لاحقاً.');
    } catch (err) {
      console.error('[ai-tools] retry error', err);
      notifyUser('error', 'حدث خطأ أثناء الاتصال بالخادم.');
    } finally {
      this.retryBtn.disabled = false;
      this.retryBtn.innerHTML = originalText;
    }
  }

  /**
   * حذف طلب AI (فقط للأدمن في وضع الاستعراض)
   */
  async deleteJob() {
    // التحقق من أن المستخدم أدمن في وضع الاستعراض
    if (window.__IS_ADMIN_VIEWING_AS_STUDENT__ !== true) {
      notifyUser('error', 'غير مصرح لك بهذا الإجراء');
      return;
    }

    if (!this.context.taskId) {
      notifyUser('error', 'لا يمكن حذف الطلب قبل تحميل تفاصيل المهمة.');
      return;
    }

    const confirmResult = await Swal.fire({
      title: 'تأكيد الحذف',
      text: 'هل أنت متأكد من حذف هذا الطلب المحدّد؟ لا يمكن التراجع عن هذا الإجراء.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'نعم، احذف النتيجة',
      cancelButtonText: 'إلغاء'
    });

    if (!confirmResult.isConfirmed) {
      return;
    }

    if (!this.deleteBtn) {
      notifyUser('error', 'زر الحذف غير متاح.');
      return;
    }

    const originalText = this.deleteBtn.innerHTML;
    this.deleteBtn.disabled = true;
    this.deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الحذف...';

    try {
      const qsParams = {
        type_key: this.typeKey,
        task_id: this.context.taskId,
      };
      
      // إذا كان هناك job محدد، نقوم بإرسال الـ id الخاص به لحذفه هو فقط
      if (this.currentJobId) {
        qsParams.job_id = this.currentJobId;
      }
      
      const qs = new URLSearchParams(qsParams);

      // استخدام API_BASE الصحيح
      const apiBase = window.API_BASE || '';
      const deleteUrl = apiBase ? `${apiBase}/students/ai-tools/delete?${qs.toString()}` : `/students/ai-tools/delete?${qs.toString()}`;

      const response = await fetch(deleteUrl, {
        method: 'DELETE',
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const result = await response.json().catch(() => ({}));

      if (result.ok) {
        notifyUser('success', 'تم حذف الطلب بنجاح');
        // إخفاء الأزرار وإعادة تعيين الحالة
        this.resultBtn.style.display = 'none';
        this.deleteBtn.style.display = 'none';
        if (this.downloadBtn) {
          this.downloadBtn.style.display = 'none';
        }
        // تحديث الحالة
        this.state = 'idle';
        this.statusBadge.classList.remove('show', 'done', 'failed', 'pending', 'processing');
        // إعادة تحميل الصفحة لإزالة الكارد
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        notifyUser('error', result.message || 'فشل حذف الطلب');
        this.deleteBtn.disabled = false;
        this.deleteBtn.innerHTML = originalText;
      }
    } catch (error) {
      console.error('[ai-tools] Delete error:', error);
      notifyUser('error', 'حدث خطأ أثناء حذف الطلب');
      this.deleteBtn.disabled = false;
      this.deleteBtn.innerHTML = originalText;
    }
  }

  /**
   * عرض Modal تأكيد خصم النقاط
   */
  async showPointsConfirmationModal(pointsCost, userPoints) {
    return new Promise((resolve) => {
      const remainingPoints = userPoints - pointsCost;
      const formattedCost = window.AI_TOOLS_COMMON?.formatPoints?.(pointsCost) || pointsCost.toString();
      const formattedCurrent = window.AI_TOOLS_COMMON?.formatPoints?.(userPoints) || userPoints.toString();
      const formattedRemaining = window.AI_TOOLS_COMMON?.formatPoints?.(remainingPoints) || remainingPoints.toString();

      if (window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire({
          icon: 'question',
          title: 'تأكيد استخدام الأداة',
          html: `
            <div style="text-align: right; direction: rtl;">
              <p style="margin-bottom: 15px;">سيتم خصم النقاط التالية:</p>
              <div style="background: #f5f7fb; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                  <span>تكلفة الأداة:</span>
                  <strong>${formattedCost} نقطة</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                  <span>رصيدك الحالي:</span>
                  <strong>${formattedCurrent} نقطة</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px solid #d7dbe1; padding-top: 8px; margin-top: 8px;">
                  <span>الرصيد بعد الاستخدام:</span>
                  <strong style="color: ${remainingPoints >= 0 ? '#059669' : '#dc2626'}">${formattedRemaining} نقطة</strong>
                </div>
              </div>
              <p style="font-size: 0.9rem; color: #666;">هل تريد المتابعة؟</p>
            </div>
          `,
          showCancelButton: true,
          confirmButtonText: 'تأكيد',
          cancelButtonText: 'إلغاء',
          confirmButtonColor: '#219ebc',
          cancelButtonColor: '#94a3b8',
          reverseButtons: true,
          customClass: {
            popup: 'swal2-rtl'
          }
        }).then((result) => {
          resolve(result.isConfirmed);
        });
      } else {
        const confirmed = confirm(`سيتم خصم ${formattedCost} نقطة. رصيدك الحالي: ${formattedCurrent} نقطة. الرصيد بعد الاستخدام: ${formattedRemaining} نقطة. هل تريد المتابعة؟`);
        resolve(confirmed);
      }
    });
  }

  /**
   * عرض Modal عدم إمكانية الوصول
   */
  showAccessDeniedModal(reason, pointsCost, userPoints, hasActivePlan) {
    let title = 'غير متاح';
    let message = '';
    let showPointsButton = false;
    let showSubscriptionButton = false;

    if (reason === 'insufficient_points') {
      title = 'نقاط غير كافية';
      message = `نقاطك غير كافية. تحتاج ${pointsCost} نقطة ولكن لديك ${userPoints} فقط.`;
      showPointsButton = true;
    } else if (reason === 'no_subscription_and_insufficient_points') {
      title = 'غير متاح';
      message = `لا تملك اشتراكاً فعالاً ولا تملك نقاط كافية. تحتاج ${pointsCost} نقطة ولكن لديك ${userPoints} فقط.`;
      showPointsButton = true;
      showSubscriptionButton = true;
    } else if (reason === 'no_subscription') {
      title = 'اشتراك مطلوب';
      message = 'لا تملك اشتراكاً فعالاً. يرجى الاشتراك لاستخدام هذه الأداة.';
      showSubscriptionButton = true;
    } else {
      message = 'غير متاح حالياً.';
    }

    if (window.Swal && typeof window.Swal.fire === 'function') {
      let confirmButtonText = 'حسناً';
      let cancelButtonText = '';
      let confirmButtonColor = '#219ebc';
      let showCancel = false;

      if (showPointsButton && showSubscriptionButton) {
        confirmButtonText = 'الحصول على نقاط';
        cancelButtonText = 'الاشتراك الآن';
        confirmButtonColor = '#ff9800';
        showCancel = true;
      } else if (showPointsButton) {
        confirmButtonText = 'الحصول على نقاط';
        confirmButtonColor = '#ff9800';
      } else if (showSubscriptionButton) {
        confirmButtonText = 'الاشتراك الآن';
      }

      window.Swal.fire({
        icon: 'warning',
        title: title,
        text: message,
        confirmButtonText: confirmButtonText,
        showCancelButton: showCancel,
        cancelButtonText: cancelButtonText,
        confirmButtonColor: confirmButtonColor,
        cancelButtonColor: '#94a3b8',
        reverseButtons: true,
        customClass: {
          popup: 'swal2-rtl'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          if (showPointsButton) {
            navigateToSection('rewards');
          } else if (showSubscriptionButton) {
            navigateToSection('upgrade');
          }
        } else if (result.dismiss === window.Swal.DismissReason.cancel && showSubscriptionButton) {
          navigateToSection('upgrade');
        }
      });
    } else {
      alert(message);
      if (showPointsButton) {
        if (confirm('هل تريد الانتقال إلى صفحة المكافآت للحصول على نقاط؟')) {
          navigateToSection('rewards');
        }
      } else if (showSubscriptionButton) {
        if (confirm('هل تريد الانتقال إلى صفحة الاشتراكات؟')) {
          navigateToSection('upgrade');
        }
      }
    }
  }

  /**
   * تحديث badges بناءً على حالة الاستخدام
   */
  updateBadges(needsPoints, pointsCost) {
    if (this.freeUsageBadge && this.pointsDeductionBadge) {
      if (needsPoints && pointsCost) {
        // سيتم خصم النقاط
        this.freeUsageBadge.style.display = 'none';
        const formattedCost = window.AI_TOOLS_COMMON?.formatPoints?.(pointsCost) || pointsCost.toString();
        this.pointsDeductionBadge.textContent = `سيتم خصم ${formattedCost} نقطة`;
        this.pointsDeductionBadge.style.display = 'block';
      } else {
        // استخدام مجاني متاح
        this.pointsDeductionBadge.style.display = 'none';
        this.freeUsageBadge.style.display = 'block';
      }
    }
  }

  /**
   * جلب قائمة جميع jobs
   */
  async loadJobsList() {
    if (!this.context.taskId) return [];

    // تهيئة pagination إذا لم تكن موجودة
    if (!this.jobsPagination) {
      this.jobsPagination = {
        page: 1,
        perPage: 5,
        total: 0,
        totalPages: 0,
        search: ''
      };
    }

    try {
      const qs = new URLSearchParams({
        type_key: this.typeKey,
        task_id: this.context.taskId,
        page: this.jobsPagination.page,
        per_page: this.jobsPagination.perPage,
      });

      // إضافة البحث لمقيم المقالات
      if (this.typeKey === 'article_evaluator_v1' && this.jobsPagination.search) {
        qs.append('search', this.jobsPagination.search);
      }

      const res = await fetch(`${API_BASE}/jobs-list?${qs.toString()}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json().catch(() => ({}));

      if (json.ok && Array.isArray(json.jobs)) {
        this.jobsList = json.jobs;
        // تحديث معلومات pagination
        if (json.total !== undefined) {
          this.jobsPagination.total = json.total;
          this.jobsPagination.totalPages = json.total_pages || Math.ceil(json.total / this.jobsPagination.perPage);
          this.jobsPagination.page = json.page || this.jobsPagination.page;
        }
        return json.jobs;
      }
      return [];
    } catch (err) {
      console.warn('[ai-tools] Failed to load jobs list:', err);
      return [];
    }
  }

  /**
   * بناء واجهة قائمة Jobs
   */
  buildJobsListUI() {
    if (!this.jobsListContainer) return;

    // تهيئة متغيرات pagination وsearch
    if (!this.jobsPagination) {
      this.jobsPagination = {
        page: 1,
        perPage: 5,
        total: 0,
        totalPages: 0,
        search: ''
      };
    }

    this.jobsListContainer.style.display = 'block';
    this.jobsListContainer.innerHTML = '';

    // Header مع البحث
    const header = document.createElement('div');
    header.className = 'ai-tool-jobs-list-header';
    header.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;';

    const headerTitle = document.createElement('div');
    headerTitle.style.cssText = 'display: flex; align-items: center; gap: 8px;';
    headerTitle.innerHTML = `<i class="fa-solid fa-clock-rotate-left"></i> <span>النتائج السابقة (${this.jobsPagination.total || 0})</span>`;
    header.appendChild(headerTitle);

    // حقل البحث (لمقيم المقالات، مقيم التقرير الاستقصائي، ومقيم الخطة الاستقصائية)
    if (['article_evaluator_v1', 'inquiry_report_evaluator_v1', 'inquiry_plan_evaluator_v1'].includes(this.typeKey)) {
      const searchContainer = document.createElement('div');
      searchContainer.style.cssText = 'display: flex; gap: 8px; align-items: center; flex: 1; min-width: 200px; max-width: 300px;';

      const searchInput = document.createElement('input');
      searchInput.type = 'text';
      searchInput.placeholder = 'بحث بالاسم أو الرقم...';
      searchInput.value = this.jobsPagination.search || '';
      searchInput.style.cssText = 'flex: 1; padding: 8px 12px; border: 1px solid #e1e5e9; border-radius: 8px; font-size: 0.9rem;';
      searchInput.addEventListener('input', (e) => {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
          this.jobsPagination.search = e.target.value.trim();
          this.jobsPagination.page = 1; // إعادة تعيين الصفحة عند البحث
          this.loadJobsList().then(() => {
            this.buildJobsListUI();
          });
        }, 500); // تأخير 500ms للبحث
      });

      const searchBtn = document.createElement('button');
      searchBtn.className = 'btn btn-sm btn-secondary';
      searchBtn.innerHTML = '<i class="fa-solid fa-search"></i>';
      searchBtn.style.cssText = 'padding: 8px 12px;';
      searchBtn.addEventListener('click', () => {
        this.jobsPagination.search = searchInput.value.trim();
        this.jobsPagination.page = 1;
        this.loadJobsList().then(() => {
          this.buildJobsListUI();
        });
      });

      searchContainer.appendChild(searchInput);
      searchContainer.appendChild(searchBtn);
      header.appendChild(searchContainer);
    }

    this.jobsListContainer.appendChild(header);

    if (!this.jobsList || this.jobsList.length === 0) {
      const emptyMsg = document.createElement('div');
      emptyMsg.style.cssText = 'text-align: center; padding: 30px; color: #666;';
      emptyMsg.textContent = this.jobsPagination.search ? 'لا توجد نتائج للبحث' : 'لا توجد نتائج سابقة';
      this.jobsListContainer.appendChild(emptyMsg);
      return;
    }

    const list = document.createElement('div');
    list.className = 'ai-jobs-list-container';

    this.jobsList.forEach((job, index) => {
      const item = document.createElement('div');
      item.className = 'ai-job-item';
      item.dataset.jobId = job.id;
      const isActive = job.id === this.currentJobId;
      if (isActive) {
        item.classList.add('active');
      }

      const itemHeader = document.createElement('div');
      itemHeader.className = 'ai-job-item-header';

      const itemInfo = document.createElement('div');
      itemInfo.className = 'ai-job-item-info';

      const itemTitle = document.createElement('div');
      itemTitle.className = 'ai-job-item-title';

      const jobNum = document.createElement('span');
      jobNum.className = 'ai-job-item-num';
      // تخصيص العنوان باسم الطالب ورقمه الوزاري/الأكاديمي للأدوات المدعومة
      if (this.typeKey === 'inquiry_report_evaluator_v1' && job.student_name && job.student_id) {
        jobNum.textContent = `${job.student_name} - ${job.student_id}`;
      } else if (['article_evaluator_v1', 'inquiry_plan_evaluator_v1'].includes(this.typeKey) && job.teacher_name && job.student_number) {
        jobNum.textContent = `${job.teacher_name} - ${job.student_number}`;
      } else {
        jobNum.textContent = `النتيجة #${this.jobsList.length - index}`;
      }
      itemTitle.appendChild(jobNum);

      const statusBadge = document.createElement('span');
      statusBadge.className = 'ai-job-item-status';
      if (job.status === 'done') {
        statusBadge.classList.add('done');
        statusBadge.innerHTML = '<i class="fa-solid fa-check-circle"></i> مكتمل';
      } else if (job.status === 'failed') {
        statusBadge.classList.add('failed');
        statusBadge.innerHTML = '<i class="fa-solid fa-xmark-circle"></i> فشل';
      } else if (job.status === 'processing') {
        statusBadge.classList.add('processing');
        statusBadge.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> قيد المعالجة';
      } else if (job.status === 'review') {
        statusBadge.classList.add('done');
        statusBadge.style.background = '#fef3c7';
        statusBadge.style.color = '#92400e';
        statusBadge.innerHTML = '<i class="fa-solid fa-clipboard-check"></i> بانتظار المراجعة';
      } else {
        statusBadge.classList.add('pending');
        statusBadge.innerHTML = '<i class="fa-solid fa-hourglass-half"></i> في الانتظار';
      }
      itemTitle.appendChild(statusBadge);

      itemInfo.appendChild(itemTitle);

      const dateText = document.createElement('div');
      dateText.className = 'ai-job-item-date';
      try {
        const date = new Date(job.created_at);
        dateText.innerHTML = `<i class="fa-solid fa-calendar"></i> <span>${date.toLocaleDateString('ar-SA', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        })}</span>`;
      } catch (e) {
        dateText.innerHTML = `<i class="fa-solid fa-calendar"></i> <span>${job.created_at}</span>`;
      }
      itemInfo.appendChild(dateText);

      itemHeader.appendChild(itemInfo);

      const itemActions = document.createElement('div');
      itemActions.className = 'ai-job-item-actions';

      if (job.status === 'done') {
        // منطق إظهار الأزرار حسب نوع النتيجة:
        // - إذا كان هناك ملف (download_url) → زر التنزيل + زر التعديل (لمقيم المقالات) + زر التقرير (لمقيم المقالات)
        // - إذا لم يكن هناك ملف → زر العرض + النسخ + زر التعديل (لمقيم المقالات) + زر التقرير (لمقيم المقالات)
        if (job.download_url) {
          // يوجد ملف → زر التنزيل
          const downloadBtn = document.createElement('a');
          downloadBtn.href = job.download_url;
          downloadBtn.target = '_blank';
          downloadBtn.rel = 'noopener';
          downloadBtn.className = 'btn btn-sm btn-secondary';
          downloadBtn.style.cssText = 'padding: 6px 14px; font-size: 0.8rem; border-radius: 8px;';
          downloadBtn.innerHTML = '<i class="fa-solid fa-download"></i> <span>تنزيل</span>';
          downloadBtn.title = 'تنزيل الملف';
          downloadBtn.addEventListener('click', (e) => e.stopPropagation());
          itemActions.appendChild(downloadBtn);

          // زر العرض
          const viewBtn = document.createElement('button');
          viewBtn.type = 'button';
          viewBtn.className = 'btn btn-sm btn-primary';
          viewBtn.style.cssText = 'padding: 6px 14px; font-size: 0.8rem; border-radius: 8px;';
          viewBtn.innerHTML = '<i class="fa-solid fa-eye"></i> <span>عرض</span>';
          viewBtn.title = 'عرض النتيجة';
          viewBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showResultForJob(job.id);
          });
          itemActions.appendChild(viewBtn);
        } else {
          // لا يوجد ملف → زر العرض و النسخ
          const viewBtn = document.createElement('button');
          viewBtn.type = 'button';
          viewBtn.className = 'btn btn-sm btn-primary';
          viewBtn.style.cssText = 'padding: 6px 14px; font-size: 0.8rem; border-radius: 8px;';
          viewBtn.innerHTML = '<i class="fa-solid fa-eye"></i> <span>عرض</span>';
          viewBtn.title = 'عرض النتيجة';
          viewBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showResultForJob(job.id);
          });
          itemActions.appendChild(viewBtn);

          const copyBtn = document.createElement('button');
          copyBtn.type = 'button';
          copyBtn.className = 'btn btn-sm btn-secondary';
          copyBtn.style.cssText = 'padding: 6px 14px; font-size: 0.8rem; border-radius: 8px;';
          copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> <span>نسخ</span>';
          copyBtn.title = 'نسخ النتيجة';
          copyBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.copyJobResult(job.id);
          });
          itemActions.appendChild(copyBtn);
        }

        // لمقيم المقالات: إضافة زر التعديل وزر التقرير
        if (this.typeKey === 'article_evaluator_v1') {
          // زر التعديل
          const editBtn = document.createElement('button');
          editBtn.type = 'button';
          editBtn.className = 'btn btn-sm btn-warning';
          editBtn.style.cssText = 'padding: 6px 14px; font-size: 0.8rem; border-radius: 8px; background: #ffc107; color: #000; border: none;';
          editBtn.innerHTML = '<i class="fa-solid fa-edit"></i> <span>تعديل</span>';
          editBtn.title = 'تعديل الطلب';
          editBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.openEditModal(job.id);
          });
          itemActions.appendChild(editBtn);

          // زر تقرير التغذية الراجعة
          const reportBtn = document.createElement('button');
          reportBtn.type = 'button';
          reportBtn.className = 'btn btn-sm btn-info';
          reportBtn.style.cssText = 'padding: 6px 14px; font-size: 0.8rem; border-radius: 8px; background: #0dcaf0; color: #000; border: none;';
          reportBtn.innerHTML = '<i class="fa-solid fa-file-lines"></i> <span>تقرير التغذية الراجعة</span>';
          reportBtn.title = 'عرض تقرير التغذية الراجعة';
          reportBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showFeedbackReport(job.id);
          });
          itemActions.appendChild(reportBtn);
        }
      } else if (job.status === 'review') {
        // حالة المراجعة: زر مراجعة التقييم
        const reviewBtn = document.createElement('button');
        reviewBtn.type = 'button';
        reviewBtn.className = 'btn btn-sm btn-primary';
        reviewBtn.style.cssText = 'padding: 6px 14px; font-size: 0.8rem; border-radius: 8px; background: #f59e0b; border-color: #f59e0b; color: #fff;';
        reviewBtn.innerHTML = '<i class="fa-solid fa-clipboard-check"></i> <span>مراجعة</span>';
        reviewBtn.title = 'مراجعة التقييم';
        reviewBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          this.currentJobId = job.id;
          this.showReviewModal();
        });
        itemActions.appendChild(reviewBtn);
      } else if (job.status === 'failed') {
        const retryBtn = document.createElement('button');
        retryBtn.type = 'button';
        retryBtn.className = 'btn btn-sm btn-primary';
        retryBtn.style.cssText = 'padding: 6px 14px; font-size: 0.8rem; border-radius: 8px;';
        retryBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> <span>إعادة</span>';
        retryBtn.title = 'إعادة المحاولة';
        retryBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          this.currentJobId = job.id;
          this.rebuildJob();
        });
        itemActions.appendChild(retryBtn);
      }

      itemHeader.appendChild(itemActions);
      item.appendChild(itemHeader);

      // النقر على العنصر للتبديل إليه
      item.addEventListener('click', () => {
        if (job.status === 'done' || job.status === 'failed' || job.status === 'review') {
          this.switchToJob(job.id);
        }
      });

      list.appendChild(item);
    });

    this.jobsListContainer.appendChild(list);

    // Pagination controls
    if (this.jobsPagination.totalPages > 1) {
      const paginationContainer = document.createElement('div');
      paginationContainer.className = 'ai-jobs-pagination';
      paginationContainer.style.cssText = 'display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px; padding: 15px; flex-wrap: wrap;';

      // زر السابق
      const prevBtn = document.createElement('button');
      prevBtn.className = 'btn btn-sm btn-secondary';
      prevBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i> السابق';
      prevBtn.disabled = this.jobsPagination.page === 1;
      prevBtn.style.cssText = 'padding: 6px 12px;';
      prevBtn.addEventListener('click', () => {
        if (this.jobsPagination.page > 1) {
          this.jobsPagination.page--;
          this.loadJobsList().then(() => {
            this.buildJobsListUI();
          });
        }
      });
      paginationContainer.appendChild(prevBtn);

      // معلومات الصفحة
      const pageInfo = document.createElement('span');
      pageInfo.style.cssText = 'padding: 6px 12px; color: #666; font-size: 0.9rem;';
      pageInfo.textContent = `صفحة ${this.jobsPagination.page} من ${this.jobsPagination.totalPages}`;
      paginationContainer.appendChild(pageInfo);

      // زر التالي
      const nextBtn = document.createElement('button');
      nextBtn.className = 'btn btn-sm btn-secondary';
      nextBtn.innerHTML = 'التالي <i class="fa-solid fa-chevron-left"></i>';
      nextBtn.disabled = this.jobsPagination.page >= this.jobsPagination.totalPages;
      nextBtn.style.cssText = 'padding: 6px 12px;';
      nextBtn.addEventListener('click', () => {
        if (this.jobsPagination.page < this.jobsPagination.totalPages) {
          this.jobsPagination.page++;
          this.loadJobsList().then(() => {
            this.buildJobsListUI();
          });
        }
      });
      paginationContainer.appendChild(nextBtn);

      this.jobsListContainer.appendChild(paginationContainer);
    }
  }

  /**
   * التبديل إلى job محدد
   */
  async switchToJob(jobId) {
    const job = this.jobsList.find(j => j.id === jobId);
    if (!job || (job.status !== 'done' && job.status !== 'failed' && job.status !== 'review')) return;

    this.currentJobId = jobId;

    // إعادة بناء القائمة لتحديث التمييز
    this.buildJobsListUI();

    // تحديث الأزرار والواجهة حسب نوع النتيجة:
    // - إذا كان هناك ملف (download_url) → فقط زر التنزيل
    // - إذا لم يكن هناك ملف → فقط زر العرض
    if (job.download_url) {
      // يوجد ملف → فقط زر التنزيل
      this.downloadBtn.href = job.download_url;
      this.downloadBtn.style.display = 'inline-flex';
      this.resultBtn.style.display = 'none'; // إخفاء زر العرض
      // إظهار زر الحذف للأدمن في وضع الاستعراض بجانب زر التنزيل
      if (window.__IS_ADMIN_VIEWING_AS_STUDENT__ === true && this.deleteBtn) {
        this.deleteBtn.style.display = 'inline-flex';
      }
    } else {
      // لا يوجد ملف → فقط زر العرض
      this.downloadBtn.style.display = 'none'; // إخفاء زر التنزيل
      // تحديث زر النتيجة - استبدال الـ listener
      const oldResultBtn = this.resultBtn;
      const newResultBtn = oldResultBtn.cloneNode(true);
      oldResultBtn.parentNode.replaceChild(newResultBtn, oldResultBtn);
      this.resultBtn = newResultBtn;
      this.resultBtn.style.display = 'inline-flex';
      this.resultBtn.addEventListener('click', () => this.showResultForJob(jobId));
      // إظهار زر الحذف للأدمن في وضع الاستعراض بجانب زر النتيجة
      if (window.__IS_ADMIN_VIEWING_AS_STUDENT__ === true && this.deleteBtn) {
        this.deleteBtn.style.display = 'inline-flex';
      }
    }

    // تحديث الحالة
    if (job.status === 'failed') {
      this.applyState('failed', 'حدث خطأ', null, jobId, null);
    } else if (job.status === 'review') {
      this.applyState('review', 'بانتظار المراجعة', null, jobId, null);
    } else {
      this.applyState('done', 'تم الإنشاء', job.download_url || null, jobId, job.files || null);
    }
  }

  /**
   * عرض نتيجة job محدد
   */
  async showResultForJob(jobId) {
    const job = this.jobsList.find(j => j.id === jobId);
    if (!job || job.status !== 'done') {
      notifyUser('info', 'النتيجة غير متاحة');
      return;
    }

    try {
      const qs = new URLSearchParams({
        type_key: this.typeKey,
        task_id: this.context.taskId,
        job_id: jobId,
      });
      const res = await fetch(`${API_BASE}/result?${qs.toString()}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json().catch(() => ({}));

      if (!json.ok || json.state !== 'done') {
        notifyUser('info', json?.message || 'لم تكتمل النتيجة بعد.');
        return;
      }

      let data = json.result || {};
      if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch { /* keep as-is */ }
      }

      // لأداة المنتدى: جلب ردود التعليقات أيضاً
      let comments = [];
      if (this.typeKey === 'forum_reply_v1') {
        try {
          const commentsRes = await fetch(`${API_BASE}/comments?task_id=${this.context.taskId}`, {
            credentials: 'include',
            headers: { 'Accept': 'application/json' },
          });
          const commentsJson = await commentsRes.json().catch(() => ({}));
          if (commentsJson?.ok && Array.isArray(commentsJson.comments)) {
            comments = commentsJson.comments;
          }
        } catch (err) {
          console.warn('[ai-tools] Failed to fetch comments:', err);
        }
      }

      this.renderResultModal(data, comments);
    } catch (err) {
      console.error('[ai-tools] showResultForJob error:', err);
      notifyUser('error', 'حدث خطأ أثناء جلب النتيجة.');
    }
  }

  /**
   * بناء نموذج مقيم المقالات الديناميكي
   */
  async buildArticleEvaluatorForm(formConfig, contentContainer, modal) {
    try {
      // تحميل الملف الخارجي ديناميكياً
      const module = await import('./ai_article_evaluator_modal.js');
      this.articleEvaluatorModule = module.default;

      // بناء الواجهة
      const formContainer = document.createElement('form');
      formContainer.className = 'ai-tool-form';
      this.articleEvaluatorModule.buildUI(formContainer, { form: formConfig });

      // إضافة زر الإرسال
      const footer = document.createElement('footer');

      // زر تفريغ الحقول
      const resetBtn = document.createElement('button');
      resetBtn.type = 'button';
      resetBtn.className = 'btn btn-outline-danger';
      resetBtn.style.cssText = 'padding: 8px 14px; font-size: 0.9rem; border-radius: 10px; margin-left: 10px; display: inline-flex; align-items: center; gap: 6px;';
      resetBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> تفريغ الحقول';
      resetBtn.addEventListener('click', () => this.resetForm());
      footer.appendChild(resetBtn);

      const submitBtn = document.createElement('button');
      submitBtn.type = 'submit';
      submitBtn.className = 'btn btn-primary';
      submitBtn.textContent = this.placement.form?.submit_label || 'إرسال للتقييم';
      footer.appendChild(submitBtn);
      const hint = document.createElement('span');
      hint.className = 'ai-hint';
      hint.textContent = 'يتم التنفيذ في الخلفية — يمكنك متابعة عملك بشكل طبيعي.';
      footer.appendChild(hint);
      formContainer.appendChild(footer);
      this.submitBtn = submitBtn;

      // إضافة event listener للنموذج
      formContainer.addEventListener('submit', async (e) => {
        e.preventDefault();
        await this.handleArticleEvaluatorSubmit();
      });

      contentContainer.appendChild(formContainer);

      // حفظ مرجع للنموذج
      this.formEl = formContainer;
    } catch (error) {
      console.error('[article-evaluator] Failed to load modal:', error);
      contentContainer.innerHTML = '<p style="color:red;">فشل تحميل واجهة الأداة</p>';
    }
  }

  /**
   * بناء نموذج مقيم مخطط البحث الاستقصائي
   */
  async buildInquiryPlanEvaluatorForm(formConfig, contentContainer, modal) {
    try {
      const module = await import('./ai_inquiry_plan_evaluator_modal.js?v=1.0.1');
      this.inquiryPlanEvaluatorModule = module.default;

      const formContainer = document.createElement('form');
      formContainer.className = 'ai-tool-form';
      this.inquiryPlanEvaluatorModule.buildUI(formContainer, { form: formConfig });

      const footer = document.createElement('footer');

      // زر تفريغ الحقول
      const resetBtn = document.createElement('button');
      resetBtn.type = 'button';
      resetBtn.className = 'btn btn-outline-danger';
      resetBtn.style.cssText = 'padding: 8px 14px; font-size: 0.9rem; border-radius: 10px; margin-left: 10px; display: inline-flex; align-items: center; gap: 6px;';
      resetBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> تفريغ الحقول';
      resetBtn.addEventListener('click', () => this.resetForm());
      footer.appendChild(resetBtn);

      const submitBtn = document.createElement('button');
      submitBtn.type = 'submit';
      submitBtn.className = 'btn btn-primary';
      submitBtn.textContent = this.placement.form?.submit_label || 'بدء التقييم';
      footer.appendChild(submitBtn);
      const hint = document.createElement('span');
      hint.className = 'ai-hint';
      hint.textContent = 'يتم التنفيذ في الخلفية — يمكنك متابعة عملك.';
      footer.appendChild(hint);
      formContainer.appendChild(footer);
      this.submitBtn = submitBtn;

      formContainer.addEventListener('submit', async (e) => {
        e.preventDefault();
        await this.submitForm();
      });

      contentContainer.appendChild(formContainer);
    } catch (err) {
      console.error('[ai-tools] Failed to load inquiry evaluator module', err);
      contentContainer.innerHTML = '<div style="padding:20px;text-align:center;color:red">فشل تحميل واجهة التقييم</div>';
    }
  }

  /**
   * عرض نتيجة تقييم مخطط البحث الاستقصائي
   */
  renderInquiryPlanEvaluatorResult(result) {
    if (typeof result === 'string') {
      try { result = JSON.parse(result); } catch { result = {}; }
    }
    if (!result || typeof result !== 'object') result = {};

    ensureStyles();
    const root = document.getElementById('ai-tools-modals-root') || document.body;
    const backdrop = document.createElement('div');
    backdrop.className = 'ai-tool-modal-backdrop show';
    backdrop.setAttribute('aria-hidden', 'false');

    const modal = document.createElement('div');
    modal.className = 'ai-tool-modal';
    modal.style.cssText = 'max-width:1100px;display:flex;flex-direction:column;max-height:90vh;';

    // ── Header ──
    const header = document.createElement('header');
    header.style.cssText = 'flex-shrink:0;';
    const titleEl = document.createElement('div');
    titleEl.className = 'ai-modal-title';
    titleEl.innerHTML = '<i class="fa-solid fa-microscope"></i> نتيجة تقييم مخطط البحث الاستقصائي';

    const headerActions = document.createElement('div');
    headerActions.style.cssText = 'display:flex;gap:10px;align-items:center';

    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'btn btn-secondary ai-copy-btn';
    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ النتيجة';
    copyBtn.addEventListener('click', () => {
      const text = this._buildInquiryResultText(result);
      navigator.clipboard.writeText(text).then(() => {
        copyBtn.textContent = '✓ تم النسخ';
        setTimeout(() => { copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ النتيجة'; }, 2000);
      }).catch(() => notifyUser('error', 'تعذر النسخ'));
    });
    headerActions.appendChild(copyBtn);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ai-modal-close';
    closeBtn.textContent = 'إغلاق';
    closeBtn.addEventListener('click', () => {
      backdrop.classList.remove('show');
      setTimeout(() => backdrop.remove(), 200);
    });
    headerActions.appendChild(closeBtn);
    header.appendChild(titleEl);
    header.appendChild(headerActions);

    // ── Body: مقطع قابل للتمرير ──
    const body = document.createElement('div');
    body.style.cssText = 'flex:1;overflow-y:auto;overflow-x:hidden;padding:20px;';

    // ── دوال بناء ──
    const sa = (txt) => (txt || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const listHTML = (arr, color) => {
      if (!Array.isArray(arr) || !arr.length) return '';
      return `<ul style="margin:6px 0;padding-right:20px">${arr.map(i=>`<li style="margin-bottom:5px;line-height:1.7;font-size:0.91em;color:${color}">${sa(i)}</li>`).join('')}</ul>`;
    };
    const card = (title, color, icon, inner) => `
      <div style="border:1.5px solid ${color};border-radius:12px;overflow:hidden;margin-bottom:16px">
        <div style="background:${color}22;padding:10px 16px;display:flex;align-items:center;gap:8px;border-bottom:1.5px solid ${color}">
          <i class="fas ${icon}" style="color:${color}"></i>
          <strong style="color:${color};font-size:0.97em">${sa(title)}</strong>
        </div>
        <div style="padding:14px 16px">${inner}</div>
      </div>`;
    const badge = (txt) => `<span style="font-size:0.8em;background:#f0f0f0;border-radius:10px;padding:2px 10px;color:#555;display:inline-block;margin-bottom:8px">${sa(txt)}</span>`;

    let html = '';

    // 1. التقييم الإجمالي
    const es = result.evaluation_summary || {};
    const score = es.overall_score || '';
    const scoreColorMap = {'ممتاز':'#27ae60','جيد جداً':'#2196f3','جيد':'#ff9800','بحاجة إلى مراجعة جوهرية':'#e53935'};
    const sc = Object.entries(scoreColorMap).find(([k])=>score.includes(k))?.[1] || '#555';
    html += `<div style="background:${sc}18;border:2px solid ${sc}55;border-radius:12px;padding:16px 20px;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px">
        <strong style="font-size:1.05em;color:#333">التقييم الإجمالي للمخطط</strong>
        <span style="background:${sc};color:#fff;padding:6px 16px;border-radius:20px;font-weight:700;font-size:0.95em">${sa(score)}</span>
      </div>
      ${es.strengths?.length ? `<div style="font-weight:600;color:#27ae60;font-size:0.9em;margin-bottom:4px"><i class="fas fa-check-circle"></i> نقاط القوة الرئيسية</div>${listHTML(es.strengths,'#2e7d32')}` : ''}
      ${es.improvement_areas?.length ? `<div style="font-weight:600;color:#e53935;font-size:0.9em;margin-bottom:4px;margin-top:8px"><i class="fas fa-tools"></i> أولويات التحسين</div>${listHTML(es.improvement_areas,'#b71c1c')}` : ''}
    </div>`;

    // 2. التغذية الراجعة العامة
    if (result.general_feedback) {
      html += card('التغذية الراجعة العامة','#4caf50','fa-comment-dots',
        `<p style="margin:0;line-height:1.85;font-size:0.92em;white-space:pre-wrap;color:#333">${sa(result.general_feedback)}</p>`);
    }

    // 3. الأقسام الستة
    const sections = [
      {key:'section_1_feedback',label:'القسم الأول: المقدمة',color:'#2196f3',icon:'fa-book-open'},
      {key:'section_2_feedback',label:'القسم الثاني: مراجعة الأدب التربوي',color:'#9c27b0',icon:'fa-graduation-cap'},
      {key:'section_3_feedback',label:'القسم الثالث: نظرة عامة على الاستقصاء',color:'#ff9800',icon:'fa-search'},
      {key:'section_4_feedback',label:'القسم الرابع: الاعتبارات الأخلاقية',color:'#00897b',icon:'fa-balance-scale'},
      {key:'section_5_feedback',label:'القسم الخامس: النتائج',color:'#1565c0',icon:'fa-chart-bar'},
      {key:'section_6_feedback',label:'القسم السادس: النقاش التأملي',color:'#6d4c41',icon:'fa-brain'},
    ];
    sections.forEach(({key,label,color,icon}) => {
      const s = result[key];
      if (!s) return;
      let inner = '';
      if (s.word_count_judgment) inner += badge('⚖ ' + s.word_count_judgment);
      if (s.table_rows_count !== undefined) inner += `<p style="margin:4px 0 8px;font-size:0.88em;color:#555"><strong>عدد صفوف الجدول:</strong> ${s.table_rows_count} | <strong>التقييم:</strong> ${sa(s.table_judgment||'')}</p>`;
      if (s.detailed_feedback) inner += `<p style="margin:8px 0;line-height:1.85;font-size:0.92em;color:#333;white-space:pre-wrap">${sa(s.detailed_feedback)}</p>`;
      if (s.strengths?.length) inner += `<div style="font-weight:600;color:#27ae60;font-size:0.88em;margin:8px 0 3px"><i class="fas fa-check"></i> نقاط القوة</div>${listHTML(s.strengths,'#2e7d32')}`;
      if (s.improvements?.length) inner += `<div style="font-weight:600;color:#e65100;font-size:0.88em;margin:8px 0 3px"><i class="fas fa-arrow-up"></i> توجيهات التحسين</div>${listHTML(s.improvements,'#bf360c')}`;
      if (s.forbidden_methods_found?.length) inner += `<div style="background:#ffebee;border-radius:8px;padding:8px 12px;margin-top:8px"><div style="font-weight:600;color:#c62828;font-size:0.88em;margin-bottom:3px"><i class="fas fa-ban"></i> أساليب غير مسموحة</div>${listHTML(s.forbidden_methods_found,'#b71c1c')}</div>`;
      html += card(label, color, icon, inner);
    });

    // 4. تقييم المراجع
    if (result.references_feedback) {
      const rf = result.references_feedback;
      const rp = rf.required_refs_present || {};
      const refNames = {inquiry_approach:'قراءة الاستقصاء كنهج وممارسة',teacher_guide_portrait:'دليل المعلم – صورة الدرس',curriculum_framework:'الإطار العام للمنهاج',teacher_standards_qrta:'معايير المعلمين – أكاديمية الملكة رانيا 2024'};
      let inner = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px">`;
      Object.entries(refNames).forEach(([k,lbl]) => {
        const ok = rp[k] === true;
        inner += `<div style="padding:6px 10px;border-radius:8px;font-size:0.83em;background:${ok?'#e8f5e9':'#ffebee'};color:${ok?'#2e7d32':'#c62828'}"><i class="fas ${ok?'fa-check-circle':'fa-times-circle'}"></i> ${sa(lbl)}</div>`;
      });
      inner += `</div>`;
      inner += badge(`إجمالي المراجع المُدخَلة: ${rf.total_references||0}`);
      if (rf.missing_required_refs?.length) inner += `<div style="background:#fff8e1;border-radius:8px;padding:8px 12px;margin-top:8px"><div style="font-weight:600;color:#f57f17;font-size:0.88em;margin-bottom:3px"><i class="fas fa-exclamation-triangle"></i> مراجع إلزامية غائبة</div>${listHTML(rf.missing_required_refs,'#e65100')}</div>`;
      if (rf.outdated_refs?.length) inner += `<div style="background:#ffebee;border-radius:8px;padding:8px 12px;margin-top:8px"><div style="font-weight:600;color:#c62828;font-size:0.88em;margin-bottom:3px"><i class="fas fa-calendar-times"></i> مراجع متقادمة (قبل 2018)</div>${listHTML(rf.outdated_refs,'#b71c1c')}</div>`;
      if (rf.overall_ref_judgment) inner += `<p style="margin:10px 0 0;font-size:0.9em;color:#333;line-height:1.8;border-top:1px solid #e0e0e0;padding-top:8px">${sa(rf.overall_ref_judgment)}</p>`;
      html += card('تقييم المراجع','#e53935','fa-book',inner);
    }

    // 5. تقييم الملاحق
    if (result.appendices_feedback) {
      const af = result.appendices_feedback;
      const mk = (lbl,val) => `<span style="padding:4px 12px;border-radius:14px;font-size:0.83em;background:${val?'#e8f5e9':'#ffebee'};color:${val?'#2e7d32':'#c62828'}"><i class="fas ${val?'fa-check':'fa-times'}"></i> ${lbl}</span>`;
      let inner = `<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">${mk('دليل المعلم',af.has_teacher_guide)}${mk('خطط الدروس',af.has_lesson_plans)}</div>`;
      if (af.additional_appendices?.length) inner += `<p style="font-size:0.88em;color:#333;margin:4px 0"><strong>ملاحق إضافية:</strong> ${af.additional_appendices.map(sa).join('، ')}</p>`;
      if (af.overall_judgment) inner += `<p style="margin:6px 0 0;font-size:0.9em;color:#333;line-height:1.8">${sa(af.overall_judgment)}</p>`;
      html += card('تقييم الملاحق','#546e7a','fa-paperclip',inner);
    }

    // 6. الرسالة الختامية
    if (result.general_guidance) {
      html += card('رسالة ختامية للطالب-المعلم','#00695c','fa-envelope-open-text',
        `<p style="margin:0;line-height:1.9;font-size:0.95em;color:#004d40;font-style:italic;white-space:pre-wrap">${sa(result.general_guidance)}</p>`);
    }

    body.innerHTML = html;
    modal.appendChild(header);
    modal.appendChild(body);
    backdrop.appendChild(modal);
    root.appendChild(backdrop);

    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.classList.remove('show');
        setTimeout(() => backdrop.remove(), 200);
      }
    });
  }

  /**
   * بناء نص منسق لنسخ نتيجة تقييم المخطط الاستقصائي
   */

  /**
   * بناء نص منسق لنسخ نتيجة تقييم المخطط الاستقصائي
   */
  _buildInquiryResultText(result) {
    const lines = [];
    if (result.evaluation_summary?.overall_score) {
      lines.push(`التقييم الإجمالي: ${result.evaluation_summary.overall_score}`);
      lines.push('');
    }
    if (result.general_feedback) {
      lines.push('── التغذية الراجعة العامة ──');
      lines.push(result.general_feedback);
      lines.push('');
    }
    const sectionDefs = [
      ['section_1_feedback', 'القسم الأول: المقدمة'],
      ['section_2_feedback', 'القسم الثاني: مراجعة الأدب التربوي'],
      ['section_3_feedback', 'القسم الثالث: نظرة عامة على الاستقصاء'],
      ['section_4_feedback', 'القسم الرابع: الاعتبارات الأخلاقية'],
      ['section_5_feedback', 'القسم الخامس: النتائج'],
      ['section_6_feedback', 'القسم السادس: النقاش التأملي'],
    ];
    sectionDefs.forEach(([key, label]) => {
      const s = result[key];
      if (!s) return;
      lines.push(`── ${label} ──`);
      if (s.word_count_judgment) lines.push(s.word_count_judgment);
      if (s.detailed_feedback) lines.push(s.detailed_feedback);
      if (Array.isArray(s.strengths) && s.strengths.length) {
        lines.push('نقاط القوة:');
        s.strengths.forEach(i => lines.push('  • ' + i));
      }
      if (Array.isArray(s.improvements) && s.improvements.length) {
        lines.push('توجيهات التحسين:');
        s.improvements.forEach(i => lines.push('  • ' + i));
      }
      lines.push('');
    });
    if (result.general_guidance) {
      lines.push('── رسالة ختامية ──');
      lines.push(result.general_guidance);
    }
    return lines.join('\n');
  }

  /**
   * عرض نتيجة تقييم تقرير البحث الاستقصائي
   */
  renderInquiryReportEvaluatorResult(result) {
    if (typeof result === 'string') {
      try { result = JSON.parse(result); } catch { result = {}; }
    }
    if (!result || typeof result !== 'object') result = {};

    ensureStyles();
    const root = document.getElementById('ai-tools-modals-root') || document.body;
    const backdrop = document.createElement('div');
    backdrop.className = 'ai-tool-modal-backdrop show';
    backdrop.setAttribute('aria-hidden', 'false');

    const modal = document.createElement('div');
    modal.className = 'ai-tool-modal';
    modal.style.cssText = 'max-width:1000px; display:flex; flex-direction:column; max-height:90vh;';

    // ── Header ──
    const header = document.createElement('header');
    header.style.cssText = 'flex-shrink:0;';
    const titleEl = document.createElement('div');
    titleEl.className = 'ai-modal-title';
    titleEl.innerHTML = '<i class="fa-solid fa-clipboard-check"></i> نتيجة تقييم تقرير البحث الاستقصائي';

    const headerActions = document.createElement('div');
    headerActions.style.cssText = 'display:flex; gap:10px; align-items:center';

    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'btn btn-secondary ai-copy-btn';
    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ النتيجة';
    copyBtn.addEventListener('click', () => {
      const text = this._buildInquiryReportResultText(result);
      navigator.clipboard.writeText(text).then(() => {
        copyBtn.textContent = '✓ تم النسخ';
        setTimeout(() => { copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ النتيجة'; }, 2000);
      }).catch(() => notifyUser('error', 'تعذر النسخ'));
    });
    headerActions.appendChild(copyBtn);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ai-modal-close';
    closeBtn.textContent = 'إغلاق';
    closeBtn.addEventListener('click', () => {
      backdrop.classList.remove('show');
      setTimeout(() => backdrop.remove(), 200);
    });
    headerActions.appendChild(closeBtn);
    header.appendChild(titleEl);
    header.appendChild(headerActions);

    // ── Body ──
    const body = document.createElement('div');
    body.style.cssText = 'flex:1; overflow-y:auto; overflow-x:hidden; padding:20px;';

    const sa = (txt) => (txt || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const listHTML = (arr, color) => {
      if (!Array.isArray(arr) || !arr.length) return '—';
      return `<ul style="margin:6px 0; padding-right:20px">${arr.map(i=>`<li style="margin-bottom:5px; line-height:1.7; font-size:0.91em; color:${color}">${sa(i)}</li>`).join('')}</ul>`;
    };

    let html = '';

    // 1. بيانات الطالب
    const si = result.student_info || {};
    html += `
      <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:14px 18px; margin-bottom:20px; font-size:0.95rem; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <div><strong>الطالب:</strong> ${sa(si.student_name || '—')}</div>
        <div><strong>الرقم الوزاري/الأكاديمي:</strong> ${sa(si.student_id || '—')}</div>
      </div>
    `;

    // 2. التقدير الكلي
    const ov = result.overall_evaluation || {};
    const gradeColorMap = {
      'D': '#10b981', // green
      'M': '#3b82f6', // blue
      'GP': '#f59e0b', // amber
      'P': '#6366f1', // indigo
      'F': '#ef4444'  // red
    };
    const gc = gradeColorMap[ov.grade_symbol] || '#6b7280';
    html += `
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <strong style="font-size:1.05rem; color:#334155;">التقدير الكلي للمقال:</strong>
        <span style="background:${gc}; color:#fff; padding:6px 16px; border-radius:20px; font-weight:700; font-size:1rem; display:inline-flex; align-items:center; gap:8px;">
          <span>${sa(ov.grade_symbol || '—')}</span>
          <span style="font-size:0.85em; font-weight:400;">(${sa(ov.grade_word || '—')})</span>
        </span>
      </div>
    `;

    // 3. جدول المعايير
    const rubricGrades = result.rubric_grades || [];
    if (rubricGrades.length > 0) {
      html += `
        <div style="margin-bottom:24px;">
          <h5 style="color:#0f172a; margin-bottom:12px; font-size:1rem; font-weight:600;"><i class="fa-solid fa-table-list"></i> تقديرات المعايير (Rubric)</h5>
          <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.88rem; min-width:600px;">
              <thead>
                <tr style="background:#0f172a; color:#fff;">
                  <th style="padding:10px 12px; text-align:right; border:1px solid #1e293b; width:30%;">المعيار</th>
                  <th style="padding:10px 12px; text-align:center; border:1px solid #1e293b; width:20%;">التقدير</th>
                  <th style="padding:10px 12px; text-align:right; border:1px solid #1e293b; width:50%;">المبرر</th>
                </tr>
              </thead>
              <tbody>
      `;

      rubricGrades.forEach((g, idx) => {
        const bg = idx % 2 === 0 ? '#f8fafc' : '#ffffff';
        const rgc = gradeColorMap[g.grade_symbol] || '#6b7280';
        html += `
          <tr style="background:${bg};">
            <td style="padding:12px; border:1px solid #e2e8f0; font-weight:500; color:#1e293b; vertical-align:top;">${sa(g.criterion_name)}</td>
            <td style="padding:12px; border:1px solid #e2e8f0; text-align:center; vertical-align:top;">
              <span style="background:${rgc}15; color:${rgc}; padding:4px 10px; border-radius:12px; font-weight:600; font-size:0.82rem; border:1px solid ${rgc}33; display:inline-block;">
                ${sa(g.grade_symbol || '—')} — ${sa(g.grade_word || '—')}
              </span>
            </td>
            <td style="padding:12px; border:1px solid #e2e8f0; color:#475569; line-height:1.6; vertical-align:top; white-space:pre-wrap;">${sa(g.justification || '—')}</td>
          </tr>
        `;
      });

      html += `
              </tbody>
            </table>
          </div>
        </div>
      `;
    }

    // 4. التغذية الراجعة
    const fb = result.feedback_report || {};
    html += `
      <div style="margin-bottom:24px;">
        <h5 style="color:#0f172a; margin-bottom:12px; font-size:1rem; font-weight:600;"><i class="fa-solid fa-comment-medical"></i> التغذية الراجعة وجوانب التطوير</h5>
        
        <!-- نقاط القوة -->
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:16px; margin-bottom:14px;">
          <div style="font-weight:600; color:#15803d; font-size:0.95rem; margin-bottom:8px;"><i class="fas fa-check-circle"></i> نقاط القوة</div>
          ${listHTML(fb.strengths, '#166534')}
        </div>

        <!-- جوانب التطوير -->
        <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:16px; margin-bottom:14px;">
          <div style="font-weight:600; color:#b45309; font-size:0.95rem; margin-bottom:8px;"><i class="fas fa-tools"></i> جوانب التطوير والتحسين</div>
          ${listHTML(fb.areas_for_development, '#92400e')}
        </div>

        <!-- ملاحظات عامة -->
        ${fb.general_notes ? `
          <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
            <div style="font-weight:600; color:#475569; font-size:0.95rem; margin-bottom:8px;"><i class="fas fa-file-alt"></i> توجيهات وملاحظات عامة</div>
            <p style="margin:0; line-height:1.8; font-size:0.9rem; color:#334155; white-space:pre-wrap;">${sa(fb.general_notes)}</p>
          </div>
        ` : ''}
      </div>
    `;

    // 5. ملفات التحميل (إذا كانت متوفرة)
    if (result.file_feedback_id || result.file_rubric_id) {
      html += `
        <div style="background:#f8fafc; border:1.5px dashed #cbd5e1; border-radius:12px; padding:16px; display:flex; flex-direction:column; gap:10px; margin-top:20px;">
          <strong style="font-size:0.95rem; color:#475569;"><i class="fa-solid fa-download"></i> ملفات التقييم المعتمدة للتحميل:</strong>
          <div style="display:flex; gap:12px; flex-wrap:wrap;">
      `;
      if (result.file_feedback_id) {
        html += `
          <a href="/students/ai-tools/download/${result.file_feedback_id}" target="_blank" class="btn btn-secondary" style="font-size:0.88rem; padding:8px 16px; border-radius:8px; display:inline-flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-file-word" style="color:#2b579a;"></i> تحميل تقرير التغذية الراجعة (Word)
          </a>
        `;
      }
      if (result.file_rubric_id) {
        html += `
          <a href="/students/ai-tools/download/${result.file_rubric_id}" target="_blank" class="btn btn-secondary" style="font-size:0.88rem; padding:8px 16px; border-radius:8px; display:inline-flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-file-word" style="color:#2b579a;"></i> تحميل جدول معايير التقييم (Word)
          </a>
        `;
      }
      html += `
          </div>
        </div>
      `;
    }

    body.innerHTML = html;
    modal.appendChild(header);
    modal.appendChild(body);
    backdrop.appendChild(modal);
    root.appendChild(backdrop);

    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.classList.remove('show');
        setTimeout(() => backdrop.remove(), 200);
      }
    });
  }

  /**
   * بناء نص منسق لنسخ نتيجة تقييم تقرير البحث الاستقصائي
   */
  _buildInquiryReportResultText(result) {
    const lines = [];
    const si = result.student_info || {};
    if (si.student_name || si.student_id) {
      lines.push(`الطالب: ${si.student_name || '—'} | الرقم: ${si.student_id || '—'}`);
      lines.push('=========================================');
      lines.push('');
    }
    const ov = result.overall_evaluation || {};
    if (ov.grade_symbol || ov.grade_word) {
      lines.push(`التقدير الكلي للمقال: ${ov.grade_symbol || '—'} — ${ov.grade_word || '—'}`);
      lines.push('-----------------------------------------');
      lines.push('');
    }
    const rubricGrades = result.rubric_grades || [];
    if (rubricGrades.length > 0) {
      lines.push('── تقديرات المعايير ──');
      rubricGrades.forEach(g => {
        lines.push(`• المعيار: ${g.criterion_name}`);
        lines.push(`  التقدير: ${g.grade_symbol || '—'} — ${g.grade_word || '—'}`);
        lines.push(`  المبرر: ${g.justification || '—'}`);
        lines.push('');
      });
      lines.push('-----------------------------------------');
    }
    const fb = result.feedback_report || {};
    if (fb.strengths || fb.areas_for_development || fb.general_notes) {
      lines.push('── التغذية الراجعة ──');
      if (fb.strengths && fb.strengths.length > 0) {
        lines.push('نقاط القوة:');
        fb.strengths.forEach(s => lines.push(`  - ${s}`));
      }
      if (fb.areas_for_development && fb.areas_for_development.length > 0) {
        lines.push('جوانب التطوير:');
        fb.areas_for_development.forEach(d => lines.push(`  - ${d}`));
      }
      if (fb.general_notes) {
        lines.push(`توجيهات وملاحظات عامة:\n${fb.general_notes}`);
      }
    }
    return lines.join('\n');
  }

  /**
   * معالجة إرسال نموذج مقيم المقالات
   */

  async handleArticleEvaluatorSubmit() {
    console.log('[article-evaluator] Submit button clicked');

    if (!this.articleEvaluatorModule) {
      console.error('[article-evaluator] Module not loaded');
      notifyUser('error', 'خطأ في تحميل الأداة');
      return;
    }

    console.log('[article-evaluator] Module is loaded, collecting payload...');
    console.log('[article-evaluator] Editing mode:', !!this.editingJobId, 'Job ID:', this.editingJobId);

    // جمع البيانات من الواجهة (القيم الحالية بعد التعديل)
    const payload = this.articleEvaluatorModule.collectPayload();
    console.log('[article-evaluator] Payload collected from UI (after edits):', payload);

    // التحقق من البيانات
    const validation = this.articleEvaluatorModule.validatePayload(payload);
    console.log('[article-evaluator] Validation result:', validation);

    if (!validation.valid) {
      const errors = validation.errors.join('\n');
      console.error('[article-evaluator] Validation errors:', errors);
      notifyUser('error', errors);
      return;
    }

    console.log('[article-evaluator] Validation passed, proceeding with submit');

    // إذا كان في وضع التعديل، تحديث payload_json أولاً ثم إعادة تعيين job
    if (this.editingJobId) {
      console.log('[article-evaluator] Editing mode: updating payload and resetting job', this.editingJobId);
      console.log('[article-evaluator] Payload to be saved:', payload);

      // تحديث payload_json في قاعدة البيانات
      try {
        const updateRes = await fetch(`${API_BASE}/job-update-payload/${this.editingJobId}`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: JSON.stringify(payload),
        });
        const updateJson = await updateRes.json().catch(() => ({}));

        if (!updateJson.ok) {
          notifyUser('error', updateJson.message || 'فشل تحديث بيانات الطلب');
          return;
        }
        console.log('[article-evaluator] Payload updated successfully');
      } catch (err) {
        console.error('[article-evaluator] Failed to update payload:', err);
        notifyUser('error', 'فشل تحديث بيانات الطلب');
        return;
      }

      // إعادة تعيين job (حذف الملف وتحديث status)
      const resetSuccess = await this.resetJob(this.editingJobId);
      if (!resetSuccess) {
        notifyUser('error', 'فشل إعادة تعيين الطلب. يرجى المحاولة مرة أخرى.');
        return;
      }
      console.log('[article-evaluator] Job reset successful, proceeding with submit');
      // مسح editingJobId بعد إعادة التعيين
      const jobIdToClear = this.editingJobId;
      this.editingJobId = null;
    }

    // إضافة type_key و task_id
    payload.type_key = this.typeKey;
    payload.task_id = this.context.taskId;

    // التحقق من الوصول قبل الإرسال
    try {
      const qs = new URLSearchParams({
        type_key: this.typeKey,
        task_id: this.context.taskId,
      });
      const accessRes = await fetch(`${API_BASE}/check-access?${qs.toString()}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const accessData = await accessRes.json();

      if (!accessData.ok || !accessData.can_access) {
        this.showAccessDeniedModal(
          accessData.reason || 'unknown',
          accessData.points_cost || 30,
          accessData.user_points || 0,
          accessData.has_active_plan || false
        );
        return;
      }

      // إذا كان سيتم خصم النقاط، عرض Modal التأكيد
      if (accessData.reason === 'points' && accessData.points_cost > 0) {
        const userPoints = accessData.user_points || await (window.AI_TOOLS_COMMON?.getUserPoints?.() || Promise.resolve(0));
        const confirmed = await this.showPointsConfirmationModal(accessData.points_cost, userPoints);
        if (!confirmed) {
          return; // المستخدم ألغى
        }
      }
    } catch (err) {
      console.warn('[ai-tools] Access check failed:', err);
      // المتابعة مع الإرسال حتى لو فشل التحقق (للتوافق مع الإصدارات القديمة)
    }

    // تعطيل زر الإرسال
    if (this.submitBtn) {
      this.submitBtn.disabled = true;
      this.submitBtn.textContent = 'جارٍ الإرسال...';
    }

    // إضافة class لمنع التفاعل مع td-card
    document.body.classList.add('ai-tool-processing');

    try {
      // إرسال الطلب
      const res = await fetch(`${API_BASE}/request`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const json = await res.json().catch(() => ({}));

      // معالجة حالات الرد المختلفة (مثل submitForm)
      if (res.status === 200 && json.ok && json.status === 'accepted') {
        console.log('[article-evaluator] Request accepted, saving payload to cache:', payload);
        // حفظ البيانات في localStorage بعد إرسال النموذج بنجاح (القيم المعدلة)
        this.saveCachedData(payload);
        this.closeModal();
        notifyUser('success', 'تم استلام الطلب وسيتم معالجته في الخلفية.');
        // إعادة تعيين currentJobId للسماح بتحديثه في refreshStatus
        this.currentJobId = null;
        this.editingJobId = null; // مسح editingJobId بعد الإرسال الناجح
        this.refreshStatus({ withSpinner: true });
        return;
      }

      if (res.status === 402 || (res.status === 400 && json.message && (json.message.includes('نقاط') || json.message.includes('اشتراك')))) {
        this.closeModal();
        this.showAccessDeniedModal(
          json.reason || 'unknown',
          json.points_cost || 30,
          json.user_points || 0,
          json.has_active_plan || false
        );
        return;
      }

      if (res.status === 409 && json.status === 'already_active') {
        this.closeModal();
        notifyUser('info', 'لديك طلب فعّال بالفعل لهذه المهمة.');
        this.refreshStatus({ withSpinner: true });
        return;
      }

      // في حالة أي رد آخر
      if (!json.ok) {
        notifyUser('error', json.message || 'فشل الإرسال');
        return;
      }

      // نجح الإرسال
      this.closeModal();
      this.currentJobId = null;
      this.refreshStatus({ withSpinner: true });
      notifyUser('success', 'تم إرسال الطلب بنجاح! جارٍ المعالجة...');

    } catch (error) {
      console.error('[article-evaluator] Submit error:', error);
      notifyUser('error', 'حدث خطأ أثناء الإرسال');
    } finally {
      // إعادة تفعيل الزر
      if (this.submitBtn) {
        this.submitBtn.disabled = false;
        this.submitBtn.textContent = this.placement.form?.submit_label || 'إرسال الطلب';
      }
      // إزالة class لمنع التفاعل
      document.body.classList.remove('ai-tool-processing');
    }
  }

  /**
   * عرض نتائج مقيم المقالات في Modal
   */
  renderArticleEvaluatorResult(result) {
    ensureStyles();
    const root = document.getElementById('ai-tools-modals-root') || document.body;
    const backdrop = document.createElement('div');
    backdrop.className = 'ai-tool-modal-backdrop show';
    backdrop.setAttribute('aria-hidden', 'false');

    const modal = document.createElement('div');
    modal.className = 'ai-tool-modal';
    modal.style.maxWidth = '1200px';

    // Header
    const header = document.createElement('header');
    const title = document.createElement('div');
    title.className = 'ai-modal-title';
    title.innerHTML = '<i class="fa-solid fa-clipboard-check"></i> نتيجة تقييم المقال';

    const headerActions = document.createElement('div');
    headerActions.style.display = 'flex';
    headerActions.style.gap = '10px';

    // زر النسخ
    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'btn btn-secondary ai-copy-btn';
    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ التغذية الراجعة';
    copyBtn.addEventListener('click', () => this.copyArticleEvaluatorResult(result));
    headerActions.appendChild(copyBtn);

    // زر الإغلاق
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ai-modal-close';
    closeBtn.textContent = 'إغلاق';
    closeBtn.addEventListener('click', () => {
      backdrop.classList.remove('show');
      setTimeout(() => backdrop.remove(), 200);
    });
    headerActions.appendChild(closeBtn);

    header.appendChild(title);
    header.appendChild(headerActions);

    // Content
    const content = document.createElement('div');
    content.className = 'ai-modal-content';
    const body = document.createElement('div');
    body.className = 'ai-result-content';
    body.style.maxHeight = '70vh';
    body.style.overflow = 'auto';

    // التغذية الراجعة العامة
    if (result.general_feedback) {
      const feedbackSection = document.createElement('div');
      feedbackSection.className = 'ai-result-field';
      feedbackSection.style.background = '#e8f5e9';
      feedbackSection.style.border = '2px solid #4caf50';
      feedbackSection.style.padding = '20px';
      feedbackSection.style.marginBottom = '20px';

      const feedbackTitle = document.createElement('h4');
      feedbackTitle.textContent = 'التغذية الراجعة العامة';
      feedbackTitle.style.margin = '0 0 12px 0';
      feedbackTitle.style.color = '#2e7d32';

      const feedbackText = document.createElement('p');
      feedbackText.style.margin = '0';
      feedbackText.style.whiteSpace = 'pre-wrap';
      feedbackText.style.lineHeight = '1.8';
      feedbackText.textContent = result.general_feedback;

      feedbackSection.appendChild(feedbackTitle);
      feedbackSection.appendChild(feedbackText);
      body.appendChild(feedbackSection);
    }

    // أقسام المقال (المقدمة، العرض، الخاتمة)
    const sections = [
      { key: 'introduction', title: 'المقدمة', color: '#2196f3' },
      { key: 'body', title: 'العرض', color: '#ff9800' },
      { key: 'conclusion', title: 'الخاتمة', color: '#9c27b0' }
    ];

    sections.forEach(({ key, title: sectionTitle, color }) => {
      const sectionData = result[key];
      if (!sectionData) return;

      const sectionContainer = document.createElement('div');
      sectionContainer.style.marginBottom = '30px';

      // عنوان القسم
      const sectionHeader = document.createElement('h3');
      sectionHeader.textContent = sectionTitle;
      sectionHeader.style.color = color;
      sectionHeader.style.borderBottom = `3px solid ${color}`;
      sectionHeader.style.paddingBottom = '8px';
      sectionHeader.style.marginBottom = '15px';
      sectionContainer.appendChild(sectionHeader);

      // التقييم الختامي للقسم
      if (sectionData.section_feedback) {
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = 'ai-result-field';
        feedbackDiv.style.background = '#fff3e0';
        feedbackDiv.style.borderRight = `4px solid ${color}`;

        const feedbackTitle = document.createElement('h5');
        feedbackTitle.textContent = 'تقييم القسم';
        feedbackTitle.style.margin = '0 0 8px 0';
        feedbackTitle.style.color = color;

        const feedbackText = document.createElement('p');
        feedbackText.style.margin = '0';
        feedbackText.style.lineHeight = '1.7';
        feedbackText.textContent = sectionData.section_feedback;

        feedbackDiv.appendChild(feedbackTitle);
        feedbackDiv.appendChild(feedbackText);
        sectionContainer.appendChild(feedbackDiv);
      }

      // الأجزاء
      if (sectionData.parts && Array.isArray(sectionData.parts)) {
        sectionData.parts.forEach((part, partIndex) => {
          const partContainer = document.createElement('div');
          partContainer.style.marginTop = '20px';

          // عنوان الجزء
          const partTitle = document.createElement('h5');
          partTitle.textContent = `${part.part_title || 'جزء ' + (partIndex + 1)}`;
          partTitle.style.color = '#0a3247';
          partTitle.style.marginBottom = '10px';
          partContainer.appendChild(partTitle);

          // النص الأصلي مع تظليل text_anchor
          if (part.part_text) {
            const textContainer = document.createElement('div');
            textContainer.className = 'ai-result-field';
            textContainer.style.background = '#f5f5f5';
            textContainer.style.padding = '15px';
            textContainer.style.marginBottom = '15px';
            textContainer.style.borderRadius = '8px';
            textContainer.style.position = 'relative';

            const textLabel = document.createElement('div');
            textLabel.textContent = 'النص الأصلي:';
            textLabel.style.fontWeight = 'bold';
            textLabel.style.marginBottom = '8px';
            textLabel.style.color = '#0a3247';
            textContainer.appendChild(textLabel);

            const textContent = document.createElement('div');
            textContent.style.whiteSpace = 'pre-wrap';
            textContent.style.lineHeight = '1.8';

            // تظليل النص بناءً على text_anchor من feedback_items
            if (part.feedback_items && Array.isArray(part.feedback_items)) {
              textContent.innerHTML = this.highlightTextAnchors(part.part_text, part.feedback_items);
            } else {
              textContent.textContent = part.part_text;
            }

            textContainer.appendChild(textContent);
            partContainer.appendChild(textContainer);
          }

          // البنية الجديدة: feedback_items كـ Array of Objects { item_index, text_anchor, feedback_paragraph }
          if (part.feedback_items && Array.isArray(part.feedback_items)) {
            part.feedback_items.forEach((feedbackItem, itemIndex) => {
              // البنية الجديدة: Object يحتوي على item_index + text_anchor + feedback_paragraph
              if (typeof feedbackItem === 'object' && feedbackItem !== null && feedbackItem.feedback_paragraph) {
                const item = feedbackItem;
                const feedbackCard = document.createElement('div');
                feedbackCard.className = 'ai-result-field';
                feedbackCard.style.borderRight = `4px solid ${this.getFeedbackColor(itemIndex)}`;
                feedbackCard.style.marginBottom = '12px';
                feedbackCard.style.background = this.getFeedbackBgColor(itemIndex);
                feedbackCard.style.padding = '15px';

                const feedbackNumber = document.createElement('div');
                feedbackNumber.style.fontWeight = 'bold';
                feedbackNumber.style.color = this.getFeedbackColor(itemIndex);
                feedbackNumber.style.marginBottom = '8px';
                feedbackNumber.textContent = `📌 نقطة ${item.item_index || (itemIndex + 1)}`;
                feedbackCard.appendChild(feedbackNumber);

                // عرض text_anchor
                if (item.text_anchor) {
                  const anchorDiv = document.createElement('div');
                  anchorDiv.style.fontSize = '0.85rem';
                  anchorDiv.style.color = '#666';
                  anchorDiv.style.marginBottom = '8px';
                  anchorDiv.style.fontStyle = 'italic';
                  anchorDiv.textContent = `↪ "${item.text_anchor}"`;
                  feedbackCard.appendChild(anchorDiv);
                }

                // عرض feedback_paragraph (فقرة واحدة مترابطة)
                const feedbackTextEl = document.createElement('p');
                feedbackTextEl.style.margin = '0';
                feedbackTextEl.style.lineHeight = '1.7';
                feedbackTextEl.style.whiteSpace = 'pre-wrap';
                feedbackTextEl.textContent = item.feedback_paragraph;
                feedbackCard.appendChild(feedbackTextEl);

                partContainer.appendChild(feedbackCard);
              }
              // دعم البنية القديمة: Array of Strings
              else if (typeof feedbackItem === 'string') {
                const feedbackCard = document.createElement('div');
                feedbackCard.className = 'ai-result-field';
                feedbackCard.style.borderRight = `4px solid ${this.getFeedbackColor(itemIndex)}`;
                feedbackCard.style.marginBottom = '12px';
                feedbackCard.style.background = this.getFeedbackBgColor(itemIndex);
                feedbackCard.style.padding = '15px';

                const feedbackNumber = document.createElement('div');
                feedbackNumber.style.fontWeight = 'bold';
                feedbackNumber.style.color = this.getFeedbackColor(itemIndex);
                feedbackNumber.style.marginBottom = '8px';
                feedbackNumber.textContent = `📌 نقطة ${itemIndex + 1}`;
                feedbackCard.appendChild(feedbackNumber);

                const feedbackTextEl = document.createElement('p');
                feedbackTextEl.style.margin = '0';
                feedbackTextEl.style.lineHeight = '1.7';
                feedbackTextEl.style.whiteSpace = 'pre-wrap';
                feedbackTextEl.textContent = feedbackItem;
                feedbackCard.appendChild(feedbackTextEl);

                partContainer.appendChild(feedbackCard);
              }
              // دعم البنية القديمة جداً: Array of Objects مع strength/development/guiding_question
              else if (typeof feedbackItem === 'object' && feedbackItem !== null && (feedbackItem.strength || feedbackItem.development || feedbackItem.guiding_question)) {
                const item = feedbackItem;
                const feedbackCard = document.createElement('div');
                feedbackCard.className = 'ai-result-field';
                feedbackCard.style.borderRight = `4px solid ${this.getFeedbackColor(itemIndex)}`;
                feedbackCard.style.marginBottom = '12px';
                feedbackCard.style.background = this.getFeedbackBgColor(itemIndex);
                feedbackCard.style.padding = '15px';

                const feedbackNumber = document.createElement('div');
                feedbackNumber.style.fontWeight = 'bold';
                feedbackNumber.style.color = this.getFeedbackColor(itemIndex);
                feedbackNumber.style.marginBottom = '8px';
                feedbackNumber.textContent = `📌 نقطة ${item.item_index || (itemIndex + 1)}`;
                feedbackCard.appendChild(feedbackNumber);

                if (item.text_anchor) {
                  const anchorDiv = document.createElement('div');
                  anchorDiv.style.fontSize = '0.85rem';
                  anchorDiv.style.color = '#666';
                  anchorDiv.style.marginBottom = '8px';
                  anchorDiv.style.fontStyle = 'italic';
                  anchorDiv.textContent = `↪ "${item.text_anchor}"`;
                  feedbackCard.appendChild(anchorDiv);
                }

                let feedbackContent = '';
                if (item.strength) {
                  feedbackContent += `<strong style="color:#2e7d32;">✓ نقطة القوة:</strong> ${item.strength}\n\n`;
                }
                if (item.development) {
                  feedbackContent += `<strong style="color:#f57c00;">→ جانب التطوير:</strong> ${item.development}\n\n`;
                }
                if (item.guiding_question) {
                  feedbackContent += `<strong style="color:#1976d2;">؟ السؤال الموجّه:</strong> ${item.guiding_question}`;
                }

                const feedbackTextEl = document.createElement('p');
                feedbackTextEl.style.margin = '0';
                feedbackTextEl.style.lineHeight = '1.7';
                feedbackTextEl.innerHTML = feedbackContent.replace(/\n/g, '<br>');
                feedbackCard.appendChild(feedbackTextEl);

                partContainer.appendChild(feedbackCard);
              }
            });
          }

          // دعم البنية القديمة جداً: feedback_paragraph مباشرة على مستوى part
          else if (part.feedback_paragraph && typeof part.feedback_paragraph === 'string') {
            const feedbackCard = document.createElement('div');
            feedbackCard.className = 'ai-result-field';
            feedbackCard.style.borderRight = `4px solid ${this.getFeedbackColor(0)}`;
            feedbackCard.style.marginBottom = '12px';
            feedbackCard.style.background = this.getFeedbackBgColor(0);
            feedbackCard.style.padding = '15px';

            const feedbackTitle = document.createElement('div');
            feedbackTitle.style.fontWeight = 'bold';
            feedbackTitle.style.color = this.getFeedbackColor(0);
            feedbackTitle.style.marginBottom = '8px';
            feedbackTitle.textContent = '📌 التغذية الراجعة';
            feedbackCard.appendChild(feedbackTitle);

            const feedbackTextEl = document.createElement('p');
            feedbackTextEl.style.margin = '0';
            feedbackTextEl.style.lineHeight = '1.7';
            feedbackTextEl.style.whiteSpace = 'pre-wrap';
            feedbackTextEl.textContent = part.feedback_paragraph;
            feedbackCard.appendChild(feedbackTextEl);

            partContainer.appendChild(feedbackCard);
          }

          sectionContainer.appendChild(partContainer);
        });
      }

      body.appendChild(sectionContainer);
    });

    content.appendChild(body);
    modal.appendChild(header);
    modal.appendChild(content);
    backdrop.appendChild(modal);
    root.appendChild(backdrop);

    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.classList.remove('show');
        setTimeout(() => backdrop.remove(), 200);
      }
    });
  }

  /**
   * تظليل النص الأصلي بناءً على text_anchor
   */
  highlightTextAnchors(originalText, feedbackItems) {
    if (!feedbackItems || feedbackItems.length === 0 || !originalText) {
      return originalText;
    }

    const colors = [
      '#fff59d', // أصفر فاتح
      '#b3e5fc', // أزرق فاتح
      '#c8e6c9', // أخضر فاتح
      '#f8bbd0', // وردي فاتح
      '#e1bee7', // بنفسجي فاتح
    ];

    // تجميع جميع التطابقات أولاً مع فهارسها
    const allMatches = [];

    feedbackItems.forEach((item, index) => {
      // التعامل مع البنية الجديدة (Object) فقط
      if (typeof item !== 'object' || item === null || !item.text_anchor) {
        return;
      }

      const textAnchor = item.text_anchor;
      const itemIndex = item.item_index || (index + 1);
      const color = colors[itemIndex % colors.length];
      const itemNumber = itemIndex;

      // استخراج أول كلمتين وآخر كلمة من text_anchor
      // دعم عدة صيغ:
      // 1. "أول كلمتين…آخر كلمة" (Unicode ellipsis)
      // 2. "أول كلمتين (...) آخر كلمة" (three dots in parentheses)
      // 3. "أول كلمتين... آخر كلمة" (three dots)
      let startWords = '';
      let endWord = '';

      // محاولة 1: (...) between parentheses (الأكثر شيوعًا في النتائج الفعلية)
      if (textAnchor.includes('(...)')) {
        const parts = textAnchor.split('(...)');
        if (parts.length === 2) {
          startWords = parts[0].trim();
          endWord = parts[1].trim();
        }
      }
      // محاولة 2: Unicode ellipsis (…)
      else if (textAnchor.includes('…')) {
        const parts = textAnchor.split('…');
        if (parts.length === 2) {
          startWords = parts[0].trim();
          endWord = parts[1].trim();
        }
      }
      // محاولة 3: ... three dots
      else if (textAnchor.includes('...')) {
        const parts = textAnchor.split('...');
        if (parts.length === 2) {
          startWords = parts[0].trim();
          endWord = parts[1].trim();
        }
      }

      if (!startWords || !endWord) {
        console.warn('[ai-tools] Could not parse text_anchor:', textAnchor);
        return;
      }

      // تنظيف الأقواس من بداية/نهاية النص إذا كانت موجودة
      startWords = startWords.replace(/^\(+|\)+$/g, '').trim();
      endWord = endWord.replace(/^\(+|\)+$/g, '').trim();

      // استخراج أول 2-3 كلمات وآخر 1-2 كلمات
      const startWordsArray = startWords.split(/\s+/).filter(w => w.length > 0).slice(0, 3);
      const endWordArray = endWord.split(/\s+/).filter(w => w.length > 0).slice(-2);

      if (startWordsArray.length === 0 || endWordArray.length === 0) {
        console.warn('[ai-tools] Invalid start/end words:', { startWords, endWord });
        return;
      }

      // بناء regex للبحث عن النص
      const startPattern = startWordsArray.map(w => this.escapeRegex(w)).join('\\s+');
      const endPattern = endWordArray.map(w => this.escapeRegex(w)).join('\\s+');

      // البحث عن النص في originalText
      let foundMatch = false;
      const regex = new RegExp(`(${startPattern}[\\s\\S]*?${endPattern})`, 'g');
      let match;
      while ((match = regex.exec(originalText)) !== null) {
        allMatches.push({
          text: match[1],
          index: match.index,
          length: match[1].length,
          color: color,
          itemNumber: itemNumber
        });
        foundMatch = true;
        // نأخذ أول تطابق فقط لكل item
        break;
      }

      // إذا لم نجد تطابق مباشر، نبحث بطريقة أكثر مرونة (أول كلمة وآخر كلمة فقط)
      if (!foundMatch) {
        const firstWord = startWordsArray[0];
        const lastWord = endWordArray[endWordArray.length - 1];

        const flexibleRegex = new RegExp(
          `(${this.escapeRegex(firstWord)}[\\s\\S]{0,400}?${this.escapeRegex(lastWord)})`,
          'g'
        );

        const flexMatch = flexibleRegex.exec(originalText);
        if (flexMatch) {
          allMatches.push({
            text: flexMatch[1],
            index: flexMatch.index,
            length: flexMatch[1].length,
            color: color,
            itemNumber: itemNumber
          });
        }
      }
    });

    // ترتيب التطابقات من النهاية إلى البداية لتجنب مشاكل الفهارس عند التلوين
    allMatches.sort((a, b) => (b.index + b.length) - (a.index + a.length));

    // تطبيق التلوين من النهاية إلى البداية
    let highlightedText = originalText;
    allMatches.forEach(match => {
      const before = highlightedText.substring(0, match.index);
      const highlighted = `<mark style="background-color:${match.color};padding:2px 4px;border-radius:3px;"><sup style="font-weight:bold;color:#000;margin-left:3px;">${match.itemNumber}</sup>${match.text}</mark>`;
      const after = highlightedText.substring(match.index + match.length);
      highlightedText = before + highlighted + after;
    });

    return highlightedText;
  }

  /**
   * escape regex characters
   */
  escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  /**
   * الحصول على لون التغذية الراجعة
   */
  getFeedbackColor(index) {
    const colors = ['#f57c00', '#1976d2', '#388e3c', '#d32f2f', '#7b1fa2'];
    return colors[index % colors.length];
  }

  /**
   * الحصول على لون خلفية التغذية الراجعة
   */
  getFeedbackBgColor(index) {
    const colors = ['#fff3e0', '#e3f2fd', '#e8f5e9', '#ffebee', '#f3e5f5'];
    return colors[index % colors.length];
  }

  /**
   * نسخ نتائج مقيم المقالات
   */
  copyArticleEvaluatorResult(result) {
    let textToCopy = '';

    if (result.general_feedback) {
      textToCopy += '=== التغذية الراجعة العامة ===\n\n';
      textToCopy += result.general_feedback + '\n\n';
    }

    ['introduction', 'body', 'conclusion'].forEach(key => {
      const sectionData = result[key];
      if (!sectionData) return;

      const sectionTitles = {
        introduction: 'المقدمة',
        body: 'العرض',
        conclusion: 'الخاتمة'
      };

      textToCopy += `\n=== ${sectionTitles[key]} ===\n\n`;

      if (sectionData.section_feedback) {
        textToCopy += `تقييم القسم:\n${sectionData.section_feedback}\n\n`;
      }

      if (sectionData.parts && Array.isArray(sectionData.parts)) {
        sectionData.parts.forEach((part, partIndex) => {
          textToCopy += `--- ${part.part_title || 'جزء ' + (partIndex + 1)} ---\n\n`;

          // البنية الجديدة: feedback_items كـ Array of Objects { item_index, text_anchor, feedback_paragraph }
          if (part.feedback_items && Array.isArray(part.feedback_items)) {
            part.feedback_items.forEach((feedbackItem, itemIndex) => {
              // البنية الجديدة: Object يحتوي على item_index + text_anchor + feedback_paragraph
              if (typeof feedbackItem === 'object' && feedbackItem !== null && feedbackItem.feedback_paragraph) {
                const item = feedbackItem;
                textToCopy += `نقطة ${item.item_index || (itemIndex + 1)}:\n`;
                if (item.text_anchor) textToCopy += `↪ "${item.text_anchor}"\n`;
                textToCopy += `${item.feedback_paragraph}\n\n`;
              }
              // دعم البنية القديمة: Array of Strings
              else if (typeof feedbackItem === 'string') {
                textToCopy += `نقطة ${itemIndex + 1}:\n${feedbackItem}\n\n`;
              }
              // دعم البنية القديمة جداً: Array of Objects مع strength/development/guiding_question
              else if (typeof feedbackItem === 'object' && feedbackItem !== null && (feedbackItem.strength || feedbackItem.development || feedbackItem.guiding_question)) {
                const item = feedbackItem;
                textToCopy += `نقطة ${item.item_index || (itemIndex + 1)}:\n`;
                if (item.text_anchor) textToCopy += `"${item.text_anchor}"\n`;
                if (item.strength) textToCopy += `✓ نقطة القوة: ${item.strength}\n`;
                if (item.development) textToCopy += `→ جانب التطوير: ${item.development}\n`;
                if (item.guiding_question) textToCopy += `؟ السؤال الموجّه: ${item.guiding_question}\n`;
                textToCopy += '\n';
              }
            });
          }
          // دعم البنية القديمة جداً: feedback_paragraph مباشرة على مستوى part
          else if (part.feedback_paragraph && typeof part.feedback_paragraph === 'string') {
            textToCopy += `التغذية الراجعة:\n${part.feedback_paragraph}\n\n`;
          }
        });
      }
    });

    this.copyToClipboard(textToCopy);
  }

  /**
   * نسخ نتيجة job
   */
  async copyJobResult(jobId) {
    try {
      const qs = new URLSearchParams({
        type_key: this.typeKey,
        task_id: this.context.taskId,
        job_id: jobId,
      });
      const res = await fetch(`${API_BASE}/result?${qs.toString()}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json().catch(() => ({}));

      if (!json.ok || json.state !== 'done') {
        notifyUser('info', 'النتيجة غير متاحة للنسخ');
        return;
      }

      const result = json.result || {};
      let textToCopy = '';

      // بناء نص قابل للنسخ حسب نوع الأداة
      if (result.content) {
        textToCopy = result.content;
      } else if (result.text) {
        textToCopy = result.text;
      } else if (typeof result === 'string') {
        textToCopy = result;
      } else {
        textToCopy = JSON.stringify(result, null, 2);
      }

      // نسخ إلى الحافظة
      await navigator.clipboard.writeText(textToCopy);
      notifyUser('success', 'تم نسخ النتيجة بنجاح');
    } catch (err) {
      console.error('[ai-tools] copyJobResult error:', err);
      notifyUser('error', 'فشل نسخ النتيجة');
    }
  }

  /**
   * فتح مودال التعديل مع تعبئة الحقول من payload
   */
  async openEditModal(jobId) {
    try {
      // جلب payload من job
      const res = await fetch(`${API_BASE}/job-payload/${jobId}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json().catch(() => ({}));

      if (!json.ok || !json.payload) {
        notifyUser('error', 'فشل جلب بيانات الطلب');
        return;
      }

      const payload = json.payload;

      // حفظ jobId للتعديل
      this.editingJobId = jobId;

      // فتح المودال
      await this.openModal();

      // تعبئة الحقول من payload
      if (this.articleEvaluatorModule && typeof this.articleEvaluatorModule.populateFields === 'function') {
        this.articleEvaluatorModule.populateFields(payload);
      } else {
        this.populateFormFromPayload(payload);
      }

      // لا حاجة لتعديل زر الإرسال - handleArticleEvaluatorSubmit سيتعامل مع resetJob

    } catch (err) {
      console.error('[ai-tools] openEditModal error:', err);
      notifyUser('error', 'فشل فتح مودال التعديل');
    }
  }

  /**
   * تعبئة الحقول من payload (fallback إذا لم تكن populateFields موجودة)
   */
  populateFormFromPayload(payload) {
    const formContainer = this.modalBackdrop?.querySelector('.ai-tool-modal-content');
    if (!formContainer) return;

    // تعبئة الحقول الثابتة
    Object.keys(payload).forEach(key => {
      if (key === 'introduction' || key === 'body' || key === 'conclusion' || key === 'word_counts' || key === 'formatting_compliance') {
        return; // سيتم التعامل معها لاحقاً
      }

      const input = formContainer.querySelector(`[name="${key}"]`);
      if (input) {
        input.value = payload[key] || '';
      }
    });

    // تعبئة formatting_compliance (chips)
    if (payload.formatting_compliance && typeof payload.formatting_compliance === 'object') {
      const chipsContainer = formContainer.querySelector('.ai-chips[data-chips-key="formatting_compliance"]');
      if (chipsContainer) {
        Object.keys(payload.formatting_compliance).forEach(chipValue => {
          const chip = chipsContainer.querySelector(`.ai-chip[data-value="${chipValue}"]`);
          if (chip && payload.formatting_compliance[chipValue] === 1) {
            chip.classList.add('active');
          }
        });
      }
    }

    // تعبئة الأقسام الديناميكية (introduction, body, conclusion)
    ['introduction', 'body', 'conclusion'].forEach(sectionKey => {
      if (payload[sectionKey] && payload[sectionKey].parts) {
        const section = formContainer.querySelector(`.article-section[data-section-key="${sectionKey}"]`);
        if (section) {
          const partsContainer = section.querySelector('.section-parts');
          if (partsContainer) {
            // حذف الأجزاء الموجودة
            partsContainer.innerHTML = '';

            // إضافة الأجزاء من payload
            payload[sectionKey].parts.forEach((part, index) => {
              const partHTML = this.articleEvaluatorModule?.buildPartHTML(sectionKey, index + 1) || '';
              if (partHTML) {
                partsContainer.insertAdjacentHTML('beforeend', partHTML);
                const partEl = partsContainer.lastElementChild;
                if (partEl) {
                  const titleInput = partEl.querySelector('.part-title');
                  const textArea = partEl.querySelector('.part-text');
                  if (titleInput) titleInput.value = part.title || '';
                  if (textArea) textArea.value = part.text || '';
                }
              }
            });

            // تحديث عدد الكلمات
            if (this.articleEvaluatorModule) {
              ['introduction', 'body', 'conclusion'].forEach(sectionKey => {
                this.articleEvaluatorModule.updateSectionWordCount(formContainer, sectionKey);
              });
            }
          }
        }
      }
    });
  }

  /**
   * إعادة تعيين job (تحديث status إلى pending وحذف الملف)
   */
  async resetJob(jobId) {
    try {
      const res = await fetch(`${API_BASE}/job-reset/${jobId}`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });
      const json = await res.json().catch(() => ({}));

      if (!json.ok) {
        notifyUser('error', json.message || 'فشل إعادة تعيين الطلب');
        return false;
      }

      // تحديث القائمة
      await this.loadJobsList();
      this.buildJobsListUI();

      return true;
    } catch (err) {
      console.error('[ai-tools] resetJob error:', err);
      notifyUser('error', 'فشل إعادة تعيين الطلب');
      return false;
    }
  }

  /**
   * عرض تقرير التغذية الراجعة
   */
  async showFeedbackReport(jobId) {
    try {
      // جلب result و payload من job
      const [resultRes, payloadRes] = await Promise.all([
        fetch(`${API_BASE}/result?${new URLSearchParams({
          type_key: this.typeKey,
          task_id: this.context.taskId,
          job_id: jobId,
        })}`, {
          credentials: 'include',
          headers: { 'Accept': 'application/json' },
        }),
        fetch(`${API_BASE}/job-payload/${jobId}`, {
          credentials: 'include',
          headers: { 'Accept': 'application/json' },
        })
      ]);

      const resultJson = await resultRes.json().catch(() => ({}));
      const payloadJson = await payloadRes.json().catch(() => ({}));

      if (!resultJson.ok || resultJson.state !== 'done' || !resultJson.result) {
        notifyUser('error', 'النتيجة غير متاحة');
        return;
      }

      const result = resultJson.result;
      const payload = payloadJson.payload || {};

      // دمج البيانات من payload مع result
      result.teacher_name = payload.teacher_name || result.teacher_name || '';
      result.student_number = payload.student_number || result.student_number || '';
      result.teacher_supervisor = payload.teacher_supervisor || result.teacher_supervisor || '';

      // معالجة formatting_compliance من payload
      if (payload.formatting_compliance && typeof payload.formatting_compliance === 'object') {
        const active = [];
        const inactive = [];
        Object.keys(payload.formatting_compliance).forEach(item => {
          if (payload.formatting_compliance[item] === 1) {
            active.push(item);
          } else {
            inactive.push(item);
          }
        });
        let formattingText = '';
        if (active.length > 0) {
          formattingText = 'ملتزم: ' + active.join('، ');
        }
        if (inactive.length > 0) {
          formattingText += (formattingText ? ' | ' : '') + 'غير ملتزم: ' + inactive.join('، ');
        }
        result.formatting_compliance = formattingText || result.formatting_compliance || '';
      }

      console.log('[ai-tools] Building feedback report modal with result:', result);
      console.log('[ai-tools] general_guidance:', result.general_guidance);
      this.buildFeedbackReportModal(result);

    } catch (err) {
      console.error('[ai-tools] showFeedbackReport error:', err);
      notifyUser('error', 'فشل عرض تقرير التغذية الراجعة');
    }
  }

  /**
   * بناء مودال تقرير التغذية الراجعة
   */
  buildFeedbackReportModal(result) {
    console.log('[ai-tools] buildFeedbackReportModal called with result:', result);
    // إنشاء backdrop للمودال
    let backdrop = document.getElementById('feedback-report-modal-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'feedback-report-modal-backdrop';
      backdrop.className = 'modal-backdrop';
      backdrop.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; overflow-y: auto;';
      document.body.appendChild(backdrop);
    }

    // إنشاء محتوى المودال
    const modal = document.createElement('div');
    modal.className = 'modal-content';
    modal.style.cssText = 'position: relative; background: white; margin: 50px auto; max-width: 900px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); padding: 30px;';

    // Header
    const header = document.createElement('div');
    header.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e1e5e9;';

    const title = document.createElement('h3');
    title.textContent = 'تقرير التغذية الراجعة';
    title.style.cssText = 'margin: 0; color: #0a3247; font-size: 1.5rem;';
    header.appendChild(title);

    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
    closeBtn.style.cssText = 'background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; padding: 5px 10px;';
    closeBtn.addEventListener('click', () => {
      backdrop.style.display = 'none';
      document.body.style.overflow = '';
    });
    header.appendChild(closeBtn);
    modal.appendChild(header);

    // Body
    const body = document.createElement('div');
    body.className = 'feedback-report-body';
    body.style.cssText = 'max-height: 70vh; overflow-y: auto;';

    // استخراج البيانات من result
    // result_json يحتوي على evaluation_summary و general_guidance
    // نحتاج أيضاً إلى payload للحصول على teacher_name, student_number, teacher_supervisor, formatting_compliance

    // جلب payload من job (سيتم تمريره لاحقاً أو جلبها من job)
    // للآن، سنستخدم البيانات المتاحة في result

    // 1. معلومات المعلم/الطالب (من payload - سيتم جلبها لاحقاً)
    // سنستخدم job.payload_json الذي سيتم تمريره
    const teacherInfo = [];
    if (result.teacher_name) teacherInfo.push(`اسم المعلم/الطالب: ${result.teacher_name}`);
    if (result.student_number) teacherInfo.push(`رقم المعلم/الطالب: ${result.student_number}`);
    if (result.teacher_supervisor) teacherInfo.push(`مدرس المعلمين: ${result.teacher_supervisor}`);

    if (teacherInfo.length > 0) {
      this.addReportSection(body, 'معلومات المعلم/الطالب', teacherInfo, 'teacher_info');
    }

    // 2. الالتزام بتعليمات الكتابة (من payload)
    if (result.formatting_compliance) {
      this.addReportSection(body, 'الالتزام بتعليمات الكتابة', [result.formatting_compliance], 'formatting_compliance');
    }

    // استخراج البيانات من evaluation_summary
    const evaluationSummary = result.evaluation_summary || {};

    // 3. المحور الأول: المعرفة والفهم والتطبيق
    const knowledgeData = evaluationSummary['المعرفة والفهم والتطبيق'] || {};
    const knowledgeStrengths = (knowledgeData.strengths || []).map(s => s.point || s).filter(Boolean);
    const knowledgeImprovements = (knowledgeData.improvement_areas || []).map(s => s.point || s).filter(Boolean);
    this.addReportAxisSection(body, 'المحور الأول: المعرفة والفهم والتطبيق',
      knowledgeStrengths,
      knowledgeImprovements,
      'knowledge');

    // 4. المحور الثاني: المهارات الإدراكية
    const cognitiveData = evaluationSummary['المهارات الإدراكيّة'] || evaluationSummary['المهارات الإدراكية'] || {};
    const cognitiveStrengths = (cognitiveData.strengths || []).map(s => s.point || s).filter(Boolean);
    const cognitiveImprovements = (cognitiveData.improvement_areas || []).map(s => s.point || s).filter(Boolean);
    this.addReportAxisSection(body, 'المحور الثاني: المهارات الإدراكية',
      cognitiveStrengths,
      cognitiveImprovements,
      'cognitive');

    // 5. المحور الثالث: المهارات المهنية
    const professionalData = evaluationSummary['المهارات المهنيّة'] || evaluationSummary['المهارات المهنية'] || {};
    const professionalStrengths = (professionalData.strengths || []).map(s => s.point || s).filter(Boolean);
    const professionalImprovements = (professionalData.improvement_areas || []).map(s => s.point || s).filter(Boolean);
    this.addReportAxisSection(body, 'المحور الثالث: المهارات المهنية',
      professionalStrengths,
      professionalImprovements,
      'professional');

    // 6. المحور الرابع: العرض والتقديم
    const presentationData = evaluationSummary['التقديم'] || {};
    const presentationStrengths = (presentationData.strengths || []).map(s => s.point || s).filter(Boolean);
    const presentationImprovements = (presentationData.improvement_areas || []).map(s => s.point || s).filter(Boolean);
    this.addReportAxisSection(body, 'المحور الرابع: العرض والتقديم',
      presentationStrengths,
      presentationImprovements,
      'presentation');

    // 7. الإرشادات العامة
    const generalGuidance = result.general_guidance || '';
    console.log('[ai-tools] general_guidance value:', generalGuidance, 'type:', typeof generalGuidance, 'length:', generalGuidance?.length);
    if (generalGuidance && String(generalGuidance).trim()) {
      console.log('[ai-tools] Adding general_guidance section');
      this.addReportSection(body, 'الإرشادات العامة', [String(generalGuidance).trim()], 'general_guidance');
    } else {
      console.log('[ai-tools] general_guidance is empty or missing, skipping section');
    }

    modal.appendChild(body);
    backdrop.innerHTML = '';
    backdrop.appendChild(modal);
    backdrop.style.display = 'block';
    document.body.style.overflow = 'hidden';

    // إغلاق عند النقر خارج المودال
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.style.display = 'none';
        document.body.style.overflow = '';
      }
    });
  }

  /**
   * إضافة قسم في تقرير التغذية الراجعة
   */
  addReportSection(container, title, content, sectionId) {
    console.log('[ai-tools] addReportSection called:', { title, content, sectionId });
    const section = document.createElement('div');
    section.className = 'feedback-report-section';
    section.style.cssText = 'margin-bottom: 30px;';

    const sectionHeader = document.createElement('div');
    sectionHeader.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;';

    const sectionTitle = document.createElement('h4');
    sectionTitle.textContent = title;
    sectionTitle.style.cssText = 'margin: 0; color: #0a3247; font-size: 1.2rem; font-weight: bold;';
    sectionHeader.appendChild(sectionTitle);

    // زر النسخ
    const copyBtn = document.createElement('button');
    copyBtn.className = 'btn btn-sm btn-secondary';
    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ';
    copyBtn.style.cssText = 'padding: 6px 12px; font-size: 0.85rem;';
    copyBtn.addEventListener('click', () => {
      const textToCopy = `${title}\n${content.join('\n')}`;
      this.copySectionContent(sectionId, title, textToCopy);
    });
    sectionHeader.appendChild(copyBtn);

    section.appendChild(sectionHeader);

    const sectionContent = document.createElement('div');
    sectionContent.style.cssText = 'background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e1e5e9;';

    content.forEach(item => {
      const p = document.createElement('p');
      p.textContent = item;
      p.style.cssText = 'margin: 8px 0; color: #1f2933; line-height: 1.6;';
      sectionContent.appendChild(p);
    });

    section.appendChild(sectionContent);
    container.appendChild(section);
  }

  /**
   * إضافة قسم محور (نقاط القوة والتحسين)
   */
  addReportAxisSection(container, axisTitle, strengths, improvements, axisId) {
    const axisSection = document.createElement('div');
    axisSection.className = 'feedback-report-axis';
    axisSection.style.cssText = 'margin-bottom: 30px;';

    const axisHeader = document.createElement('h4');
    axisHeader.textContent = axisTitle;
    axisHeader.style.cssText = 'margin: 0 0 20px 0; color: #0a3247; font-size: 1.2rem; font-weight: bold;';
    axisSection.appendChild(axisHeader);

    // نقاط القوة
    if (strengths && strengths.length > 0) {
      const strengthsSection = document.createElement('div');
      strengthsSection.style.cssText = 'margin-bottom: 20px;';

      const strengthsHeader = document.createElement('div');
      strengthsHeader.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;';

      const strengthsTitle = document.createElement('h5');
      strengthsTitle.textContent = 'نقاط القوة';
      strengthsTitle.style.cssText = 'margin: 0; color: #059669; font-size: 1rem; font-weight: bold;';
      strengthsHeader.appendChild(strengthsTitle);

      const copyBtn = document.createElement('button');
      copyBtn.className = 'btn btn-sm btn-secondary';
      copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ';
      copyBtn.style.cssText = 'padding: 6px 12px; font-size: 0.85rem;';
      copyBtn.addEventListener('click', () => {
        const textToCopy = `${axisTitle}\nنقاط القوة\n${this.formatStrengthsOrImprovements(strengths)}`;
        this.copySectionContent(`${axisId}_strengths`, `${axisTitle} - نقاط القوة`, textToCopy);
      });
      strengthsHeader.appendChild(copyBtn);

      strengthsSection.appendChild(strengthsHeader);

      const strengthsContent = document.createElement('div');
      strengthsContent.style.cssText = 'background: #f0fdf4; padding: 15px; border-radius: 8px; border: 1px solid #86efac;';
      strengthsContent.innerHTML = this.formatStrengthsOrImprovements(strengths, true);
      strengthsSection.appendChild(strengthsContent);
      axisSection.appendChild(strengthsSection);
    }

    // نقاط التحسين
    if (improvements && improvements.length > 0) {
      const improvementsSection = document.createElement('div');
      improvementsSection.style.cssText = 'margin-bottom: 20px;';

      const improvementsHeader = document.createElement('div');
      improvementsHeader.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;';

      const improvementsTitle = document.createElement('h5');
      improvementsTitle.textContent = 'نقاط التحسين';
      improvementsTitle.style.cssText = 'margin: 0; color: #dc2626; font-size: 1rem; font-weight: bold;';
      improvementsHeader.appendChild(improvementsTitle);

      const copyBtn = document.createElement('button');
      copyBtn.className = 'btn btn-sm btn-secondary';
      copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> نسخ';
      copyBtn.style.cssText = 'padding: 6px 12px; font-size: 0.85rem;';
      copyBtn.addEventListener('click', () => {
        const textToCopy = `${axisTitle}\nنقاط التحسين\n${this.formatStrengthsOrImprovements(improvements)}`;
        this.copySectionContent(`${axisId}_improvements`, `${axisTitle} - نقاط التحسين`, textToCopy);
      });
      improvementsHeader.appendChild(copyBtn);

      improvementsSection.appendChild(improvementsHeader);

      const improvementsContent = document.createElement('div');
      improvementsContent.style.cssText = 'background: #fef2f2; padding: 15px; border-radius: 8px; border: 1px solid #fca5a5;';
      improvementsContent.innerHTML = this.formatStrengthsOrImprovements(improvements, true);
      improvementsSection.appendChild(improvementsContent);
      axisSection.appendChild(improvementsSection);
    }

    container.appendChild(axisSection);
  }

  /**
   * تنسيق نقاط القوة أو التحسين
   */
  formatStrengthsOrImprovements(items, asHTML = false) {
    if (!Array.isArray(items) || items.length === 0) return '';

    if (asHTML) {
      return items.map((item, index) => {
        const text = typeof item === 'object' && item.point ? item.point : String(item);
        return `<p style="margin: 8px 0; color: #1f2933; line-height: 1.6;">${index + 1}. ${text}</p>`;
      }).join('');
    } else {
      return items.map((item, index) => {
        const text = typeof item === 'object' && item.point ? item.point : String(item);
        return `${index + 1}. ${text}`;
      }).join('\n');
    }
  }

  /**
   * نسخ محتوى قسم معين
   */
  copySectionContent(sectionId, sectionTitle, content) {
    try {
      // إزالة HTML tags إذا كان هناك
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = content;
      const plainText = tempDiv.textContent || tempDiv.innerText || content;

      // استخدام copyToClipboard الموجود في الكلاس
      this.copyToClipboard(plainText);
      notifyUser('success', `تم نسخ "${sectionTitle}"`);
    } catch (err) {
      console.error('[ai-tools] copySectionContent error:', err);
      notifyUser('error', 'فشل نسخ المحتوى');
    }
  }
}

async function mountAiTools(context) {
  ensureStyles();
  const container = document.getElementById('ai-tools-panel');
  if (!container) return;

  container.innerHTML = `
    <div class="ai-tools-empty">
      <i class="fas fa-spinner fa-spin"></i>
      <div>
        <strong>يتم التحقق من الأدوات المتاحة...</strong>
      </div>
    </div>`;

  try {
    const placements = await fetchPlacements(context);
    container.innerHTML = '';

    if (!placements.length) {
      container.innerHTML = `
        <div class="ai-tools-empty">
          <i class="fas fa-circle-info"></i>
          <div>
            <strong>لا توجد أدوات متاحة لهذه المهمة حاليًا.</strong>
            <p>ستتم إضافة أدوات إضافية قريبًا.</p>
          </div>
        </div>`;
      return;
    }

    placements.forEach(placement => {
      // تسجيل للتحقق من البيانات
      if (!placement.form || !placement.form.sections || placement.form.sections.length === 0) {
        console.warn('[ai-tool-factory] Placement missing form config:', placement.type_key, placement);
      }
      new AiToolInstance({ container, placement, context });
    });
  } catch (err) {
    console.error('[ai-tools] failed to mount', err);
    container.innerHTML = `
      <div class="ai-tools-empty">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
          <strong>تعذّر تحميل أدوات الذكاء الاصطناعي.</strong>
          <p>يرجى إعادة المحاولة لاحقًا.</p>
        </div>
      </div>`;
  }
}

export default { mount: mountAiTools };


