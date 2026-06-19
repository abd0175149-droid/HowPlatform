@extends('layouts.main')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/home.css?v=1.0.7') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/library.css?v=1.1.5') }}">
@endpush

@section('content')
  <section class="library-hero container reveal" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:3rem 2rem; text-align:center; margin-top:2rem;">
    <h1 style="color:#0f172a; font-weight:900; margin-bottom:1rem; font-size:clamp(2rem, 4vw, 3rem);">المكتبة الرقمية</h1>
    <p class="lead" style="color:#64748b; font-size:1.1rem; max-width:600px; margin:0 auto;">اختر الصف الدراسي للوصول إلى الكتب وأدلة المعلمين والتحضير وأوراق العمل والمزيد.</p>
  </section>

  @includeIf('partials.ad-space')

  <section class="container">
    <h2 class="section-title">الصفوف الدراسية</h2>

    @if(!empty($grades) && count($grades))
      <div class="grid-3 grades-grid">
        @foreach($grades as $g)
          @php
            $name = data_get($g, 'name', data_get($g, 'name_ar'));
            $slug = data_get($g, 'slug', 'grade-'.data_get($g, 'level'));
            $subjectsCount = (int) data_get($g, 'subjects_count', 0);
          @endphp

          <a class="grade-card reveal"
             href="{{ route('library.grade', ['grade' => $slug]) }}"
             aria-label="{{ $name }}">
            <div class="grade-badge">{{ $name }}</div>
            <div class="grade-meta">
              <span>{{ $subjectsCount > 0 ? $subjectsCount.' مادة' : 'مواد متعددة' }}</span>
              <span class="sep">•</span>
              <span>ملفات متنوعة</span>
            </div>
          </a>
        @endforeach
      </div>
    @else
      <p class="muted">لا توجد صفوف متاحة حاليًا.</p>
    @endif
  </section>
@endsection

@push('scripts')
  <script defer src="{{ asset('assets/js/library.js?v=1.0.4') }}"></script>
@endpush
