/**
 * ai_stakeholder_integration_modal.js
 * ملف التحكم في نافذة "دمج أصحاب العلاقة" للطلاب
 */

document.addEventListener('DOMContentLoaded', () => {
    const siModalBackdrop = document.getElementById('siModalBackdrop');
    if (!siModalBackdrop) return;
  
    const btnCloseSiModal = document.getElementById('btnCloseSiModal');
    const form = document.getElementById('stakeholderIntegrationForm');
    const btnSubmitSi = document.getElementById('btnSubmitSi');
  
    // الحقول
    const taskIdInput = document.getElementById('si_task_id');
    const criteriaContainer = document.getElementById('siCriteriaContainer');
    const criteriaList = document.getElementById('siCriteriaList');
    
    // متغير لتخزين المعايير (للإرسال)
    let currentCriteria = [];
  
    // 1. فتح النافذة
    window.openStakeholderIntegrationModal = function(taskId, pointsCost, taskPrompt) {
      if (taskIdInput) taskIdInput.value = taskId;
      
      // تهيئة النموذج
      form.reset();
      currentCriteria = [];
      
      // محاولة استخراج المعايير من taskPrompt إذا كان موجوداً ومكتوباً بصيغة JSON
      if (taskPrompt) {
        try {
          const parsed = typeof taskPrompt === 'string' ? JSON.parse(taskPrompt) : taskPrompt;
          if (parsed && parsed.criteria && Array.isArray(parsed.criteria)) {
            currentCriteria = parsed.criteria;
          }
        } catch (e) {
          console.warn('Failed to parse task_prompt for criteria', e);
        }
      }
      
      // عرض المعايير في الواجهة إذا وجدت
      if (currentCriteria.length > 0) {
        criteriaContainer.style.display = 'flex';
        let html = '<ul style="margin:0; padding-right:20px;">';
        currentCriteria.forEach(c => {
          if (typeof c === 'string') {
            html += `<li>${c}</li>`;
          } else if (c.code && c.description) {
            html += `<li><strong>${c.code}:</strong> ${c.description}</li>`;
          }
        });
        html += '</ul>';
        criteriaList.innerHTML = html;
      } else {
        criteriaContainer.style.display = 'none';
      }
      
      siModalBackdrop.style.display = 'flex';
    };
  
    // 2. إغلاق النافذة
    const closeModal = () => {
      siModalBackdrop.style.display = 'none';
      form.reset();
    };
    if (btnCloseSiModal) btnCloseSiModal.addEventListener('click', closeModal);
  
    siModalBackdrop.addEventListener('click', (e) => {
      if (e.target === siModalBackdrop) closeModal();
    });
  
    // 3. إرسال الطلب
    if (btnSubmitSi) {
      btnSubmitSi.addEventListener('click', async (e) => {
        e.preventDefault();
        
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        
        const taskId = taskIdInput.value;
        const typeKey = document.getElementById('si_type_key').value;
        
        // تجميع البيانات
        const payloadData = {
          type_key: typeKey,
          task_id: taskId,
          grade_level: document.getElementById('si_grade_level').value,
          subject_area: document.getElementById('si_subject_area').value,
          learning_outcome: document.getElementById('si_learning_outcome').value,
          stakeholder_role: document.getElementById('si_stakeholder_role').value,
          criteria: currentCriteria
        };
  
        // التحقق من النقاط
        try {
          btnSubmitSi.disabled = true;
          btnSubmitSi.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري التحقق...';
          
          const checkRes = await fetch(`/students/ai-tools/check-access?type_key=${typeKey}&task_id=${taskId}`);
          const checkData = await checkRes.json();
          
          if (!checkData.ok || !checkData.can_access) {
            Swal.fire({
              icon: 'error',
              title: 'لا يمكن التنفيذ',
              text: checkData.message || 'ليس لديك رصيد كافٍ أو أنك استنفذت المحاولات.'
            });
            return;
          }
  
          // تأكيد الخصم
          const costMsg = checkData.has_free_usage 
            ? 'هذه المحاولة <b>مجانية</b> (تمت إعادة تعيين الطلب السابق بنجاح).'
            : `سيتم خصم <b>${checkData.points_cost}</b> نقطة من رصيدك.`;
            
          const result = await Swal.fire({
            title: 'تأكيد التنفيذ',
            html: costMsg + '<br><br>هل أنت متأكد من رغبتك في المتابعة وتوليد الأداة؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، نفذ',
            cancelButtonText: 'إلغاء'
          });
  
          if (!result.isConfirmed) return;
  
          // إرسال الطلب الفعلي
          btnSubmitSi.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الإرسال...';
          const reqRes = await fetch('/students/ai-tools/request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payloadData)
          });
          const reqData = await reqRes.json();
  
          if (reqData.ok) {
            Swal.fire({
              icon: 'success',
              title: 'تم إرسال الطلب',
              text: 'جاري العمل عليه في الخلفية. يمكنك متابعة الحالة في صفحة المهمة.'
            }).then(() => {
              closeModal();
              if (window.jobsListApp && typeof window.jobsListApp.loadJobs === 'function') {
                window.jobsListApp.loadJobs(1);
              } else {
                window.location.reload();
              }
            });
          } else {
            Swal.fire('خطأ', reqData.message || 'حدث خطأ أثناء الإرسال', 'error');
          }
  
        } catch (err) {
          console.error(err);
          Swal.fire('خطأ', 'حدث خطأ في الاتصال بالسيرفر.', 'error');
        } finally {
          btnSubmitSi.disabled = false;
          btnSubmitSi.innerHTML = '<i class="fa-solid fa-paper-plane"></i> إرسال الطلب';
        }
      });
    }
});
