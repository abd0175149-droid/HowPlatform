@extends('layouts.app')
@section('title','إدارة الأدوات | لوحة المشرف')

@section('content')
  <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div>
      <h1 class="page-title">إدارة الأدوات</h1>
      <p class="page-description">إضافة وتعديل وحذف الأدوات، وإسناد التصنيفات، والتحكّم بالظهور.</p>
    </div>
    <a href="{{ route('admin.tools.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> أداة جديدة</a>
  </div>

  @if(session('ok'))
    <div class="alert alert-success" style="background:#dcfce7; color:#166534; padding:.75rem 1rem; border-radius:10px; margin-bottom:1rem;">{{ session('ok') }}</div>
  @endif

  <div style="overflow-x:auto; background:#fff; border-radius:14px; box-shadow:var(--shadow);">
    <table style="width:100%; border-collapse:collapse; min-width:760px;">
      <thead>
        <tr style="background:#f8fafc; text-align:right;">
          <th style="padding:.75rem;">الأداة</th>
          <th style="padding:.75rem;">التصنيفات</th>
          <th style="padding:.75rem;">الاستخدام</th>
          <th style="padding:.75rem;">التقييم</th>
          <th style="padding:.75rem;">مفعّلة</th>
          <th style="padding:.75rem;">مميّزة</th>
          <th style="padding:.75rem;">إجراءات</th>
        </tr>
      </thead>
      <tbody>
        @forelse($tools as $tool)
          <tr style="border-top:1px solid #eef2f7;">
            <td style="padding:.75rem;">
              <div style="display:flex; align-items:center; gap:.5rem;">
                <i class="{{ $tool->icon_class ?: 'fas fa-cubes' }}" style="color:{{ $tool->icon_color ?: '#f97316' }};"></i>
                <div>
                  <div style="font-weight:600;">{{ $tool->name }}</div>
                  <div style="font-size:.8rem; color:#94a3b8;">{{ $tool->slug }}</div>
                </div>
              </div>
            </td>
            <td style="padding:.75rem; font-size:.85rem;">
              @forelse($tool->categories as $c)
                <span class="tool-category">{{ $c->name }}</span>
              @empty
                <span style="color:#cbd5e1;">—</span>
              @endforelse
            </td>
            <td style="padding:.75rem;">{{ number_format((int) optional($tool->stats)->uses_count) }}</td>
            <td style="padding:.75rem;">
              @if(optional($tool->stats)->rating_avg)
                <i class="fas fa-star" style="color:#f5a623;"></i> {{ number_format($tool->stats->rating_avg,1) }}
              @else <span style="color:#cbd5e1;">—</span> @endif
            </td>
            <td style="padding:.75rem;">
              <form method="post" action="{{ route('admin.tools.toggle', $tool) }}">
                @csrf <input type="hidden" name="field" value="is_active">
                <button type="submit" class="btn" style="border:none; background:transparent; cursor:pointer; font-size:1.1rem;">
                  <i class="fas {{ $tool->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}" style="color:{{ $tool->is_active ? '#16a34a' : '#cbd5e1' }};"></i>
                </button>
              </form>
            </td>
            <td style="padding:.75rem;">
              <form method="post" action="{{ route('admin.tools.toggle', $tool) }}">
                @csrf <input type="hidden" name="field" value="is_featured">
                <button type="submit" class="btn" style="border:none; background:transparent; cursor:pointer; font-size:1.1rem;">
                  <i class="fas {{ $tool->is_featured ? 'fa-star' : 'fa-star' }}" style="color:{{ $tool->is_featured ? '#f5a623' : '#cbd5e1' }};"></i>
                </button>
              </form>
            </td>
            <td style="padding:.75rem; white-space:nowrap;">
              <a href="{{ route('admin.tools.edit', $tool) }}" class="btn" style="color:var(--primary-600);"><i class="fas fa-pen"></i></a>
              <a href="{{ route('tools.show', $tool->slug) }}" target="_blank" class="btn" style="color:#64748b;"><i class="fas fa-eye"></i></a>
              <form method="post" action="{{ route('admin.tools.destroy', $tool) }}" style="display:inline;"
                    onsubmit="return confirm('حذف الأداة «{{ $tool->name }}» نهائيًا؟');">
                @csrf @method('DELETE')
                <button type="submit" class="btn" style="border:none; background:transparent; cursor:pointer; color:#dc2626;"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" style="padding:1.5rem; text-align:center; color:#94a3b8;">لا توجد أدوات بعد.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:1rem;">{{ $tools->links() }}</div>
@endsection
