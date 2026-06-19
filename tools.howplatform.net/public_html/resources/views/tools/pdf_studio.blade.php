{{-- PDF Studio — HOW Tools (Light theme, tool-specific sidebar, button toggles) --}}
@extends('layouts.app')
@section('title','مختبر PDF | HOW Tools')
<link rel="stylesheet" href="{{ asset('assets/css/pdf_studio.css?v=1.0.2') }}">

@section('sidebar')
  {{-- سايدبار متناسق مع أدوات HOW --}}
  <div class="sidebar-section">
    <a href="{{ route('tools.index') }}" class="sidebar-link">
      <i class="fas fa-arrow-right"></i> العودة لجميع الأدوات
    </a>
  </div>

  <div class="sidebar-section">
    <h3 class="sidebar-title">الأداة</h3>
    <div class="sidebar-link active" style="cursor:default">
      <i class="fas fa-file-pdf"></i> مختبر PDF
    </div>
  </div>

  {{-- أقسام الأداة (تبديل عبر data-target) --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">أقسام الأداة</h3>
    <a class="sidebar-link" href="#" data-target="ocr">
      <i class="fas fa-magic"></i> OCR
    </a>
    <a class="sidebar-link" href="#" data-target="merge">
      <i class="fas fa-layer-group"></i> دمج PDF
    </a>
    <a class="sidebar-link" href="#" data-target="split">
      <i class="fas fa-cut"></i> تقسيم PDF
    </a>
    <a class="sidebar-link" href="#" data-target="compress">
      <i class="fas fa-compress-alt"></i> ضغط PDF
    </a>
    <a class="sidebar-link" href="#" data-target="convert">
      <i class="fas fa-file-export"></i> تحويل إلى PDF
    </a>
    <a class="sidebar-link" href="#" data-target="watermark">
      <i class="fas fa-tint"></i> علامة مائية
    </a>
    <a class="sidebar-link" href="#" data-target="files">
      <i class="fas fa-download"></i> تنزيلاتي (هذه الجلسة)
    </a>
  </div>
@endsection

@section('content')

<div class="pdfstudio" id="pdfstudio"
     data-api-base="{{ config('services.pdf_api.base') }}"
     data-api-key="{{ config('services.pdf_api.key') }}">

  <div id="alertArea"></div>

  {{-- مُشغِّل الأدوات (أزرار البداية) --}}
  <div class="panel tool-launcher">
    <div class="tool-grid">
      <button class="tool-btn" data-target="ocr">
        <i class="fas fa-magic"></i><span>OCR</span>
      </button>
      <button class="tool-btn" data-target="merge">
        <i class="fas fa-layer-group"></i><span>دمج PDF</span>
      </button>
      <button class="tool-btn" data-target="split">
        <i class="fas fa-cut"></i><span>تقسيم PDF</span>
      </button>
      <button class="tool-btn" data-target="compress">
        <i class="fas fa-compress-alt"></i><span>ضغط PDF</span>
      </button>
      <button class="tool-btn" data-target="convert">
        <i class="fas fa-file-export"></i><span>تحويل إلى PDF</span>
      </button>
      <button class="tool-btn" data-target="watermark">
        <i class="fas fa-tint"></i><span>علامة مائية</span>
      </button>
      <button class="tool-btn" data-target="files">
        <i class="fas fa-download"></i><span>تنزيلاتي</span>
      </button>
    </div>
  </div>
    
   {{-- OCR --}}
   <section class="panel section" data-section="ocr">
      {{-- رأس القسم --}}
      <div class="section-head topbar">
        <h2 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:8px">
          <i class="fas fa-magic"></i>
          <span>OCR (تحويل نص من PDF)</span>
        </h2>
      </div>
    
      {{-- جسم القسم: عمودان --}}
      <div class="section-body two two-column">
        {{-- العمود الأيسر: الرفع + معلومات الملف + أزرار --}}
        <div class="column">
          <div class="drop drop-zone" data-drop="ocr" style="display:flex;flex-direction:column;gap:10px;align-items:center;justify-content:center;min-height:200px">
            <i class="fas fa-cloud-upload-alt" style="font-size:2rem;opacity:.7"></i>
            <div class="muted">اسحب PDF هنا أو انقر للاختيار</div>
            <input type="file" accept="application/pdf" class="d-none" id="pick-ocr"/>
          </div>
    
          {{-- هنا سيظهر كارت المعاينة + الأزرار (يبنيه الـ JS) --}}
          <div id="ocrUploadInfo" class="stack"></div>
        </div>
    
        {{-- العمود الأيمن: النتيجة --}}
        <div class="column">
          <div class="result-area panel" style="min-height:200px;background:var(--surface-weak,#f8fafc)">
            <div class="result-header topbar" style="margin-bottom:8px">
              <div class="result-title" style="font-weight:600">النتيجة</div>
            </div>
            <div class="result-content" id="ocrResult" class="muted" style="color:var(--text-secondary,#64748b)">—</div>
          </div>
        </div>
      </div>
    </section>

  {{-- Merge --}}
  <section class="panel section" data-section="merge">
    <div class="topbar"><h2 style="margin:0;font-size:1.1rem">دمج ملفات PDF</h2></div>
    <div class="two">
      <div>
        <div class="drop" data-drop="merge">
          اسحب ملفين PDF فأكثر أو انقر للاختيار
          <input type="file" accept="application/pdf" multiple class="d-none" id="pick-merge"/>
        </div>
        <div id="mergeList" style="display:none; margin-top:10px">
          <div class="muted">رتّب الملفات بالسحب:</div>
          <div id="mergeReorder"></div>
          <div class="hr"></div>
          <label>اسم الملف الناتج</label>
          <input id="mergeOut" type="text" placeholder="merged.pdf">
          <div class="row" style="margin-top:8px">
            <button class="btn primary" id="btnDoMerge">تنفيذ الدمج</button>
          </div>
        </div>
      </div>
      <div>
        <div class="topbar"><div>النتيجة</div></div>
        <div id="mergeResult" class="muted">—</div>
      </div>
    </div>
  </section>

  {{-- Split --}}
  <section class="panel section" data-section="split">
    <div class="topbar"><h2 style="margin:0;font-size:1.1rem">تقسيم PDF</h2></div>
    <div class="two">
      <div>
        <div class="drop" data-drop="split">
          اسحب PDF هنا أو انقر للاختيار
          <input type="file" accept="application/pdf" class="d-none" id="pick-split"/>
        </div>
        <div id="splitConfig" style="display:none; margin-top:10px">
          <div class="row">
            <label style="flex:1">النطاقات (مثل: 1-3,5,7-9)</label>
            <input id="ranges" type="text" placeholder="1-3,5,7-9">
          </div>
          <div class="row" style="margin-top:8px">
            <label class="row" style="gap:6px"><input type="checkbox" id="splitSeparate"> حفظ كملفات متعددة</label>
            <button class="btn" id="btnPreviewPages">معاينة الصفحات</button>
            <button class="btn primary" id="btnDoSplit">تنفيذ التقسيم</button>
          </div>
          <div id="splitPreviews" class="cards" style="margin-top:10px"></div>
        </div>
      </div>
      <div>
        <div class="topbar"><div>النتائج</div></div>
        <div id="splitResult" class="muted">—</div>
      </div>
    </div>
  </section>

  {{-- Compress --}}
  <section class="panel section" data-section="compress">
    <div class="topbar"><h2 style="margin:0;font-size:1.1rem">ضغط PDF</h2></div>
    <div class="two">
      <div>
        <div class="drop" data-drop="compress">
          اسحب PDF هنا أو انقر للاختيار
          <input type="file" accept="application/pdf" class="d-none" id="pick-compress"/>
        </div>
        <div id="compressConfig" style="display:none; margin-top:10px">
          <label>مستوى الضغط</label>
          <select id="compressLevel">
            <option value="low">low</option>
            <option value="medium" selected>medium</option>
            <option value="high">high</option>
          </select>
          <label style="margin-top:6px">اسم الملف الناتج</label>
          <input id="compressOut" type="text" placeholder="compressed.pdf">
          <div class="row" style="margin-top:8px">
            <button class="btn primary" id="btnDoCompress">تنفيذ الضغط</button>
          </div>
        </div>
      </div>
      <div>
        <div class="topbar"><div>النتيجة</div></div>
        <div id="compressResult" class="muted">—</div>
      </div>
    </div>
  </section>

  {{-- Convert --}}
  <section class="panel section" data-section="convert">
    <div class="topbar"><h2 style="margin:0;font-size:1.1rem">تحويل إلى PDF</h2></div>
    <div class="two">
      <div>
        <div class="drop" data-drop="convert">
          اسحب DOCX/TXT/MD/PDF هنا أو انقر للاختيار
          <input type="file" accept=".pdf,.docx,.txt,.md,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown" class="d-none" id="pick-convert"/>
        </div>
        <div id="convertConfig" style="display:none; margin-top:10px">
          <label>اسم الملف الناتج (PDF)</label>
          <input id="convertOut" type="text" placeholder="output.pdf">
          <div class="row" style="margin-top:8px">
            <button class="btn primary" id="btnDoConvert">تنفيذ التحويل</button>
          </div>
        </div>
      </div>
      <div>
        <div class="topbar"><div>النتيجة</div></div>
        <div id="convertResult" class="muted">—</div>
      </div>
    </div>
  </section>

  {{-- Watermark --}}
  <section class="panel section" data-section="watermark">
    <div class="topbar"><h2 style="margin:0;font-size:1.1rem">علامة مائية</h2></div>
    <div class="two">
      <div>
        <div class="drop" data-drop="watermark">
          اسحب PDF هنا أو انقر للاختيار
          <input type="file" accept="application/pdf" class="d-none" id="pick-watermark"/>
        </div>
        <div id="wmConfig" style="display:none; margin-top:10px">
          <div class="row">
            <div style="flex:1">
              <label>النص</label>
              <input id="wmText" type="text" placeholder="CONFIDENTIAL">
            </div>
            <div style="width:140px">
              <label>الشفافية</label>
              <input id="wmOpacity" type="number" step="0.05" min="0" max="1" value="0.2">
            </div>
          </div>
          <div class="row" style="margin-top:8px">
            <div style="flex:1">
              <label>الموضع</label>
              <select id="wmPosition">
                <option value="center">center</option>
                <option value="top">top</option>
                <option value="bottom">bottom</option>
                <option value="diagonal" selected>diagonal</option>
                <option value="tile">tile</option>
              </select>
            </div>
            <div style="width:160px">
              <label>حجم الخط</label>
              <input id="wmFontSize" type="number" min="8" max="300" value="64">
            </div>
          </div>
          <div class="row" style="margin-top:8px">
            <button class="btn" id="btnWmPreview">معاينة</button>
            <label style="flex:1">اسم الملف الناتج</label>
            <input id="wmOut" type="text" placeholder="watermarked.pdf">
            <button class="btn primary" id="btnWmCommit">إنشاء النهائي</button>
          </div>
        </div>
      </div>
      <div>
        <div class="topbar"><div>المعاينة / النتيجة</div></div>
        <div id="wmResult" class="muted">—</div>
      </div>
    </div>
  </section>

  {{-- تنزيلاتي (هذه الجلسة فقط) --}}
  <section class="panel section" data-section="files">
    <div class="topbar"><div>تنزيلاتي (هذه الجلسة)</div><button class="btn" id="btnRefreshFiles">تحديث</button></div>
    <div id="filesList" class="cards"></div>
  </section>

</div>

{{-- JS مفصول --}}
<script src="{{ asset('assets/js/pdf_studio.js?v=1.0.2') }}" defer></script>
@endsection
