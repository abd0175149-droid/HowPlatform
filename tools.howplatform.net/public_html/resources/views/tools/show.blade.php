@extends('layouts.app')
@section('title', $tool->name . ' | HOW Tools')

@section('content')
  <div class="page-header">
    <h1 class="page-title">{{ $tool->name }}</h1>
    <p class="page-description">{{ $tool->description }}</p>
  </div>

  <div class="reveal" style="display:flex; gap:1.5rem; flex-wrap:wrap; align-items:flex-start;">
    <img src="{{ $tool->image_url }}" alt="{{ $tool->name }}" style="width: 480px; max-width:100%; border-radius:16px; box-shadow:var(--shadow);">
    <div>
      <p><strong>التصنيفات:</strong>
        @foreach($tool->categories as $c)
          <span class="tool-category">{{ $c->name }}</span>
        @endforeach
      </p>
      @if(optional($tool->stats)->rating_avg)
        <p style="margin-top:.5rem;"><strong>التقييم:</strong> <i class="fas fa-star"></i> {{ number_format($tool->stats->rating_avg,1) }}</p>
      @endif

      <div style="margin-top:1rem;">
        <a href="{{ route('tools.go', $tool->slug) }}" class="btn btn-primary">استخدم الأداة</a>
      </div>
    </div>
  </div>
@endsection
