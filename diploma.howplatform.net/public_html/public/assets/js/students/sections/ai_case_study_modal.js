// students/sections/ai_case_study_modal.js
// Requires in layout.php: <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

const UI = {
  root: null, openBtn: null, downloadBtn: null, statusBadge: null,
  modalBackdrop: null, closeModalBtn: null, form: null, submitBtn: null,
  fields: {},
};

// -------------------- Helpers --------------------
function urlParam(name) { return new URLSearchParams(location.search).get(name); }

function getTaskId() {
  const fromPayload = (window.__pendingSectionPayload && window.__pendingSectionPayload.task_id) || null;
  return String(
    UI.root?.dataset.taskId ||
    fromPayload ||
    urlParam('task_id') ||
    UI.fields.task_id?.value ||
    '0'
  ).trim();
}

function getTypeKey() {
  return (
    UI.root?.dataset.typeKey ||
    urlParam('type_key') ||
    UI.fields.type_key?.value ||
    'case_study_v1'
  );
}

function getCsrfHeader() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? { 'X-CSRF-TOKEN': meta.getAttribute('content') || '' } : {};
}

function showEl(el){ if(!el) return; el.hidden=false; el.style.display='block'; }
function hideEl(el){ if(!el) return; el.hidden=true;  el.style.display='none'; }

// -------------------- Modal open/close --------------------
function openModal() {
  if (!UI.modalBackdrop) return;
  UI.modalBackdrop.style.display = 'flex';
  UI.modalBackdrop.setAttribute('aria-hidden', 'false');
  // تحميل نص الحالة من task_prompt إذا كان متوفراً
  loadCaseTextFromTask();
}

function closeModal() {
  if (!UI.modalBackdrop) return;
  UI.modalBackdrop.style.display = 'none';
  UI.modalBackdrop.setAttribute('aria-hidden', 'true');
}

function setStatusBadge(text) {
  if (!UI.statusBadge) return;
  UI.statusBadge.style.display = text ? 'inline-flex' : 'none';
  UI.statusBadge.textContent = text || '';
}
function disableRequest(msg = '') {
  if (UI.openBtn) UI.openBtn.disabled = true;
  setStatusBadge(msg);
}
function enableRequest() {
  if (UI.openBtn) UI.openBtn.disabled = false;
  setStatusBadge('');
}
function showDownload(url) {
  if (!url || !UI.downloadBtn) return;
  UI.downloadBtn.href = url;
  UI.downloadBtn.style.display = 'inline-flex';
}
function hideDownload() {
  if (!UI.downloadBtn) return;
  UI.downloadBtn.style.display = 'none';
  UI.downloadBtn.removeAttribute('href');
}

// -------------------- Load case text from task --------------------
async function loadCaseTextFromTask() {
  const taskId = getTaskId();
  if (!taskId || taskId === '0') {
    console.warn('[Case Study] No task ID provided');
    return;
  }

  console.log('[Case Study] Loading task prompt for task_id:', taskId);

  try {
    const res = await fetch(`/students/ai-tools/get-task-prompt?task_id=${taskId}`, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include'
    });
    const json = await res.json().catch(() => ({}));
    
    if (json.ok && json.task_prompt) {
      const taskPrompt = json.task_prompt.trim();
      console.log('[Case Study] Received task_prompt length:', taskPrompt.length);
      
      // محاولة تحليل JSON
      try {
        const caseStudyData = JSON.parse(taskPrompt);
        console.log('[Case Study] Parsed JSON:', caseStudyData);
        if (caseStudyData && caseStudyData.type === 'case_study') {
          console.log('[Case Study] Found case_study data with', caseStudyData.cases?.length || 0, 'cases');
          // تحميل بيانات دراسة الحالة من JSON
          loadCaseStudyData(caseStudyData);
          return;
        }
      } catch (e) {
        console.warn('[Case Study] Failed to parse as JSON:', e);
        // إذا لم يكن JSON، استخدمه كنص عادي
      }
      
      // استخدام النص العادي (للتوافق مع البيانات القديمة)
      if (UI.fields.case_text) {
        UI.fields.case_text.value = taskPrompt;
        detectMultipleCases(taskPrompt);
      }
    } else {
      console.warn('[Case Study] No task_prompt in response:', json);
    }
  } catch (e) {
    console.warn('[Case Study] Failed to load case text:', e);
  }
}

