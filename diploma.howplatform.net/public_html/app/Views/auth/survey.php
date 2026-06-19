<div class="survey-container">
    <div class="survey-header">
        <h1>استبيان التسجيل</h1>
        <p>يساعدنا استبيانك في تحسين تجربتك على المنصة وتقديم أفضل الخدمات التعليمية لك</p>
    </div>

    <div class="survey-card">
        <div class="survey-progress">
            <div class="progress-text" id="progressText">الخطوة 1 من 5</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressBar" style="width: 20%"></div>
            </div>
        </div>

        <?php // الفورم داخل نفس الصفحة ?>
        <form id="surveyForm">
        <div class="survey-step active" data-step="1">
            <div class="form-group">
                <h3><i class="fas fa-question-circle"></i> اختر الطريقة التي عرفت بها عن منصتنا:</h3>
        
                <div class="radio-group">
                    <label><input type="radio" name="how_known" value="friend" required> <span>عن طريق صديق</span></label>
                </div>
                <div class="radio-group">
                    <label><input type="radio" name="how_known" value="social"> <span>من وسائل التواصل الاجتماعي</span></label>
                </div>
                <div class="radio-group">
                    <label><input type="radio" name="how_known" value="ad"> <span>إعلان مدفوع (فيسبوك، إنستغرام...)</span></label>
                </div>
                <div class="radio-group">
                    <label><input type="radio" name="how_known" value="search"> <span>من محرك بحث (Google)</span></label>
                </div>
                <div class="radio-group">
                    <label><input type="radio" name="how_known" value="other" id="otherOption"> <span>أخرى</span></label>
                </div>
        
                <div class="other-option" id="otherText">
                    <input  id="otherTextInput" type="text" class="form-input" name="other_source" placeholder="يرجى تحديد المصدر الآخر...">
                </div>
            </div>
        </div>
        
        <div class="survey-step" data-step="2">
            <div class="form-group">
                <h3><i class="fas fa-check-circle"></i> اختر الأسباب التي دفعتك للتسجيل (يمكن اختيار أكثر من سبب):</h3>
        
                <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="content"><span>جودة المحتوى التعليمي</span></label></div>
                <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="design"><span>سهولة الاستخدام</span></label></div>
                <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="ratings"><span>التقييمات الإيجابية</span></label></div>
                <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="price"><span>السعر أو المجانية</span></label></div>
                <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="services"><span>الخدمات المقدمة</span></label></div>
                <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="other_reason" id="otherReason"><span>أخرى</span></label></div>
        
                <div class="other-option" id="otherReasonText">
                    <input id="otherReasonTextInput" type="text" class="form-input" name="other_reason_text" placeholder="يرجى تحديد السبب الآخر...">
                </div>
            </div>
        </div>
        

        <div class="survey-step" data-step="3">
            <div class="form-group">
                <h3><i class="fas fa-cogs"></i> ما نوع الخدمات التي تنوي استخدامها؟</h3>
        
                <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="follow_tasks"><span>متابعة المهام</span></label></div>
                <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="evaluate_tasks"><span>تقييم المهام</span></label></div>
                <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="exams"><span>الاختبارات</span></label></div>
                <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="view_models"><span>الاطلاع على نماذج</span></label></div>
                <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="request_designs"><span>طلب تصاميم</span></label></div>
            </div>
        </div>

        <div class="survey-step" data-step="4">
            <div class="form-group">
                <h3><i class="fas fa-mobile-alt"></i> ما الجهاز الذي تفضله لاستخدام المنصة؟</h3>
        
                <div class="radio-group"><label><input type="radio" name="device" value="phone" required><span>هاتف محمول</span></label></div>
                <div class="radio-group"><label><input type="radio" name="device" value="computer"><span>جهاز كمبيوتر</span></label></div>
                <div class="radio-group"><label><input type="radio" name="device" value="tablet"><span>جهاز لوحي</span></label></div>
                <div class="radio-group"><label><input type="radio" name="device" value="all"><span>جميع ما سبق</span></label></div>
            </div>
        </div>
        
     
        <div class="survey-step" data-step="5">
            <div class="form-group">
                <h3><i class="fas fa-star-half-alt"></i> ما مدى رضاك عن تجربة التسجيل؟</h3>
        
                <div class="rating-container">
                    <div class="rating-option"><div class="rating-circle" data-value="1">1</div><div class="rating-label">غير راضٍ</div></div>
                    <div class="rating-option"><div class="rating-circle" data-value="2">2</div><div class="rating-label">قليل الرضا</div></div>
                    <div class="rating-option"><div class="rating-circle" data-value="3">3</div><div class="rating-label">متوسط</div></div>
                    <div class="rating-option"><div class="rating-circle" data-value="4">4</div><div class="rating-label">راضٍ</div></div>
                    <div class="rating-option"><div class="rating-circle" data-value="5">5</div><div class="rating-label">راضٍ جداً</div></div>
                </div>
        
                <input type="hidden" name="satisfaction" id="satisfactionRating" required>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="nav-btn prev-btn" id="prevBtn" disabled><i class="fas fa-arrow-right"></i> السابق</button>
            <button type="button" class="nav-btn next-btn" id="nextBtn">التالي <i class="fas fa-arrow-left"></i></button>
        </div>

        </form>
    </div>

    <div class="footer-note">
        <p>جميع المعلومات التي تقدمها ستُعامل بسرية تامة ولن تُشارك مع أي جهة خارجية.</p>
    </div>
