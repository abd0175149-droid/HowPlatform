@extends('layouts.app')
@section('title', 'عرض توزيع المجموعات')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/groupings.css') }}">
<style>
  /* تحسينات إضافية على التصميم الحالي */
  .groups-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
  }
  
  .session-header {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: var(--surface-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: var(--shadow-sm);
  }
  
  .session-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
  }
  
  .session-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
  }
  
  .groups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
  }
  
  .group-card {
    --group-color: var(--primary-500);
    background: var(--surface-card);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 240px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  
  .group-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  }
  
  .group-head {
    padding: 1rem;
    background: color-mix(in srgb, var(--group-color), #fff 80%);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }
  
  .swatch {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: var(--group-color);
  }
  
  .group-title {
    font-weight: 800;
    color: #0f172a;
    font-size: 1.1rem;
  }
  
  .group-body {
    padding: 1rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
  }
  
  .member {
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border: 1px dashed var(--border-color);
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    background: var(--surface-100);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .member-count {
    margin-top: auto;
    padding-top: 0.75rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-align: center;
    border-top: 1px dashed var(--border-color);
  }
  
  .naming-panel {
    background: var(--surface-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
  }
  
  .field {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
  }
  
  .field label {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 1rem;
  }
  
  /* تصميم جديد لخيارات التسمية - بديل جميل للراديو */
  .naming-options {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
  }
  
  .naming-option {
    position: relative;
    flex: 1;
    min-width: 150px;
  }
  
  .naming-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
  }
  
  .naming-option-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1.25rem 1rem;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background: var(--surface-base);
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    height: 100%;
  }
  
  .naming-option-label:hover {
    border-color: var(--primary-300);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
  }
  
  .naming-option input[type="radio"]:checked + .naming-option-label {
    border-color: var(--primary-500);
    background: color-mix(in srgb, var(--primary-500), #fff 92%);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-500), transparent 80%);
  }
  
  .option-icon {
    font-size: 1.5rem;
    color: var(--primary-500);
  }
  
  .option-title {
    font-weight: 700;
    color: var(--text-primary);
  }
  
  .option-desc {
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.3;
  }
  
  .naming-option input[type="radio"]:checked + .naming-option-label .option-icon,
  .naming-option input[type="radio"]:checked + .naming-option-label .option-title {
    color: var(--primary-700);
  }
  
  .option-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .sub-options {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--border-color);
  }
  
  select.input, textarea.input, input.input {
    line-height: 1.2rem;
    border: 1px solid var(--primary-300);
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    background: var(--surface-card);
    color: var(--text-primary);
    transition: all 0.15s ease;
  }
  
  select.input:focus, textarea.input:focus, input.input:focus {
    outline: none;
    border-color: var(--primary-500);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-500), transparent 80%);
  }
  
  textarea.input {
    min-height: 100px;
    resize: vertical;
  }
  
  .hint {
    color: var(--text-secondary);
    font-size: 0.85rem;
  }
  
  .muted {
    color: var(--text-secondary);
  }
  
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: var(--surface-100);
    border: 1px solid var(--border-color);
    font-size: 0.85rem;
  }
  
  .badge-primary {
    background: color-mix(in srgb, var(--primary-500), #fff 90%);
    border-color: color-mix(in srgb, var(--primary-500), #fff 70%);
    color: var(--primary-800);
  }
  
  .actions-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin: 1.5rem 0 0.5rem;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.6rem 1rem;
    background: var(--surface-base);
    cursor: pointer;
    font-weight: 500;
    transition: all 0.15s ease;
    text-decoration: none;
    font-size: 0.9rem;
  }
  
  .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }
  
  .btn.primary {
    background: var(--primary-500);
    border-color: var(--primary-500);
    color: #fff;
  }
  
  .btn.primary:hover {
    background: var(--primary-600);
    border-color: var(--primary-600);
  }
  
  .btn.danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
  }
  
  .btn.danger:hover {
    background: #b91c1c;
    border-color: #b91c1c;
  }
  
  @media (max-width: 768px) {
    .groups-grid {
      grid-template-columns: 1fr;
    }
    
    .session-meta {
      flex-direction: column;
      align-items: flex-start;
    }
    
    .session-actions {
      flex-direction: column;
      align-items: stretch;
    }
    
    .naming-options {
      flex-direction: column;
    }
    
    .naming-option {
      min-width: 100%;
    }
  }
