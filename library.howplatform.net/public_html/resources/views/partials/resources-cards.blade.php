{{-- resources/views/library/partials/resources-cards.blade.php --}}
@php use Illuminate\Support\Facades\Storage; @endphp

@forelse($resources as $r)
  @php
    // رابط التنزيل
    $downloadUrl = route('library.download', $r->file_id);

    // ✅ رابط المعاينة عبر الراوت (الأكثر اعتمادية عبر الأقراص العامة/الخاصة/S3)
    // نعرض الصورة فقط إذا كان هناك مسار معاينة مُسجَّل في الجدول
    $previewUrl = !empty($r->preview_storage_path)
        ? route('library.preview', $r->file_id)
        : null;

    // ✅ تجهيز "تاريخ فقط" (تفضيل updated_on إن وُجد)
    $dateText = '';
    $datetimeAttr = '';
    if (!empty($r->updated_on)) {
        $dateText = $r->updated_on; // Y-m-d
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

    // ✅ تحديد أيقونة نوع الملف (بالامتداد أولاً ثم MIME)
    $nameOrPath = (string) ($r->original_name ?? $r->storage_path ?? '');
    $ext        = strtolower(pathinfo($nameOrPath, PATHINFO_EXTENSION));
    $mime       = (string) ($r->mime_type ?? '');
    $iconClass  = 'fa-file';

    // بالامتداد
    if     (in_array($ext, ['pdf']))                                          $iconClass = 'fa-file-pdf';
    elseif (in_array($ext, ['doc','docx']))                                   $iconClass = 'fa-file-word';
    elseif (in_array($ext, ['xls','xlsx']))                                   $iconClass = 'fa-file-excel';
    elseif (in_array($ext, ['ppt','pptx']))                                   $iconClass = 'fa-file-powerpoint';
    elseif (in_array($ext, ['zip','rar','7z','tar','gz']))                    $iconClass = 'fa-file-zipper';
    elseif (in_array($ext, ['mp3','wav','m4a','ogg','flac']))                 $iconClass = 'fa-file-audio';
    elseif (in_array($ext, ['mp4','mkv','webm','mov','avi']))                 $iconClass = 'fa-file-video';
    elseif (in_array($ext, ['jpg','jpeg','png','gif','bmp','svg','webp']))    $iconClass = 'fa-file-image';
    elseif (in_array($ext, ['csv']))                                          $iconClass = 'fa-file-csv';
    elseif (in_array($ext, ['txt','md','rtf']))                               $iconClass = 'fa-file-lines';
    elseif (in_array($ext, ['json','xml','html','htm','css','js','php']))     $iconClass = 'fa-file-code';

    // بالـ MIME (لو متاح)
    if ($mime) {
        if     (strpos($mime,'image/') === 0)                                    $iconClass = 'fa-file-image';
        elseif (strpos($mime,'audio/') === 0)                                    $iconClass = 'fa-file-audio';
        elseif (strpos($mime,'video/') === 0)                                    $iconClass = 'fa-file-video';
        elseif ($mime === 'application/pdf')                                     $iconClass = 'fa-file-pdf';
        elseif (strpos($mime,'word')!==false || strpos($mime,'msword')!==false || strpos($mime,'officedocument.wordprocessingml')!==false) $iconClass = 'fa-file-word';
        elseif (strpos($mime,'excel')!==false || strpos($mime,'spreadsheetml')!==false) $iconClass = 'fa-file-excel';
        elseif (strpos($mime,'powerpoint')!==false || strpos($mime,'presentationml')!==false) $iconClass = 'fa-file-powerpoint';
        elseif (strpos($mime,'zip')!==false || strpos($mime,'rar')!==false || strpos($mime,'7z')!==false || strpos($mime,'compressed')!==false) $iconClass = 'fa-file-zipper';
        elseif (strpos($mime,'csv')!==false)                                     $iconClass = 'fa-file-csv';
        elseif (strpos($mime,'text')!==false)                                    $iconClass = 'fa-file-lines';
    }
    $extUpperHint = strtoupper($ext ?: (explode('/', $mime)[1] ?? 'FILE'));
  @endphp

  <article class="card resource-card tilt" data-file-id="{{ $r->file_id }}">
    <a class="stretched-link" href="{{ $downloadUrl }}" aria-label="تحميل {{ $r->title }}"></a>

    {{-- جسم البطاقة: معلومات يسار + صورة يمين (Grid عبر CSS) --}}
    <div class="res-card-content">
      {{-- عمود الصورة (يمين) --}}
      @if(!empty($previewUrl))
        <figure class="res-thumb">
          <img src="{{ $previewUrl }}" alt="{{ $r->title }}" loading="lazy" decoding="async">
        </figure>
      @endif

      {{-- عمود المعلومات (يسار الصورة) --}}
      <div class="res-info">
        <header class="res-head">
          <span class="chip">{{ $r->subject_name }}</span>
        </header>

        <h3 class="res-title">
          <i class="fa-solid {{ $iconClass }}" aria-hidden="true" title="{{ $extUpperHint }}"></i>
          {{ $r->title }}
        </h3>

        <header class="res-head">
          @if($dateText)
            <time class="chip ghost" datetime="{{ $datetimeAttr }}">محدّث: {{ $dateText }}</time>
          @endif

          <a class="download-link" href="{{ $downloadUrl }}" title="تحميل" aria-label="تحميل">
            <i class="fa-solid fa-download" aria-hidden="true"></i>
          </a>
        </header>
      </div>
    </div>

    {{-- الميتا أسفل الصورة + المعلومات --}}
    <ul class="res-meta">
      <li><i class="fa-regular fa-folder"></i> {{ $r->category_name }}</li>
      @if(!empty($r->semester_name))
        <li><i class="fa-regular fa-calendar"></i> {{ $r->semester_name }}</li>
      @endif
      <li><i class="fa-solid fa-scale-balanced"></i> {{ $r->size_mb }} MB</li>
    </ul>
  </article>
@empty
@endforelse

