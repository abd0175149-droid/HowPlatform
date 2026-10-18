
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>الصفحة الترحيبية لدبلوم إعداد المعلمين</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="stylesheet" href="<?= base_url('assets/css/tokens.css?v=1.11.2') ?>">
  <style>

    a {
      color: var(--link);
      text-decoration: none;
      transition: var(--transition);
    }

    a:hover {
      color: var(--link-hover);
      text-decoration: none;
    }

    .welcome-screen {
      width: min(960px, 92vw);
      margin: 0 auto 64px;
      padding-bottom: 64px;
    }

    .hero {
      position: relative;
      margin-top: 38.4px;
      padding: clamp(38.4px, 8vw, 57.6px) clamp(19.2px, 6vw, 51.2px);
      border-radius: var(--border-radius);
      overflow: hidden;
      background: var(--surface-0);
      border: 0.8px solid var(--border-color);
      color: var(--text-strong);
      box-shadow: none;
    }

    .hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background: none;
      pointer-events: none;
    }

    .hero-content {
      position: relative;
      z-index: 1;
      display: grid;
      gap: 16px;
      justify-items: center;
      text-align: center;
    }

    .hero-tag {
      display: inline-flex;
      align-items: center;
      gap: .6rem;
      padding: .45rem 1rem;
      border-radius: 799.2px;
      background: var(--primary-50);
      border: 0.8px solid var(--border-color);
      color: var(--primary-700);
      font-weight: 700;
      letter-spacing: 0.24px;
    }

    .hero-title {
      margin: 0;
      font-size: clamp(1.8rem, 3.4vw, 2.8rem);
      font-weight: 800;
      letter-spacing: 0.32px;
    }

    .hero-subtitle {
      margin: 0;
      font-size: clamp(1.05rem, 2.2vw, 1.3rem);
      max-width: 576px;
      color: var(--text-muted);
    }

    .hero-meta {
      display: flex;
      gap: 12.8px;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 9.6px;
    }

    .hero-meta span {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .4rem .9rem;
      border-radius: 9.6px;
      background: var(--surface-1);
      border: 0.8px solid var(--border-color);
      color: var(--text-strong);
      font-weight: 600;
    }

    .layout {
      margin-top: clamp(25.6px, 6vw, 38.4px);
      display: grid;
      grid-template-columns: 224px 1fr;
      gap: clamp(16px, 5vw, 32px);
    }
    .section-nav {
      position: sticky;
      top: 25.6px;
      align-self: start;
      display: grid;
      gap: 9.6px;
      padding: 16px;
      border-radius: var(--border-radius);
      background: var(--surface-0);
      border: 0.8px solid var(--border-color);
      box-shadow: none;
    }

    .nav-button {
      appearance: none;
      border: 0.8px solid transparent;
      border-radius: 12.8px;
      padding: .85rem 1rem;
      background: transparent;
      font: inherit;
      font-weight: 700;
      color: var(--text);
      text-align: right;
      display: flex;
      align-items: center;
      gap: .6rem;
      cursor: pointer;
      transition: var(--transition);
    }

    .nav-button .dot {
      inline-size: 6.4px;
      block-size: 6.4px;
      border-radius: 50%;
      background: var(--border-color);
      transition: var(--transition);
    }

    .nav-button.is-active {
      background: var(--primary-50);
      color: var(--primary-700);
      border-color: var(--primary-300);
      box-shadow: none;
    }

    .nav-button.is-active .dot {
      background: var(--accent-500);
      box-shadow: none;
    }

    .nav-button:focus-visible {
      outline: none;
      box-shadow: var(--focus-ring);
    }

    .sections {
      display: grid;
      gap: 25.6px;
    }

    .content-section {
      display: none;
    }

    .content-section.is-active {
      display: block;
      animation: fadeIn .35s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .section-card {
      background: var(--surface-0);
      border-radius: var(--border-radius);
      padding: clamp(19.2px, 3vw, 28.8px);
      border: 0.8px solid var(--border-color);
      box-shadow: none;
    }

    .section-title {
      margin: 0 0 19.2px;
      font-size: clamp(1.4rem, 2.6vw, 2rem);
      font-weight: 800;
      color: var(--text-strong);
    }

    .section-body {
      display: grid;
      gap: 22.4px;
    }

    .section-block {
      display: grid;
      gap: 9.6px;
      background: var(--surface-1);
      border: 0.8px solid var(--border-color);
      border-radius: var(--border-radius);
      padding: 16px 19.2px;
    }

    .section-block h3,
    .section-block h4 {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--primary-700);
    }

    .section-block p {
      margin: 0;
    }

    .bullet-list,
    .steps-list,
    .qa-list,
    .topic-list {
      margin: 0;
      padding-inline-start: 16px;
      display: grid;
      gap: .6rem;
    }

    .bullet-list li,
    .topic-list li {
      list-style: disc;
    }

    .steps-list {
      counter-reset: step;
      gap: 1.2rem;
      padding-inline-start: 19.2px;
    }

    .steps-list > li {
      background: var(--surface-0);
      border: 0.8px solid var(--border-color);
      border-radius: var(--border-radius);
      padding: 12.8px 14.4px;
      list-style: none;
      position: relative;
    }

    .steps-list > li::before {
      counter-increment: step;
      content: counter(step);
      position: absolute;
      inset-inline-start: -25.6px;
      inset-block-start: 12.8px;
      inline-size: 19.2px;
      block-size: 19.2px;
      border-radius: 50%;
      background: var(--primary-500);
      color: var(--text-inverse);
      display: grid;
      place-items: center;
      font-weight: 700;
    }

    .steps-list h4 {
      margin-bottom: .4rem;
      font-size: 1.05rem;
      color: var(--primary-700);
    }

    .faq-list {
      margin: 0;
      display: grid;
      gap: 9.6px;
    }

    .faq-item {
      background: var(--surface-0);
      border: 0.8px solid var(--border-color);
      border-radius: var(--border-radius);
      padding: 12.8px 14.4px;
      box-shadow: none;
    }

    .faq-item dt {
      font-weight: 800;
      color: var(--primary-700);
    }

    .faq-item dd {
      margin: 4.8px 0 0;
    }

    .qa-list {
      list-style: none;
      padding: 0;
      gap: 12.8px;
    }
    .qa-list li {
      border: 0.8px solid var(--border-color);
      border-radius: var(--border-radius);
      background: var(--surface-0);
      padding: 12.8px 14.4px;
    }

    .qa-question {
      margin: 0 0 6.4px;
      font-weight: 700;
      color: var(--primary-700);
    }

    .qa-answer {
      margin: 0;
      color: var(--text);
    }

    .writing-sample {
      display: grid;
      gap: 9.6px;
      border: 0.8px solid var(--border-color);
      border-radius: var(--border-radius);
      padding: 17.6px 19.2px;
      background: var(--surface-0);
      box-shadow: none;
    }

    .writing-sample h4 {
      margin: 0;
      font-size: 1.15rem;
      color: var(--primary-700);
    }

    .writing-sample h5 {
      margin: 0;
      font-size: 1.05rem;
      color: var(--text-strong);
    }

    .writing-sample p {
      margin: 0;
    }

    .legend {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      font-size: .95rem;
      color: var(--text-muted);
    }

    @media (max-width: 960px) {
      .layout {
        grid-template-columns: 1fr;
      }

      .section-nav {
        position: static;
        padding: 12.8px;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      }

      .nav-button {
        justify-content: center;
        text-align: center;
      }

      .steps-list > li::before {
        inset-inline-start: auto;
        inset-inline-end: 9.6px;
        inset-block-start: 9.6px;
      }
    }

    @media (max-width: 640px) {
      .hero-content {
        justify-items: stretch;
        text-align: right;
      }

      .hero-tag,
      .hero-subtitle,
      .hero-meta {
        justify-content: flex-start;
        text-align: right;
      }

      .section-block {
        padding: 14.4px;
      }
    }
  </style>
