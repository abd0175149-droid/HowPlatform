/* assets/js/admin/quick_add_course.js */
/* global Swal */

/*============================
=  أدوات مساعدة لل DOM       =
============================*/
function getEls() {
    const modal = document.getElementById('quickCourseModal');
    return {
        modal,
        yearSel : modal.querySelector('#qcAcademicYear'),
        uniSel  : modal.querySelector('#qcUniversity'),
        semSel  : modal.querySelector('#qcSemester'),
        nameInp : modal.querySelector('#qcName'),
        codeInp : modal.querySelector('#qcCode'),
        descInp : modal.querySelector('#qcDesc'),
        btnSave : modal.querySelector('#qcSave'),
        btnClose: modal.querySelector('#qcClose'),
        btnCancel:modal.querySelector('#qcCancel')
    };
}

/*============================
=  تهيئة المودال لأول مرة    =
============================*/
function bindModalEvents() {
    const els = getEls();

    /* تحميل الأعوام الدراسية */
    fetch('/api/courses/meta')
        .then(r => r.json())
        .then(d => {
            els.yearSel.innerHTML = '<option value="">اختر العام الدراسي</option>';
            d.academic_years.forEach(y => {
                els.yearSel.innerHTML += `<option value="${y.id}">${y.label}</option>`;
            });
        });

    /* أحداث الفلاتر */
    els.yearSel.addEventListener('change', handleYearChange);

    /* أحداث الأزرار */
    els.btnSave  .addEventListener('click', saveCourse);
    els.btnClose .addEventListener('click', hide);
    els.btnCancel.addEventListener('click', hide);
}

/*========================================
=            الدوال الداخلية              =
========================================*/
function handleYearChange() {
    const {yearSel, uniSel, semSel} = getEls();
    const y = yearSel.value;
    if (!y) return;

    uniSel.disabled = semSel.disabled = true;
    uniSel.innerHTML = semSel.innerHTML = '<option>…</option>';

    fetch(`/api/courses/universities/${y}`)
        .then(r => r.json())
        .then(d => {
            uniSel.disabled = false;
            uniSel.innerHTML = '<option value="">اختر الجامعة</option>';
            d.universities.forEach(u =>
                uniSel.innerHTML += `<option value="${u.id}">${u.name}</option>`);
        });

    fetch(`/api/courses/semesters/${y}`)
        .then(r => r.json())
        .then(d => {
            semSel.disabled = false;
            semSel.innerHTML = '<option value="">اختر الفصل</option>';
            d.semesters.forEach(s =>
                semSel.innerHTML += `<option value="${s.id}">${s.name}</option>`);
        });
}

function saveCourse() {
    const {
        yearSel, uniSel, semSel,
        nameInp, codeInp, descInp,
        btnSave
    } = getEls();

    const year = yearSel.value, uni = uniSel.value, sem = semSel.value;
    const name = nameInp.value.trim(), code = codeInp.value.trim();
    const desc = descInp.value.trim();

   if (!year || !uni || !sem || !name || !code) {
    showWarning('يرجى تعبئة كل الحقول المطلوبة.');
    return;
    }


    btnSave.disabled = true;
    btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الحفظ…';

    fetch('/api/courses/store', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            name,
            code,
            description: desc,
            academic_year_id: year,
            university_id: uni,
            semester_id: sem
        })
    })
    .then(r => r.json())
    .then(d => {
        btnSave.disabled = false;
        btnSave.innerHTML = '<i class="fas fa-save"></i> حفظ';
    
        if (d.success) {
            showSuccess(d.message || 'تمت إضافة المساق');
            clearFields();
            hide();
        } else {
            showError(d.message || 'فشل الحفظ');
        }
    })
    .catch(() => {
        btnSave.disabled = false;
        btnSave.innerHTML = '<i class="fas fa-save"></i> حفظ';
        showError('تعذّر الاتصال بالخادم');
    });
}

function clearFields() {
    const {yearSel, uniSel, semSel, nameInp, codeInp, descInp} = getEls();
    yearSel.selectedIndex = uniSel.selectedIndex = semSel.selectedIndex = 0;
    uniSel.disabled = semSel.disabled = true;
    nameInp.value = codeInp.value = descInp.value = '';
}

/*========= إظهار / إخفاء =========*/
function show(){
    const {modal} = getEls();
    if (!modal.dataset.bound) {
        bindModalEvents();
        modal.dataset.bound = '1';
    }
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function hide(){
    const {modal} = getEls();
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

/*============================
=   التصدير لـ Dashboard     =
============================*/
export function showQuickCourseModal(){
    show();
}
