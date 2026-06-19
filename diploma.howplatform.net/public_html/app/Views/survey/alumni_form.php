<?php
/** @var array $universities */
/** @var array $specialties */
/** @var array $diplomas */
/** @var array $statusOptions */
/** @var array $timingOptions */
/** @var array $priceOptions */
/** @var array $challengeOptions */
/** @var array $supportOptions */
/** @var array $featureOptions */
/** @var array $contentOptions */
/** @var array $prefill */
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>استبيان خريجي وطلبة الدبلوم العالي</title>

  <!-- ملف التنسيقات الأصلي لديك -->
  <link rel="stylesheet" href="<?= base_url('assets/css/survey_alumni.css') ?>">

  <!-- تحسينات/تعديلات مكمّلة ضمن الصفحة (لا تغيّر المنطق العام) -->
  <style>
    /* منع أي تمدّد أفقي */
    html, body { max-width: 100%; overflow-x: hidden; }

    /* عرض منطقة الاستبيان = 70% من الشاشة (مع حد أقصى) */
    .survey-container {
      width: clamp(320px, 70vw, 1100px);
      margin-inline: auto;
    }

    /* موضع وتصميم شريط التقدّم: أسفل الهيدر وفوق الاستبيان */
    .progress-wrap {
      margin: 1rem auto 1.25rem;
      width: clamp(320px, 96vw, 1100px);
    }
    .progress-head {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: .5rem; color: #4b5563; font-weight: 700; font-size: .9rem;
    }
    .progress-bar {
      position: relative; height: 10px; background: #eef2ff; border-radius: 999px;
      box-shadow: inset 0 1px 2px rgba(0,0,0,.05);
      overflow: hidden;
    }
    .progress-fill {
      --progress: 0%;
      position: absolute; inset-inline-start: 0; top: 0; height: 100%;
      width: var(--progress);
      background: linear-gradient(90deg,#4f46e5,#0ea5e9);
      border-radius: 999px;
      transition: width .3s ease;
    }

    /* زر الإرسال يظهر عند الخطوة الأخيرة (JS يضيف .show) */
    .nav-submit { display: none; }
    .nav-submit.show { display: inline-flex; }

    /* فقاعة قيمة السلايدر: ضبط التموضع من اليسار (سيتحكّم بها JS) */
    .slider-container { position: relative; }
    .slider-value {
      position: absolute; top: -40px; left: 0; transform: translateX(-50%);
    }

    /* تمييز الحقول غير المكتملة بالأحمر عند عدم الصلاحية */
    [aria-invalid="true"],
    [aria-invalid="true"] + .rating-option,
    .form-group:has([aria-invalid="true"]) select,
    .form-group:has([aria-invalid="true"]) textarea,
    .form-group:has([aria-invalid="true"]) input:not([type="radio"]):not([type="checkbox"]) {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239,68,68,.12) !important;
    }

    /* تحسين الوصولية لمؤشرات الخطوات */
    .step-dot[aria-current="step"] { outline: 2px solid #4f46e5; outline-offset: 2px; }

    /* هوامش ألطف أعلى/أسفل منطقة الاستبيان */
    .survey-main { padding-top: .5rem; }
  </style>

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>

  <!-- الهيدر المشترك -->
  <?= $this->include('partials/header') ?>

  <!-- شريط التقدّم (أسفل الهيدر وفوق الاستبيان) -->
  <div class="progress-wrap" aria-label="شريط التقدّم">
    <div class="progress-head">
      <div>التقدّم</div>
      <div><span id="progressStep">1</span> / <span id="progressTotal">9</span></div>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="--progress: 11%;"></div>
    </div>
  </div>

  <!-- المحتوى الرئيسي -->
  <main class="survey-main" id="mainContent">
    <div class="survey-container">

      <!-- رأس الاستبيان -->
      <div class="survey-header-section">
        <div class="survey-icon"><i data-lucide="graduation-cap"></i></div>
        <h1>استبيان خريجي وطلبة الدبلوم العالي</h1>
        <p class="survey-description">مشاركتكم تساعدنا في تطوير حلول داعمة مبنية على تجربتكم الحقيقية</p>

        <div class="survey-info">
          <div class="info-item">
            <i data-lucide="clock" class="info-icon"></i>
            <span>7-10 دقائق</span>
          </div>
          <div class="info-item">
            <i data-lucide="users" class="info-icon"></i>
            <span>بيانات مجمّعة</span>
          </div>
          <div class="info-item">
            <i data-lucide="shield-check" class="info-icon"></i>
            <span>خصوصية محمية</span>
          </div>
        </div>
      </div>

      <!-- النموذج -->
      <form id="alumniSurveyForm" method="post" action="<?= site_url('survey/alumni') ?>" novalidate>
        <?= csrf_field() ?>

        <!-- Honey-pot -->
        <input type="text" name="website" class="honey-pot" tabindex="-1" autocomplete="off" aria-hidden="true">

        <!-- الخطوة 1: الموافقة (إلزامي) -->
        <div class="survey-step active" data-step="1">
          <div class="step-card">
            <div class="step-header">
              <div class="step-icon"><i data-lucide="file-text"></i></div>
              <div class="step-title">
                <h3>الموافقة على المشاركة <span class="badge-req">إلزامي</span></h3>
                <p>موافقتك ضرورية للمشاركة في هذا الاستبيان</p>
              </div>
            </div>

            <div class="step-content">
              <div class="consent-options">
                <label class="consent-option">
                  <input type="radio" name="consent" value="1" required>
                  <div class="consent-card consent-agree">
                    <div class="consent-icon"><i data-lucide="check-circle"></i></div>
                    <div class="consent-text">
                      <strong>أوافق على المشاركة</strong>
                      <p>أوافق على استخدام بياناتي لأغراض التطوير والتحسين التعليمي فقط</p>
                    </div>
                  </div>
                </label>

                <label class="consent-option">
                  <input type="radio" name="consent" value="0">
                  <div class="consent-card consent-decline">
                    <div class="consent-icon"><i data-lucide="x-circle"></i></div>
                    <div class="consent-text">
                      <strong>لا أوافق</strong>
                      <p>في هذه الحالة لن يتم حفظ أي بيانات</p>
                    </div>
                  </div>
                </label>
              </div>

              <div class="privacy-notice">
                <i data-lucide="info"></i>
                <p>جميع البيانات تُحلل بشكل مجمّع ولن تُنشر معلومات شخصية دون إذن مسبق</p>
              </div>
            </div>
          </div>
        </div>

        <!-- الخطوة 2: البيانات العامة (إلزامي + اختياري) -->
        <div class="survey-step" data-step="2">
          <div class="step-card">
            <div class="step-header">
              <div class="step-icon"><i data-lucide="user"></i></div>
              <div class="step-title">
                <h3>البيانات العامة</h3>
                <p>معلومات أساسية عن تجربتك الأكاديمية</p>
              </div>
            </div>

            <div class="step-content">
              <div class="form-grid">
                <div class="form-group">
                  <label for="completion_year">سنة الإتمام/الالتحاق <span class="badge-req">إلزامي</span></label>
                  <select name="completion_year" id="completion_year" required>
                    <option value="">اختر العام الأكاديمي</option>
                    <option>2016/2017</option>
                    <option>2017/2018</option>
                    <option>2018/2019</option>
                    <option>2019/2020</option>
                    <option>2020/2021</option>
                    <option>2021/2022</option>
                    <option>2022/2023</option>
                    <option>2023/2024</option>
                    <option>2024/2025</option>
                  </select>
                  <small class="form-hint">العام الذي التحقت أو أنهيت فيه البرنامج</small>
                </div>

                <div class="form-group">
                  <label for="university_id">الجامعة <span class="badge-req">إلزامي</span></label>
                  <select name="university_id" id="university_id" required>
                    <option value="">اختر الجامعة</option>
                    <?php foreach ($universities as $u): ?>
                      <option value="<?= (int)$u['id'] ?>"><?= esc($u['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="form-hint">الجامعة المرتبطة ببرنامج الدبلوم</small>
                </div>

                <div class="form-group">
                  <label for="diploma_type_id">مسار الدبلوم <span class="badge-req">إلزامي</span></label>
                  <select name="diploma_type_id" id="diploma_type_id" required>
                    <option value="">اختر المسار</option>
                    <?php foreach ($diplomas as $d): ?>
                      <option value="<?= (int)$d['id'] ?>"><?= esc($d['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="form-hint">نوع البرنامج أو المسار التعليمي</small>
                </div>

                <div class="form-group">
                  <label for="specialty_id">التخصص <span class="badge-req">إلزامي</span></label>
                  <select name="specialty_id" id="specialty_id" required>
                    <option value="">اختر التخصص</option>
                    <?php foreach ($specialties as $s): ?>
                      <option value="<?= (int)$s['id'] ?>"><?= esc($s['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="form-hint">المبحث أو التخصص الدراسي</small>
                </div>

                <div class="form-group">
                  <label for="city">المحافظة/المدينة <span class="badge-opt">اختياري</span></label>
                  <select name="city" id="city">
                    <option value="">اختر المحافظة</option>
                    <option>عمّان</option>
                    <option>إربد</option>
                    <option>الزرقاء</option>
                    <option>البلقاء</option>
                    <option>جرش</option>
                    <option>عجلون</option>
                    <option>المفرق</option>
                    <option>مادبا</option>
                    <option>الكرك</option>
                    <option>الطفيلة</option>
                    <option>معان</option>
                    <option>العقبة</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="current_status_id">الوضع الحالي <span class="badge-opt">اختياري</span></label>
                  <select name="current_status_id" id="current_status_id">
                    <option value="">اختر الوضع</option>
                    <?php foreach ($statusOptions as $o): ?>
                      <option value="<?= (int)$o['id'] ?>"><?= esc($o['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- الخطوة 3: التجربة الدراسية (اختياري) -->
        <div class="survey-step" data-step="3">
          <div class="step-card">
            <div class="step-header">
              <div class="step-icon"><i data-lucide="book-open"></i></div>
              <div class="step-title">
                <h3>التجربة الدراسية <span class="badge-opt">اختياري</span></h3>
                <p>قيّم جوانب مختلفة من تجربتك الدراسية</p>
              </div>
            </div>

            <div class="step-content">
              <div class="rating-scale-legend">
                <span class="scale-label">ضعيف جداً</span>
                <div class="scale-numbers"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span></div>
                <span class="scale-label">ممتاز</span>
              </div>

              <div class="rating-questions">
                <div class="rating-item">
                  <div class="rating-question">
                    <h5>وضوح خطة الدبلوم</h5>
                    <p>مدى فهمك لبنية الخطة ومتطلباتها منذ البداية</p>
                  </div>
                  <div class="rating-scale" data-name="exp_plan_clarity"></div>
                </div>

                <div class="rating-item">
                  <div class="rating-question">
                    <h5>توزيع العبء الدراسي</h5>
                    <p>توازن المهام والواجبات والوقت خلال الفصل</p>
                  </div>
                  <div class="rating-scale" data-name="exp_workload_balance"></div>
                </div>

                <div class="rating-item">
                  <div class="rating-question">
                    <h5>توفر المصادر الداعمة</h5>
                    <p>سهولة الوصول لمصادر ومراجع تدعم التعلم</p>
                  </div>
                  <div class="rating-scale" data-name="exp_resources_avail"></div>
                </div>

                <div class="rating-item">
                  <div class="rating-question">
                    <h5>وضوح متطلبات التدريب</h5>
                    <p>فهمك لمتطلبات الخبرة الميدانية منذ بداية الفصل</p>
                  </div>
                  <div class="rating-scale" data-name="field_req_clarity"></div>
                </div>

                <div class="rating-item">
                  <div class="rating-question">
                    <h5>فعالية الإرشاد</h5>
                    <p>جودة وإفادة الإرشاد الأكاديمي والميداني</p>
                  </div>
                  <div class="rating-scale" data-name="field_guidance_effective"></div>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- الخطوة 4: المهام والتقييم (اختياري) -->
        <div class="survey-step" data-step="4">
          <div class="step-card">
            <div class="step-header">
              <div class="step-icon"><i data-lucide="clipboard-check"></i></div>
              <div class="step-title">
                <h3>المهام والتقييم <span class="badge-opt">اختياري</span></h3>
                <p>تقييم وضوح المهام وأساليب التقييم</p>
              </div>
            </div>

            <div class="step-content">
              <div class="rating-scale-legend">
                <span class="scale-label">غير واضح</span>
                <div class="scale-numbers"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span></div>
                <span class="scale-label">واضح جداً</span>
              </div>

              <div class="rating-questions">
                <div class="rating-item">
                  <div class="rating-question">
                    <h5>فهم المقالات التأملية</h5>
                    <p>وضوح الهدف والمتطلبات للمقالات التأملية</p>
                  </div>
                  <div class="rating-scale" data-name="eval_reflective_clear"></div>
                </div>

                <div class="rating-item">
                  <div class="rating-question">
                    <h5>فهم المقالات التحليلية</h5>
                    <p>وضوح المطلوب في المقالات التحليلية</p>
                  </div>
                  <div class="rating-scale" data-name="eval_analytic_clear"></div>
                </div>

                <div class="rating-item">
                  <div class="rating-question">
                    <h5>وضوح البحث الاستقصائي</h5>
                    <p>وضوح خطوات وأدوات البحث الاستقصائي</p>
                  </div>
                  <div class="rating-scale" data-name="inquiry_method_clear"></div>
                </div>

                <div class="rating-item">
                  <div class="rating-question">
                    <h5>تصميم أدوات التقييم</h5>
                    <p>سهولة تصميم الاختبارات والواجبات</p>
                  </div>
                  <div class="rating-scale" data-name="assess_design_easy"></div>
                </div>

                <div class="rating-item">
                  <div class="rating-question">
                    <h5>وضوح معايير التقويم</h5>
                    <p>وضوح بنود ومعايير التقويم (Rubrics)</p>
                  </div>
                  <div class="rating-scale" data-name="rubric_clarity"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- الخطوة 5: التحديات (اختياري) -->
        <div class="survey-step" data-step="5">
          <div class="step-card">
            <div class="step-header">
              <div class="step-icon"><i data-lucide="alert-triangle"></i></div>
              <div class="step-title">
                <h3>التحديات التي واجهتك <span class="badge-opt">اختياري</span></h3>
                <p>ساعدنا في فهم الصعوبات التي مررت بها</p>
              </div>
            </div>

            <div class="step-content">
              <div class="challenges-section">
                <h4>اختر التحديات التي واجهتك</h4>
                <div class="chip-grid">
                  <?php foreach ($challengeOptions as $o): ?>
                    <label class="chip-item">
                      <input type="checkbox" name="challenges[]" value="<?= (int)$o['id'] ?>">
                      <span class="chip-label"><?= esc($o['name']) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="stress-level">
                <h4>مستوى الضغط في أصعب الأوقات</h4>
                <div class="rating-scale-legend">
                  <span class="scale-label">لا ضغط</span>
                  <div class="scale-numbers"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span></div>
                  <span class="scale-label">ضغط شديد</span>
                </div>
                <div class="rating-scale" data-name="stress_peak_level"></div>
              </div>

              <div class="text-questions">
                <div class="form-group">
                  <label for="hardest_three">أصعب 3 أمور واجهتك ولماذا؟ <span class="badge-opt">اختياري</span></label>
                  <textarea name="hardest_three" id="hardest_three" rows="4" placeholder="اكتب بإيجاز..."></textarea>
                  <small class="form-hint">شاركنا تجربتك لمساعدة الطلاب المستقبليين</small>
                </div>

                <div class="form-group">
                  <label for="best_resources">ما الموارد التي ساعدتك أكثر؟ <span class="badge-opt">اختياري</span></label>
                  <textarea name="best_resources" id="best_resources" rows="4" placeholder="اذكر موارد أو قنوات دعم..."></textarea>
                  <small class="form-hint">هذا يساعدنا في تطوير موارد دعم أفضل</small>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- الخطوة 6: قنوات الدعم (اختياري) -->
        <div class="survey-step" data-step="6">
          <div class="step-card">
            <div class="step-header">
              <div class="step-icon"><i data-lucide="life-buoy"></i></div>
              <div class="step-title">
                <h3>قنوات الدعم <span class="badge-opt">اختياري</span></h3>
                <p>أنواع الدعم التي تلقيتها أو احتجت إليها</p>
              </div>
            </div>

            <div class="step-content">
              <div class="support-section">
                <h4>قنوات الدعم التي استخدمتها</h4>
                <div class="chip-grid">
                  <?php foreach ($supportOptions as $o): ?>
                    <label class="chip-item">
                      <input type="checkbox" name="supports[]" value="<?= (int)$o['id'] ?>">
                      <span class="chip-label"><?= esc($o['name']) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="effectiveness-rating">
                <h4>تقييم فعالية قنوات الدعم المتاحة</h4>
                <div class="rating-scale-legend">
                  <span class="scale-label">غير فعالة</span>
                  <div class="scale-numbers"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span></div>
                  <span class="scale-label">فعالة جداً</span>
                </div>
                <div class="rating-scale" data-name="support_effectiveness"></div>
              </div>

              <div class="form-group">
                <label for="missing_support">ما الذي كان ينقصك من الدعم؟ <span class="badge-opt">اختياري</span></label>
                <textarea name="missing_support" id="missing_support" rows="4" placeholder="اذكر خدمة، مورد، أو تنظيم كنت تتمنى وجوده..."></textarea>
                <small class="form-hint">اقتراحاتك تساعد في تطوير منصة الدعم</small>
              </div>

            </div>
          </div>
        </div>

        <!-- الخطوة 7: ميزات المنصة (اختياري) -->
        <div class="survey-step" data-step="7">
          <div class="step-card">
            <div class="step-header">
              <div class="step-icon"><i data-lucide="star"></i></div>
              <div class="step-title">
                <h3>ميزات منصة الدعم المرغوبة <span class="badge-opt">اختياري</span></h3>
                <p>ساعدنا في تطوير المنصة التي تحتاجونها</p>
              </div>
            </div>

            <div class="step-content">
              <div class="features-section">
                <h4>الميزات الأكثر أهمية (حتى 5 خيارات)</h4>
                <div class="chip-grid limit-selection" data-max="5">
                  <?php foreach ($featureOptions as $o): ?>
                    <label class="chip-item">
                      <input type="checkbox" name="features[]" value="<?= (int)$o['id'] ?>">
                      <span class="chip-label"><?= esc($o['name']) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div class="selection-counter" id="featuresCounter">0/5 محدد</div>
              </div>

              <div class="content-section">
                <h4>تفضيلات المحتوى (حتى 3 خيارات)</h4>
                <div class="chip-grid limit-selection" data-max="3">
                  <?php foreach ($contentOptions as $o): ?>
                    <label class="chip-item">
                      <input type="checkbox" name="contents[]" value="<?= (int)$o['id'] ?>">
                      <span class="chip-label"><?= esc($o['name']) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div class="selection-counter" id="contentsCounter">0/3 محدد</div>
              </div>

              <div class="preferences-grid">
                <div class="form-group">
                  <label for="timing_option_id">متى تفضل تلقي الدعم؟ <span class="badge-opt">اختياري</span></label>
                  <select name="timing_option_id" id="timing_option_id">
                    <option value="">اختر التوقيت المناسب</option>
                    <?php foreach ($timingOptions as $o): ?>
                      <option value="<?= (int)$o['id'] ?>"><?= esc($o['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-group">
                  <label for="usage_probability">احتمالية استخدامك للمنصة (0-10) <span class="badge-opt">اختياري</span></label>
                  <div class="slider-container">
                    <input type="range" name="usage_probability" id="usage_probability" min="0" max="10" value="5" class="range-slider">
                    <div class="slider-value" id="usageValue">5</div>
                  </div>
                  <div class="slider-labels"><span>مستبعد تماماً</span><span>مؤكد الاستخدام</span></div>
                </div>

                <div class="form-group">
                  <label for="price_range_id">النطاق السعري الشهري المقبول <span class="badge-opt">اختياري</span></label>
                  <select name="price_range_id" id="price_range_id">
                    <option value="">اختر النطاق السعري</option>
                    <?php foreach ($priceOptions as $o): ?>
                      <option value="<?= (int)$o['id'] ?>"><?= esc($o['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- الخطوة 8: الأثر والنتائج (اختياري) -->
        <div class="survey-step" data-step="8">
          <div class="step-card">
            <div class="step-header">
              <div class="step-icon"><i data-lucide="trending-up"></i></div>
              <div class="step-title">
                <h3>الأثر والنتائج <span class="badge-opt">اختياري</span></h3>
                <p>كيف أثر البرنامج على أدائك وتطورك المهني</p>
              </div>
            </div>

            <div class="step-content">
              <div class="impact-section">
                <h4>المجالات التي تحسنت فيها</h4>
                <div class="impact-grid">
                  <label class="impact-item">
                    <input type="checkbox" name="perf_improved_pck" value="1">
                    <div class="impact-card"><i data-lucide="brain"></i><span>المعرفة البيداغوجية للمحتوى</span></div>
                  </label>
                  <label class="impact-item">
                    <input type="checkbox" name="perf_improved_plan" value="1">
                    <div class="impact-card"><i data-lucide="calendar"></i><span>التخطيط للتدريس</span></div>
                  </label>
                  <label class="impact-item">
                    <input type="checkbox" name="perf_improved_class_mgmt" value="1">
                    <div class="impact-card"><i data-lucide="users"></i><span>إدارة الصف</span></div>
                  </label>
                  <label class="impact-item">
                    <input type="checkbox" name="perf_improved_assessment" value="1">
                    <div class="impact-card"><i data-lucide="clipboard-check"></i><span>تقويم تعلم الطلبة</span></div>
                  </label>
                  <label class="impact-item">
                    <input type="checkbox" name="perf_improved_technology" value="1">
                    <div class="impact-card"><i data-lucide="monitor"></i><span>توظيف التكنولوجيا</span></div>
                  </label>
                  <label class="impact-item">
                    <input type="checkbox" name="perf_improved_research" value="1">
                    <div class="impact-card"><i data-lucide="search"></i><span>البحث التربوي</span></div>
                  </label>
                  <label class="impact-item">
                    <input type="checkbox" name="perf_improved_prof_communication" value="1">
                    <div class="impact-card"><i data-lucide="message-circle"></i><span>التواصل المهني</span></div>
                  </label>
                  <label class="impact-item">
                    <input type="checkbox" name="perf_improved_other" value="1">
                    <div class="impact-card"><i data-lucide="plus"></i><span>أخرى</span></div>
                  </label>
                </div>
              </div>

              <div class="recommendation-section">
                <h4>مدى تأثير البرنامج على أدائك المهني</h4>
                <div class="form-group">
                  <div class="slider-container">
                    <input type="range" name="recommend_likelihood" id="recommend_likelihood" min="0" max="10" value="5" class="range-slider">
                    <div class="slider-value" id="recommendValue">5</div>
                  </div>
                  <div class="slider-labels"><span>لم يؤثر إطلاقاً</span><span>أثر بشكل كبير جداً</span></div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- الخطوة 9: قصتك والتواصل (اختياري) -->
        <div class="survey-step" data-step="9">
          <div class="step-card">
            <div class="step-header">
              <div class="step-icon"><i data-lucide="message-square"></i></div>
              <div class="step-title">
                <h3>قصتك والتواصل <span class="badge-opt">اختياري</span></h3>
                <p>شاركنا تجربتك وبيانات التواصل (اختياري)</p>
              </div>
            </div>

            <div class="step-content">
              <div class="story-section">
                <div class="form-group">
                  <label for="story">شارك قصة أو موقف من تجربتك <span class="badge-opt">اختياري</span></label>
                  <textarea name="story" id="story" rows="5" placeholder="اكتب موقفاً يوضح تحدياً واجهته وكيف تعاملت معه..."></textarea>
                  <small class="form-hint">قصتك قد تلهم وتساعد طلاباً آخرين</small>
                </div>

                <label class="consent-option story-consent">
                  <input type="checkbox" name="allow_anonymous_quote" value="1">
                  <div class="consent-card">
                    <div class="consent-icon"><i data-lucide="quote"></i></div>
                    <div class="consent-text">
                      <strong>أوافق على الاقتباس المجهول</strong>
                      <p>يمكن استخدام جزء من قصتي بشكل مجهول الهوية لأغراض تعريفية وتحفيزية</p>
                    </div>
                  </div>
                </label>
              </div>

              <div class="contact-section">
                <h4>بيانات التواصل (اختياري)</h4>
                <p class="section-desc">في حال رغبتكم في المشاركة في تطوير المنصة أو تلقي النتائج</p>

                <div class="form-grid">
                  <div class="form-group">
                    <label for="contact_name">الاسم <span class="badge-opt">اختياري</span></label>
                    <input type="text" name="contact_name" id="contact_name" value="<?= esc($prefill['name']) ?>" placeholder="محمد أحمد">
                  </div>
                  <div class="form-group">
                    <label for="contact_email">البريد الإلكتروني <span class="badge-opt">اختياري</span></label>
                    <input type="email" name="contact_email" id="contact_email" value="<?= esc($prefill['email']) ?>" placeholder="name@example.com">
                  </div>
                  <div class="form-group">
                    <label for="contact_phone">رقم الهاتف/واتساب <span class="badge-opt">اختياري</span></label>
                    <input type="tel" name="contact_phone" id="contact_phone" value="<?= esc($prefill['phone']) ?>" placeholder="07xxxxxxxx">
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- التنقّل -->
        <div class="survey-navigation" aria-label="التنقّل بين الخطوات">
          <button type="button" class="nav-btn nav-prev" id="prevBtn" disabled>
            <i data-lucide="chevron-right"></i> السابق
          </button>

          <div class="step-indicators" aria-hidden="true">
            <div class="step-dot active" data-step="1"></div>
            <div class="step-dot" data-step="2"></div>
            <div class="step-dot" data-step="3"></div>
            <div class="step-dot" data-step="4"></div>
            <div class="step-dot" data-step="5"></div>
            <div class="step-dot" data-step="6"></div>
            <div class="step-dot" data-step="7"></div>
            <div class="step-dot" data-step="8"></div>
            <div class="step-dot" data-step="9"></div>
          </div>

          <div class="nav-actions">
            <button type="button" class="nav-btn nav-next" id="nextBtn">
              التالي <i data-lucide="chevron-left"></i>
            </button>
            <button type="submit" class="nav-btn nav-submit" id="submitBtn">
              <i data-lucide="send"></i> إرسال الاستبيان
            </button>
          </div>
        </div>

      </form>
    </div>
  </main>

  <!-- طبقة التحميل -->
  <div class="loading-overlay" id="loadingOverlay" aria-hidden="true">
    <div class="loading-content">
      <div class="loading-spinner"></div>
      <p>جارٍ إرسال الاستبيان...</p>
    </div>
  </div>

  <!-- التوست -->
  <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

  <!-- الفوتر -->
  <?= $this->include('partials/footer') ?>

  <!-- متغيّرات CI -->
  <script>
    window.CI_BASE = "<?= rtrim(site_url(), '/') ?>";
    window.CSRF_TOKEN = "<?= csrf_hash() ?>";
  </script>

  <!-- سكربت مُحدّث (يحافظ على المنطق العام) -->
  <script>
  class ModernSurvey {
    constructor() {
      this.currentStep = 1;
      this.totalSteps = 9;
      this.isSubmitting = false;

      this.form = document.getElementById('alumniSurveyForm');
      this.steps = document.querySelectorAll('.survey-step');
      this.prevBtn = document.getElementById('prevBtn');
      this.nextBtn = document.getElementById('nextBtn');
      this.submitBtn = document.getElementById('submitBtn');

      this.progressFill = document.querySelector('.progress-fill');
      this.progressStepText = document.getElementById('progressStep');
      this.progressTotalText = document.getElementById('progressTotal');

      this.loadingOverlay = document.getElementById('loadingOverlay');
      this.toastContainer = document.getElementById('toastContainer');

      this.init();
    }

    init() {
      window.modernSurvey = this;
      if (this.progressTotalText) this.progressTotalText.textContent = this.totalSteps;

      this.setupEventListeners();
      this.buildRatingScales();
      this.setupChipLimits();
      this.setupConsentGate();
      this.setupSliders();
      this.updateProgress();
      this.updateNavigation();
      this.initAutoSave();

      if (window.lucide) lucide.createIcons();

      this.validateCurrentStep();
      this.updateNextDisabledByValidity();
    }

    setupEventListeners() {
      this.prevBtn.addEventListener('click', () => this.previousStep());
      this.nextBtn.addEventListener('click', () => this.nextStep());
      this.submitBtn.addEventListener('click', (e) => this.handleSubmit(e));

      document.querySelectorAll('.step-dot').forEach((dot, index) => {
        dot.addEventListener('click', () => this.goToStep(index + 1));
      });

      this.form.addEventListener('submit', (e) => this.handleSubmit(e));

      document.querySelectorAll('input[name="consent"]').forEach(radio => {
        radio.addEventListener('change', () => this.handleConsentChange());
      });

      const validityHandler = () => {
        this.validateCurrentStep();
        this.updateNextDisabledByValidity();
      };
      this.form.addEventListener('input', validityHandler);
      this.form.addEventListener('change', validityHandler);
    }

    buildRatingScales() {
      document.querySelectorAll('.rating-scale[data-name]').forEach(scale => {
        const name = scale.dataset.name;
        if (!name) return;
        scale.innerHTML = '';
        for (let i = 1; i <= 5; i++) {
          const label = document.createElement('label');
          label.innerHTML = `
            <input type="radio" name="${name}" value="${i}">
            <div class="rating-option"><span>${i}</span></div>
          `;
          scale.appendChild(label);
        }
      });
    }

    setupChipLimits() {
      document.querySelectorAll('.limit-selection').forEach(container => {
        const max = parseInt(container.dataset.max || '0', 10);
        const inputs = container.querySelectorAll('input[type="checkbox"]');

        let counter = null;
        const next = container.nextElementSibling;
        if (next && next.classList.contains('selection-counter')) counter = next;

        const updateSelection = () => {
          const selected = Array.from(inputs).filter(i => i.checked);
          if (counter) counter.textContent = `${selected.length}/${max} محدد`;

          if (max > 0 && selected.length >= max) {
            inputs.forEach(i => { if (!i.checked) { i.disabled = true; i.setAttribute('aria-disabled','true'); }});
          } else {
            inputs.forEach(i => { i.disabled = false; i.removeAttribute('aria-disabled'); });
          }
        };

        inputs.forEach(input => input.addEventListener('change', updateSelection));
        updateSelection();
      });
    }

    setupConsentGate() { this.handleConsentChange(); }

    handleConsentChange() {
      const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
      const isAgreed = consentValue === '1';

      const gatedSteps = Array.from(this.steps).slice(1);
      gatedSteps.forEach(step => {
        const inputs = step.querySelectorAll('input, select, textarea, button');
        inputs.forEach(el => { el.disabled = !isAgreed; });
        step.style.opacity = isAgreed ? '1' : '0.5';
        step.style.pointerEvents = isAgreed ? 'auto' : 'none';
      });

      this.updateNavigation();
      this.updateNextDisabledByValidity();

      if (consentValue === '0') this.showToast('لا يمكن متابعة الاستبيان بدون الموافقة', 'warning');
    }

    setupSliders() {
      const setupSingleSlider = (sliderId, bubbleId) => {
        const slider = document.getElementById(sliderId);
        const bubble = document.getElementById(bubbleId);
        if (!(slider && bubble)) return;

        const update = () => {
          const min = Number(slider.min || 0);
          const max = Number(slider.max || 10);
          const val = Number(slider.value);
          const percent = max > min ? ((val - min) / (max - min)) : 0;
          bubble.textContent = val;
          bubble.style.left = `${percent * 100}%`;
        };
        slider.addEventListener('input', update);
        update();
      };
      setupSingleSlider('usage_probability', 'usageValue');
      setupSingleSlider('recommend_likelihood', 'recommendValue');
    }

    validateCurrentStep() {
      const stepEl = this.steps[this.currentStep - 1];
      if (!stepEl) return true;

      const requiredInputs = stepEl.querySelectorAll('[required]');
      let isValid = true;

      stepEl.querySelectorAll('[aria-invalid="true"]').forEach(el => el.setAttribute('aria-invalid', 'false'));

      requiredInputs.forEach(input => {
        let ok = true;
        if (input.type === 'radio') {
          const group = stepEl.querySelectorAll(`[name="${input.name}"]`);
          ok = Array.from(group).some(r => r.checked);
          group.forEach(r => r.setAttribute('aria-invalid', ok ? 'false' : 'true'));
        } else {
          ok = !!String(input.value || '').trim();
          input.setAttribute('aria-invalid', ok ? 'false' : 'true');
        }
        if (!ok) isValid = false;
      });

      return isValid;
    }

    updateNextDisabledByValidity() {
      if (this.currentStep < this.totalSteps) {
        const stepEl = this.steps[this.currentStep - 1];

        if (this.currentStep === 1) {
          const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
          this.nextBtn.disabled = consentValue !== '1';
          return;
        }

        const hasRequired = stepEl.querySelector('[required]') !== null;
        this.nextBtn.disabled = hasRequired ? !this.validateCurrentStep() : false;
      } else {
        this.nextBtn.disabled = false;
      }
    }

    updateProgress() {
      const dots = document.querySelectorAll('.step-dot');
      dots.forEach((dot, idx) => {
        dot.classList.remove('active', 'completed');
        dot.removeAttribute('aria-current');
        if (idx + 1 === this.currentStep) {
          dot.classList.add('active');
          dot.setAttribute('aria-current', 'step');
        } else if (idx + 1 < this.currentStep) {
          dot.classList.add('completed');
        }
      });

      const pct = Math.max(0, Math.min(100, Math.round((this.currentStep / this.totalSteps) * 100)));
      if (this.progressFill) {
        this.progressFill.style.setProperty('--progress', pct + '%');
        this.progressFill.style.width = pct + '%';
      }
      if (this.progressStepText) this.progressStepText.textContent = this.currentStep;
    }

    updateNavigation() {
      const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
      const isAgreed = consentValue === '1';

      this.prevBtn.disabled = this.currentStep === 1;

      if (this.currentStep === this.totalSteps) {
        this.nextBtn.style.display = 'none';
        this.submitBtn.classList.add('show'); // تأكد ظهور زر الإرسال بالخطوة الأخيرة
      } else {
        this.nextBtn.style.display = 'inline-flex';
        this.submitBtn.classList.remove('show');
      }

      if (this.currentStep === 1) this.nextBtn.disabled = !isAgreed;
    }

    goToStep(stepNumber) {
      if (stepNumber < 1 || stepNumber > this.totalSteps) return;

      const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
      if (stepNumber > 1 && consentValue !== '1') {
        this.showToast('يجب الموافقة أولاً للمتابعة', 'warning');
        return;
      }

      this.steps[this.currentStep - 1]?.classList.remove('active');
      this.currentStep = stepNumber;
      this.steps[this.currentStep - 1]?.classList.add('active');

      this.updateProgress();
      this.updateNavigation();
      this.updateNextDisabledByValidity();

      window.scrollTo({ top: 0, behavior: 'smooth' });
      if (window.lucide) lucide.createIcons();
    }

    nextStep() {
      if (this.currentStep >= this.totalSteps) return;

      if (!this.validateCurrentStep()) {
        this.showToast('يرجى إكمال جميع الحقول المطلوبة في هذه الخطوة', 'error');
        const stepEl = this.steps[this.currentStep - 1];
        const firstInvalid = stepEl.querySelector('[aria-invalid="true"]');
        if (firstInvalid && typeof firstInvalid.focus === 'function') firstInvalid.focus({ preventScroll: false });
        return;
      }

      this.goToStep(this.currentStep + 1);
    }

    previousStep() {
      if (this.currentStep <= 1) return;
      this.goToStep(this.currentStep - 1);
    }

    async handleSubmit(e) {
      e.preventDefault();
      if (this.isSubmitting) return;

      const honeypot = document.querySelector('.honey-pot');
      if (honeypot && honeypot.value.trim() !== '') return;

      const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
      if (consentValue !== '1') {
        this.showToast('يجب الموافقة على المشاركة لإرسال الاستبيان', 'error');
        this.goToStep(1);
        return;
      }

      if (!this.form.reportValidity()) {
        this.showToast('يرجى التحقق من جميع الحقول المطلوبة', 'error');
        return;
      }

      if (!window.fetch) { this.form.submit(); return; }

      this.isSubmitting = true;
      this.showLoading(true);

      try {
        const formData = new FormData(this.form);
        const response = await fetch(this.form.action, {
          method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const newToken = response.headers.get('X-CSRF-TOKEN');
        if (newToken) window.CSRF_TOKEN = newToken;

        let result;
        const ct = response.headers.get('content-type') || '';
        result = ct.includes('application/json') ? await response.json() : { ok: response.ok };

        if (response.ok && result.ok !== false) {
          this.showToast(result.message || 'تم إرسال الاستبيان بنجاح! شكراً لمشاركتكم 🎉', 'success');
          this.clearAutoSave();
          if (result.redirect) {
            setTimeout(()=>{ window.location.href = result.redirect; }, 1200);
          } else {
            setTimeout(()=>{ this.resetForm(); }, 1500);
          }
        } else {
          this.showToast(result.message || `حدث خطأ أثناء إرسال الاستبيان (${response.status})`, 'error');
        }
      } catch (err) {
        console.error(err);
        let msg = 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.';
        if (err?.name === 'TypeError' && String(err.message||'').includes('fetch')) msg = 'خطأ في الاتصال بالشبكة. يرجى التحقق من الإنترنت.';
        else if (err?.name === 'AbortError') msg = 'انتهت مهلة الطلب. يرجى المحاولة مرة أخرى.';
        this.showToast(msg, 'error');
      } finally {
        this.isSubmitting = false;
        this.showLoading(false);
      }
    }

    resetForm() {
      this.form.reset();
      this.goToStep(1);
      this.buildRatingScales();
      this.setupChipLimits();
      this.setupSliders();
      this.handleConsentChange();
      this.updateNextDisabledByValidity();
    }

    showLoading(show) { this.loadingOverlay?.classList.toggle('show', !!show); }

    showToast(message, type='info') {
      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      toast.textContent = message;
      this.toastContainer.appendChild(toast);
      setTimeout(()=>toast.classList.add('show'), 30);
      setTimeout(()=>{
        toast.classList.remove('show');
        setTimeout(()=>toast.remove(), 300);
      }, 5000);
    }

    /* ---------- Auto Save ---------- */
    initAutoSave() {
      if (!window.localStorage) return;
      const KEY = 'how_survey_autosave';

      try {
        const saved = localStorage.getItem(KEY);
        if (saved) this.loadFormData(JSON.parse(saved));
      } catch(e){ console.warn('Failed to load auto-save', e); }

      let t;
      const trigger = (d)=>{ clearTimeout(t); t = setTimeout(()=>this.autoSave(), d); };
      this.form.addEventListener('change', ()=>trigger(600));
      this.form.addEventListener('input', ()=>trigger(900));
    }
    autoSave() {
      if (!window.localStorage) return;
      try { localStorage.setItem('how_survey_autosave', JSON.stringify(this.getFormData())); }
      catch(e){ console.warn('Failed to auto-save', e); }
    }
    clearAutoSave() { try { localStorage.removeItem('how_survey_autosave'); } catch(e){} }
    getFormData() {
      const data = {};
      const fd = new FormData(this.form);
      for (const [k,v] of fd.entries()) {
        if (k.includes('[]')) {
          const ck = k.replace('[]',''); if (!data[ck]) data[ck]=[]; data[ck].push(v);
        } else if (k !== 'csrf_test_name' && !k.includes('website')) {
          data[k]=v;
        }
      }
      return data;
    }
    loadFormData(data) {
      Object.entries(data).forEach(([k,v])=>{
        if (Array.isArray(v)) {
          v.forEach(val=>{
            const input = this.form.querySelector(`[name="${k}[]"][value="${val}"]`);
            if (input) input.checked = true;
          });
        } else {
          const input = this.form.querySelector(`[name="${k}"]`);
          if (input) {
            if (input.type === 'radio' || input.type === 'checkbox') {
              const ii = this.form.querySelector(`[name="${k}"][value="${v}"]`);
              if (ii) ii.checked = true;
            } else { input.value = v; }
          }
        }
      });
      this.setupChipLimits();
      this.setupSliders();
      this.handleConsentChange();
      this.updateNextDisabledByValidity();
    }
  }

  document.addEventListener('DOMContentLoaded', ()=>{ new ModernSurvey(); });
  document.addEventListener('visibilitychange', ()=>{
    if (document.visibilityState === 'hidden' && window.modernSurvey) window.modernSurvey.autoSave();
  });
  </script>
</body>
</html>
