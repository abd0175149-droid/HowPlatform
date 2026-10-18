@extends('layouts.admin')
@section('title','حزم النقاط | Plans')
@section('heading','حزم النقاط (سلّم التسعير)')

@section('content')
  <p class="text-muted">سلّم تسعير النقاط المستخدَم عند إصدار بطاقات النقاط (الأدمن والبائعون). عدّل السعر هنا ليُطبَّق على الإصدارات الجديدة.</p>

  <div class="row g-3">
    {{-- إضافة حزمة --}}
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <h6 class="fw-bold mb-3"><i class="fas fa-plus"></i> إضافة حزمة</h6>
          <form method="post" action="{{ route('admin.packages.store') }}">
            @csrf
            <div class="mb-2">
              <label class="form-label">عدد النقاط</label>
              <input type="number" name="points" class="form-control" min="1" value="{{ old('points') }}" required>
            </div>
            <div class="mb-2">
              <label class="form-label">السعر</label>
              <div class="input-group">
                <input type="number" step="0.01" name="price_amount" class="form-control" min="0" value="{{ old('price_amount') }}" required>
                <span class="input-group-text">JOD</span>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">اسم عرضي (اختياري)</label>
              <input name="label" class="form-control" value="{{ old('label') }}" placeholder="يُبنى تلقائيًا">
            </div>
            <div class="mb-2">
              <label class="form-label">الترتيب</label>
              <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order',0) }}">
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" name="is_active" class="form-check-input" id="np_active" value="1" checked>
              <label class="form-check-label" for="np_active">مفعّلة</label>
            </div>
            <button class="btn btn-brand w-100"><i class="fas fa-plus"></i> إضافة</button>
          </form>
        </div>
      </div>
    </div>

    {{-- القائمة --}}
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body p-0">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>النقاط</th><th>السعر</th><th>الاسم</th><th>الترتيب</th><th>الحالة</th><th></th>
              </tr>
            </thead>
            <tbody>
              @forelse($packages as $pkg)
                <tr>
                  <td class="fw-bold">{{ number_format($pkg->points) }}</td>
                  <td>{{ rtrim(rtrim(number_format((float)$pkg->price_amount,2),'0'),'.') }} {{ $pkg->price_currency }}</td>
                  <td class="text-muted">{{ $pkg->label ?: '—' }}</td>
                  <td>{{ $pkg->sort_order }}</td>
                  <td>
                    @if($pkg->is_active)
                      <span class="badge bg-success">مفعّلة</span>
                    @else
                      <span class="badge bg-secondary">معطّلة</span>
                    @endif
                  </td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#edit-{{ $pkg->id }}"><i class="fas fa-pen"></i></button>
                    <form method="post" action="{{ route('admin.packages.toggle',$pkg) }}" class="d-inline">
                      @csrf
                      <button class="btn btn-sm btn-outline-{{ $pkg->is_active ? 'warning' : 'success' }}">
                        <i class="fas fa-power-off"></i>
                      </button>
                    </form>
                    <form method="post" action="{{ route('admin.packages.destroy',$pkg) }}" class="d-inline" onsubmit="return confirm('حذف هذه الحزمة؟')">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
                <tr class="collapse" id="edit-{{ $pkg->id }}">
                  <td colspan="6" class="bg-light">
                    <form method="post" action="{{ route('admin.packages.update',$pkg) }}" class="row g-2 align-items-end">
                      @csrf @method('PUT')
                      <div class="col-md-3">
                        <label class="form-label small">النقاط</label>
                        <input type="number" name="points" class="form-control form-control-sm" min="1" value="{{ $pkg->points }}" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small">السعر (JOD)</label>
                        <input type="number" step="0.01" name="price_amount" class="form-control form-control-sm" min="0" value="{{ $pkg->price_amount }}" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small">الاسم</label>
                        <input name="label" class="form-control form-control-sm" value="{{ $pkg->label }}">
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control form-control-sm" min="0" value="{{ $pkg->sort_order }}">
                      </div>
                      <div class="col-md-1">
                        <input type="hidden" name="is_active" value="{{ $pkg->is_active ? 1 : 0 }}">
                        <button class="btn btn-sm btn-brand w-100">حفظ</button>
                      </div>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-4">لا توجد حزم بعد.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
