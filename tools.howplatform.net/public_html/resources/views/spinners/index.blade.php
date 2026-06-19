{{-- resources/views/spinners/index.blade.php --}}
@extends('layouts.app')
@section('title','العجلة الدوارة | أدوات HOW')

@push('styles')
<style>
  /* Flat Design Overrides */
  .card-flat {
    background: #fff; border-radius: 16px; padding: 24px; border: 1px solid #e5e7eb; box-shadow: none; transition: border-color 0.2s, background 0.2s;
    display: flex; flex-direction: column; height: 100%;
  }
  .card-flat:hover { border-color: var(--primary, #219ebc); background: #f8fafc; }
  
  .btn-flat {
    background: var(--primary, #219ebc); color: #fff; border: none; padding: 0.75rem 1.5rem;
    border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; justify-content: center;
  }
  .btn-flat:hover { background: #1a829c; color: #fff; }
  
  .btn-outline-flat {
    background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 0.75rem 1.5rem;
    border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; justify-content: center;
  }
  .btn-outline-flat:hover { background: #f1f5f9; color: #334155; }

  .btn-danger-flat {
    background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; padding: 0.75rem 1.5rem;
    border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; justify-content: center;
  }
  .btn-danger-flat:hover { background: #fee2e2; }

  .grid-cards {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;
  }
  
  .badge-flat {
    display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700;
  }
  .badge-blue { background: #e0f2fe; color: #0284c7; }
  .badge-green { background: #dcfce7; color: #16a34a; }
</style>
@endpush

@section('sidebar')
  @include('partials.sidebar-spinners')
@endsection

@section('content')
<div class="page-head" style="margin-bottom: 2rem; display:flex; justify-content:space-between; flex-wrap:wrap; gap:1rem; align-items:center;">
  <div style="display:flex; align-items:center; gap:1rem;">
    <div style="background:#e0f2fe; color:#0284c7; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.5rem;">
      <i class="fas fa-dharmachakra"></i>
    </div>
    <div>
      <h1 style="margin:0; font-size:1.5rem; font-weight:800; color:#0f172a;">العجلة الدوارة</h1>
      <div style="font-size:0.875rem; color:#64748b;">استخدم العجلة التفاعلية لتوزيع المهام وسحب الأسماء بعدل</div>
    </div>
  </div>
  <a class="btn-flat" href="{{ route('user.spinners.create') }}">
    <i class="fas fa-plus"></i> إنشاء عجلة جديدة
  </a>
</div>

@if($classes->isEmpty())
  <div class="card-flat" style="background: #fffbeb; border-color: #fde68a; margin-bottom: 1.5rem; align-items: flex-start;">
    <div style="display:flex; gap:0.75rem; align-items:center; margin-bottom:0.5rem;">
      <i class="fas fa-info-circle" style="color:#b45309; font-size:1.5rem;"></i>
      <h3 style="margin:0; color:#b45309; font-size:1.1rem;">لا توجد صفوف حتى الآن</h3>
    </div>
    <p style="color:#92400e; margin-bottom:1rem;">يمكنك إنشاء عجلة دوّارة بالإدخال اليدوي، ولكن لتجربة أفضل يرجى إضافة صفوفك الدراسية.</p>
    <a href="{{ url('/my/classes') }}" class="btn-flat" style="background:#b45309;">إضافة صف الآن</a>
  </div>
@endif

<div class="grid-cards">
@forelse($rows as $r)
  <div class="card-flat">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
      <div style="font-size:1.1rem; font-weight:800; color:#0f172a;">{{ $r->title }}</div>
      @if($r->status === 'active')
        <div class="badge-flat badge-green"><i class="fas fa-check-circle"></i> نشط</div>
      @else
        <div class="badge-flat badge-blue"><i class="fas fa-clock"></i> {{ $r->status }}</div>
      @endif
    </div>
    
    <div style="font-size:0.9rem; color:#475569; margin-bottom:1.5rem; display:flex; flex-direction:column; gap:0.5rem; flex: 1;">
      <div><i class="fas fa-chalkboard-teacher" style="color:#94a3b8; width:20px;"></i> <b>الصف:</b> {{ $r->class_name ?? 'إدخال يدوي (بدون صف)' }}</div>
      <div><i class="fas fa-users" style="color:#94a3b8; width:20px;"></i> <b>مشاركون:</b> {{ \DB::table('spinner_items')->where('session_id', $r->id)->where('is_active', 1)->count() }} فعال</div>
    </div>
    
    <div style="display:flex; gap:0.5rem;">
      <a class="btn-flat" href="{{ route('user.spinners.show',$r->id) }}" style="flex:1;">
        <i class="fas fa-play"></i> فتح العجلة
      </a>
      <form method="post" action="{{ route('user.spinners.destroy.post',$r->id) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذه العجلة؟');" style="margin:0;">
        @csrf 
        <button type="submit" class="btn-danger-flat" title="حذف" style="padding:0.75rem 1rem;">
          <i class="fas fa-trash"></i>
        </button>
      </form>
    </div>
  </div>
@empty
  <div style="grid-column: 1 / -1; text-align:center; padding: 4rem 1rem; border:2px dashed #cbd5e1; border-radius:16px; background:#f8fafc;">
    <i class="fas fa-dharmachakra" style="font-size:3rem; color:#94a3b8; margin-bottom:1rem;"></i>
    <h3 style="margin:0; color:#334155; font-size:1.25rem; font-weight:800;">لا توجد عجلات حتى الآن</h3>
    <p style="color:#64748b; margin-top:0.5rem; margin-bottom:1.5rem;">ابدأ بإنشاء عجلة تفاعلية لتوزيع المهام على طلابك!</p>
    <a href="{{ route('user.spinners.create') }}" class="btn-flat"><i class="fas fa-plus"></i> إنشاء عجلة جديدة</a>
  </div>
@endforelse
</div>
@endsection