// -------------------- Load case study data from JSON --------------------
function loadCaseStudyData(data) {
  // تحميل المعايير
  if (data.criteria && Array.isArray(data.criteria) && data.criteria.length > 0) {
    const criteriaContainer = document.getElementById('criteriaContainer');
    const criteriaList = document.getElementById('criteriaList');
    if (criteriaContainer && criteriaList) {
      criteriaContainer.style.display = 'block';
      criteriaList.innerHTML = data.criteria.map(c => `<div style="margin-bottom: 6px;">• ${c}</div>`).join('');
    }
  } else {
    const criteriaContainer = document.getElementById('criteriaContainer');
    if (criteriaContainer) {
      criteriaContainer.style.display = 'none';
    }
  }

  // تحميل الحالات
  if (data.cases && Array.isArray(data.cases) && data.cases.length > 0) {
    // التأكد من وجود الحقل (محاولة إعادة الحصول عليه إذا لم يكن محدداً)
    let caseSelect = UI.fields.selected_case_index;
    if (!caseSelect) {
      caseSelect = document.getElementById('selected_case_index');
      if (caseSelect) {
        UI.fields.selected_case_index = caseSelect;
      }
    }
    
    const casePreview = document.getElementById('casePreview');
    const casePreviewField = document.getElementById('casePreviewField');
    
    if (caseSelect) {
      caseSelect.innerHTML = '<option value="">— اختر الحالة —</option>';
      data.cases.forEach((caseData, index) => {
        const opt = document.createElement('option');
        opt.value = index;
        opt.textContent = caseData.title || `الحالة ${index + 1}`;
        caseSelect.appendChild(opt);
      });
      
      // إظهار حقل الاختيار
      const caseSelectionField = document.getElementById('caseSelectionField');
      if (caseSelectionField) {
        caseSelectionField.hidden = false;
        caseSelectionField.style.display = 'block';
      }
      
      // حفظ البيانات في متغير عام للاستخدام لاحقاً
      window.caseStudyData = data;
      
      // إزالة المستمعات القديمة (إذا كانت موجودة) لتجنب التكرار
      const newCaseSelect = caseSelect.cloneNode(true);
      caseSelect.parentNode.replaceChild(newCaseSelect, caseSelect);
      UI.fields.selected_case_index = newCaseSelect;
      
      // مستمع تغيير الحالة المختارة
      newCaseSelect.addEventListener('change', () => {
        const selectedIndex = parseInt(newCaseSelect.value);
        if (selectedIndex >= 0 && selectedIndex < data.cases.length) {
          const selectedCase = data.cases[selectedIndex];
          if (casePreview) {
            casePreview.innerHTML = `
              <h5 style="margin: 0 0 10px 0; color: #219ebc;">${selectedCase.title || `الحالة ${selectedIndex + 1}`}</h5>
              <p style="margin: 0; white-space: pre-wrap;">${selectedCase.text || ''}</p>
            `;
          }
          if (casePreviewField) {
            casePreviewField.hidden = false;
            casePreviewField.style.display = 'block';
          }
          // تعيين نص الحالة في case_text (مخفي) للاستخدام في الإرسال
          if (UI.fields.case_text) {
            UI.fields.case_text.value = selectedCase.text || '';
            const caseTextField = UI.fields.case_text.closest('.ai-field');
            if (caseTextField) {
              caseTextField.hidden = true;
            }
          }
        } else {
          if (casePreview) {
            casePreview.innerHTML = '<p style="color: #6c757d; margin: 0;">اختر حالة لعرض معاينتها</p>';
          }
          if (casePreviewField) {
            casePreviewField.hidden = true;
          }
          if (UI.fields.case_text) {
            UI.fields.case_text.value = '';
          }
        }
      });
      
      console.log('[Case Study] Loaded', data.cases.length, 'cases');
    } else {
      console.warn('[Case Study] selected_case_index element not found');
    }
  } else {
    // إذا لم تكن هناك حالات، إخفاء حقل الاختيار
    const caseSelectionField = document.getElementById('caseSelectionField');
    if (caseSelectionField) {
      caseSelectionField.hidden = true;
    }
  }
}

