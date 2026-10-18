@extends('layouts.admin')
@section('title','الإشعارات | Plans')
@section('heading','الإشعارات')

@section('content')
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="btn-group btn-group-sm" role="group">
      <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline-secondary {{ ($filter ?? '')==='' ? 'active' : '' }}">الكل ({{ number_format($total) }})</a>
      <a href="{{ route('admin.notifications.index', ['filter'=>'unread']) }}" class="btn btn-outline-secondary {{ ($filter ?? '')==='unread' ? 'active' : '' }}">غير المقروءة ({{ number_format($unread) }})</a>
    </div>
    <div class="d-flex gap-2">
      <form method="post" action="{{ route('admin.notifications.readAll') }}">@csrf
        <button class="btn btn-sm btn-outline-primary" {{ $unread ? '' : 'disabled' }}><i class="fas fa-check-double"></i> تمييز الكل كمقروء</button>
      </form>
      <form method="post" action="{{ route('admin.notifications.clear') }}" onsubmit="return confirm('حذف الإشعارات المقروءة؟')">@csrf
        <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-broom"></i> حذف المقروءة</button>
      </form>
      <form method="post" action="{{ route('admin.notifications.clear') }}" onsubmit="return confirm('حذف كل الإشعارات نهائيًا؟')">@csrf
        <input type="hidden" name="scope" value="all">
        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> حذف الكل</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="list-group list-group-flush">
      @forelse($items as $n)
        <div class="list-group-item d-flex align-items-start gap-3 {{ $n->read_at ? '' : 'bg-light' }}">
          <div style="width:38px;height:38px;border-radius:11px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;color:#fff;background:{{ $n->color }}">
            <i class="fas {{ $n->icon }}"></i>
          </div>
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2">
              @unless($n->read_at)<span class="badge bg-warning text-dark" style="font-size:.6rem;">جديد</span>@endunless
              @if($n->link)
                <a href="{{ $n->link }}" class="fw-bold text-decoration-none" style="color:var(--brand-deep)">{{ $n->title }}</a>
              @else
                <span class="fw-bold" style="color:var(--brand-deep)">{{ $n->title }}</span>
              @endif
            </div>
            @if($n->body)<div class="text-muted small">{{ $n->body }}</div>@endif
            <div class="text-muted" style="font-size:.72rem;">{{ $n->timeAgo() }}</div>
          </div>
          <div class="text-nowrap">
            <form method="post" action="{{ route('admin.notifications.read', $n) }}" class="d-inline">@csrf
              <button class="btn btn-sm btn-outline-secondary" title="{{ $n->read_at ? 'تمييز كغير مقروء' : 'تمييز كمقروء' }}">
                <i class="fas {{ $n->read_at ? 'fa-envelope' : 'fa-envelope-open' }}"></i>
              </button>
            </form>
            <form method="post" action="{{ route('admin.notifications.destroy', $n) }}" class="d-inline" onsubmit="return confirm('حذف هذا الإشعار؟')">@csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" title="حذف"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </div>
      @empty
        <div class="list-group-item text-center text-muted py-5"><i class="fas fa-check-circle fa-2x mb-2 d-block"></i>لا توجد إشعارات.</div>
      @endforelse
    </div>
  </div>

  <div class="mt-3">{{ $items->links() }}</div>
@endsection
