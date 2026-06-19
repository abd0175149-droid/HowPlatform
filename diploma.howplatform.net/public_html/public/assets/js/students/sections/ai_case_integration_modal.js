// students/sections/ai_case_integration_modal.js
// Requires in layout.php: <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

const UI = {
  root: null, openBtn: null, downloadBtn: null, statusBadge: null,
  modalBackdrop: null, closeModalBtn: null, form: null, submitBtn: null,
  fields: {},
};

// -------------------- Cases Data --------------------
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
    'case_integration_v1'
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

async function checkStatus(opts = {}) {
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
  console.log('[Case Integration] submitRequest called');
  
  if(!UI.submitBtn) {
    console.error('[Case Integration] UI.submitBtn is null!');
    UI.submitBtn = document.getElementById('btnSubmit');
    if (!UI.submitBtn) {
      showError('تعذر العثور على زر الإرسال. يرجى إعادة تحميل الصفحة.');
      return;
    }
  }
  
  console.log('[Case Integration] Disabling submit button');
  UI.submitBtn.disabled = true;

  const teacher = UI.fields.teacher_name?.value.trim();
  const specialty = UI.fields.teacher_specialty?.value.trim();
  const caseCategory = UI.fields.case_category?.value.trim();
  const caseValue = UI.fields.case?.value.trim();
  const caseDescription = UI.fields.case_description?.value.trim();

  if(!teacher){
    showInfo('يرجى إدخال اسم المعلم الطالب.');
    UI.submitBtn.disabled = false;
    return;
  }

  if(!specialty){
    showInfo('يرجى إدخال التخصص.');
    UI.submitBtn.disabled = false;
    return;
  }

  if(!caseCategory){
    showInfo('يرجى اختيار تصنيف الحالة.');
    UI.submitBtn.disabled = false;
    return;
  }

  if(!caseValue){
    showInfo('يرجى اختيار نوع الحالة.');
    UI.submitBtn.disabled = false;
    return;
  }

  if(!caseDescription || caseDescription.length < 20){
    showInfo('وصف الحالة يجب أن يحتوي على 20 حرف على الأقل.');
    UI.submitBtn.disabled = false;
    return;
  }

  // جلب معايير المعلمين من task_prompt إذا كان متوفراً
  let criteria = [];
  const taskId = getTaskId();
  if (taskId && taskId !== '0') {
    try {
      const res = await fetch(`/students/ai-tools/get-task-prompt?task_id=${taskId}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'include'
      });
      const json = await res.json().catch(() => ({}));
      
      if (json.ok && json.task_prompt) {
        try {
          const parsed = JSON.parse(json.task_prompt);
          if (parsed && parsed.criteria && Array.isArray(parsed.criteria)) {
            criteria = parsed.criteria;
          }
        } catch (e) {
          // إذا لم يكن JSON، تجاهل
        }
      }
    } catch (e) {
      console.warn('[Case Integration] Failed to load criteria:', e);
    }
  }

  // بناء قيمة case كاملة: التصنيف + النوع
  const fullCaseValue = `${caseCategory} - ${caseValue}`;

  const payload = {
    type_key: getTypeKey(),
    task_id: Number(getTaskId() || '0'),

    teacher_name: teacher,
    teacher_specialty: specialty,
    case: fullCaseValue,
    case_description: caseDescription,
    criteria: criteria,
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

// -------------------- Mount --------------------
async function _mount(){
  UI.root=document.getElementById('aiCaseIntegrationRoot');
  UI.openBtn=document.getElementById('btnOpenModal');
  UI.downloadBtn=document.getElementById('btnDownload');
  UI.statusBadge=document.getElementById('statusBadge');
  UI.modalBackdrop=document.getElementById('ciModalBackdrop');
  UI.closeModalBtn=document.getElementById('btnCloseModal');
  UI.form=document.getElementById('caseIntegrationForm');
  UI.submitBtn=document.getElementById('btnSubmit');

  UI.fields={
    type_key: document.getElementById('type_key'),
    task_id:  document.getElementById('task_id'),
    teacher_name: document.getElementById('teacher_name'),
    teacher_specialty: document.getElementById('teacher_specialty'),
    case_category: document.getElementById('case_category'),
    case: document.getElementById('case'),
    case_description: document.getElementById('case_description'),
  };

  if(UI.fields.type_key && !UI.fields.type_key.value) UI.fields.type_key.value=getTypeKey();
  if(UI.fields.task_id && !UI.fields.task_id.value)   UI.fields.task_id.value=getTaskId()||'0';

  // معالجة الحالات المتسلسلة: عند اختيار التصنيف، تحديث قائمة النوع
  if (UI.fields.case_category && UI.fields.case) {
    UI.fields.case_category.addEventListener('change', () => {
      const category = UI.fields.case_category.value;
      const caseSelect = UI.fields.case;
      
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
  }

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
      text:'هل أنت متأكد من إرسال طلب دمج الحالة؟',
      showCancelButton:true,confirmButtonText:'نعم، أرسل',cancelButtonText:'إلغاء',
      heightAuto:false,zIndex:SWZ
    });
    if(confirm.isConfirmed) submitRequest();
  });

  // حالة الزر/التنزيل عند التحميل
  await checkStatus({ withSpinner: true, silent: true });
  startStatusAutoRefresh();
}

async function init(){
  try{
    await _mount();
  } catch(e){
    console.warn('[ai_case_integration] root not found, abort init.', e?.message||e);
  }
}

/* ===== UMD Shim ===== */
try {
  window.AI_CASE_INTEGRATION_MODAL = window.AI_CASE_INTEGRATION_MODAL || {};
  window.AI_CASE_INTEGRATION_MODAL.init  = init;
  window.AI_CASE_INTEGRATION_MODAL.open  = openModal;
  window.AI_CASE_INTEGRATION_MODAL.close = closeModal;
  
  // دالة للاستخدام من ai_tool_factory
  window.initCaseIntegrationModal = async function(taskId) {
    console.log('[Case Integration] initCaseIntegrationModal called with taskId:', taskId);
    
    if (UI.fields.task_id) {
      UI.fields.task_id.value = String(taskId || '');
    }
  };
} catch {}

/* ===== ESM Exports ===== */
export { init, openModal, closeModal };
export default { init, open: openModal, close: closeModal };

