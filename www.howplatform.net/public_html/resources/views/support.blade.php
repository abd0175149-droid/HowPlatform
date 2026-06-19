{{-- resources/views/support.blade.php --}}
@extends('layouts.main')

@section('title', $seo['title'] ?? 'الدعم الفني')

@section('content')
  <div id="fxbg"></div>
  <div class="pulse-gradient" aria-hidden="true"></div>

  {{-- Hero --}}
  <section class="hero" style="padding:6rem 0 3rem">
    <div class="container hero-grid" style="gap:2.5rem;">
      <div class="hero-copy" style="align-items: flex-start;">
        <h1>الدعم الفني</h1>
        <p class="lead" style="max-width:60ch">
          هل لديك استفسار، اقتراح، أو مشكلة تقنية؟  
          فريق <strong>كيف التعليمية</strong> جاهز لمساعدتك. تواصل معنا عبر القنوات التالية وسنردّ عليك في أقرب وقت ممكن.
        </p>
      </div>
      <div class="blob" aria-hidden="true"></div>
    </div>
  </section>

  {{-- بيانات الاتصال --}}
  <section>
    <div class="container">
      <h2 class="section-title">معلومات الاتصال</h2>
      <div class="grid-3">
        <div class="card">
          <h3><i class="fas fa-envelope"></i> البريد الإلكتروني</h3>
          <p><a href="mailto:support@howplatform.net" style="text-decoration:none;color:unset;">support@howplatform.net</a></p>
          <p style="color:#cde9ff">القناة الأسرع لتتبّع الطلبات وتوثيقها.</p>
        </div>
        <div class="card">
          <h3><i class="fas fa-phone"></i> الهاتف</h3>
            <p>
              <a href="tel:+962791405085" style="text-decoration:none;color:unset;" dir="ltr">
                +962 79 140 5085
              </a>
            </p>
          <p style="color:#cde9ff">اتصل بنا خلال ساعات العمل الرسمية (بتوقيت عمّان).</p>
        </div>
        <div class="card">
          <h3><i class="fas fa-map-marker-alt"></i> العنوان</h3>
          <p>عمّان – الأردن</p>
          <p style="color:#cde9ff">خدمة رقمية تغطي الأردن والعالم العربي.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- روابط إضافية --}}
  <section class="statistics">
    <div class="container" style="text-align:center">
      <h2 class="section-title">روابط مفيدة</h2>
      <div class="cta-row" style="justify-content:center">
        <a href="{{ url('/faq') }}" class="btn btn-ghost"><i class="fas fa-question-circle"></i> الأسئلة الشائعة</a>
        <a href="{{ url('/privacy') }}" class="btn btn-ghost"><i class="fas fa-user-shield"></i> سياسة الخصوصية</a>
        <a href="{{ url('/terms') }}" class="btn btn-ghost"><i class="fas fa-file-contract"></i> الشروط والأحكام</a>
      </div>
    </div>
  </section>
@endsection
