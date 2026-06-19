/* global Swal */

export function init() {
  console.log("✅ pending_requests.js: init started");

  /* ───────── متغيّرات عامة ───────── */
  let currentPage = 1;
  const perPage   = 10;
  let totalPages  = 1;
  let debounceTimer;

  /* ───────── انتظار عناصر DOM ثم البدء ───────── */
  const interval = setInterval(() => {
    const $ = id => document.getElementById(id);

    const statusFilter   = $("status-filter");
    const usernameSearch = $("username-search");
    const phoneSearch    = $("phone-search");
    const tableBody      = $("requests-table-body");

    const countPending   = $("count-pending");
    const countApproved  = $("count-approved");
    const countRejected  = $("count-rejected");
    const totalRevenue   = $("total-revenue");

    const firstPageBtn   = $("firstPageBtn");
    const prevPageBtn    = $("prevPageBtn");
    const nextPageBtn    = $("nextPageBtn");
    const lastPageBtn    = $("lastPageBtn");
    const paginationInfo = $("paginationInfo");
    const currentYear  = document.getElementById('currentYear');
  if (currentYear) currentYear.textContent = new Date().getFullYear();

    const elements = [ statusFilter, usernameSearch, phoneSearch, tableBody,
                       countPending, countApproved, countRejected, totalRevenue,
                       firstPageBtn, prevPageBtn, nextPageBtn, lastPageBtn, paginationInfo ];

    if (elements.some(el => !el)) return;   // انتظر حتى تظهر جميع العناصر
    clearInterval(interval);                // ابدأ بعد توافرها جميعًا

    /* ───────── ربط الترقيم ───────── */
    [firstPageBtn, prevPageBtn, nextPageBtn, lastPageBtn].forEach(btn => {
      btn.addEventListener("click", () => {
        switch (btn.id) {
          case "firstPageBtn": currentPage = 1; break;
          case "prevPageBtn":  if (currentPage > 1)          currentPage--; break;
          case "nextPageBtn":  if (currentPage < totalPages) currentPage++; break;
          case "lastPageBtn":  currentPage = totalPages;      break;
        }
        fetchRequests();
      });
    });

    const resetAndFetch = () => { currentPage = 1; fetchRequests(); };
    statusFilter.addEventListener("change", resetAndFetch);
    [usernameSearch, phoneSearch].forEach(inp => {
      inp.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(resetAndFetch, 700);
      });
    });

    /* ───────── الجلب الأوّل ───────── */
    fetchRequests();

    /* =======================================================
     *                     الدوال الداخلية
     * ======================================================= */

    /* ❶ جلب الطلبات */
    function fetchRequests() {
      tableBody.innerHTML =
        `<tr><td colspan="9" style="text-align:center">جاري التحميل…</td></tr>`;

      const qs = new URLSearchParams({
        status  : statusFilter.value,
        username: usernameSearch.value.trim(),
        phone   : phoneSearch.value.trim(),
        page    : currentPage,
        per_page: perPage
      });

      fetch(`/api/pending_requests/fetch?${qs.toString()}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" }
      })
      .then(r => r.json())
      .then(data => {
        renderStats(data.stats);
        renderRequests(data.requests);
        totalPages  = data.pagination.total_pages;
        currentPage = data.pagination.current_page;
        updatePaginationUI();
      })
      .catch(() => {
        tableBody.innerHTML =
          `<tr><td colspan="9" style="text-align:center">حدث خطأ أثناء التحميل</td></tr>`;
      });
    }

    /* ❷ إحصاءات البطاقات */
    function renderStats(s) {
      countPending.textContent  = s.pending;
      countApproved.textContent = s.approved;
      countRejected.textContent = s.rejected;
      totalRevenue.textContent  = `${s.total_revenue} دينار`;
    }

    /* ❸ تحديث شريط الترقيم */
    function updatePaginationUI() {
      paginationInfo.textContent = `صفحة ${currentPage} من ${totalPages}`;
      firstPageBtn.disabled = prevPageBtn.disabled = (currentPage === 1);
      nextPageBtn.disabled  = lastPageBtn.disabled = (currentPage === totalPages);
    }

    /* ❹ بناء الجدول */
    function renderRequests(list) {
      if (!list.length) {
        tableBody.innerHTML = `<tr><td colspan="9" style="text-align:center">لا توجد طلبات</td></tr>`;
        return;
      }

      tableBody.innerHTML = list.map(r => `
      <tr>
        <td>#REQ${r.id}</td>
        <td>${r.name}</td>
        <td>${r.phone_number || "-"}</td>
        <td>${r.plan}</td>
        <td>${r.created_at}</td>
        <td><span class="status-badge status-${r.status}">${translateStatus(r.status)}</span></td>
        ${notesCell(r)}
        <td>
          <button class="action-btn btn-contact"
                  data-user-id="${r.user_id}"
                  data-plan-id="${r.plan_type_id}"
                  data-plan-name="${r.plan}"
                  data-plan-amount="${r.amount}">
            <i class="fas fa-comment"></i> تواصل
          </button>
        </td>
        <td>${actionButtons(r)}</td>
      </tr>`).join("");

      /* ربط أحداث بعد بناء DOM */
      bindNotesEditing();
      bindRejectButtons();
      bindApproveButtons();
      bindReviewButtons();
      bindContactButtons();
    }

    /* عون: خلية الملاحظات */
    const notesCell = r =>
      r.status === "pending"
        ? `<td class="notes-cell pending" contenteditable="true" data-id="${r.id}">${r.notes || ""}</td>`
        : `<td class="notes-cell locked">${r.notes || ""}</td>`;

    /* عون: أزرار الإجراءات */
    const actionButtons = r => {
      if (r.status !== "pending") {
        return `<button class="action-btn btn-review" data-id="${r.id}">
                  <i class="fas fa-search"></i> مراجعة
                </button>`;
      }
      return `
        <button class="action-btn btn-reject" data-id="${r.id}">
          <i class="fas fa-times"></i> رفض
        </button>
        <button class="action-btn btn-approve"
                data-id="${r.id}" data-username="${r.name}" data-plan="${r.plan}">
          <i class="fas fa-check"></i> قبول
        </button>`;
    };

    /* عون: ترجمة */
    const translateStatus = s =>
      s === "pending"  ? "معلقة" :
      s === "approved" ? "مقبولة" :
      s === "rejected" ? "مرفوضة" : s;

    /* عون: debounce */
    const debounce = (fn, d = 700) => {
      let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); };
    };

    /* =======================================================
     *           ❺ الملاحظات – حفظ تلقائي
     * ======================================================= */
    function bindNotesEditing() {
      document.querySelectorAll(".notes-cell.pending").forEach(cell => {
        const id = cell.dataset.id;
        const save = debounce(() => {
          fetch(`/api/pending_requests/update_note/${id}`, {
            method : "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest",
              "X-HTTP-Method-Override": "PATCH"
            },
            body: JSON.stringify({ notes: cell.textContent.trim() })
          }).then(r => r.json()).then(d => {
            if (!d.success) console.error("فشل حفظ الملاحظة", d.message);
          });
        }, 700);
        cell.addEventListener("input", save);
      });
    }

    /* =======================================================
     *           ❻ زر الرفض
     * ======================================================= */
    function bindRejectButtons() {
  document.querySelectorAll(".btn-reject").forEach(btn => {
    btn.addEventListener("click", () => {
      const id = btn.dataset.id;

      showConfirm({
        title: "تأكيد الرفض",
        text: "سيتم رفض الطلب دون اشعار المستخدم",
        confirmText: "نعم، ارفض",
        cancelText: "إلغاء",
        onConfirm: async () => {
          try {
            const res = await fetch(`/api/pending_requests/reject/${id}`, {
              method: "POST",
              headers: { "X-Requested-With": "XMLHttpRequest" }
            });

            const j = await res.json();

            if (j.success) {
              showSuccess(j.message, undefined, true); // بدون زر
              fetchRequests();
            } else {
              showError(j.message);
            }
          } catch {
            showError("فشل الاتصال بالخادم");
          }
        }
      });
    });
  });
}

    /* =======================================================
     *           ❼ زر القبول + المودال
     * ======================================================= */
    function bindApproveButtons() {
      document.querySelectorAll(".btn-approve").forEach(btn => {
        btn.addEventListener("click", () => {
          const id   = btn.dataset.id;
          const name = btn.dataset.username;
          const plan = btn.dataset.plan;

          $("input-username").value      = name;
          $("input-plan-name").value     = plan;
          $("input-premium-until").value = getSuggestedPremiumUntil(plan);

          document.querySelectorAll("#payment-modal .form-control")
                  .forEach(el => el.disabled = false);

          const mdlBtn = document.querySelector("#payment-modal .btn-primary");
          mdlBtn.textContent = "تسجيل الدفع";
          mdlBtn.onclick     = () => handleApproveSubmit(id);

          loadPaymentMethods();
          $("payment-modal").classList.add("active");
        });
      });
    }

function handleApproveSubmit(id) {
  const amount       = parseFloat(document.getElementById("input-amount").value);
  const methodId     = document.getElementById("input-payment-method").value;
  const invoice      = document.getElementById("input-invoice").value;
  const gatewayId    = document.getElementById("input-gateway-id").value;
  const payDate      = document.getElementById("input-payment-date").value;
  const notes        = document.getElementById("input-notes").value;
  const premiumUntil = document.getElementById("input-premium-until").value;

  if (!amount || !methodId || !payDate) {
    showError("يرجى تعبئة الحقول المطلوبة");
    return;
  }

  fetch(`/api/pending_requests/approve/${id}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest"
    },
    body: JSON.stringify({
      amount,
      payment_method_id: methodId,
      invoice_number: invoice,
      gateway_transaction_id: gatewayId,
      payment_date: payDate,
      notes,
      premium_until: premiumUntil
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showSuccess(data.message || "تمت العملية بنجاح", undefined, true);  // ✅ تنبيه النجاح
      document.getElementById("payment-modal").classList.remove("active"); // ✅ إغلاق المودال
      fetchRequests(); // ✅ تحديث الطلبات
    } else {
      showError(data.message || "حدث خطأ أثناء الموافقة");
    }
  })
  .catch(() => showError("فشل الاتصال بالخادم"));
}
    /* تحميل طرق الدفع لمرة واحدة */
    let paymentMethodsLoaded = false;
    function loadPaymentMethods(cb) {
      if (paymentMethodsLoaded) { if (cb) cb(); return; }
      fetch("/api/pending_requests/payment-methods", {
        headers: { "X-Requested-With": "XMLHttpRequest" }
      })
      .then(r => r.json())
      .then(arr => {
        const sel = $("input-payment-method");
        sel.innerHTML = '<option value="">اختر طريقة الدفع</option>';
        arr.forEach(m => sel.innerHTML += `<option value="${m.id}">${m.name}</option>`);
        paymentMethodsLoaded = true;
        if (cb) cb();
      })
      .catch(() => console.error("فشل جلب طرق الدفع"));
    }

    /* حساب تاريخ نهاية الترقية */
    function getSuggestedPremiumUntil(plan) {
      const d = new Date();
      const y = d.getFullYear(), m = d.getMonth() + 1;
      const end = /فصل/.test(plan)
        ? (m >= 6 ? new Date(y + 1, 1, 1) : new Date(y, 5, 30))
        : (m <= 6 ? new Date(y, 5, 30)    : new Date(y + 1, 5, 30));
      return end.toISOString().split("T")[0];
    }

    /* =======================================================
     *           ❽ زر مراجعة / إعادة إلى معلّقة
     * ======================================================= */
