@extends('layouts.admin')
@section('title','الخطط | Plans')
@section('heading','الخطط والمزايا')

@section('content')
  <div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.plans.create') }}" class="btn btn-brand btn-sm"><i class="fas fa-plus"></i> خطة جديدة</a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>الخطة</th><th>الرتبة</th><th>السعر</th><th>المدة</th><th>نقاط المنحة</th><th>المزايا</th><th>مفعّلة</th><th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($plans as $plan)
            <tr>
              <td>
                <div class="fw-semibold">{{ $plan->name }}</div>
                <div class="text-muted small">{{ $plan->slug }}</div>
              </td>
              <td>{{ $plan->level }}</td>
              <td>{{ number_format($plan->price,2) }} {{ $plan->currency }}</td>
              <td>{{ $plan->duration_days }} يوم</td>
              <td>{{ number_format($plan->grant_points) }}</td>
              <td><span class="badge bg-info">{{ $plan->features_count }}</span></td>
              <td>
                <form method="post" action="{{ route('admin.plans.toggle', $plan) }}">@csrf
                  <button class="btn btn-sm border-0 bg-transparent p-0">
                    <i class="fas {{ $plan->is_active ? 'fa-toggle-on text-success' : 'fa-toggle-off text-secondary' }} fa-lg"></i>
                  </button>
                </form>
              </td>
              <td class="text-nowrap">
                <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen"></i></a>
                <form method="post" action="{{ route('admin.plans.destroy', $plan) }}" class="d-inline"
                      onsubmit="return confirm('حذف الخطة «{{ $plan->name }}»؟');">@csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">لا توجد خطط بعد. أنشئ أول خطة.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
