{{-- resources/views/library/grade.blade.php --}}
@extends('layouts.main')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/home.css?v=1.0.7') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/library.css?v=1.1.5') }}">
@endpush

@php
  use Illuminate\Support\Str;
  $grade = $grade ?? null;
  $gradeSlug   = is_string($grade) ? $grade : ('grade-'.data_get($grade,'level'));
  $gradeTitle  = is_string($grade) ? Str::of($grade)->replace('-', ' ')->title() : data_get($grade,'name_ar');
  $activeCat   = $activeCategory  ?? '';
  $activeSub   = $activeSubject   ?? '';
  $activeSort  = $activeSort      ?? 'recent';
  $activeSem   = $activeSemester  ?? '';
  $searchTerm  = $searchTerm      ?? '';
@endphp

@section('content')
  <form method="GET" id="filtersForm" action="{{ route('library.grade',['grade'=>$gradeSlug]) }}">
    <input type="hidden" name="category" id="categoryInput" value="{{ $activeCat }}">
    {{-- الترويسة الفاتحة ومحرك البحث --}}
    <section class="clean-hero reveal">
      <div class="hero-bg-decoration"></div>
      <div class="container hero-content">
        <div class="hero-badge">المكتبة الرقمية</div>
        <h1 class="hero-title">{{ $gradeTitle }}</h1>
        <p class="hero-subtitle">استكشف آلاف الموارد التعليمية المصممة خصيصاً لمرحلتك الدراسية بأعلى جودة وسرعة.</p>
        
        <div class="hero-search-box">
          <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
          <input name="q" id="q" type="search" placeholder="ابحث عن ملخص، درس، أو اختبار..." autocomplete="off"
                 aria-label="بحث عن ملف بالعنوان" value="{{ old('q', $searchTerm) }}">
          <button type="submit" class="search-btn" aria-label="بحث"><i class="fa-solid fa-arrow-left"></i></button>
        </div>
      </div>
    </section>

    {{-- الفلاتر العائمة (Sticky Segmented Filters) --}}
    <section class="clean-filters-wrap reveal" id="filters">
      <div class="container filters-inner">
        <div class="cats-scroll-row" role="tablist" aria-label="التصنيفات">
          <button type="button" class="clean-pill {{ $activeCat===''?'active':'' }}" data-cat="">الكل</button>
          @foreach(($categories ?? []) as $c)
            @php /** @var object $c */ @endphp
            <button type="button" class="clean-pill {{ $activeCat===$c->slug?'active':'' }}" data-cat="{{ $c->slug }}">{{ $c->name_ar }}</button>
          @endforeach
        </div>

        <div class="advanced-filters">
          <div class="clean-select-wrap">
            <i class="fa-solid fa-book-open icon"></i>
            <select name="subject_id" id="subject" aria-label="اختيار المادة">
              <option value="">كل المواد</option>
              @foreach(($subjects ?? []) as $s)
                @php /** @var object $s */ @endphp
                <option value="{{ $s->id }}" @selected($activeSub == $s->id)>{{ $s->name_ar }}</option>
              @endforeach
            </select>
          </div>

          <div class="clean-select-wrap">
            <i class="fa-solid fa-calendar-days icon"></i>
            <select name="semester_id" id="semester" aria-label="اختيار الفصل الدراسي">
              <option value="" @selected($activeSem==='')>الفصلين</option>
              @foreach(($semesters ?? []) as $sem)
                @php /** @var object $sem */ @endphp
                <option value="{{ $sem->id }}" @selected((string)$activeSem===(string)$sem->id)>{{ $sem->name_ar }}</option>
              @endforeach
            </select>
          </div>

          <div class="clean-select-wrap">
            <i class="fa-solid fa-arrow-down-a-z icon"></i>
            <select name="sort" id="sort" aria-label="ترتيب النتائج">
              <option value="recent" @selected($activeSort==='recent')>الأحدث</option>
              <option value="az"     @selected($activeSort==='az')>أ-ي</option>
              <option value="za"     @selected($activeSort==='za')>ي-أ</option>
            </select>
          </div>

          <div class="view-toggle" role="tablist" aria-label="تبديل العرض">
            <button type="button" class="view-btn active" data-view="grid" aria-selected="true">
              <i class="fa-solid fa-border-all"></i>
            </button>
            <button type="button" class="view-btn" data-view="list">
              <i class="fa-solid fa-list"></i>
            </button>
          </div>
        </div>
      </div>
    </section>
  </form>

  @includeIf('partials.ad-space')

  {{-- شبكة الموارد --}}
  <section class="container resources-section reveal">
    <div id="resources" class="grid-3 resources-grid" data-view="grid" aria-live="polite">
      <!-- إعلان AdSense كأول عنصر في الشبكة -->
      <article class="card resource-card ad-card" style="display:flex; align-items:center; justify-content:center; padding: 1rem; min-height: 250px;">
        <!-- Library Feed Ad -->
        <ins class="adsbygoogle"
             style="display:block; width:100%; height:100%;"
             data-ad-client="ca-pub-3572625518315562"
             data-ad-slot="5596897178"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
      </article>

      @forelse($resources as $r)
        @php
          $downloadUrl = route('library.download', $r->file_id);
          $previewUrl  = !empty($r->preview_storage_path) ? route('library.preview', $r->file_id) : null;
          $dateText = ''; $datetimeAttr = '';
          if (!empty($r->updated_on)) {
            $dateText = $r->updated_on;
            try { $datetimeAttr = \Illuminate\Support\Carbon::parse($r->updated_on)->toAtomString(); }
            catch (\Throwable $e) { $datetimeAttr = $r->updated_on; }
          } elseif (!empty($r->updated_at)) {
            try {
              $c = $r->updated_at instanceof \DateTimeInterface
                    ? \Illuminate\Support\Carbon::instance($r->updated_at)
                    : \Illuminate\Support\Carbon::parse($r->updated_at);
              $dateText     = $c->toDateString();
              $datetimeAttr = $c->toAtomString();
            } catch (\Throwable $e) {
              $raw          = (string) $r->updated_at;
              $dateText     = preg_replace('/\s.*/', '', $raw) ?: $raw;
              $datetimeAttr = $raw;
            }
          }
          $nameOrPath = (string) ($r->original_name ?? $r->storage_path ?? '');
          $ext        = strtolower(pathinfo($nameOrPath, PATHINFO_EXTENSION));
          $mime       = (string) ($r->mime_type ?? '');
          $iconClass  = 'fa-file';
          if     (in_array($ext, ['pdf']))                                       $iconClass = 'fa-file-pdf';
          elseif (in_array($ext, ['doc','docx']))                                $iconClass = 'fa-file-word';
          elseif (in_array($ext, ['xls','xlsx']))                                $iconClass = 'fa-file-excel';
          elseif (in_array($ext, ['ppt','pptx']))                                $iconClass = 'fa-file-powerpoint';
          elseif (in_array($ext, ['zip','rar','7z','tar','gz']))                 $iconClass = 'fa-file-zipper';
          elseif (in_array($ext, ['mp3','wav','m4a','ogg','flac']))              $iconClass = 'fa-file-audio';
          elseif (in_array($ext, ['mp4','mkv','webm','mov','avi']))              $iconClass = 'fa-file-video';
          elseif (in_array($ext, ['jpg','jpeg','png','gif','bmp','svg','webp'])) $iconClass = 'fa-file-image';
          elseif (in_array($ext, ['csv']))                                       $iconClass = 'fa-file-csv';
          elseif (in_array($ext, ['txt','md','rtf']))                            $iconClass = 'fa-file-lines';
          elseif (in_array($ext, ['json','xml','html','htm','css','js','php']))  $iconClass = 'fa-file-code';
          if ($mime) {
            if     (strpos($mime,'image/') === 0)             $iconClass = 'fa-file-image';
            elseif (strpos($mime,'audio/') === 0)             $iconClass = 'fa-file-audio';
            elseif (strpos($mime,'video/') === 0)             $iconClass = 'fa-file-video';
            elseif ($mime === 'application/pdf')              $iconClass = 'fa-file-pdf';
            elseif (strpos($mime,'word')!==false
                 || strpos($mime,'msword')!==false
                 || strpos($mime,'officedocument.wordprocessingml')!==false) $iconClass = 'fa-file-word';
            elseif (strpos($mime,'excel')!==false
                 || strpos($mime,'spreadsheetml')!==false)   $iconClass = 'fa-file-excel';
            elseif (strpos($mime,'powerpoint')!==false
                 || strpos($mime,'presentationml')!==false) $iconClass = 'fa-file-powerpoint';
            elseif (strpos($mime,'zip')!==false
                 || strpos($mime,'rar')!==false
                 || strpos($mime,'7z')!==false
                 || strpos($mime,'compressed')!==false)     $iconClass = 'fa-file-zipper';
            elseif (strpos($mime,'csv')!==false)             $iconClass = 'fa-file-csv';
            elseif (strpos($mime,'text')!==false)            $iconClass = 'fa-file-lines';
          }
          $extUpperHint = strtoupper($ext ?: (explode('/', $mime)[1] ?? 'FILE'));
          $isImage = $iconClass === 'fa-file-image';
        @endphp

        <article class="clean-card" data-file-id="{{ $r->file_id }}">
          @if(!empty($previewUrl))
            <div class="clean-card-thumb">
              <img src="{{ $previewUrl }}" alt="{{ $r->title }}" loading="lazy" decoding="async">
              <div class="thumb-overlay">
                <i class="fa-solid fa-eye"></i> معاينة
              </div>
            </div>
          @endif
          
          <div class="clean-card-body">
            <div class="clean-card-header">
              <span class="clean-tag subject-tag">{{ $r->subject_name }}</span>
              <div class="clean-file-icon {{ $extUpperHint }}">
                <i class="fa-solid {{ $iconClass }}" aria-hidden="true" title="{{ $extUpperHint }}"></i>
              </div>
            </div>

            <h3 class="clean-card-title">
              <a href="{{ $previewUrl ?? $downloadUrl }}" 
                 class="stretched-link {{ !empty($previewUrl) ? 'lightbox-trigger' : '' }}" 
                 @if(!empty($previewUrl)) data-preview="true" @endif>
                {{ $r->title }}
              </a>
            </h3>

            <div class="clean-card-meta">
              <div class="meta-item"><i class="fa-regular fa-folder"></i> {{ $r->category_name }}</div>
              @if(!empty($r->semester_name))
                <div class="meta-item"><i class="fa-regular fa-calendar"></i> {{ $r->semester_name }}</div>
              @endif
              <div class="meta-item"><i class="fa-solid fa-weight-scale"></i> {{ $r->size_mb }} MB</div>
            </div>
          </div>

          <div class="clean-card-footer">
            @if($dateText)
              <time class="clean-time" datetime="{{ $datetimeAttr }}">{{ $dateText }}</time>
            @endif
            <div class="download-action">
              <a class="clean-download-btn" href="{{ $downloadUrl }}" title="تحميل الملف" aria-label="تحميل">
                <span>تحميل</span>
                <i class="fa-solid fa-download" aria-hidden="true"></i>
              </a>
            </div>
          </div>
        </article>

      @empty
        {{-- لا موارد --}}
      @endforelse
    </div>

    <div id="no-results" class="clean-empty-state" @if($resources->count()) hidden @endif>
      <div class="empty-icon-wrap">
        <i class="fa-solid fa-box-open"></i>
      </div>
      <h3>لا توجد نتائج مطابقة</h3>
      <p>عذراً، لم يتم العثور على أية ملفات تطابق الفلاتر أو البحث الحالي. يرجى تجربة كلمات مختلفة أو إزالة بعض الفلاتر.</p>
    </div>

    <div class="pagination-wrap" id="pagination">
      @if($resources->hasPages())
        {{ $resources->onEachSide(1)->links('vendor.pagination.how') }}
      @endif
    </div>
  </section>
