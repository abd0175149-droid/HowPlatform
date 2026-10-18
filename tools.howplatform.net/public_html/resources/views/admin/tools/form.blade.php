@extends('layouts.app')
@section('title', ($tool->exists ? 'تعديل' : 'إضافة') . ' أداة | لوحة المشرف')

@section('content')
  @php $editing = $tool->exists; @endphp

  <div class="page-header">
    <h1 class="page-title">{{ $editing ? 'تعديل أداة' : 'إضافة أداة جديدة' }}</h1>
    <p class="page-description"><a href="{{ route('admin.tools.index') }}" style="color:var(--primary-600); text-decoration:none;">&larr; رجوع لقائمة الأدوات</a></p>
  </div>

  @if($errors->any())
    <div class="alert" style="background:#fee2e2; color:#991b1b; padding:.75rem 1rem; border-radius:10px; margin-bottom:1rem;">
      <ul style="margin:0; padding-inline-start:1.25rem;">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="post"
        action="{{ $editing ? route('admin.tools.update', $tool) : route('admin.tools.store') }}"
        style="background:#fff; border-radius:14px; box-shadow:var(--shadow); padding:1.5rem; max-width:780px;">
    @csrf
    @if($editing) @method('PUT') @endif

    @php
      $field = fn($label, $name, $value, $ph='', $type='text') =>
        '<label style="display:block; margin-bottom:1rem;"><span style="display:block; font-weight:600; margin-bottom:.25rem;">'.$label.'</span>'.
        '<input type="'.$type.'" name="'.$name.'" value="'.e($value).'" placeholder="'.$ph.'" style="width:100%; padding:.6rem .75rem; border:1px solid #e2e8f0; border-radius:10px;"></label>';
    @endphp

    {!! $field('اسم الأداة *', 'name', old('name', $tool->name)) !!}
    {!! $field('المعرّف slug (إنجليزي، اتركه فارغًا للتوليد التلقائي)', 'slug', old('slug', $tool->slug), 'smart-grouping') !!}
    {!! $field('الرابط (وجهة الأداة)', 'url', old('url', $tool->url), 'https://tools.howplatform.net/...') !!}

    <label style="display:block; margin-bottom:1rem;">
      <span style="display:block; font-weight:600; margin-bottom:.25rem;">الوصف</span>
      <textarea name="description" rows="3" style="width:100%; padding:.6rem .75rem; border:1px solid #e2e8f0; border-radius:10px;">{{ old('description', $tool->description) }}</textarea>
    </label>

    {!! $field('رابط الصورة', 'image_url', old('image_url', $tool->image_url)) !!}

    <div style="display:flex; gap:1rem; flex-wrap:wrap;">
      <div style="flex:1; min-width:200px;">{!! $field('أيقونة (FontAwesome class)', 'icon_class', old('icon_class', $tool->icon_class), 'fas fa-users') !!}</div>
      <div style="flex:1; min-width:140px;">{!! $field('لون الأيقونة', 'icon_color', old('icon_color', $tool->icon_color), '#f97316') !!}</div>
    </div>

    {!! $field('تدرّج الخلفية (CSS gradient)', 'gradient', old('gradient', $tool->gradient), 'linear-gradient(45deg,#ffedd5,#fed7aa)') !!}

    <label style="display:block; margin-bottom:1rem;">
      <span style="display:block; font-weight:600; margin-bottom:.5rem;">التصنيفات</span>
      <div style="display:flex; flex-wrap:wrap; gap:.5rem;">
        @foreach($categories as $c)
          <label style="display:inline-flex; align-items:center; gap:.35rem; background:#f8fafc; padding:.35rem .6rem; border-radius:8px; cursor:pointer;">
            <input type="checkbox" name="categories[]" value="{{ $c->id }}"
                   {{ in_array($c->id, old('categories', $selected)) ? 'checked' : '' }}>
            {{ $c->name }}
          </label>
        @endforeach
      </div>
    </label>

    <div style="display:flex; gap:1.5rem; margin:1rem 0;">
      <label style="display:inline-flex; align-items:center; gap:.4rem;">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $tool->is_active) ? 'checked' : '' }}> مفعّلة
      </label>
      <label style="display:inline-flex; align-items:center; gap:.4rem;">
        <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $tool->is_featured) ? 'checked' : '' }}> مميّزة
      </label>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ $editing ? 'حفظ التعديلات' : 'إنشاء الأداة' }}</button>
  </form>
@endsection