</div>

<script>
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
  const otherTextInput = document.getElementById("otherTextInput");
  const reasonCheckboxes = document.querySelectorAll("input[name='reasons[]']");
  const otherReasonText = document.getElementById("otherReasonText");
  const otherReasonTextInput = document.getElementById("otherReasonTextInput");
  const SURVEY_URL = "<?= site_url('auth/survey') ?>";

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

      fetch(SURVEY_URL, {
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

</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
        .survey-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        .survey-header {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeIn 1s ease;
        }

        .survey-header h1 {
            color: var(--primary-darker);
            font-size: 2.5rem;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .survey-header h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, var(--accent), var(--accent-dark));
            border-radius: 4px;
        }

        .survey-header p {
            color: #555;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .survey-card {
            background-color: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .survey-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--accent), var(--accent-dark));
        }

        .survey-progress {
            margin-bottom: 30px;
            text-align: center;
        }

        .progress-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-darker);
            margin-bottom: 10px;
        }

        .progress-bar {
            height: 12px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, var(--accent), var(--accent-dark));
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .survey-step {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .survey-step.active {
            display: block;
        }

        .step-title {
            font-size: 1.8rem;
            color: var(--primary-dark);
            margin-bottom: 25px;
            text-align: center;
            position: relative;
            padding-bottom: 5px;
        }

        .step-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(to right, var(--accent), var(--accent-dark));
            border-radius: 3px;
        }

        .form-group {
            margin-bottom: 25px;
            padding: 5px;
            background: #f9f9f9;
            border-radius: 12px;
            border-left: 4px solid var(--accent);
        }

        .form-group h3 {
            color: var(--primary-darker);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group h3 i {
            color: var(--accent);
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #444;
            font-size: 1.1rem;
        }

        .radio-group, .checkbox-group {
            margin-bottom: 15px;
            padding: 5px;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .radio-group:hover, .checkbox-group:hover {
            background: rgba(253, 126, 20, 0.05);
        }

        .radio-group label, .checkbox-group label {
            display: flex;
            align-items: center;
            margin-bottom: 0px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .radio-group label:hover, .checkbox-group label:hover {
            background: rgba(253, 126, 20, 0.1);
        }

        .radio-group input, .checkbox-group input {
            margin-left: 12px;
            width: 22px;
            height: 22px;
            cursor: pointer;
        }

        .radio-group span, .checkbox-group span {
            font-size: 1.1rem;
            color: #333;
        }

        .other-option {
            margin-top: 15px;
            padding: 0 15px;
            display: none;
        }

        .form-input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            margin-top: 10px;
        }

        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(253, 126, 20, 0.15);
            outline: none;
            background-color: #fff;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .nav-btn {
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .prev-btn {
            background-color: #e9ecef;
            color: #495057;
        }

        .prev-btn:hover {
            background-color: #dee2e6;
            transform: translateY(-2px);
        }

        .next-btn, .submit-btn {
            background: linear-gradient(to right, var(--accent), var(--accent-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(253, 126, 20, 0.3);
        }

        .next-btn:hover, .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(253, 126, 20, 0.4);
        }

        .prev-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .step-indicators {
            display: flex;
            justify-content: center;
            margin-top: 25px;
            gap: 10px;
        }

        .step-indicator {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: #ced4da;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .step-indicator.active {
            background-color: var(--accent);
            transform: scale(1.2);
        }

        .footer-note {
            text-align: center;
            margin-top: 30px;
            color: #6c757d;
            font-size: 0.95rem;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .rating-container {
            display: flex;
            justify-content: flex-start;
            margin-top: 20px;
        }

        .rating-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 18%;
        }

        .rating-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 8px;
        }

        .rating-circle:hover {
            transform: scale(1.1);
        }

        .rating-circle.active {
            background: var(--accent);
            color: white;
        }

        .rating-label {
            font-size: 0.9rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .survey-header h1 {
                font-size: 2rem;
            }
            
            .survey-card {
                padding: 25px 20px;
            }
            
            .step-title {
                font-size: 1.5rem;
            }
            
            .nav-btn {
                padding: 12px 25px;
                font-size: 1rem;
            }
            
            .rating-container {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .rating-option {
                width: 30%;
            }
        }

        @media (max-width: 480px) {
            .survey-header h1 {
                font-size: 1.7rem;
            }
            
            .survey-header p {
                font-size: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-btn {
                width: 100%;
            }
            
            .rating-option {
                width: 45%;
            }
        }
    </style>
    