</style>
@endpush

@section('sidebar')
  @include('partials.sidebar-groupings')
@endsection

@section('content')
  @include('groupings._steps', ['active' => 'preview'])
  
  <div class="groups-container">
    {{-- رأس الجلسة --}}
    <div class="session-header">
      <div class="session-meta">
        <div class="badge badge-primary">
          <i class="fas fa-users"></i> 
          الصف: <strong>{{ $session->class_name ?? 'غير محدد' }}</strong>
        </div>
        
        <div class="badge">
          <i class="fas fa-layer-group"></i> 
          عدد المجموعات: <strong>{{ $groups->count() }}</strong>
        </div>
        
        @php
          $totalStudents = 0;
          foreach($groups as $group) {
            $totalStudents += count($group->members ?? collect());
          }
        @endphp
        
        <div class="badge">
          <i class="fas fa-user-graduate"></i> 
          إجمالي الطلاب: <strong>{{ $totalStudents }}</strong>
        </div>
        
        @php
          $nm = optional($groups->first())->naming_mode ?? null;
          $ns = optional($groups->first())->name_set ?? null;
        @endphp
        
        <div class="badge">
          <i class="fas fa-tag"></i> 
          أسلوب التسمية:
          <strong>
            @if($nm === 'set') قائمة جاهزة ({{ $ns ?: '—' }})
            @elseif($nm === 'custom') مخصصة
            @else افتراضي
            @endif
          </strong>
        </div>
        
        <div class="muted" style="margin-inline-start: auto">
          <i class="fas fa-hashtag"></i> رقم الجلسة: #{{ $session->id }}
        </div>
      </div>
      
      <div class="session-actions">
        <div class="actions-bar">
          <form method="POST" action="{{ route('user.groupings.generate', $session->id) }}">
        @csrf
        <input type="hidden" name="reshuffle" value="1">
        <button type="submit" class="btn"><i class="fas fa-random"></i> إعادة توزيع </button>
      </form>
          <button class="btn primary no-print" id="btn-fullscreen">
            <i class="fas fa-expand"></i> تكبير الشاشة
          </button>
          <a class="btn" href="{{ route('user.groupings.pdf', $session->id) }}">
          <i class="fas fa-file-pdf"></i> تنزيل PDF
        </a>

        </div>
      </div>
    </div>

    {{-- لوحة تسمية المجموعات - تم نقلها للأعلى --}}
    <div class="naming-panel no-print">
      <form method="POST" action="{{ route('user.groupings.applyNames', $session->id) }}" id="namesForm">
        @csrf
        
        <h3 style="margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
          <i class="fas fa-tags"></i> تسمية المجموعات
        </h3>
        
        <p class="hint" style="margin-bottom: 1.5rem;">
          اختر طريقة مناسبة لتسمية مجموعاتك. يمكنك تغيير الأسماء دون الحاجة إلى إعادة التوزيع.
        </p>

        <div class="field">
          <label>اختر طريقة التسمية</label>
          <div class="naming-options">
            {{-- خيار التسمية الافتراضي --}}
            <div class="naming-option">
              <input type="radio" name="naming_mode" value="default" id="naming_default" class="input" checked>
              <label for="naming_default" class="naming-option-label">
                <div class="option-content">
                  <i class="fas fa-sort-numeric-down option-icon"></i>
                  <div class="option-title">التسمية الافتراضية</div>
                  <div class="option-desc">المجموعة 1، المجموعة 2، ...</div>
                </div>
              </label>
            </div>
            
            {{-- خيار القوائم الجاهزة --}}
            <div class="naming-option">
              <input type="radio" name="naming_mode" value="set" id="naming_set" class="input">
              <label for="naming_set" class="naming-option-label">
                <div class="option-content">
                  <i class="fas fa-list-alt option-icon"></i>
                  <div class="option-title">قائمة جاهزة</div>
                  <div class="option-desc">اختر من قوائم الأسماء المعدة مسبقاً</div>
                </div>
              </label>
            </div>
            
            {{-- خيار الأسماء المخصصة --}}
            <div class="naming-option">
              <input type="radio" name="naming_mode" value="custom" id="naming_custom" class="input">
              <label for="naming_custom" class="naming-option-label">
                <div class="option-content">
                  <i class="fas fa-edit option-icon"></i>
                  <div class="option-title">أسماء مخصصة</div>
                  <div class="option-desc">أدخل أسماء المجموعات بنفسك</div>
                </div>
              </label>
            </div>
          </div>
        </div>

        {{-- خيارات القوائم الجاهزة --}}
        <div class="sub-options" id="setRow" style="display:none">
          <div class="field">
              <select name="name_set_key" id="name_set_key" class="input" style="min-width:260px">
          @foreach($nameSets as $key => $names)
            <option value="{{ $key }}" {{ (old('name_set_key', $groups->first()->name_set ?? '') === $key) ? 'selected' : '' }}>
              {{ $nameSetLabels[$key] ?? $key }} ({{ count($names) }})
            </option>
          @endforeach
        </select>
        
        <div class="hint">اختر قائمة تغطي عدد مجموعاتك الحالي (<strong>{{ $groups->count() }}</strong>).</div>
        <div class="hint" id="setPreview" style="margin-top:.25rem"></div>
        
        <script>
        (function(){
          window.NAME_SETS = @json($nameSets, JSON_UNESCAPED_UNICODE);
          const selectEl = document.getElementById('name_set_key');
          const previewEl= document.getElementById('setPreview');
          const previewCount = {{ max(1, min(8, $minGroups)) }};
        
          function updatePreview(){
            const key = selectEl?.value;
            const arr = (window.NAME_SETS?.[key] || []).slice(0, previewCount);
            previewEl.textContent = arr.length ? ('مثال: ' + arr.join('، ') + '…') : '';
          }
          selectEl?.addEventListener('change', updatePreview);
          document.addEventListener('DOMContentLoaded', updatePreview);
        })();
        </script>
          </div>
        </div>

        {{-- خيار الأسماء المخصصة --}}
        <div class="sub-options" id="customRow" style="display:none">
          <div class="field">
            <label for="custom_names">أدخل أسماء المجموعات</label>
            <textarea name="custom_names" id="custom_names" class="input" 
                      placeholder="فريق الأحمر, فريق الأزرق, فريق الأخضر ..."></textarea>
            <div class="hint">
              يجب أن يكون عدد الأسماء مساويًا لعدد المجموعات (<strong>{{ $groups->count() }}</strong>).
              @if($groups->count() > 0)
                <span id="name-count" style="color: #dc2626;"></span>
              @endif
            </div>
          </div>
        </div>

        <div class="actions-bar">
          <button type="submit" class="btn primary">
            <i class="fas fa-check"></i> تطبيق التغييرات
          </button>
          
          <button type="button" class="btn" id="btnSuggest">
            <i class="fas fa-lightbulb"></i> اقتراح أسماء تلقائيًا
          </button>
          
          <button type="button" class="btn danger" id="btnReset">
            <i class="fas fa-undo"></i> الرجوع إلى الوضع الافتراضي
          </button>
        </div>
      </form>
    </div> 



    {{-- عرض المجموعات --}}
    <div class="groups-grid" id="groupsGrid">
      @foreach($groups as $g)
        @php
          $members = $g->members ?? collect();
          $color = $g->color ?: '#219ebc';
          $memberCount = count($members);
        @endphp
        
        <div class="group-card" style="--group-color: {{ $color }}">
          <div class="group-head">
            <span class="swatch" aria-hidden="true"></span>
            <div class="group-title">{{ $g->group_label }}</div>
          </div>
          
          <div class="group-body">
            @forelse($members as $m)
              <div class="member">
                <i class="far fa-user"></i>
                {{ $m->student_name_snapshot ?? $m->student_name ?? '' }}
              </div>
            @empty
              <div class="muted" style="text-align: center; margin-top: 1rem;">
                لا يوجد طلاب في هذه المجموعة
              </div>
            @endforelse
            
            @if($memberCount > 0)
              <div class="member-count">
                <i class="fas fa-users"></i> {{ $memberCount }} طالب
              </div>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function(){
  const qs = s => document.querySelector(s);
  const qsa = s => Array.from(document.querySelectorAll(s));

  const modeInputs = qsa('input[name="naming_mode"]');
  const setRow = qs('#setRow');
  const customRow = qs('#customRow');
  const customNames = qs('#custom_names');
  const nameCount = qs('#name-count');
  const form = qs('#namesForm');
  const expected = {{ $groups->count() }};

  function updateVisibility() {
    const val = (modeInputs.find(r => r.checked) || {}).value;
    setRow.style.display = val === 'set' ? '' : 'none';
    customRow.style.display = val === 'custom' ? '' : 'none';
    
    // تحديث عدد الأسماء في الوضع المخصص
    if (val === 'custom' && customNames && nameCount) {
      updateNameCount();
    }
  }
  
  function updateNameCount() {
    const arr = customNames.value.split(',').map(s => s.trim()).filter(Boolean);
    const count = arr.length;
    
    if (count === expected) {
      nameCount.textContent = ` (${count} - صحيح)`;
      nameCount.style.color = '#16a34a';
    } else {
      nameCount.textContent = ` (${count} - يجب أن يكون ${expected})`;
      nameCount.style.color = '#dc2626';
    }
  }

  modeInputs.forEach(r => r.addEventListener('change', updateVisibility));
  
  if (customNames) {
    customNames.addEventListener('input', updateNameCount);
  }
  
  updateVisibility();

  // اقتراح أسماء تلقائيًا
  qs('#btnSuggest')?.addEventListener('click', () => {
    const setSelect = qs('#name_set_key');
    
    // اختيار قائمة مناسبة بناءً على عدد المجموعات
    let chosen = 'AR_NUMERIC';
    if (expected <= 10) chosen = 'COLORS';
    else if (expected <= 22) chosen = 'ARAB_COUNTRIES';
    else if (expected <= 26) chosen = 'A_Z';
    
    setSelect.value = chosen;
    modeInputs.forEach(r => { 
      if (r.value === 'set') r.checked = true; 
    });
    
    updateVisibility();
    
    // إظهار رسالة تأكيد
    showToast(`تم اختيار "${setSelect.options[setSelect.selectedIndex].text}" تلقائيًا`, 'success');
  });

  // الرجوع للوضع الافتراضي
  qs('#btnReset')?.addEventListener('click', () => {
    modeInputs.forEach(r => { 
      if (r.value === 'default') r.checked = true; 
    });
    
    updateVisibility();
    
    if (customNames) {
      customNames.value = '';
    }
    
    showToast('تم الرجوع إلى التسمية الافتراضية', 'info');
  });

  // التحقق من عدد الأسماء قبل الإرسال في الوضع المخصص
  form?.addEventListener('submit', (e) => {
    const mode = (modeInputs.find(r => r.checked) || {}).value;
    
    if (mode === 'custom') {
      const arr = customNames.value.split(',').map(s => s.trim()).filter(Boolean);

      
      if (arr.length !== expected) {
        e.preventDefault();
        showToast(`يجب أن يكون عدد الأسماء مساويًا لعدد المجموعات بالضبط (${expected}). لديك حاليًا ${arr.length} أسماء.`, 'error');
        return false;
      }
    }
  });

  // وضع ملء الشاشة
  const btnFS = qs('#btn-fullscreen');
  const shell = qs('#groupsGrid');
  
  btnFS?.addEventListener('click', () => {
    if (!document.fullscreenElement) {
      (shell.requestFullscreen ? shell.requestFullscreen() :
       shell.webkitRequestFullscreen ? shell.webkitRequestFullscreen() :
       shell.msRequestFullscreen && shell.msRequestFullscreen());
    } else {
      (document.exitFullscreen ? document.exitFullscreen() :
       document.webkitExitFullscreen ? document.webkitExitFullscreen() :
       document.msExitFullscreen && document.msExitFullscreen());
    }
  });

  // دالة لعرض الرسائل
  function showToast(message, type = 'info') {
    const colors = {
      success: '#16a34a',
      error: '#dc2626',
      info: '#2563eb'
    };
    
    const toast = document.createElement('div');
    toast.textContent = message;
    toast.style.cssText = `
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: ${colors[type] || colors.info};
      color: white;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      z-index: 1000;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
      document.body.removeChild(toast);
    }, 3000);
  }
})();
</script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // إخفاء التنبيهات تلقائيًا بعد 10 ثوانٍ
    setTimeout(() => {
      document.querySelectorAll('.auto-dismiss').forEach(el => {
        el.style.transition = "opacity 0.5s ease";
        el.style.opacity = "0";
        setTimeout(() => el.remove(), 500);
      });
    }, 10000);
  });
</script>
@endpush