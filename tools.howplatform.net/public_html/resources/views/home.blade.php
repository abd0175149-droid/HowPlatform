@extends('layouts.app')
@section('title','الرئيسية | HOW Tools')

@section('content')
@php
    // لو لم يصل $tools من الكنترولر، نستخدم بيانات افتراضية لعرض الصفحة
    $tools = $tools ?? [
        [
            'name'      => 'منشئ الدروس التفاعلية',
            'desc'      => 'أداة لإنشاء دروس تفاعلية تحتوي على وسائط متعددة وأنشطة جذابة.',
            'image'     => 'https://tools.howplatform.net/public/assets/images/logo.png',
            'gradient'  => 'linear-gradient(45deg, #ffedd5, #fed7aa)',
            'icon'      => 'fas fa-pencil-alt',
            'iconColor' => '#f97316',
            'rating'    => 4.8,
            'category'  => 'أدوات الكتابة',
            'url'       => 'https://tools.howplatform.net/',
            'active'    => 1, 
        ],
        [
            'name'      => 'محلل التقدم التعليمي',
            'desc'      => 'تتبّع تقدّم الطلاب مع لوحات قياس وتقارير مفصلة.',
            'image'     => 'https://via.placeholder.com/400x200/fed7aa/ea580c?text=أداة+التحليل',
            'gradient'  => 'linear-gradient(45deg, #fed7aa, #fdba74)',
            'icon'      => 'fas fa-chart-bar',
            'iconColor' => '#ea580c',
            'rating'    => 4.9,
            'category'  => 'أدوات التحليل',
            'url'       => '#',
        ],
        [
            'name'      => 'منصة الإدارة الصفية',
            'desc'      => 'إدارة الفصول والأنشطة والحضور في مكان واحد.',
            'image'     => 'https://via.placeholder.com/400x200/fdba74/c2410c?text=الإدارة+الصفية',
            'gradient'  => 'linear-gradient(45deg, #fdba74, #fb923c)',
            'icon'      => 'fas fa-chalkboard-teacher',
            'iconColor' => '#c2410c',
            'rating'    => 4.7,
            'category'  => 'أدوات الإدارة الصفية',
            'url'       => '#',
        ],
        [
            'name'      => 'منصة الألعاب التعليمية',
            'desc'      => 'حوّل الدروس إلى ألعاب تفاعلية لتحفيز الطلاب.',
            'image'     => 'https://via.placeholder.com/400x200/fb923c/9a3412?text=أدوات+ترفيهية',
            'gradient'  => 'linear-gradient(45deg, #fb923c, #f97316)',
            'icon'      => 'fas fa-gamepad',
            'iconColor' => '#9a3412',
            'rating'    => 4.6,
            'category'  => 'الأدوات الترفيهية',
            'url'       => '#',
        ],
        [
            'name'      => 'منصة الوسائط المتعددة',
            'desc'      => 'صمّم وقدّم محتوى تعليمي متعدد الوسائط بشكل تفاعلي.',
            'image'     => 'https://via.placeholder.com/400x200/f97316/7c2d12?text=أدوات+الوسائط',
            'gradient'  => 'linear-gradient(45deg, #f97316, #ea580c)',
            'icon'      => 'fas fa-video',
            'iconColor' => '#7c2d12',
            'rating'    => 4.8,
            'category'  => 'أدوات الوسائط',
            'url'       => '#',
        ],
        [
            'name'      => 'مساعد الذكاء الاصطناعي',
            'desc'      => 'أنشئ محتوى تعليمي مخصص وأجب الأسئلة فورياً.',
            'image'     => 'https://via.placeholder.com/400x200/ea580c/000?text=أداة+تعليمية',
            'gradient'  => 'linear-gradient(45deg, #ea580c, #c2410c)',
            'icon'      => 'fas fa-brain',
            'iconColor' => '#000000',
            'rating'    => 4.5,
            'category'  => 'أدوات متقدمة',
            'url'       => '#',
        ],
    ];
@endphp

<section class="reveal">
  <div class="page-header">
    <h1 class="page-title">اكتشف الأدوات التعليمية</h1>
    <p class="page-description">منصة شاملة تجمع أفضل الأدوات والتقنيات التعليمية لتحسين تجربة التعلم والتعليم.</p>
  </div>

  <div class="tools-grid">
    @foreach($tools as $t)
      {{-- لو تريد البطاقة قابلة للنقر بالكامل استبدل div بـ <a href="{{ $t['url'] ?? '#' }}" class="tool-card"> --}}
        <a href="{{ $t['url'] ?? '#' }}" class="tool-card" style="display:block;" target="_blank" rel="noopener">

        <div class="tool-media">
          <img
            src="{{ $t['image'] ?? ('https://via.placeholder.com/400x200/ffedd5/f97316?text=' . urlencode($t['name'] ?? 'أداة')) }}"
            alt="{{ $t['name'] ?? 'أداة' }}"
            class="tool-image">

          <div class="tool-animation"
               style="background: {{ $t['gradient'] ?? 'linear-gradient(45deg, #ffedd5, #fed7aa)' }}; display:flex; align-items:center; justify-content:center;">
            <i class="{{ $t['icon'] ?? 'fas fa-cubes' }}"
               style="font-size:3rem; color: {{ $t['iconColor'] ?? '#f97316' }};"></i>
          </div>
        </div>

        <div class="tool-content">
          <h3 class="tool-title">{{ $t['name'] ?? 'اسم الأداة' }}</h3>
          <p class="tool-description">{{ $t['desc'] ?? 'وصف مختصر للأداة.' }}</p>

          <div class="tool-meta">
            <span class="tool-category">{{ $t['category'] ?? 'غير مصنّف' }}</span>

            @if(isset($t['rating']) && $t['rating'] !== '')
              <div class="tool-rating">
                <i class="fas fa-star"></i>
                {{ is_numeric($t['rating']) ? number_format($t['rating'],1) : $t['rating'] }}
              </div>
            @endif
          </div>
        </div>
      </a>
    @endforeach
  </div>
</section>

{{-- (اختياري) قسم مزايا سريع بنفس ستايلك --}}
<section class="features-section reveal">
  <h2 class="section-title">لماذا تختار منصة كيف؟</h2>
  <div class="features-grid">
    <div class="feature-item">
      <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
      <h3 class="feature-title">آمن وموثوق</h3>
      <p class="feature-description">بيئة آمنة تحافظ على خصوصية بياناتك وتوفر تجربة مستقرة.</p>
    </div>
    <div class="feature-item">
      <div class="feature-icon"><i class="fas fa-rocket"></i></div>
      <h3 class="feature-title">سهل الاستخدام</h3>
      <p class="feature-description">واجهة بديهية للبدء مباشرة دون تدريب مسبق.</p>
    </div>
    <div class="feature-item">
      <div class="feature-icon"><i class="fas fa-sync-alt"></i></div>
      <h3 class="feature-title">تحديثات مستمرة</h3>
      <p class="feature-description">نضيف أدوات وتحسينات جديدة باستمرار لتطوير تجربتك.</p>
    </div>
  </div>
</section>
@endsection