@endsection

@push('scripts')
<script>
(() => {
  'use strict';

  // ===== مراجع DOM =====
  const form           = document.getElementById('filtersForm');
  const q              = document.getElementById('q');
  const subject        = document.getElementById('subject');
  const semester       = document.getElementById('semester');
  const sort           = document.getElementById('sort');
  const catInput       = document.getElementById('categoryInput');
  const grid           = document.getElementById('resources');
  const paginationWrap = document.getElementById('pagination');
  const noRes          = document.getElementById('no-results');

  // عنوان نفس الصفحة من الراوت (يُمرّر من الكنترولر)
  const pageUrl = @json(route('library.grade', ['grade' => $gradeSlug]));

  let currentAbortCtrl = null;
  let typingTimer = 0;

  // ===== أدوات مساعدة =====
  function buildQuery(includeAjax = true) {
    const fd = new FormData(form);
    const p  = new URLSearchParams(fd);
    if (includeAjax) p.set('ajax', '1'); // نطلب رد جزئي JSON
    return p;
  }

  function setBusy(isBusy) {
    if (!grid) return;
    grid.classList.toggle('loading', !!isBusy);
    grid.setAttribute('aria-busy', isBusy ? 'true' : 'false');
  }

  function rebindTilt() {
    if (!grid) return;
    grid.querySelectorAll('.tilt').forEach((card) => {
      const rect = () => card.getBoundingClientRect();
      function onMove(e) {
        const r = rect();
        const x = (e.clientX - r.left) / r.width;
        const y = (e.clientY - r.top) / r.height;
        card.style.setProperty('--rx', (y - 0.5) * 6 + 'deg');
        card.style.setProperty('--ry', (x - 0.5) * -8 + 'deg');
        card.style.setProperty('--mx', x * 100 + '%');
        card.style.setProperty('--my', y * 100 + '%');
      }
      card.onpointermove  = onMove;
      card.onpointerleave = () => {
        card.style.removeProperty('--rx');
        card.style.removeProperty('--ry');
        card.style.removeProperty('--mx');
        card.style.removeProperty('--my');
      };
    });
  }

  function updateUI({ gridHtml, paginationHtml, noResults }) {
    if (grid && gridHtml !== undefined) grid.innerHTML = gridHtml;

    if (paginationWrap) {
      if (paginationHtml) {
        paginationWrap.innerHTML = paginationHtml;
        paginationWrap.hidden = false;
      } else {
        paginationWrap.innerHTML = '';
        paginationWrap.hidden = true;
      }
    }

    if (noRes) noRes.hidden = !noResults;

    rebindTilt();
    // تفعيل الإعلانات للوحدات الجديدة فقط
    window.hydrateAds?.();
  }

  async function fetchResources(pushHistory = true) {
    if (!form || !grid) return;

    const params = buildQuery(true);

    // حدّث العنوان بدون ajax=1
    if (pushHistory) {
      const clean = new URLSearchParams(params);
      clean.delete('ajax');
      const qs = clean.toString();
      history.pushState(null, '', qs ? `${pageUrl}?${qs}` : pageUrl);
    }

    if (currentAbortCtrl) currentAbortCtrl.abort();
    currentAbortCtrl = new AbortController();

    setBusy(true);
    try {
      const url = `${pageUrl}?${params.toString()}`;
      const res = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        signal: currentAbortCtrl.signal,
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      updateUI({
        gridHtml:       data.grid ?? '',
        paginationHtml: data.pagination ?? '',
        noResults:      !!data.noResults,
      });
    } catch (e) {
      console.warn('Fetch resources failed:', e);
    } finally {
      setBusy(false);
      currentAbortCtrl = null;
    }
  }

  // ===== AdSense: تحميل السكربت مرة واحدة + تفعيل الوحدات الجديدة فقط =====
  (function setupAdsense() {
    function ensureAdsScript() {
      return new Promise((resolve) => {
        // جاهز؟
        if (window.adsbygoogle && window.adsbygoogle.loaded) return resolve();

        // موجود بالـ DOM؟
        const existing = document.getElementById('adsbygoogle-js');
        if (existing) {
          if (existing.dataset.ready === '1') return resolve();
          existing.addEventListener('load', () => {
            existing.dataset.ready = '1';
            resolve();
          }, { once: true });
          return;
        }

        // غير موجود: نزّله الآن
        const s = document.createElement('script');
        s.id = 'adsbygoogle-js';
        s.async = true;
        s.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3572625518315562';
        s.crossOrigin = 'anonymous';
        s.addEventListener('load', () => {
          s.dataset.ready = '1';
          resolve();
        }, { once: true });
        document.head.appendChild(s);
      });
    }

    function hydrateAds() {
      try {
        window.adsbygoogle = window.adsbygoogle || [];
        document.querySelectorAll('ins.adsbygoogle').forEach(function(el){
          if (!el.getAttribute('data-adsbygoogle-status')) {
            (adsbygoogle).push({});
          }
        });
      } catch(e) { console.warn('AdSense hydrate failed', e); }
    }


    // متاحة عالميًا
    window.hydrateAds = hydrateAds;

    // فعّل للوحدات المرسومة SSR بعد جاهزية DOM
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => requestAnimationFrame(hydrateAds), { once: true });
    } else {
      requestAnimationFrame(hydrateAds);
    }
  })();

  // ===== أحداث الفلاتر =====
  if (q) {
    q.addEventListener('input', () => {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => fetchResources(), 700); // Debounce
    });
  }
  subject?.addEventListener('change', () => fetchResources());
  semester?.addEventListener('change', () => fetchResources());
  sort?.addEventListener('change', () => fetchResources());

  // التصنيفات
  document.querySelectorAll('.cats-scroll-row .clean-pill').forEach((p) => {
    p.addEventListener('click', () => {
      document.querySelectorAll('.cats-scroll-row .clean-pill').forEach((x) => x.classList.remove('active'));
      p.classList.add('active');
      if (catInput) catInput.value = p.dataset.cat || '';
      fetchResources();
    });
  });

  // تبديل طريقة العرض
  document.querySelectorAll('.view-toggle [data-view]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const to = btn.dataset.view;
      grid?.setAttribute('data-view', to);
      document.querySelectorAll('.view-toggle [data-view]').forEach((b) => {
        b.setAttribute('aria-selected', 'false');
        b.classList.remove('active');
      });
      btn.setAttribute('aria-selected', 'true');
      btn.classList.add('active');
    });
  });

  // اعتراض ترقيم الصفحات
  document.addEventListener('click', (e) => {
    const a = e.target.closest('.pagination a');
    if (!a) return;
    e.preventDefault();

    const url    = new URL(a.href);
    const params = url.searchParams;

    // مزامنة القيم مع النموذج
    if (q)        q.value        = params.get('q')           ?? '';
    if (subject)  subject.value  = params.get('subject_id')  ?? '';
    if (semester) semester.value = params.get('semester_id') ?? '';
    if (sort)     sort.value     = params.get('sort')        ?? 'recent';
    if (catInput) catInput.value = params.get('category')    ?? '';

    // اطلب بالـ AJAX وحدّث العنوان برابط نظيف
    params.set('ajax', '1');
    const clean = params.toString().replace(/(^|&)ajax=1(&|$)/, '$1').replace(/&$/, '');
    history.pushState(null, '', `${pageUrl}?${clean}`);
    fetchResources(false);
  });

  // دعم الرجوع/التقدم
  window.addEventListener('popstate', () => {
    const p = new URLSearchParams(location.search);

    if (q)        q.value        = p.get('q')           ?? '';
    if (subject)  subject.value  = p.get('subject_id')  ?? '';
    if (semester) semester.value = p.get('semester_id') ?? '';
    if (sort)     sort.value     = p.get('sort')        ?? 'recent';
    if (catInput) catInput.value = p.get('category')    ?? '';

    document.querySelectorAll('.cats-scroll-row .clean-pill').forEach((x) => {
      x.classList.toggle('active', (x.dataset.cat || '') === (catInput?.value || ''));
    });

    fetchResources(false);
  });

  // منع الإرسال الافتراضي للنموذج
  form?.addEventListener('submit', (e) => e.preventDefault());

  // تفعيل الميل على الكروت المرسومة SSR فورًا
  rebindTilt();

  // تفعيل الإعلانات للوحدات الحالية (لو الصفحة محمّلة)
  window.hydrateAds?.();

  // ===== نظام Lightbox للصور =====
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.lightbox-trigger');
    if (trigger) {
      e.preventDefault();
      const modal = document.getElementById('imageLightbox');
      const frame = document.getElementById('lightboxFrame');
      if (modal && frame) {
        frame.src = trigger.href;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // قفل التمرير
      }
    }

    const closeBtn = e.target.closest('[data-close-lightbox]');
    if (closeBtn) {
      const modal = document.getElementById('imageLightbox');
      const frame = document.getElementById('lightboxFrame');
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => { if (frame) frame.src = ''; }, 300);
      }
    }
  });

})();
</script>

<script defer src="{{ asset('assets/js/library.js?v=1.0.4') }}"></script>
@endpush
