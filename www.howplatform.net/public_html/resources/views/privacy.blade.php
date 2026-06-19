{{-- resources/views/privacy.blade.php --}}
@extends('layouts.main')

@section('title', $seo['title'] ?? 'سياسة الخصوصية')

@section('content')
  <div id="fxbg"></div>
  <div class="pulse-gradient" aria-hidden="true"></div>

  {{-- Hero --}}
  <section class="hero" style="padding:6rem 0 3rem">
    <div class="container hero-grid" style="grid-template-columns:1fr;">
      <div class="hero-copy" style="align-items:center;text-align:center">
        <h1>سياسة الخصوصية</h1>
        <p class="lead" style="max-width:65ch;margin:auto">
          في <strong>منصة كيف التعليمية</strong> نحرص على حماية خصوصيتك عند زيارة
          <strong>الموقع الرئيسي</strong> (<code>howplatform.net</code>). توضّح هذه الصفحة كيف نجمع بياناتك ونستخدمها ونحميها ضمن هذا الموقع فقط.
        </p>
      </div>
    </div>
  </section>

  {{-- السياسة --}}
  <section>
    <div class="container">
      <div class="card" style="line-height:1.9">
        <h2>نطاق السياسة</h2>
        <p>
          تنطبق هذه السياسة على الموقع الرئيسي <code>howplatform.net</code> وصفحاته التعريفية (مثل: الرئيسية، حول، الخدمات، الدعم، التواصل، الأسئلة الشائعة، الشروط والأحكام).
          لا تنطبق على المنصّات الفرعية المستقلة مثل <code>diploma.howplatform.net</code> و<code>library.howplatform.net</code> التي قد تمتلك سياسات منفصلة داخلها.
        </p>

        <h2>ما البيانات التي نجمعها؟</h2>
        <ul>
          <li><strong>بيانات التواصل الاختيارية:</strong> التي ترسلها لنا عبر النماذج أو البريد (الاسم، البريد، نص الرسالة).</li>
          <li><strong>بيانات تقنية لازمة للتشغيل:</strong> مثل عنوان IP ونوع الجهاز/المتصفح وملفات السجل القياسية لأغراض الأمن والأداء.</li>
          <li><strong>كوكيز أساسية/تحليلية:</strong> لتحسين تجربة الاستخدام وقياس الاستخدام العام بشكل مجمّع وغير مُعرِّف لشخصك.</li>
        </ul>
        <p style="color:var(--text-secondary)">
          لا يجمع الموقع الرئيسي معلومات أكاديمية، أو ملفات مرفوعة، أو بيانات دفع/اشتراك. أي عمليات من هذا النوع تتم — إن وُجدت — داخل المنصّات الفرعية المختصة.
        </p>

        <h2>كيف نستخدم بياناتك؟</h2>
        <ul>
          <li>الرد على استفساراتك ومتابعة طلبات الدعم الواردة عبر قنوات التواصل.</li>
          <li>تحسين محتوى الموقع وأدائه وتجربة الاستخدام.</li>
          <li>الحفاظ على أمان الموقع والامتثال للمتطلبات القانونية المعمول بها.</li>
        </ul>

        <h2>ملفات تعريف الارتباط (Cookies)</h2>
        <ul>
          <li><strong>ضرورية:</strong> لتشغيل عناصر الموقع الأساسية.</li>
          <li><strong>تفضيلات:</strong> لحفظ بعض اختيارات الواجهة (إن لزم).</li>
          <li><strong>تحليلية:</strong> لقياس التصفح بشكل مجمّع لتحسين التجربة.</li>
        </ul>
        <p>
          يمكنك إدارة الكوكيز من إعدادات متصفحك. قد يؤثر تعطيل بعض الكوكيز على بعض وظائف الموقع.
        </p>

        <h2>مشاركة البيانات</h2>
        <p>
          لا نبيع بياناتك الشخصية. قد نشارك بعض البيانات عند الضرورة مع مزوّدي الخدمات الموثوقين
          (مثل: استضافة الويب، البريد) بهدف تشغيل الموقع، وبما يتوافق مع التزامات السرية ومعالجة البيانات.
          كما قد نشارك بيانات عند وجود التزام قانوني واجب النفاذ.
        </p>

        <h2>أين نُعالج البيانات ومدة الاحتفاظ؟</h2>
        <p>
          قد تُعالج بياناتك وتُخزّن لدى مزوّد الاستضافة المُعتمد لدينا. نحتفظ بالبيانات للمدد اللازمة لتحقيق
          الأغراض المذكورة أعلاه، ثم نحذفها أو نُجهِّلها بشكل آمن. على سبيل المثال، قد نحتفظ بسجلات الخادم لفترة زمنية
          معقولة لأغراض الأمن والتدقيق.
        </p>

        <h2>حقوقك</h2>
        <ul>
          <li>الاطلاع على البيانات الشخصية التي زوّدتنا بها عبر الموقع الرئيسي.</li>
          <li>طلب تصحيحها أو حذفها عندما لا توجد حاجة مشروعة للاحتفاظ بها.</li>
          <li>سحب الموافقة على المعالجة المبنية على الموافقة (إن وُجدت).</li>
        </ul>
        <p>
          لممارسة حقوقك أو للاستفسار، راسلنا عبر <a href="{{ route('contact') }}">صفحة التواصل</a>
          أو البريد: <a href="mailto:support@howplatform.net">support@howplatform.net</a>.
        </p>

        <h2>خصوصية الأطفال</h2>
        <p>
          المحتوى موجه لطلاب الجامعات والمعلمين والمهتمين بالتعليم. لا نستهدف جمع بيانات من الأطفال دون السنّ
          المسموح به وفق القوانين المحلية. إذا كنت وليّ أمر وتعتقد أنه تم جمع بيانات دون قصد، يرجى التواصل معنا لحذفها.
        </p>

        <h2>تحديثات السياسة</h2>
        <p>
          قد نُحدّث هذه السياسة من وقت لآخر لتعكس تغييرات قانونية أو تشغيلية. يسري العمل بالإصدار المنشور على هذه الصفحة
          وتاريخ آخر تحديث: <strong>{{ now()->toDateString() }}</strong>.
        </p>

        <h2>التواصل</h2>
        <p>
          لأي استفسار حول هذه السياسة أو ممارسات الخصوصية في الموقع الرئيسي،
          يُرجى التواصل عبر <a href="{{ route('contact') }}">صفحة التواصل</a>
          أو البريد: <a href="mailto:support@howplatform.net">support@howplatform.net</a>.
        </p>
      </div>
    </div>
  </section>

  {{-- روابط إضافية --}}
  <section class="statistics">
    <div class="container" style="text-align:center">
      <div class="cta-row" style="justify-content:center">
        <a href="{{ url('/terms') }}" class="btn btn-ghost">الشروط والأحكام</a>
        <a href="{{ route('support') }}" class="btn btn-ghost">الدعم الفني</a>
        <a href="{{ route('contact') }}" class="btn btn-ghost">التواصل</a>
      </div>
    </div>
  </section>

  {{-- SEO JSON-LD --}}
  @push('scripts')
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "PrivacyPolicy",
        "name": "سياسة الخصوصية — كيف التعليمية (الموقع الرئيسي)",
        "url": "{{ $seo['url'] ?? url('/privacy') }}",
        "publisher": {
          "@type": "Organization",
          "name": "منصة كيف التعليمية | How Platform",
          "url": "https://howplatform.net"
        }
      }
    </script>
  @endpush
@endsection
