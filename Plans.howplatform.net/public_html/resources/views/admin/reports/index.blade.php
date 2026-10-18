@extends('layouts.admin')
@section('title','التقارير | Plans')
@section('heading','التقارير')

@section('content')
  {{-- بطاقات سريعة --}}
  <div class="row g-3 mb-4">
    @php
      $cards=[
        ['الخطط المفعّلة',$plansCount,'#116580','fa-box'],
        ['اشتراكات فعّالة',$subs['active_now'],'#16a34a','fa-id-card'],
        ['بطاقات مستبدَلة',$cards['redeemed'],'#6366f1','fa-ticket'],
        ['إجمالي النقاط',$wallet['total_points'],'#f59e0b','fa-coins'],
      ];
    @endphp
    @foreach($cards as [$l,$n,$c,$ic])
      <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small"><i class="fas {{ $ic }}"></i> {{ $l }}</div><div class="n" style="color:{{ $c }}">{{ number_format($n) }}</div></div></div>
    @endforeach
  </div>

  <div class="row g-4">
    {{-- اشتراكات فعّالة حسب الخطة --}}
    <div class="col-lg-6">
      <div class="card h-100"><div class="card-body">
        <h2 class="h6 mb-3"><i class="fas fa-chart-pie"></i> الاشتراكات الفعّالة حسب الخطة</h2>
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>الخطة</th><th>المستوى</th><th class="text-end">العدد</th></tr></thead>
          <tbody>
            @forelse($byPlan as $r)
              <tr><td>{{ $r->plan }}</td><td><span class="text-muted small">L{{ $r->level }}</span></td><td class="text-end fw-bold">{{ number_format($r->c) }}</td></tr>
            @empty
              <tr><td colspan="3" class="text-muted text-center py-3">لا اشتراكات فعّالة.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div></div>
    </div>

    {{-- البطاقات --}}
    <div class="col-lg-6">
      <div class="card h-100"><div class="card-body">
        <h2 class="h6 mb-3"><i class="fas fa-ticket"></i> البطاقات</h2>
        <div class="row g-2 small">
          <div class="col-6">الإجمالي: <b>{{ number_format($cards['total']) }}</b></div>
          <div class="col-6">متاحة: <b>{{ number_format($cards['active']) }}</b></div>
          <div class="col-6">مستبدَلة: <b>{{ number_format($cards['redeemed']) }}</b></div>
          <div class="col-6">معطّلة: <b>{{ number_format($cards['disabled']) }}</b></div>
          <div class="col-12"><hr class="my-2"></div>
          <div class="col-4">اشتراك: <b>{{ number_format($cards['sub']) }}</b></div>
          <div class="col-4">نقاط: <b>{{ number_format($cards['points']) }}</b></div>
          <div class="col-4">مختلطة: <b>{{ number_format($cards['mixed']) }}</b></div>
        </div>
      </div></div>
    </div>

    {{-- المحفظة --}}
    <div class="col-lg-6">
      <div class="card h-100"><div class="card-body">
        <h2 class="h6 mb-3"><i class="fas fa-wallet"></i> المحفظة</h2>
        <div class="row g-2 small">
          <div class="col-6">عدد المحافظ: <b>{{ number_format($wallet['accounts']) }}</b></div>
          <div class="col-6">إجمالي الرصيد: <b>{{ number_format($wallet['total_points']) }}</b></div>
          <div class="col-6">نقاط مكتسبة (إجمالي): <b>{{ number_format($wallet['earned']) }}</b></div>
          <div class="col-6">نقاط مصروفة (إجمالي): <b>{{ number_format($wallet['spent']) }}</b></div>
          <div class="col-12">قيود الدفتر: <b>{{ number_format($wallet['ledger']) }}</b></div>
        </div>
      </div></div>
    </div>

    {{-- المبيعات --}}
    <div class="col-lg-6">
      <div class="card h-100"><div class="card-body">
        <h2 class="h6 mb-3"><i class="fas fa-cash-register"></i> مبيعات الشهر ({{ $periodKey }})</h2>
        <div class="row g-2 small">
          <div class="col-6">عدد البطاقات المباعة: <b>{{ number_format($sales['count']) }}</b></div>
          <div class="col-6">صافي القيمة: <b>{{ number_format($sales['amount'],2) }} JOD</b></div>
        </div>
        <hr class="my-2">
        <h3 class="h6 mt-2">سلّم تسعير النقاط</h3>
        <table class="table table-sm mb-0">
          <tbody>
            @foreach($packages as $pk)
              <tr><td>{{ number_format($pk->points) }} نقطة</td><td class="text-end">{{ rtrim(rtrim(number_format((float)$pk->price_amount,2),'0'),'.') }} {{ $pk->price_currency }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div></div>
    </div>
  </div>
@endsection
