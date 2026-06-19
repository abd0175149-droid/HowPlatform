document.addEventListener("DOMContentLoaded", () => {
  const steps = document.querySelectorAll(".survey-step");
  const nextBtn = document.getElementById("nextBtn");
  const prevBtn = document.getElementById("prevBtn");
  const progressBar = document.getElementById("progressBar");
  const progressText = document.getElementById("progressText");
  const form = document.getElementById("surveyForm");
  const satisfactionInput = document.getElementById("satisfactionRating");

  const ratingCircles = document.querySelectorAll(".rating-circle");
  const howKnownRadios = document.querySelectorAll("input[name='how_known']");
  const otherText = document.getElementById("otherText");
  const reasonCheckboxes = document.querySelectorAll("input[name='reasons[]']");
  const otherReasonText = document.getElementById("otherReasonText");

  let currentStep = 0;

  function showStep(index) {
    steps.forEach((step, i) => step.classList.toggle("active", i === index));
    progressBar.style.width = ((index + 1) / steps.length) * 100 + "%";
    progressText.textContent = `الخطوة ${index + 1} من ${steps.length}`;
    prevBtn.disabled = index === 0;
    nextBtn.textContent = index === steps.length - 1 ? "إرسال" : "التالي";
  }

  showStep(currentStep);

  nextBtn.addEventListener("click", () => {
    let valid = true;

    switch (currentStep) {
      case 0:
        if (!document.querySelector("input[name='how_known']:checked")) {
          valid = false;
          Swal.fire({ icon: "warning", title: "يرجى اختيار طريقة معرفتك بالمنصة" });
        }
        break;

      case 1:
        if (document.querySelectorAll("input[name='reasons[]']:checked").length === 0) {
          valid = false;
          Swal.fire({ icon: "warning", title: "يرجى اختيار سبب واحد على الأقل للتسجيل" });
        }
        break;

      case 2:
        if (document.querySelectorAll("input[name='services[]']:checked").length === 0) {
          valid = false;
          Swal.fire({ icon: "warning", title: "يرجى اختيار خدمة واحدة على الأقل" });
        }
        break;

      case 3:
        if (!document.querySelector("input[name='device']:checked")) {
          valid = false;
          Swal.fire({ icon: "warning", title: "يرجى اختيار نوع الجهاز المفضل" });
        }
        break;

      case 4:
        if (satisfactionInput.value === "") {
          valid = false;
          Swal.fire({ icon: "warning", title: "يرجى تقييم مدى رضاك" });
        }
        break;
    }

    if (!valid) return;

    // إذا لم تكن آخر خطوة: انتقل للخطوة التالية
    if (currentStep < steps.length - 1) {
      currentStep++;
      showStep(currentStep);
    } else {
      // إرسال النموذج
      const formData = new FormData(form);

      fetch("/survey/submit", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: formData,
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            Swal.fire({
              icon: "success",
              title: "شكراً لمشاركتك",
              text: data.message,
              confirmButtonText: "متابعة"
            }).then(() => {
              window.location.href = data.redirect;
            });
          } else {
            Swal.fire({
              icon: "error",
              title: "خطأ",
              text: data.message
            });
          }
        })
        .catch(err => {
          console.error("خطأ في الاتصال:", err);
          Swal.fire({
            icon: "error",
            title: "تعذر الاتصال بالخادم",
            text: "يرجى المحاولة لاحقاً."
          });
        });
    }
  });

  prevBtn.addEventListener("click", () => {
    if (currentStep > 0) {
      currentStep--;
      showStep(currentStep);
    }
  });

  // تحديد تقييم الرضا
  ratingCircles.forEach(circle => {
    circle.addEventListener("click", function () {
      const value = this.getAttribute("data-value");
      satisfactionInput.value = value;

      ratingCircles.forEach(c => c.classList.remove("active"));
      this.classList.add("active");
    });
  });

  // عرض حقل "أخرى" في السؤال الأول
  howKnownRadios.forEach(radio => {
    radio.addEventListener("change", () => {
      otherText.style.display = document.getElementById("otherOption").checked ? "block" : "none";
    });
  });

  // عرض حقل "أخرى" في أسباب التسجيل
  reasonCheckboxes.forEach(cb => {
    cb.addEventListener("change", () => {
      const show = document.getElementById("otherReason").checked;
      otherReasonText.style.display = show ? "block" : "none";
    });
  });
});