// -------------------- Detect multiple cases --------------------
function detectMultipleCases(text) {
  const caseSelect = UI.fields.selected_case_index;
  if (!caseSelect) return;

  // محاولة استخراج الحالات
  const patterns = [
    /الحالة\s+(?:الأولى|الثانية|الثالثة|الرابعة|الخامسة|1|2|3|4|5)[:：]/g,
    /Case\s+(?:1|2|3|4|5|One|Two|Three|Four|Five)[:：]/gi,
  ];

  let cases = [];
  let found = false;

  for (const pattern of patterns) {
    const matches = [...text.matchAll(pattern)];
    if (matches.length > 1) {
      found = true;
      // استخراج الحالات
      for (let i = 0; i < matches.length; i++) {
        const start = matches[i].index;
        const end = i < matches.length - 1 ? matches[i + 1].index : text.length;
        const caseText = text.substring(start, end).trim();
        if (caseText) {
          cases.push({
            index: i,
            title: `الحالة ${i + 1}`,
            text: caseText.substring(0, 100) + '...'
          });
        }
      }
      break;
    }
  }

  // تحديث قائمة الاختيار
  caseSelect.innerHTML = '<option value="-1">— جميع الحالات —</option>';
  if (cases.length > 1) {
    cases.forEach((c, idx) => {
      const opt = document.createElement('option');
      opt.value = idx;
      opt.textContent = c.title;
      caseSelect.appendChild(opt);
    });
    showEl(caseSelect.parentElement);
  } else {
    hideEl(caseSelect.parentElement);
  }
}

// -------------------- SweetAlert2 --------------------
const SWZ = 20000;
function showSuccess(msg){Swal.fire({icon:'success',title:'تم بنجاح',text:msg,confirmButtonText:'حسناً',heightAuto:false,zIndex:SWZ});}
function showError(msg){Swal.fire({icon:'error',title:'حدث خطأ',text:msg,confirmButtonText:'إغلاق',heightAuto:false,zIndex:SWZ});}
function showInfo(msg){Swal.fire({icon:'info',title:'تنبيه',text:msg,confirmButtonText:'حسناً',heightAuto:false,zIndex:SWZ});}
function showLoading(msg='جاري المعالجة...'){Swal.fire({title:msg,allowOutsideClick:false,heightAuto:false,zIndex:SWZ,didOpen:()=>Swal.showLoading()});}
function closeLoading(){try{Swal.close();}catch(_){}}

// -------------------- Status (auto-refresh friendly) --------------------
let _statusTimer = null;

function setStatusBadgeLoading(text){
  if (!UI.statusBadge) return;
  UI.statusBadge.style.display = 'inline-flex';
  UI.statusBadge.innerHTML = `<span class="mini-spin"></span> ${text || 'جاري التحقق...'}`;
}

function startStatusAutoRefresh(){
  if (_statusTimer) return;
  _statusTimer = setInterval(()=>{ checkStatus({ silent:true, withSpinner:true }); }, 60_000);
}

function stopStatusAutoRefresh(){
  if (_statusTimer){ clearInterval(_statusTimer); _statusTimer = null; }
}

