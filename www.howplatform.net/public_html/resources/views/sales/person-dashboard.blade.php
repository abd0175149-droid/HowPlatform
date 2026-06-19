@extends('layouts.main')

@section('content')
<div class="container-fluid">

    <h2 class="mb-4 text-center">لوحة مبيعاتي</h2>

    {{-- الكروت (إجمالي، عدد البطاقات، السقف، الرصيد الحالي) --}}
    <div class="row justify-content-center text-center mb-4">
        <div class="col-6 col-md-3 mb-3">
            <div class="p-3 bg-light border rounded shadow-sm h-100">
                <div class="mb-2"><i class="fas fa-sack-dollar fa-lg text-success"></i></div>
                <small class="text-muted d-block">إجمالي المبيعات</small>
                <div class="fw-bold text-success fs-5">
                    {{ number_format($vouchersByPlatform->sum('total'), 2) }} <small>JOD</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="p-3 bg-light border rounded shadow-sm h-100">
                <div class="mb-2"><i class="fas fa-ticket fa-lg text-primary"></i></div>
                <small class="text-muted d-block">عدد البطاقات</small>
                <div class="fw-bold text-primary fs-5">
                    {{ $vouchersByPlatform->sum('count') }}
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="p-3 bg-light border rounded shadow-sm h-100">
                <div class="mb-2"><i class="fas fa-bullseye fa-lg text-dark"></i></div>
                <small class="text-muted d-block">السقف الشهري</small>
                <div class="fw-bold text-dark fs-6">
                    @if($limit)
                        {{ number_format($limit, 2) }} <small>JOD</small>
                    @else
                        <span class="text-muted">غير محدد</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="p-3 bg-light border rounded shadow-sm h-100">
                <div class="mb-2"><i class="fas fa-wallet fa-lg text-info"></i></div>
                <small class="text-muted d-block">الرصيد الحالي</small>
                <div class="fw-bold text-info fs-5">
                    {{ number_format($currentBalance, 2) }} <small>JOD</small>
                </div>
            </div>
        </div>
    </div>

    {{-- المبيعات حسب المنصة --}}
    <h5 class="mb-2 text-center">📌 المبيعات حسب المنصة</h5>
    <div class="row justify-content-center text-center mb-3">
        @forelse($vouchersByPlatform as $row)
            <div class="col-6 col-md-3 mb-2">
                <div class="p-3 bg-white border rounded shadow-sm h-100">
                    <div class="mb-1"><i class="fas fa-building fa-lg text-info"></i></div>
                    <small class="text-muted d-block">{{ strtoupper($row['platform']) }}</small>
                    <div class="fw-bold text-primary small">{{ $row['count'] }} بطاقة</div>
                    <div class="fw-bold text-success small">{{ number_format($row['total'], 2) }} JOD</div>
                </div>
            </div>
        @empty
            <p class="text-muted">لا توجد بيانات لعرضها</p>
        @endforelse
    </div>

    {{-- المبيعات حسب نوع الاشتراك --}}
    <h5 class="mb-2 text-center">📌 المبيعات حسب نوع الاشتراك</h5>
    <div class="row justify-content-center text-center mb-4">
        @forelse($vouchersByType as $row)
            <div class="col-6 col-md-3 mb-2">
                <div class="p-3 bg-white border rounded shadow-sm h-100">
                    <div class="mb-1"><i class="fas fa-layer-group fa-lg text-warning"></i></div>
                    <small class="text-muted d-block">{{ $row['plan_name'] }}</small>
                    <div class="fw-bold text-primary small">{{ $row['count'] }} بطاقة</div>
                    <div class="fw-bold text-success small">{{ number_format($row['total'], 2) }} JOD</div>
                </div>
            </div>
        @empty
            <p class="text-muted">لا توجد بيانات لعرضها</p>
        @endforelse
    </div>

    <hr>

    {{-- العمليات المالية --}}
    <h4 class="mb-2 text-center">💰 العمليات المالية</h4>

    {{-- فلاتر مصغّرة --}}
    <form method="GET" class="row g-1 align-items-end justify-content-center mb-2 filters-compact" dir="rtl">
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm mb-1">نوع العملية</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">الكل</option>
                <option value="voucher_sale" {{ request('type')=='voucher_sale' ? 'selected' : '' }}>بيع بطاقة</option>
                <option value="payment"      {{ request('type')=='payment' ? 'selected' : '' }}>دفعة مالية</option>
                <option value="adjustment"   {{ request('type')=='adjustment' ? 'selected' : '' }}>تعديل</option>
                <option value="refund"       {{ request('type')=='refund' ? 'selected' : '' }}>استرداد</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm mb-1">من تاريخ</label>
            <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm mb-1">إلى تاريخ</label>
            <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label form-label-sm mb-1">بحث (رقم الفاتورة/ملاحظة)</label>
            <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="اكتب جزءًا من رقم الفاتورة أو الملاحظة">
        </div>
        <div class="col-12 col-md-3 text-center text-md-start mt-1">
            <button class="btn btn-primary btn-sm px-3">تطبيق</button>
            <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm px-3">إعادة التعيين</a>
        </div>
    </form>

    <div class="d-flex justify-content-center">
        <div style="width:95%;">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-bordered text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:64px">#</th>
                            <th>رقم الفاتورة</th>
                            <th>العملية</th>
                            <th>المبلغ</th>
                            <th>الرصيد قبل</th>
                            <th>الرصيد بعد</th>
                            <th>ملاحظات</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $i => $trx)
                            <tr>
                                <td>{{ $transactions->firstItem() + $i }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $trx->doc_code ?? '—' }}</span></td>
                                <td>
                                    @switch($trx->type)
                                        @case('voucher_sale') بيع بطاقة @break
                                        @case('payment')      دفعة مالية @break
                                        @case('refund')       استرداد @break
                                        @default              تعديل
                                    @endswitch
                                </td>
                                <td>{{ number_format((float)$trx->amount, 2) }}</td>
                                <td>{{ number_format((float)$trx->balance_before, 2) }}</td>
                                <td>{{ number_format((float)$trx->balance_after, 2) }}</td>
                                <td>{{ $trx->note ?: '—' }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($trx->created_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted">لا توجد عمليات لعرضها</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ترقيم عربي مرتب --}}
            @if($transactions->hasPages())
                <div class="mt-2 d-flex justify-content-center" dir="rtl">
                {{ $transactions->onEachSide(1)->links('pagination::how') }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection

{{-- ستايل خفيف لدمج الواجهة بشكل ناعم --}}
@push('styles')
<style>
    /* تصغير مسافات الفلاتر */
    .filters-compact .form-label-sm { font-size: .85rem; }
    .filters-compact .form-control-sm, 
    .filters-compact .form-select-sm { padding-top:.25rem; padding-bottom:.25rem; }
    .filters-compact .btn-sm { border-radius: .5rem; }

    /* تحسين مظهر البادجات */
    .badge.border { border-color: #dee2e6 !important; }

    /* ترقيم عربي ناعم */
    .pagination { --bs-pagination-padding-x: .5rem; --bs-pagination-padding-y: .25rem; }
    .pagination .page-link { border-radius: .5rem; }
</style>
@endpush