function bindReviewButtons() {
  document.querySelectorAll(".btn-review").forEach(btn => {
    btn.addEventListener("click", () => {
      const id = btn.dataset.id;

      fetch(`/api/pending_requests/show/${id}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" }
      })
        .then(r => r.json())
        .then(j => {
          if (!j.success) {
            showError("فشل في تحميل بيانات الطلب");
            return;
          }

          const d = j.data;

          // تعبئة البيانات في النموذج
          document.getElementById("input-username").value       = d.user_name;
          document.getElementById("input-plan-name").value      = d.plan_name;
          document.getElementById("input-amount").value         = d.amount || "";
          document.getElementById("input-invoice").value        = d.invoice_number || "";
          document.getElementById("input-gateway-id").value     = d.gateway_transaction_id || "";
          document.getElementById("input-payment-date").value   = d.payment_date ? d.payment_date.split(" ")[0] : "";
          document.getElementById("input-premium-until").value  = d.premium_until || "";
          document.getElementById("input-notes").value          = d.notes || "";

          // تعطيل الحقول للعرض فقط
          document.querySelectorAll("#payment-modal .form-control")
                  .forEach(el => el.disabled = true);

          // إعداد زر الإجراء (إعادة إلى معلقة)
          const mdlBtn = document.querySelector("#payment-modal .btn-primary");
          mdlBtn.textContent = "إعادة إلى معلّقة";
          mdlBtn.onclick = () => {
            showConfirm({
              title: 'هل أنت متأكد؟',
              text: 'سيتم إعادة الطلب إلى قائمة المعلّقة.',
              confirmText: 'نعم، أعده',
              cancelText: 'إلغاء',
              onConfirm: () => {
                fetch(`/api/pending_requests/revert/${id}`, {
                  method: "POST",
                  headers: { "X-Requested-With": "XMLHttpRequest" }
                })
                .then(r2 => r2.json())
                .then(j2 => {
                  if (j2.success) {
                    showSuccess(j2.message, undefined, true);
                    document.getElementById("payment-modal").classList.remove("active");
                    fetchRequests();
                  } else {
                    showError(j2.message || "فشل في تنفيذ العملية");
                  }
                })
                .catch(() => showError("فشل الاتصال بالخادم"));
              }
            });
          };

          // تحميل طرق الدفع وتحديد الحالية
          loadPaymentMethods(() => {
            document.getElementById("input-payment-method").value = d.payment_method_id || "";
          });

          // إظهار المودال
          document.getElementById("payment-modal").classList.add("active");
        })
        .catch(() => showError("فشل الاتصال بالخادم"));
    });
  });
}

    /* =======================================================
     *           ❾ التواصل (واتساب / بريد)
     * ======================================================= */
 /* ❾ التواصل (واتساب / بريد) */
function bindContactButtons() {
  document.querySelectorAll('.btn-contact').forEach(btn => {
    btn.addEventListener('click', () => openContactModal(btn));   // ⬅️ استدعاء واحد
  });
}

function normalizeJordanPhoneNumber(rawPhone) {
  if (!rawPhone) return '';
  let phone = rawPhone.replace(/\s+/g, '');

  switch (true) {
    case phone.startsWith('+962'):
      return '00962' + phone.slice(4);

    case phone.startsWith('00962'):
      return phone;

    case phone.startsWith('0'):
      return '00962' + phone.slice(1);

    case /^7\d{8}$/.test(phone):
      return '00962' + phone;

    default:
      return '';
  }
}
async function openContactModal(btn) {
  const userId  = btn.dataset.userId;
  const planId  = btn.dataset.planId;
  const plan    = btn.dataset.planName;
  const amount  = btn.dataset.planAmount;
  const modal   = document.getElementById('contact-modal');

  try {
    /* جلب بيانات المستخدم */
    const res  = await fetch(`/api/pending_requests/contact/${userId}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!res.ok) throw Error();
    const user = (await res.json()).data;


    const phone = normalizeJordanPhoneNumber(user.phone_number);


    /* توليد الرسالة الافتراضية */
    const message =
      `مرحباً ${user.name}\n` +
      `بخصوص طلبك لترقية حسابك (${plan})، يرجى دفع (${amount} دينار).\n` +
      `طرق الدفع:\n` +
      `- كلك: abdulrzaqa\n` +
      `- محفظة: 0789154719\n\n` +
      `شكراً لتواصلك!`;

    /* تعبئة المودال */
    document.getElementById('contact-username').value = user.name;
    document.getElementById('contact-phone').value    = phone;
    document.getElementById('contact-email').value    = user.email;
    document.getElementById('contact-message').value  = message;

    /* حفظ القيم للزرّين */
    modal.dataset.userId = userId;
    modal.dataset.planId = planId;
    modal.dataset.phone  = phone;          // مهمّ للواتساب

    modal.classList.add('active');
  } catch {
    showError("فشل في جلب بيانات المستخدم");
  }
}


/* ───────── زر إرسال عبر واتساب ───────── */
/* $("contact-whatsapp").addEventListener("click", () => {
  const modal     = $("contact-modal");
  let   rawPhone  = modal.dataset.phone || $("contact-phone").value.trim();

  rawPhone = rawPhone.replace(/^\+/, "").replace(/\D/g, "");
  if (!rawPhone) {
    showError("رقم الهاتف غير متوفّر");
    return;
  }

  const msg = encodeURIComponent($("contact-message").value.trim());
  window.open(`https://wa.me/${rawPhone}?text=${msg}`, "_blank");
});*/
$("contact-whatsapp").addEventListener("click", () => {
  const modal     = $("contact-modal");
  let rawPhone    = modal.dataset.phone || $("contact-phone").value.trim();

  // إزالة أي بادئة زائدة، ثم التأكد أن الرقم يبدأ بـ +962
  rawPhone = rawPhone.replace(/^00/, "").replace(/\D/g, ""); // تحوّل 00962... إلى 962...
  if (!rawPhone) {
    showError("رقم الهاتف غير متوفّر");
    return;
  }

  const msg = encodeURIComponent($("contact-message").value.trim());

  // نضيف + قبل الرقم لإرسال صحيح
  window.open(`https://wa.me/+${rawPhone}?text=${msg}`, "_blank");
});


/* ───────── زر إرسال عبر الإيميل ───────── */
$("contact-email-btn").addEventListener("click", async () => {
  const modal  = $("contact-modal");
  const userId = modal.dataset.userId;
  const planId = modal.dataset.planId;
  const body   = $("contact-message").value.trim();

  if (!userId || !planId) {
    showError("بيانات غير مكتملة لإرسال البريد");
    return;
  }

  const btn = $("contact-email-btn");
  btn.disabled = true;             // تعطيل الزر أثناء الإرسال

  try {
    const res = await fetch("/api/pending_requests/email", {
      method : "POST",
      headers: {
        "Content-Type"    : "application/json",
        "X-Requested-With": "XMLHttpRequest"
      },
      body: JSON.stringify({
        user_id      : userId,
        plan_type_id : planId,
        subject      : "رسوم الترقية – منصة كيف؟",
        message      : body
      })
    });
    const json = await res.json();
    if (json.success) {
      showSuccess("أرسلت الرسالة بنجاح", undefined, true);
      modal.classList.remove("active");
    } else {
      throw new Error(json.message || "فشل في إرسال البريد");
    }
  } catch (e) {
    showError(e.message);
  } finally {
    btn.disabled = false;          // إعادة تفعيل الزر
  }
});


    /* =======================================================
     *           ❿ إغلاق المودالات
     * ======================================================= */
    document.querySelectorAll(".close-modal").forEach(btn => {
      btn.addEventListener("click", () => {
        ["payment-modal", "contact-modal"].forEach(id => $(id).classList.remove("active"));
      });
    });
    document.querySelectorAll(".close-modal-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        ["payment-modal", "contact-modal"].forEach(id => $(id).classList.remove("active"));
      });
    });

  }, 100); // end setInterval
}