async function checkStatus(opts = {}){
  const { silent = false, withSpinner = false } = opts;
  try{
    const taskId  = getTaskId();
    const typeKey = getTypeKey();
    if(!taskId || !typeKey){
      enableRequest();
      hideDownload();
      stopStatusAutoRefresh();
      return;
    }

    if (withSpinner) setStatusBadgeLoading('جاري التحقق من الحالة...');

    const q   = new URLSearchParams({ type_key:typeKey, task_id:taskId });
    const res = await fetch(`/students/ai-tools/status?${q.toString()}`, {
      method:'GET', headers:{ 'Accept':'application/json' }, credentials:'include'
    });
    const json = await res.json().catch(()=>({}));

    if(!json.ok){
      enableRequest();
      hideDownload();
      if(!silent) showError(json.message || 'تعذّر التحقق من الحالة.');
      stopStatusAutoRefresh();
      return;
    }

    switch(json.state){
      case 'done':
        disableRequest('تم إنشاء الملف — متاح للتنزيل');
        if (json.download_url) showDownload(json.download_url);
        stopStatusAutoRefresh();
        break;

      case 'processing':
        disableRequest('');
        if (withSpinner) setStatusBadgeLoading('قيد المعالجة...');
        else setStatusBadge('قيد المعالجة...');
        hideDownload();
        startStatusAutoRefresh();
        break;

      case 'pending':
        disableRequest('');
        if (withSpinner) setStatusBadgeLoading('في قائمة الانتظار...');
        else setStatusBadge('في قائمة الانتظار...');
        hideDownload();
        startStatusAutoRefresh();
        break;

      case 'failed':
        disableRequest('فشل سابق — راجع السجل أو أعد المحاولة');
        hideDownload();
        stopStatusAutoRefresh();
        break;

      default:
        enableRequest();
        hideDownload();
        stopStatusAutoRefresh();
    }
  }catch(e){
    console.error('status error:', e);
    if(!silent) showError('حدث خطأ أثناء التحقق من الحالة.');
  }
}