</head>
<body>
  <div class="welcome-screen">
    <header class="hero">
      <div class="hero-content">
        <span class="hero-tag">📚 دبلوم إعداد المعلمين</span>
        <h1 class="hero-title">مرحبًا بكم في الرحلة المهنية الأكثر أثرًا</h1>
        <p class="hero-subtitle">كل ما تحتاج معرفته عن اللقاء التعريفي، المقابلة الشخصية، والمهمة الكتابية متاح هنا في اقسام منظمة يمكنك التنقل بينها بسهولة.</p>
        <div class="hero-meta">
          <span>🗓️ يبدأ البرنامج مع اللقاء التعريفي</span>
          
          <span>🧠 يرتكز على أفضل الممارسات التربوية</span>
        </div>
      </div>
    </header>

    <div class="layout">
      <nav class="section-nav" role="tablist" aria-label="أقسام المحتوى الترحيبي">
        <button type="button" class="nav-button is-active" role="tab" id="tab-orientation" aria-controls="section-orientation" aria-selected="true" data-section-target="section-orientation" tabindex="0"><span class="dot"></span>اللقاء التعريفي</button>
        <button type="button" class="nav-button" role="tab" id="tab-interview" aria-controls="section-interview" aria-selected="false" data-section-target="section-interview" tabindex="-1"><span class="dot"></span>المقابلة الشخصية</button>
        <button type="button" class="nav-button" role="tab" id="tab-writing" aria-controls="section-writing" aria-selected="false" data-section-target="section-writing" tabindex="-1"><span class="dot"></span>المهمة الكتابية</button>
        <button type="button" class="nav-button" role="tab" id="tab-samples" aria-controls="section-samples" aria-selected="false" data-section-target="section-samples" tabindex="-1"><span class="dot"></span>أمثلة مكتوبة</button>
      </nav>

      <main class="sections">
        <section id="section-orientation" class="content-section is-active" role="tabpanel" aria-labelledby="tab-orientation" aria-hidden="false">
          <article class="section-card">
            <h2 class="section-title" tabindex="-1">🟣 اللقاء التعريفي (Orientation)</h2>
            <div class="section-body">
              <div class="section-block">
                <h3>🎯 الهدف</h3>
                <ul class="bullet-list">
                  <li>شرح هيكلة البرنامج ومتطلبات الفصلين.</li>
                  <li>توضيح القوانين والأنظمة الجامعية (المهام الأسبوعية، إدارة المباني، قواعد الانضباط).</li>
                  <li>ضبط التوقعات والسلوك المهني منذ اليوم الأول، وهو جوهر الدبلوم وهدفه الأسمى: تعزيز أفضل الممارسات التربوية.</li>
                </ul>
              </div>

              <div class="section-block">
                <h3>📋 ماذا تحضر؟</h3>
                <ul class="bullet-list">
                  <li>الهوية الشخصية / الباجة التعريفية (في حال طُلبت منك).</li>
                  <li>دفتر ملاحظات + قلم: أساسي لتسجيل التعليمات والمهام.</li>
                  <li>الهاتف الشخصي: استعمله فقط عند الحاجة التعليمية، وتجنب أي إزعاج للآخرين.</li>
                </ul>
              </div>

              <div class="section-block">
                <h3>🤝 آداب الحضور (سِمة المعلم الحقيقي)</h3>
                <ul class="bullet-list">
                  <li>الوصول قبل الموعد بـ 10–15 دقيقة على الأقل.</li>
                  <li>الالتزام بـ اللباس المحتشم والمناسب للحدث، فأنت في حرم جامعي.</li>
                  <li>كتم صوت الهاتف.</li>
                  <li>المشاركة الفاعلة، وطرح الأسئلة بوضوح واختصار عند فتح باب النقاش.</li>
                </ul>
              </div>

              <div class="section-block">
                <h3>❓ أسئلة شائعة (FAQ)</h3>
                <div class="faq-list">
                  <div class="faq-item">
                    <dt>هل الحضور إلزامي؟</dt>
                    <dd>نعم، لأنه يشرح متطلبات أساسية للقبول في البرنامج.</dd>
                  </div>
                  <div class="faq-item">
                    <dt>هل هناك مهام بعد اللقاء؟</dt>
                    <dd>نعم، ستُكلّف بـ المهمة الكتابية و المقابلة الشخصية.</dd>
                  </div>
                  <div class="faq-item">
                    <dt>كم يستمر اللقاء؟</dt>
                    <dd>من ساعة إلى ساعة ونصف (غالبًا).</dd>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </section>
        <section id="section-interview" class="content-section" role="tabpanel" aria-labelledby="tab-interview" aria-hidden="true">
          <article class="section-card">
            <h2 class="section-title" tabindex="-1">🟢 المقابلة الشخصية (Interview)</h2>
            <div class="section-body">
              <div class="section-block">
                <h3>🎯 الهدف</h3>
                <ul class="bullet-list">
                  <li>قياس الملاءمة المهنية للبرنامج.</li>
                  <li>التحقق من أخلاقيات المعلم وقدرته على التفكير التأملي.</li>
                  <li>تقييم مهارات الاتصال والتعامل مع المواقف الصفية.</li>
                </ul>
              </div>

              <div class="section-block">
                <h3>📝 كيف تستعد؟</h3>
                <ul class="bullet-list">
                  <li>ملف تعريفي مختصر: خلفيتك الأكاديمية، دافعك للتدريس، نقاط قوة + مجالان للتطوير.</li>
                  <li>موقفان صفّيان حقيقيان: واحد ناجح وآخر تحدٍ واجهته، باستخدام نموذج STAR:
                    <div class="legend">Situation: سياق الموقف · Task: المطلوب منك · Action: ما فعلته · Result/Reflect: النتيجة وما تعلمته.</div>
                  </li>
                  <li>مراجعة مفاهيم أساسية: إدارة الصف، التقييم التكويني، التعلم النشط، الفروق الفردية، التعلم الاجتماعي العاطفي، معايير التخصص والإطار الوطني.</li>
                </ul>
              </div>

              <div class="section-block">
                <h3>❔ أسئلة متوقعة مع إجابات نموذجية</h3>
                <ol class="qa-list">
                  <li>
                    <h4 class="qa-question">1) عرّف نفسك ولماذا التحقت بالدبلوم؟</h4>
                    <p class="qa-answer">“أنا (الاسم)، خريج/ـة (التخصص). التحقت بالدبلوم لتعزيز مهاراتي التدريسية وتنمية قدراتي بما يواكب متطلبات العصر، ولأتمكن من تطبيق أفضل الممارسات في الغرفة الصفية.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">2) ما فلسفتك التدريسية في سطرين؟</h4>
                    <p class="qa-answer">“التعلّم يتمحور حول الطالب، ويُبنى على دافعيته وخبراته. دوري أن أكون ميسّرًا يخلق بيئة آمنة وغنية بالأنشطة، ويستخدم التغذية الراجعة لتحسين التعلّم.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">3) صف موقفًا صفّيًا ناجحًا (STAR).</h4>
                    <p class="qa-answer">S: نشاط جماعي فيه شوشره. · T: ضبط الإيقاع مع الحفاظ على الحافز. · A: وضعت قواعد إشارات، قلّصت وقت النشاط، عيّنت مراقب وقت، وقدّمت مثالًا عمليًا. · R: تحسّن الانخراط، وتعلمت أن أوضح التعليمات بصريًا منذ البداية.</p>
                  </li>
                  <li>
                    <h4 class="qa-question">4) صف موقفًا تحدّيت فيه داخل الصف.</h4>
                    <p class="qa-answer">S: طالب رفض المشاركة. · T: إشراكه دون إحراجه. · A: تحدثت معه على انفراد، ربطت النشاط باهتماماته، قدّمت دعمًا تدريجيًا، وسألت أسئلة مفتوحة. · R: بدأ بالمشاركة الجزئية ثم الكاملة، وتعلمت أهمية بناء علاقة فردية.</p>
                  </li>
                  <li>
                    <h4 class="qa-question">5) كيف تتعامل مع أولياء الأمور؟</h4>
                    <p class="qa-answer">“أبدأ بالاستماع، ثم أشاركه البيانات الدقيقة، وأقترح خطة دعم مشتركة مع خطوات متابعة واضحة.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">6) ماذا تفعل لو تأخر الطلبة في تنفيذ المهمة؟</h4>
                    <p class="qa-answer">“أحلل سبب التأخير، أجزّئ المهمة، أحدد نقاط الدعم، وأوفر وقتًا إضافيًا مع متابعة فردية إذا لزم.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">7) هل من تجارب في التعليم المدمج؟</h4>
                    <p class="qa-answer">“نعم، قمت بتوظيف منصات تفاعلية لتقديم محتوى مسبق، ثم استخدمت وقت الصف للنقاش العميق والتطبيق العملي.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">8) كيف تدير الحصة لو واجهت سلوكًا مشتتًا؟</h4>
                    <p class="qa-answer">“أعيد التذكير بالتوقعات، أستخدم إشارات غير لفظية، أقدم خيارين إيجابيين، وإن استمر السلوك أنتقل لخطة متابعة فردية.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">9) ما دورك في تقييم الطلبة بشكل عادل؟</h4>
                    <p class="qa-answer">“أستخدم rubrics واضحة، أوفر تغذية راجعة بنّاءة، وأمنح الطالب فرصة للتعديل بناءً على الملاحظات.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">10) كيف تتعامل مع اختلاف مستويات الطالبات/الطلبة؟</h4>
                    <p class="qa-answer">“أصمم أنشطة متدرجة، أوفّر دعمًا إضافيًا، وأمنح المتقدمين تحديات أثرائية لضمان انخراط الجميع.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">11) ما أدواتك في تقييم الأداء؟</h4>
                    <p class="qa-answer">“أعتمد rubrics، ملاحظة صفية ممنهجة، تقييم ذاتي، ومشاريع قصيرة مرتبطة بالأهداف التعليمية.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">12) أعط مثالًا لنشاط تعلّم نشط.</h4>
                    <p class="qa-answer">“Think–Pair–Share حول سؤال تحفيزي، ثم جمع الأفكار على لوحة رقمية وربطها بأهداف الدرس.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">13) أخلاقيات المعلّم؟</h4>
                    <p class="qa-answer">“الإنصاف، السرية، احترام التنوع، الحياد، سلامة الطلبة، الالتزام بالتشريعات الجامعية والتعليمية.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">14) نقاط قوتك؟</h4>
                    <p class="qa-answer">“تصميم أنشطة جاذبة وتحفيز الطلبة.”</p>
                  </li>
                  <li>
                    <h4 class="qa-question">15) لماذا ينبغي قبولك؟</h4>
                    <p class="qa-answer">“لأنني أملك الحافز والقدرة على تطوير نفسي، وسأنقل أثر الدبلوم مباشرة إلى ممارساتي الصفية.”</p>
                  </li>
                </ol>
              </div>

              <div class="section-block">
                <h3>💡 نصائح ذهبية</h3>
                <ul class="bullet-list">
                  <li>أجب بإيجاز (30–60 ثانية).</li>
                  <li>اربط كل إجابة بالأثر على تعلم الطلبة.</li>
                  <li>استخدم لغة عربية سليمة وواضحة.</li>
                  <li>استحضر مثالًا واقعيًا في معظم الإجابات.</li>
                </ul>
              </div>
            </div>
          </article>
        </section>
        <section id="section-writing" class="content-section" role="tabpanel" aria-labelledby="tab-writing" aria-hidden="true">
          <article class="section-card">
            <h2 class="section-title" tabindex="-1">📝 المهمة الكتابية (Writing Task)</h2>
            <div class="section-body">
              <div class="section-block">
                <h3>🎯 الهدف</h3>
                <ul class="bullet-list">
                  <li>قياس قدرتك على الكتابة الأكاديمية بلغة واضحة ومنظمة.</li>
                  <li>اختبار تفكيرك التأملي والنقدي في قضايا التعليم.</li>
                  <li>التحقق من قدرتك على الربط بين النظرية والتطبيق داخل الصف.</li>
                </ul>
              </div>

              <div class="section-block">
                <h3>🗂 كيف تكتب المهمة؟</h3>
                <ol class="steps-list">
                  <li>
                    <h4>المقدمة (10%)</h4>
                    <ul class="bullet-list">
                      <li>جملة افتتاحية توضّح الموضوع.</li>
                      <li>تحديد الهدف أو السؤال الرئيس الذي ستجيب عنه.</li>
                    </ul>
                  </li>
                  <li>
                    <h4>العرض (20%)</h4>
                    <ul class="bullet-list">
                      <li>خلفية أو إطار نظري مختصر (مفهوم أو فكرة أساسية).</li>
                      <li>الاستشهاد بمصدر أو تجربة واقعية (إن أمكن).</li>
                    </ul>
                  </li>
                  <li>
                    <h4>التطبيق العملي (40%)</h4>
                    <ul class="bullet-list">
                      <li>مثال أو موقف صفّي.</li>
                      <li>خطوات عملية توضّح كيف تطبق الفكرة في صفّك.</li>
                      <li>أدوات تقييم تكويني أو طرق متابعة أثر النشاط.</li>
                    </ul>
                  </li>
                  <li>
                    <h4>التأمل والتطوير (20%)</h4>
                    <ul class="bullet-list">
                      <li>ما الصعوبات المتوقعة؟</li>
                      <li>ما الذي ستفعله بشكل أفضل في المرات القادمة؟</li>
                    </ul>
                  </li>
                  <li>
                    <h4>الخاتمة (10%)</h4>
                    <ul class="bullet-list">
                      <li>إعادة صياغة الفكرة الرئيسة.</li>
                      <li>توصية أو فكرة ملهمة للقارئ.</li>
                    </ul>
                  </li>
                </ol>
              </div>

              <div class="section-block">
                <h3>📌 عناوين مقترحة للكتابة</h3>
                <ul class="topic-list">
                  <li>دور التعلّم النشط في تحفيز الطلبة.</li>
                  <li>كيف يمكن للمعلم أن يراعي الفروق الفردية داخل الصف؟</li>
                  <li>أهمية التقييم التكويني في تحسين التعلّم.</li>
                  <li>إدارة الصف بين الحزم والمرونة.</li>
                  <li>دور المعلّم في بناء علاقة إيجابية مع طلبته.</li>
                  <li>كيف يمكن توظيف التكنولوجيا لدعم التعليم؟</li>
                </ul>
              </div>

              <div class="section-block">
                <h3>💡 نصائح ذهبية للمهمة الكتابية</h3>
                <ul class="bullet-list">
                  <li>التزم بعدد الكلمات: 250–500 كلمة.</li>
                  <li>اكتب جُملًا واضحة قصيرة، كل فقرة من 3–5 جمل.</li>
                  <li>تجنّب الحشو؛ كل فكرة يجب أن تُخدم بمثال أو دليل.</li>
                  <li>اربط أفكارك دائمًا بـ أثرها على تعلم الطلبة.</li>
                  <li>راجع لغتك: علامات الترقيم، سلامة الجمل، وضوح الأفكار.</li>
                  <li>اكتب مسودة أولية → راجعها → انسخ النسخة النهائية.</li>
                  <li>يمكنك استخدام ميزة الذكاء الاصطناعي "فطين" عبر منصتنا لتقييم مقالك وتحديد نقاط القوة ومجالات التحسين.</li>
                  <li>ابدأ المحادثة مع فطين عبر خيار تقييم مقال تأملي.</li>
                </ul>
              </div>
            </div>
          </article>
        </section>
        <section id="section-samples" class="content-section" role="tabpanel" aria-labelledby="tab-samples" aria-hidden="true">
          <article class="section-card">
            <h2 class="section-title" tabindex="-1">✍ أمثلة مكتوبة</h2>
            <div class="section-body">
              <div class="writing-sample">
                <h4>🔹 المثال الأول (موضوع: التعلّم النشط)</h4>
                <h5>العنوان: التعلّم النشط ودوره في تحفيز الطلبة</h5>
                <p>يُعتبر التعلّم النشط من أهم الأساليب الحديثة التي تجعل الطالب محور العملية التعليمية. فبدلاً من الاكتفاء بالشرح التقليدي، يعتمد هذا الأسلوب على مشاركة المتعلم في بناء المعرفة.</p>
                <p>في هذا السياق، يساهم التعلّم النشط في رفع مستوى الدافعية لدى الطلبة، ويعزز التفكير النقدي لديهم. تشير الأبحاث إلى أن الطلبة يتذكرون ما يقومون به أكثر مما يسمعونه فقط، وهذا دليل على قيمة هذا الأسلوب.</p>
                <p>في تجربتي الصفية، وظفت استراتيجية "فكر – شارك – اعرض"، حيث طرحت سؤالًا مفتوحًا، ثم طلبت من كل طالب التفكير بمفرده، ومناقشة الفكرة مع زميل، وأخيرًا عرض ما توصلا إليه على الصف. ساعد هذا النشاط في إشراك معظم الطلبة، حتى أولئك الذين عادة ما يترددون في المشاركة.</p>
                <p>رغم ذلك، واجهت تحديًا في إدارة الوقت. لذا في المرات القادمة، سأحدد وقتًا أدق لكل مرحلة، وأستخدم إشارات بصرية لتنبيه الطلبة عند الانتقال بين الخطوات.</p>
                <p>ختامًا، يمكن القول إن التعلّم النشط ليس مجرد أسلوب تعليمي، بل هو فلسفة تعيد تعريف دور المعلم والمتعلم، وتحوّل الصف إلى بيئة تفاعلية ثرية تُنمّي مهارات القرن الحادي والعشرين.</p>
              </div>
              <div class="writing-sample">
                <h4>🔹 المثال الثاني (موضوع: الفروق الفردية)</h4>
                <h5>العنوان: مراعاة الفروق الفردية في الغرفة الصفية</h5>
                <p>تُعد الفروق الفردية من الحقائق التربوية التي لا يمكن تجاهلها، حيث يختلف الطلبة في قدراتهم واهتماماتهم ودوافعهم. ويُعد نجاح المعلم مرهونًا بقدرته على التعامل مع هذا التنوع.</p>
                <p>لقد لاحظت في إحدى الحصص أن بعض الطلبة أنجزوا التمرين بسرعة بينما واجه آخرون صعوبة. بدلاً من الانتظار السلبي، جهزت أنشطة إضافية للطلبة المتقدمين (تمارين تحدي)، وفي الوقت نفسه قدمت مساعدة فردية للمتعثرين.</p>
                <p>هذه الاستراتيجية جعلت جميع الطلبة مشغولين ومتحفزين، وأعطت كل طالب فرصة للتعلّم وفق سرعته. كما استخدمت التقييم التكويني عبر أسئلة قصيرة للتأكد من فهم الجميع قبل الانتقال للمرحلة التالية.</p>
                <p>رغم النجاح، إلا أنني شعرت بحاجة إلى إعداد خطط مسبقة أكثر مرونة، بحيث يكون لدي دائمًا "خطة ب" للمتعلمين الذين يحتاجون دعماً إضافياً.</p>
                <p>إن مراعاة الفروق الفردية تعكس عدالة المعلم، وتُسهم في بناء بيئة صفية يشعر فيها كل طالب بالقيمة والانتماء. وهي مهارة مهنية أساسية يجب أن يطورها كل معلم.</p>
              </div>
              <div class="writing-sample">
                <h4>🔹 المثال الثالث</h4>
                <h5>دور المعلّم المتخصص في مبحثه في تنمية المجتمع</h5>
                <p>يُنظر إلى المعلّم في كل زمان ومكان على أنه ركيزة أساسية في بناء الأفراد والمجتمعات. ومع ظهور التخصصات الدقيقة في التعليم، أصبح للمعلم المتخصص دور أعظم في صياغة وعي الطلبة وربط معارفهم بحاجات المجتمع. فالمعلم المتخصص لا يقتصر عمله على نقل المعلومات، بل يتجاوز ذلك إلى تنمية التفكير النقدي، وتعزيز القيم، وتوجيه الطلبة نحو خدمة بيئتهم المحلية.</p>
                <p>يبدأ هذا الدور من الغرفة الصفية، حيث يقوم المعلم المتخصص في العلوم مثلًا بتدريب طلبته على ممارسات البحث العلمي البسيطة، وربط الدروس بالقضايا البيئية التي يعيشها المجتمع؛ كالمحافظة على المياه أو إدارة النفايات. أما معلم اللغة العربية فيوظف النصوص الأدبية في تنمية الوعي الثقافي وتعزيز قيم الحوار والانتماء. وهكذا يصبح المنهاج الدراسي جسرًا بين المعرفة الأكاديمية ومتطلبات الحياة الواقعية.</p>
                <p>إن المعلّم المتخصص يُعدّ أيضًا قدوة مهنية، فهو يعكس صورة الانضباط والجدية والإبداع في التعامل مع مبحثه. هذا السلوك يُلهم الطلبة لتقدير قيمة العلم، ويحفّزهم على التفكير في كيفية توظيف تخصصاتهم مستقبلًا لخدمة مجتمعهم. فعندما يرى الطالب أن معلمه يوظف أمثلته وأساليبه من واقع المجتمع، يزداد شعوره بالمسؤولية تجاه محيطه.</p>
                <p>على الصعيد العملي، يسهم المعلّم المتخصص في تنمية مهارات القرن الحادي والعشرين لدى طلبته، كالعمل الجماعي، حل المشكلات، والتفكير الناقد. هذه المهارات لا تنعكس فقط على إنجازات الطلبة الأكاديمية، بل تمتد لتؤهلهم للمشاركة الإيجابية في مجتمعاتهم. فطالب الرياضيات الذي يتعلم التفكير المنطقي والقدرة على تحليل البيانات، سيصبح أكثر قدرة على التعامل مع مشكلات اقتصادية أو اجتماعية مستقبلية. وطالب الدراسات الاجتماعية الذي يتعلم تحليل القضايا الوطنية، سيكون أكثر وعيًا بدوره كمواطن فاعل.</p>
                <p>ولا يقتصر دور المعلم المتخصص على التدريس المباشر، بل يشمل أيضًا المشاركة المجتمعية. فالكثير من المعلمين يقودون مبادرات طلابية لخدمة البيئة أو المجتمع، مثل حملات التشجير أو الأنشطة التطوعية. هذه المبادرات تجعل المدرسة جزءًا حيًا من المجتمع، وتساعد الطلبة على ممارسة ما يتعلمونه في مواقف حياتية حقيقية.</p>
                <p>مع ذلك، يواجه المعلّم تحديات تتمثل في ضيق الوقت، وكثرة الأعباء الإدارية، والفجوة أحيانًا بين المنهاج وواقع المجتمع. إلا أن المعلم المتخصص الناجح يتغلب على هذه العقبات من خلال التخطيط الجيد، واستثمار استراتيجيات التعليم النشط، وبناء شراكات مع المجتمع المحلي. وبهذا يحافظ على صلة مستمرة بين التعليم وحاجات المجتمع.</p>
                <p>في الختام، يمكن القول إن المعلّم المتخصص هو حلقة الوصل بين المعرفة الأكاديمية والتنمية المجتمعية. فهو ينقل المعرفة بوعي، ويزرع القيم بمسؤولية، ويصنع من الغرفة الصفية منطلقًا لتغيير المجتمع نحو الأفضل. وإذا أدرك كل معلم متخصص هذه الرسالة السامية، فإن أثره سيتجاوز حدود المدرسة ليشمل بناء أجيال قادرة على مواجهة تحديات المستقبل والمساهمة في نهضة الوطن.</p>
              </div>
              <div class="writing-sample">
                <h4>🔹 المثال الرابع</h4>
                <h5>تحديات العصر وتأثيرها على عملية التعليم</h5>
                <p>يشهد العالم المعاصر تحولات متسارعة في شتى المجالات، من التكنولوجيا الرقمية إلى التغيرات الاقتصادية والاجتماعية، وهو ما انعكس بشكل مباشر على عملية التعليم. فلم يعد التعليم مجرد نقل للمعرفة من المعلّم إلى الطالب، بل أصبح مسؤولية مشتركة تتطلب مرونة عالية، وقدرة على التكيّف مع المستجدات، وتوظيفًا فعّالًا للأدوات الحديثة.</p>
                <p>من أبرز هذه التحديات الثورة الرقمية، حيث تغيّر مفهوم الصف بفعل انتشار الإنترنت ومنصات التعلّم الإلكتروني. ورغم ما وفرته التقنية من فرص، مثل الوصول السهل إلى المصادر وتعدد وسائل العرض، إلا أنها فرضت تحديات حقيقية على المعلم والطالب. فالمعلم مطالب بامتلاك مهارات رقمية متقدمة ليتمكن من إدارة التعلم عن بعد أو المدمج، بينما يواجه الطالب خطر التشتت وضعف التركيز أمام وفرة المعلومات وتشعبها.</p>
                <p>إلى جانب ذلك، يبرز تحدي الفجوة المعرفية بين الأجيال. فجيل اليوم ينمو في بيئة غنية بالتقنية والاتصال الفوري، بينما ما زالت بعض النظم التعليمية تعتمد على طرائق تقليدية في التدريس. هذا التباين يخلق فجوة بين أسلوب تعلم الطالب وما يقدمه المعلم، مما يستدعي إعادة النظر في طرق التدريس وأساليب التقييم لتكون أكثر ملاءمة لعصر السرعة والمعلومة المفتوحة.</p>
                <p>كما يفرض التغير الاجتماعي والثقافي تحديات أخرى. فالهجرة، والانفتاح الإعلامي، وتعدد الخلفيات الثقافية للطلبة، تجعل الصف بيئة متنوعة تحتاج إلى معلم يمتلك وعيًا بالاختلافات، وقدرة على إدارة الحوار، وتقديم محتوى يتسم بالشمولية. وهنا يصبح دور المعلم أساسيًا في غرس قيم التسامح، والانتماء، والمواطنة المسؤولة.</p>
                <p>أما التحدي الاقتصادي، فيظهر في تفاوت الفرص التعليمية بين الطلبة. فهناك طلبة يملكون أجهزة حديثة واتصالًا سريعًا بالإنترنت، بينما يعاني آخرون من ضعف الإمكانيات. هذا التفاوت قد يؤدي إلى فجوة تعليمية متزايدة ما لم يُصمم التعليم ليكون متاحًا وعادلًا للجميع. لذلك، تبرز الحاجة إلى حلول مبتكرة، مثل توظيف الموارد المفتوحة، وتقديم بدائل غير رقمية، أو شراكات مع مؤسسات المجتمع لسد الفجوات.</p>
                <p>ولا يمكن إغفال التحديات النفسية التي يواجهها الطلبة اليوم. فقد زادت ضغوط الحياة، وتنامت الحاجة إلى دعم الصحة النفسية داخل المدارس. يتطلب ذلك من المعلم أن يكون واعيًا بأهمية الدعم العاطفي والاجتماعي، وأن يتبنى استراتيجيات تراعي الجانب النفسي للطلبة، مثل بناء بيئة صفية آمنة، وتشجيع التعبير عن الرأي، والاهتمام بالأنشطة التي تعزز الثقة بالنفس.</p>
                <p>في مواجهة هذه التحديات، لا يكفي أن يظل التعليم في دائرة التلقين. بل يجب أن يتجه نحو التعلم القائم على الكفايات، حيث يُقاس نجاح الطالب بقدرته على توظيف المعرفة في حل مشكلات واقعية، والتكيف مع المتغيرات. وهنا يظهر دور المعلم كقائد للتعلم، يسعى إلى بناء مهارات التفكير النقدي، والعمل الجماعي، والتعلم الذاتي، التي تمثل أدوات أساسية لمواجهة تحديات العصر.</p>
                <p>في الختام، يمكن القول إن التعليم في زمننا الحاضر يقف على مفترق طرق. فإما أن يظل أسيرًا للطرائق التقليدية، فيفقد قدرته على التأثير، أو أن يتطور ليتواءم مع متطلبات العصر. وهذا يتطلب معلمين مؤهلين، مناهج مرنة، وسياسات تعليمية تراعي العدالة والابتكار معًا. فالتعليم لم يعد رفاهية، بل هو الضمانة الأولى لنهضة المجتمعات وقدرتها على مواجهة تحديات المستقبل.</p>
              </div>
            </div>
          </article>
        </section>
      </main>
    </div>
  </div>
  <script src="welcome.js" defer></script>
</body>
</html>