// -------------------- Submit --------------------
async function submitRequest(){
  console.log('[Case Study] submitRequest called');
  
  if(!UI.submitBtn) {
    console.error('[Case Study] UI.submitBtn is null!');
    // محاولة إعادة الحصول على الزر
    UI.submitBtn = document.getElementById('btnSubmit');
    if (!UI.submitBtn) {
      showError('تعذر العثور على زر الإرسال. يرجى إعادة تحميل الصفحة.');
      return;
    }
  }
  
  console.log('[Case Study] Disabling submit button');
  UI.submitBtn.disabled = true;

  const teacher = UI.fields.teacher_name?.value.trim();
  const grade   = UI.fields.grade_level?.value.trim();
  let caseText = UI.fields.case_text?.value.trim();

  // إذا كانت هناك حالات من JSON، استخدم النص من الحالة المختارة
  if (window.caseStudyData && window.caseStudyData.cases) {
    const selectedIndex = UI.fields.selected_case_index?.value ? parseInt(UI.fields.selected_case_index.value) : -1;
    if (selectedIndex >= 0 && selectedIndex < window.caseStudyData.cases.length) {
      const selectedCase = window.caseStudyData.cases[selectedIndex];
      caseText = selectedCase.text || '';
    }
  }

  if(!teacher || !grade){
    showInfo('يرجى تعبئة: اسم المعلم + المرحلة.');
    UI.submitBtn.disabled = false;
    return;
  }

  // التحقق من اختيار حالة إذا كانت متعددة
  if (window.caseStudyData && window.caseStudyData.cases && window.caseStudyData.cases.length > 1) {
    const selectedIndex = UI.fields.selected_case_index?.value ? parseInt(UI.fields.selected_case_index.value) : -1;
    if (selectedIndex < 0) {
      showInfo('يرجى اختيار حالة للتحليل.');
      UI.submitBtn.disabled = false;
      return;
    }
  }

  if(!caseText || caseText.length < 50){
    showInfo('نص دراسة الحالة يجب أن يحتوي على 50 حرف على الأقل.');
    UI.submitBtn.disabled = false;
    return;
  }

  // جمع المعايير من JSON (إذا كانت متوفرة)
  let criteria = [];
  if (window.caseStudyData && window.caseStudyData.criteria && Array.isArray(window.caseStudyData.criteria)) {
    criteria = window.caseStudyData.criteria.filter(c => c && c.trim());
  } else {
    // للتوافق مع البيانات القديمة (إذا كانت المعايير في واجهة)
    const criteriaInputs = document.querySelectorAll('.criterion-item');
    criteriaInputs.forEach(input => {
      const value = input.value?.trim();
      if (value) {
        criteria.push(value);
      }
    });
  }

  if (criteria.length === 0) {
    showInfo('لم يتم العثور على معايير المعلمين. يرجى التأكد من أن المهمة تحتوي على معايير.');
    UI.submitBtn.disabled = false;
    return;
  }

  const selectedCaseIndex = UI.fields.selected_case_index?.value ? parseInt(UI.fields.selected_case_index.value) : -1;
  const analysisNature = UI.fields.analysis_nature?.value?.trim();

  if (!analysisNature) {
    showInfo('يرجى اختيار طبيعة التحليل.');
    UI.submitBtn.disabled = false;
    return;
  }

  const payload = {
    type_key: getTypeKey(),
    task_id: Number(getTaskId() || '0'),

    teacher_name: teacher,
    grade_level: grade,
    case_text: caseText,
    selected_case_index: selectedCaseIndex >= 0 ? selectedCaseIndex : null,
    criteria: criteria,
    analysis_nature: analysisNature,
  };

  try{
    console.log('%c📦 JSON Payload:', 'color:#0a7; font-weight:bold;');
    console.log(JSON.stringify(payload,null,2));
  }catch(_){}

  try{
    showLoading('جاري إرسال الطلب...');
    const res = await fetch('/students/ai-tools/request', {
      method:'POST',
      headers:{
        'Content-Type':'application/json; charset=utf-8',
        'Accept':'application/json',
        ...getCsrfHeader()
      },
      credentials:'include',
      body: JSON.stringify(payload),
    });
    closeLoading();
    const json = await res.json().catch(()=>({}));

    if (res.status === 200 && json.ok && json.status === 'accepted'){
      closeModal();
      disableRequest('تم استلام الطلب — قيد المعالجة');
      hideDownload();
      if (typeof checkStatus === 'function') checkStatus({ withSpinner:true, silent:true });
      if (typeof startStatusAutoRefresh === 'function') startStatusAutoRefresh();
      showSuccess('تم استلام الطلب وسيتم تجهيز الملف في الخلفية.');
      return;
    }

    if (res.status === 409 && json.status === 'already_active'){
      closeModal();
      disableRequest('لديك طلب فعّال بالفعل');
      if (typeof checkStatus === 'function') checkStatus({ withSpinner:true, silent:true });
      if (typeof startStatusAutoRefresh === 'function') startStatusAutoRefresh();
      showInfo('لا يمكنك إرسال طلب جديد الآن لهذه المهمة.');
      return;
    }

    if (res.status === 200 && json.status === 'already_done' && json.download_url){
      closeModal();
      disableRequest('تم الإنشاء سابقًا — متاح للتنزيل');
      showDownload(json.download_url);
      if (typeof stopStatusAutoRefresh === 'function') stopStatusAutoRefresh();
      showInfo('الملف منشأ سابقًا — تم إظهار زر التنزيل.');
      return;
    }

    if (res.status === 409 && json.status === 'failed_locked'){
      closeModal();
      disableRequest('فشل سابق — يحتاج تدخّل');
      hideDownload();
      if (typeof stopStatusAutoRefresh === 'function') stopStatusAutoRefresh();
      showError('توجد عملية فاشلة مقفلة لنفس المهمة والنوع.');
      return;
    }

    showError(json.message || 'تعذّر تنفيذ الطلب. حاول لاحقًا.');
  }catch(e){
    console.error('request error:', e);
    closeLoading();
    showError('حدث خطأ أثناء الاتصال بالخادم.');
  }finally{
    UI.submitBtn.disabled = false;
  }
}

// -------------------- Add/Remove Criteria --------------------
function addCriterionRow(value = '') {
  const container = document.getElementById('criteriaContainer');
  if (!container) return;

  const div = document.createElement('div');
  div.className = 'criterion-row';
  div.innerHTML = `
    <input type="text" class="criterion-item" value="${value}" placeholder="مثال: 1.1.4 - فهم طبيعة التعلم">
    <button type="button" class="btn-remove-criterion" onclick="this.parentElement.remove()">
      <i class="fa-solid fa-trash"></i>
    </button>
  `;
  container.appendChild(div);
}

function addCriterion() {
  addCriterionRow();
}

// -------------------- Mount --------------------
async function _mount(){
  UI.root=document.getElementById('aiCaseStudyRoot');
  UI.openBtn=document.getElementById('btnOpenModal');
  UI.downloadBtn=document.getElementById('btnDownload');
  UI.statusBadge=document.getElementById('statusBadge');
  UI.modalBackdrop=document.getElementById('csModalBackdrop');
  UI.closeModalBtn=document.getElementById('btnCloseModal');
  UI.form=document.getElementById('caseStudyForm');
  UI.submitBtn=document.getElementById('btnSubmit');

  UI.fields={
    type_key: document.getElementById('type_key'),
    task_id:  document.getElementById('task_id'),
    teacher_name: document.getElementById('teacher_name'),
    grade_level: document.getElementById('grade_level'),
    case_text: document.getElementById('case_text'), // حقل مخفي
    selected_case_index: document.getElementById('selected_case_index'),
    analysis_nature: document.getElementById('analysis_nature'),
  };

  if(UI.fields.type_key && !UI.fields.type_key.value) UI.fields.type_key.value=getTypeKey();
  if(UI.fields.task_id && !UI.fields.task_id.value)   UI.fields.task_id.value=getTaskId()||'0';

  UI.openBtn?.addEventListener('click',()=>{
    if(UI.openBtn.disabled){
      showInfo('لديك طلب فعّال أو مكتمل لهذه المهمة.');
      return;
    }
    openModal();
  });
  UI.closeModalBtn?.addEventListener('click', closeModal);
  UI.modalBackdrop?.addEventListener('click',(e)=>{ if(e.target===UI.modalBackdrop) closeModal(); });

  // إغلاق بالمفتاح Escape
  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape' && UI.modalBackdrop && getComputedStyle(UI.modalBackdrop).display !== 'none'){
      closeModal();
    }
  });

  // تأكيد الإرسال
  UI.submitBtn?.addEventListener('click', async (e)=>{
    e.preventDefault();
    if(UI.openBtn?.disabled){
      showInfo('لا يمكن الإرسال الآن لوجود طلب فعّال.');
      return;
    }
    const confirm=await Swal.fire({
      icon:'question',title:'تأكيد الإرسال',
      text:'هل أنت متأكد من إرسال طلب تحليل دراسة الحالة؟',
      showCancelButton:true,confirmButtonText:'نعم، أرسل',cancelButtonText:'إلغاء',
      heightAuto:false,zIndex:SWZ
    });
    if(confirm.isConfirmed) submitRequest();
  });

  // زر إضافة معيار
  const addBtn = document.getElementById('btnAddCriterion');
  if (addBtn) {
    addBtn.addEventListener('click', addCriterion);
  }

  // حالة الزر/التنزيل عند التحميل
  await checkStatus({ withSpinner: true, silent: true });
  startStatusAutoRefresh();
}

async function init(){
  try{
    await _mount();
  } catch(e){
    console.warn('[ai_case_study] root not found, abort init.', e?.message||e);
  }
}

/* ===== UMD Shim ===== */
try {
  window.AI_CASE_STUDY_MODAL = window.AI_CASE_STUDY_MODAL || {};
  window.AI_CASE_STUDY_MODAL.init  = init;
  window.AI_CASE_STUDY_MODAL.open  = openModal;
  window.AI_CASE_STUDY_MODAL.close = closeModal;
  
  // دالة للاستخدام من ai_tool_factory
  window.initCaseStudyModal = async function(taskId) {
    console.log('[Case Study] initCaseStudyModal called with taskId:', taskId);
    
    // التأكد من تهيئة جميع الحقول والأزرار
    if (!UI.submitBtn || !UI.fields.selected_case_index) {
      console.log('[Case Study] Initializing all fields and buttons...');
      // إعادة تهيئة جميع العناصر
      UI.modalBackdrop = document.getElementById('csModalBackdrop');
      UI.closeModalBtn = document.getElementById('btnCloseModal');
      UI.form = document.getElementById('caseStudyForm');
      UI.submitBtn = document.getElementById('btnSubmit');
      
      UI.fields.type_key = document.getElementById('type_key');
      UI.fields.task_id = document.getElementById('task_id');
      UI.fields.teacher_name = document.getElementById('teacher_name');
      UI.fields.grade_level = document.getElementById('grade_level');
      UI.fields.case_text = document.getElementById('case_text');
      UI.fields.selected_case_index = document.getElementById('selected_case_index');
      UI.fields.analysis_nature = document.getElementById('analysis_nature');
      
      // ربط الأحداث إذا لم تكن مربوطة
      if (UI.submitBtn) {
        // إزالة المستمع القديم إذا كان موجوداً
        const newSubmitBtn = UI.submitBtn.cloneNode(true);
        UI.submitBtn.parentNode.replaceChild(newSubmitBtn, UI.submitBtn);
        UI.submitBtn = newSubmitBtn;
        
        UI.submitBtn.addEventListener('click', async (e) => {
          e.preventDefault();
          e.stopPropagation();
          console.log('[Case Study] Submit button clicked');
          
          // التحقق من أن الحقول محددة
          if (!UI.fields.teacher_name || !UI.fields.grade_level) {
            console.warn('[Case Study] Fields not initialized, reinitializing...');
            UI.fields.teacher_name = document.getElementById('teacher_name');
            UI.fields.grade_level = document.getElementById('grade_level');
            UI.fields.analysis_nature = document.getElementById('analysis_nature');
          }
          
          if (UI.openBtn?.disabled) {
            showInfo('لا يمكن الإرسال الآن لوجود طلب فعّال.');
            return;
          }
          
          const confirm = await Swal.fire({
            icon: 'question',
            title: 'تأكيد الإرسال',
            text: 'هل أنت متأكد من إرسال طلب تحليل دراسة الحالة؟',
            showCancelButton: true,
            confirmButtonText: 'نعم، أرسل',
            cancelButtonText: 'إلغاء',
            heightAuto: false,
            zIndex: SWZ
          });
          
          if (confirm.isConfirmed) {
            console.log('[Case Study] User confirmed, calling submitRequest...');
            await submitRequest();
          }
        });
        console.log('[Case Study] Submit button event listener attached');
      } else {
        console.error('[Case Study] btnSubmit element not found!');
      }
      
      if (UI.closeModalBtn && !UI.closeModalBtn.hasAttribute('data-listener-attached')) {
        UI.closeModalBtn.setAttribute('data-listener-attached', 'true');
        UI.closeModalBtn.addEventListener('click', closeModal);
      }
      
      if (UI.modalBackdrop && !UI.modalBackdrop.hasAttribute('data-listener-attached')) {
        UI.modalBackdrop.setAttribute('data-listener-attached', 'true');
        UI.modalBackdrop.addEventListener('click', (e) => {
          if (e.target === UI.modalBackdrop) closeModal();
        });
      }
    }
    
    if (UI.fields.task_id) {
      UI.fields.task_id.value = String(taskId || '');
    }
    
    // تحميل البيانات من task_prompt
    if (taskId) {
      // انتظار قصير للتأكد من أن DOM جاهز
      await new Promise(resolve => setTimeout(resolve, 100));
      await loadCaseTextFromTask();
    }
  };
} catch {}

/* ===== ESM Exports ===== */
export { init, openModal, closeModal };
export default { init, open: openModal, close: closeModal };

