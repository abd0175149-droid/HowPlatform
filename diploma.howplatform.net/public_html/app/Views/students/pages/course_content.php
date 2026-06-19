<?php

/**
 * Course Content View
 * Path: app/Views/student/course_content.php
 */
?>
<!-- Course Content Container -->
<div id="course-content-container" class="course-container">
    <!-- Loading State -->
    <div id="loading-state" class="loading-container">
        <div class="loading-spinner"></div>
        <h3>جاري تحميل محتوى المساق...</h3>
        <p>الرجاء الانتظار لحظات</p>
    </div>

    <!-- Error State -->
    <div id="error-state" class="error-state" style="display: none;">
        <i class="fas fa-exclamation-triangle error-icon"></i>
        <h2 class="error-title">حدث خطأ في تحميل المحتوى</h2>
        <p class="error-message">تعذر تحميل محتوى المساق. الرجاء المحاولة مرة أخرى.</p>
        <button class="retry-btn" onclick="reloadCurrentSection()">
            <i class="fas fa-redo"></i> إعادة المحاولة
        </button>
    </div>

    <!-- Content Container -->
    <div id="course-content" style="display: none;">
        <!-- Course Header -->
        <div class="course-header fade-in">
            <div class="course-header-content">
                <div class="course-code" id="course-code">CS101</div>
                <h1 class="course-title" id="course-title">اسم المساق</h1>
                <p class="course-description" id="course-description">
                    وصف المساق سيظهر هنا...
                </p>
                <div class="course-stats" id="course-stats">
                    <div class="stat-item">
                        <i class="fas fa-calendar-week"></i>
                        <span id="total-weeks">0 أسبوع</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-tasks"></i>
                        <span id="total-tasks">0 مهمة</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-file"></i>
                        <span id="total-files">0 ملف</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Section -->
        <div class="progress-section slide-up">
            <div class="progress-header">
                <h3 class="progress-title">تقدمك في المساق</h3>
                <span class="progress-percentage" id="progress-percentage">0%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
            </div>
            <div class="progress-details" id="progress-details">
                <div>الأسابيع المتاحة: <span id="available-weeks">0</span></div>
                <div>الأسابيع القادمة: <span id="upcoming-weeks">0</span></div>
                <div>الأسابيع المغلقة: <span id="locked-weeks">0</span></div>
            </div>
        </div>

        <!-- Weeks Timeline -->
        <div class="weeks-timeline">
            <div class="timeline-line"></div>
            <div id="weeks-container">
                <!-- الأسابيع ستُضاف هنا ديناميكياً -->
            </div>
        </div>
    </div>
</div>

<!-- Templates -->
<template id="week-template">
    <div class="week-container slide-up" data-week-id="">
        <div class="week-marker"></div>
        <div class="week-card">
            <div class="week-header">
                <div class="week-info">
                    <h3 class="week-title"></h3>
                    <div class="week-date">
                        <i class="far fa-calendar-alt"></i>
                        <span class="week-date-text"></span>
                    </div>
                </div>
                <div class="week-status"></div>
            </div>
            <div class="tasks-grid">
                <!-- المهام ستُضاف هنا -->
            </div>
            <div class="empty-week" style="display: none;">
                <i class="fas fa-hourglass-half"></i>
                <p>لا توجد مهام في هذا الأسبوع حالياً</p>
            </div>
        </div>
    </div>
</template>

<template id="task-template">
    <div class="task-card" data-task-id="">
        <div class="task-header">
            <div class="task-title"></div>
            <div class="task-type"></div>
        </div>
        <div class="task-date">
            <i class="far fa-calendar-alt"></i>
            <span class="task-date-text"></span>
        </div>
        <div class="task-description"></div>
        <div class="files-section" style="display: none;">
            <div class="files-header">
                <i class="fas fa-paperclip"></i>
                <span>الملفات المرفقة</span>
            </div>
            <div class="files-list">
                <!-- الملفات ستُضاف هنا -->
            </div>
        </div>
        <div class="task-actions">
            <button class="action-btn view-btn">
                <i class="fas fa-eye"></i> التفاصيل
            </button>
            <button class="action-btn submit-btn">
                <i class="fas fa-play"></i> بدء المهمة
            </button>
        </div>
    </div>
</template>

<template id="file-template">
    <div class="file-item" data-file-id="">
        <i class="file-icon"></i>
        <div class="file-info">
            <div class="file-name"></div>
            <div class="file-details"></div>
        </div>
        <div class="file-premium" style="display: none;">بريميوم</div>
    </div>
</template>
<style>
    /* =========================================================
   students.brand.css — Brand-aligned UI (Course + Task Details)
   حمّله بعد أي CSS أساسي لديك
   ========================================================= */

    /* ===== Brand Tokens (انسخها فقط إن لم تكن موجودة لديك) ===== */
    :root {
        --primary-50: #e8f5fb;
        --primary-100: #d2ebf7;
        --primary-200: #bfe2f2;
        --primary-300: #8ecae6;
        --primary-400: #5fb6e2;
        --primary-500: #219ebc;
        --primary-600: #1b81a0;
        --primary-700: #156683;
        --primary-800: #104b65;
        --primary-900: #0a3247;

        --accent-50: #fff3d6;
        --accent-100: #ffe6ad;
        --accent-200: #ffd985;
        --accent-300: #ffcb5c;
        --accent-400: #ffbe34;
        --accent-500: #fd9e02;
        --accent-600: #d38202;
        --accent-700: #a76602;
        --accent-800: #7b4a01;
        --accent-900: #503001;

        --neutral-0: #fff;
        --neutral-50: #f9f9f9;
        --neutral-100: #f2f2f2;
        --neutral-200: #e6e6e6;
        --neutral-300: #d9d9d9;
        --neutral-400: #bfbfbf;
        --neutral-500: #a6a6a6;
        --neutral-600: #8c8c8c;
        --neutral-700: #737373;
        --neutral-800: #4d4d4d;
        --neutral-900: #2b2b2b;
        --neutral-950: #171717;

        --background: var(--neutral-50);
        --surface-0: var(--neutral-0);
        --surface-1: var(--neutral-100);
        --surface-2: var(--neutral-200);
        --surface-3: var(--neutral-0);

        --text-strong: #023047;
        --text: #023047;
        --text-light: #126782;
        --text-muted: #555;
        --text-inverse: #fff;

        --success-500: #2ecc71;
        --success-600: #27ae60;
        --success-100: #e7f8ef;
        --warning-500: #f1c40f;
        --warning-600: #c39c09;
        --warning-100: #fff8da;
        --error-500: #e74c3c;
        --error-600: #c0392b;
        --error-100: #fde7e5;
        --info-500: #4aa3ff;
        --info-600: #2e7fd6;
        --info-100: #e8f1ff;

        --border-color: var(--neutral-200);
        --border-strong: var(--neutral-300);
        --focus-ring: 0 0 0 3px rgba(33, 158, 188, .25);
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, .08);
        --shadow-md: 0 6px 18px rgba(0, 0, 0, .10);
        --shadow-lg: 0 16px 40px rgba(0, 0, 0, .16);

        --border-radius: 16px;
        --transition: all .25s ease;
    }

    /* ===== Dark Theme — Monochrome (كل الألوان درجات أسود) ===== */
     [data-theme="dark"] {
          /* الخلفيات والسطوح */
  --background: #0e141a;      /* داكن مائل للأزرق للمحافظة على الهوية */
  --surface-0: #0f1720;       /* قاعدة عناصر */
  --surface-1: #121c25;       /* بطاقات/قوائم */
  --surface-2: #18232d;       /* سطوح أعمق */
  --surface-3: #0f1720;

  /* النصوص */
  --text-strong: #e6f2f7;     /* عناوين */
  --text: #cfe0e7;            /* نص أساسي */
  --text-light: #a6c4d2;      /* نص ثانوي */
  --text-muted: #98a6b3;      /* شروح وتعليقات */
  --text-inverse: #0c1216;    /* نص فوق السطوح الفاتحة (Badges/Toasts الفاتحة) */

  /* الأزرق (Primary) – إشراقة أعلى على الدارك */
  --primary-50:  #0a2631;
  --primary-100: #0d3242;
  --primary-200: #11465a;
  --primary-300: #176a7f;
  --primary-400: #1e8eab;     /* hover على الدارك */
  --primary-500: #2bb3d3;     /* اللون الرئيسي على الدارك */
  --primary-600: #21a1be;     /* pressed/active */
  --primary-700: #1a8aa6;
  --primary-800: #166f86;
  --primary-900: #10556a;

  /* البرتقالي (Accent) – طيف كهرماني لطيف على الدارك */
  --accent-50:  #2c1d01;
  --accent-100: #3a2702;
  --accent-200: #543603;
  --accent-300: #7a4d04;
  --accent-400: #ffb54d;      /* hover */
  --accent-500: #fd9e02;      /* يبقى لون الهوية */
  --accent-600: #d88302;      /* pressed */
  --accent-700: #b16e02;
  --accent-800: #8c5801;
  --accent-900: #6b4401;

  /* حالات النظام (أعدنا ضبط 100 كخلفية باهتة مناسبة للداكن) */
  --success-100: #0f2d1f;
  --success-500: #2ecc71;
  --success-600: #27ae60;

  --warning-100: #2a2204;
  --warning-500: #f1c40f;
  --warning-600: #c39c09;

  --error-100: #2e1512;
  --error-500: #e74c3c;
  --error-600: #c0392b;

  --info-100: #0c233a;
  --info-500: #4aa3ff;
  --info-600: #2e7fd6;

  /* الحدود والظلال والتركيز */
  --border-color: rgba(255, 255, 255, .08);
  --border-strong: rgba(255, 255, 255, .16);
  --focus-ring: 0 0 0 3px rgba(43, 179, 211, .35); /* من primary-500 */
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, .6);
  --shadow-md: 0 8px 24px rgba(0, 0, 0, .5);
  --shadow-lg: 0 18px 48px rgba(0, 0, 0, .45);

  /* نفس نصف القطر والانتقالات */
  --border-radius: 16px;
  --transition: all .25s ease;
}

/* تفعيل تلقائي حسب تفضيل النظام إذا لم يُحدّد data-theme يدوياً */
@media (prefers-color-scheme: dark) {
  :root:not([data-theme]) {
    /* نفس قيم الدارك بالأعلى (مختصر: استدعاء عبر selector ثاني إن رغبت بالنسخ) */
  }
}

/* أمثلة استخدام (اختياري): زر وبطاقة مبنيين على التوكنز */
.button-primary {
  background: var(--primary-500);
  color: var(--text-inverse);
  border: 1px solid transparent;
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-sm);
  transition: var(--transition);
}
.button-primary:hover { background: var(--primary-400); }
.button-primary:active { background: var(--primary-600); }
.button-primary:focus { outline: var(--focus-ring); }

.card {
  background: var(--surface-1);
  color: var(--text);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-sm);
}
.card .card-title { color: var(--text-strong); }
.card .muted { color: var(--text-muted); }
           }

    /* =========================================================
   A) Course Content Page
   ========================================================= */

    .course-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 30px 20px;
        background: var(--background);
        min-height: 100vh;
    }

    /* Loading */
    .loading-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 400px;
        flex-direction: column;
        gap: 20px;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 4px solid var(--primary-200);
        border-top: 4px solid var(--primary-500);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Header */
    .course-header {
        background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
        color: var(--text-inverse);
        padding: 40px 30px;
        border-radius: var(--border-radius);
        margin-bottom: 30px;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
    }

    .course-header::before {
        content: "";
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
        animation: float 20s linear infinite;
        pointer-events: none;
    }

    @keyframes float {
        from {
            transform: translate(-100px, -100px);
        }

        to {
            transform: translate(100px, 100px);
        }
    }

    .course-code {
        background: rgba(255, 255, 255, .2);
        padding: 8px 16px;
        border-radius: 20px;
        display: inline-block;
        font-size: .9rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: VAR(--primary-100);
    }

    .course-title {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 5px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, .3);
    }

    .course-description {
        font-size: 1.1rem;
        margin-bottom: 5px;
        opacity: .95;
        line-height: 1.7;
    }

    .course-stats {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
    }

    .course-stats span {
        color: var(--primary-100);
        background: unset;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, .15);
        padding: 5px 8px;
        border-radius: 12px;
        backdrop-filter: blur(10px);
    }

    .stat-item i {
        color: var(--accent-300);
    }

    /* Progress */
    .progress-section {
        background: var(--surface-0);
        border-radius: var(--border-radius);
        padding: 25px;
        margin-bottom: 10px;
        box-shadow: var(--shadow-sm);
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
    }

    .progress-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-strong);
    }

    .progress-percentage {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--primary-600);
    }

    .progress-bar {
        width: 100%;
        height: 12px;
        background: var(--neutral-200);
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 10px;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-400), var(--primary-600));
        border-radius: 6px;
        transition: width .5s ease;
    }

    .progress-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        font-size: .9rem;
        color: var(--text-muted);
    }

    /* Weeks + Tasks */
    .weeks-timeline {
        position: relative;
        margin: 40px 0;
    }

    .timeline-line {
        position: absolute;
        right: 30px;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--primary-300), var(--primary-500));
        border-radius: 2px;
        z-index: 1;
    }

    .week-container {
        position: relative;
        margin-bottom: 40px;
        z-index: 2;
    }

    .week-marker {
        position: absolute;
        right: 14px;
        top: 20px;
        width: 32px;
        height: 32px;
        background: var(--primary-500);
        border: 4px solid var(--surface-0);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: .9rem;
        box-shadow: var(--shadow-md);
        z-index: 3;
    }

    .week-marker.locked {
        background: var(--neutral-400);
    }

    .week-card {
        background: var(--surface-0);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 25px;
        margin-right: 80px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }

    .week-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-300);
    }

    .week-card.locked {
        opacity: .6;
        background: var(--neutral-100);
    }

    .week-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }

    .week-info h3 {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-strong);
        margin-bottom: 8px;
    }

    .week-date {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-muted);
        font-size: .95rem;
    }

    .week-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: .85rem;
        font-weight: 600;
        min-width: 80px;
        text-align: center;
    }

    .status-available {
        background: var(--success-100);
        color: var(--success-600);
        border: 1px solid var(--success-500);
    }

    .status-upcoming {
        background: var(--warning-100);
        color: var(--warning-600);
        border: 1px solid var(--warning-500);
    }

    .status-locked {
        background: var(--neutral-100);
        color: var(--neutral-600);
        border: 1px solid var(--neutral-400);
    }

    .tasks-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .task-card {
        background: var(--surface-1);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .task-card::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 4px;
        height: 100%;
        background: var(--primary-500);
        transition: var(--transition);
    }

    .task-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-300);
    }

    .task-card:hover::before {
        width: 8px;
    }

    .task-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .task-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-strong);
        line-height: 1.4;
        flex: 1;
        margin-left: 10px;
    }

    .task-type {
        padding: 4px 8px;
        border-radius: 8px;
        font-size: .75rem;
        font-weight: 700;
    }

    .type-lecture {
        background: var(--info-100);
        color: var(--info-600);
    }

    .type-assignment {
        background: var(--warning-100);
        color: var(--warning-600);
    }

    .type-project {
        background: var(--accent-100);
        color: var(--accent-600);
    }

    .type-quiz {
        background: var(--error-100);
        color: var(--error-600);
    }

    .task-date {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--text-muted);
        font-size: .9rem;
        margin-bottom: 15px;
    }

    .task-description {
        color: var(--text-muted);
        font-size: .9rem;
        line-height: 1.5;
        margin-bottom: 20px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .task-actions {
        display: flex;
        gap: 10px;
    }

    .action-btn {
        flex: 1;
        padding: 10px 15px;
        border: none;
        border-radius: 8px;
        font-size: .9rem;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        text-decoration: none;
    }

    .view-btn {
        background: var(--accent-400);
        color: var(--text-strong);
        border: 1px solid var(--border-color);
    }

    .view-btn:hover {
        background: var(--accent-500);
        color: var(--accent-50);
        border-color: var(--accent-500);
    }

    .submit-btn {
        background: var(--primary-500);
        color: #fff;
    }

    .submit-btn:hover {
        background: var(--primary-600);
        transform: translateY(-1px);
    }

    /* Files mini-list inside task */
    .files-section {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--border-color);
    }

    .file-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        background: var(--surface-0);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 8px;
        transition: var(--transition);
    }

    .file-item:hover {
        border-color: var(--primary-300);
        background: var(--primary-50);
    }

    .file-icon {
        font-size: 1.2rem;
        color: var(--primary-500);
    }

    .file-name {
        font-weight: 700;
        color: var(--text-strong);
        font-size: .9rem;
    }

    .file-details {
        font-size: .8rem;
        color: var(--text-muted);
    }

    .file-premium {
        background: var(--accent-100);
        color: var(--accent-700);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: .7rem;
        font-weight: 700;
    }

    .file-locked {
        color: var(--neutral-500);
        cursor: not-allowed;
    }

    /* =========================================================
   B) Task Details Page
   ========================================================= */
    .task-details-page {
        --radius: 16px;
        --ring: 0 0 0 3px rgba(33, 158, 188, .25);
        display: flex;
        flex-direction: column;
        gap: 20px;
        padding: 24px 16px;
        background: var(--background);
    }

    .td-header {
        background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
        color: #fff;
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        padding: 22px 20px;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .td-header::before {
        content: "";
        position: absolute;
        inset: -60% auto auto -60%;
        width: 200%;
        height: 200%;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160"><g fill="rgba(255,255,255,.09)"><circle cx="10" cy="10" r="2"/><circle cx="80" cy="60" r="2"/><circle cx="140" cy="90" r="2"/></g></svg>') repeat;
        animation: tdFloat 22s linear infinite;
        pointer-events: none;
    }

    @keyframes tdFloat {
        to {
            transform: translate(120px, 120px);
        }
    }

    .td-title-wrap {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
    }

    #td-back-week {
        background: rgba(255, 255, 255, .15);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .35);
        padding: 10px 14px;
        border-radius: 12px;
        cursor: pointer;
        backdrop-filter: blur(8px);
        transition: transform .2s ease, background .2s ease;
    }

    #td-back-week:hover {
        transform: translateY(-1px);
        background: rgba(255, 255, 255, .22);
    }

    .td-title {
        margin: 0;
        font-size: 1.6rem;
        font-weight: 800;
        text-shadow: 0 2px 4px rgba(0, 0, 0, .25);
    }

    .td-meta {
        opacity: .95;
        font-size: .95rem;
    }

    .td-nav {
        display: flex;
        gap: 8px;
        position: relative;
        z-index: 1;
    }

    .td-nav .btn {
        background: rgba(255, 255, 255, .14);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .35);
        padding: 10px 14px;
        border-radius: 12px;
        cursor: pointer;
        transition: transform .2s ease, background .2s ease;
    }

    .td-nav .btn:hover {
        transform: translateY(-1px);
        background: rgba(255, 255, 255, .24);
    }

    .td-nav .btn[disabled] {
        opacity: .55;
        cursor: not-allowed;
    }

    .td-grid {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 20px;
    }

    .td-sidebar {
        position: sticky;
        top: 16px;
        background: var(--surface-0);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 16px;
        box-shadow: var(--shadow-sm);
    }

    .td-week-title {
        margin: 0 0 12px;
        font-weight: 800;
        color: var(--text-strong);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .td-task-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .td-task-list li button {
        width: 100%;
        text-align: right;
        padding: 12px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--surface-1);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--text);
        font-weight: 700;
    }

    .td-task-list li button:hover {
        background: var(--primary-50);
        border-color: var(--primary-300);
    }

    .td-task-list li button.active {
        background: var(--primary-100);
        border-color: var(--primary-500);
        color: var(--primary-900);
        box-shadow: inset 0 0 0 1px var(--primary-300);
    }

    .td-main {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .td-card {
        background: var(--surface-0);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 18px;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .td-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-300);
    }

    .td-dates {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .td-dates .meta {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        border-radius: 12px;
        background: var(--surface-1);
        border: 1px solid var(--border-color);
        font-weight: 700;
        color: var(--text);
    }

    .td-dates .meta i {
        color: var(--primary-500);
    }

    .td-desc {
        line-height: 1.8;
        color: var(--text);
    }

    .td-desc a {
        color: var(--primary-600);
        text-decoration: none;
        border-bottom: 1px dashed currentColor;
    }

    .td-desc a:hover {
        color: var(--primary-700);
    }

    .td-media[hidden] {
        display: none !important;
    }

    .td-media-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 14px;
        margin-top: 6px;
    }

    .td-media-grid .media {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        background: var(--surface-0);
        box-shadow: var(--shadow-sm);
        transition: transform .2s, box-shadow .2s, border-color .2s;
        cursor: zoom-in;
    }

    .td-media-grid .media:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-300);
    }

    .td-media-grid img,
    .td-media-grid video,
    .td-media-grid audio {
        width: 100%;
        display: block;
        aspect-ratio: 4/3;
        object-fit: cover;
    }

    .td-files[hidden] {
        display: none !important;
    }

    .td-files-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 6px;
    }

    .td-file {
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 12px;
        background: var(--surface-1);
        transition: var(--transition);
    }

    .td-file:hover {
        background: var(--primary-50);
        border-color: var(--primary-300);
    }

    .td-file .icon {
        font-size: 1.2rem;
        color: var(--primary-500);
    }

    .td-file .grow {
        flex: 1;
    }

    .td-file .name {
        font-weight: 800;
        color: var(--text-strong);
    }

    .td-file .meta {
        font-size: .85rem;
        color: var(--text-muted);
    }

    .td-file .badge {
        background: var(--accent-100);
        color: var(--accent-700);
        padding: 2px 8px;
        border-radius: 6px;
        font-size: .75rem;
        font-weight: 800;
    }

    .td-file .actions {
        display: flex;
        gap: 8px;
    }

    .td-file .btn-dl,
    .td-file .btn-view {
        padding: 8px 10px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 800;
        font-size: .9rem;
        border: 1px solid var(--border-color);
        background: var(--surface-0);
        color: var(--text);
        transition: all .2s;
    }

    .td-file .btn-dl:hover,
    .td-file .btn-view:hover {
        background: var(--primary-100);
        border-color: var(--primary-300);
        color: var(--primary-900);
    }

    .td-file.locked {
        opacity: .7;
        cursor: not-allowed;
    }

    .td-file.locked .btn-dl,
    .td-file.locked .btn-view {
        pointer-events: none;
    }

    .td-upgrade[hidden] {
        display: none !important;
    }

    .td-upgrade .td-upgrade-box {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px;
        border-radius: 12px;
        background: linear-gradient(90deg, var(--accent-100), var(--accent-300));
        border: 1px solid var(--accent-400);
    }

    #td-go-upgrade {
        background: linear-gradient(90deg, var(--accent-500), var(--accent-600));
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 10px 14px;
        font-weight: 800;
        cursor: pointer;
        transition: transform .2s, opacity .2s;
    }

    #td-go-upgrade:hover {
        transform: translateY(-1px);
        opacity: .95;
    }

    .td-modal {
        position: fixed;
        inset: 0;
        display: none;
        z-index: 9999;
    }

    .td-modal[aria-hidden="false"] {
        display: block;
    }

    .td-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, .5);
        backdrop-filter: blur(2px);
    }

    .td-modal-dialog {
        position: absolute;
        inset: 5%;
        background: var(--surface-0);
        border-radius: 16px;
        box-shadow: var(--shadow-lg);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--border-color);
    }

    .td-modal-close {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 6px 10px;
        cursor: pointer;
        transition: background .2s;
    }

    .td-modal-close:hover {
        background: var(--primary-50);
    }

    #pdfFrame {
        flex: 1;
        width: 100%;
    }

    /* Utilities */
    .btn {
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: #fff;
        cursor: pointer;
        transition: background .2s, transform .2s;
    }

    .btn:hover {
        background: var(--primary-50);
    }

    .btn-primary {
        background: var(--primary-500);
        border-color: var(--primary-500);
        color: #fff;
    }

    .btn-primary:hover {
        background: var(--primary-600);
    }

    /* Responsive */
    @media (max-width:1024px) {
        .td-grid {
            grid-template-columns: 1fr;
        }

        .td-sidebar {
            position: relative;
            top: auto;
        }
    }

    @media (max-width:768px) {
        .course-container {
            padding: 20px 15px;
        }

        .course-title {
            font-size: 1.8rem;
        }

        .timeline-line {
            right: 20px;
        }

        .week-marker {
            right: 4px;
            width: 28px;
            height: 28px;
            font-size: .8rem;
        }

        .week-card {
            margin-right: 60px;
            padding: 20px;
        }

        .tasks-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .task-actions {
            flex-direction: column;
        }

        .progress-details {
            grid-template-columns: 1fr;
        }

        .td-header {
            flex-direction: column;
            gap: 12px;
        }

        .td-title {
            font-size: 1.3rem;
        }

        .td-nav {
            width: 100%;
            justify-content: space-between;
        }
    }

    @media (max-width:480px) {
        .course-header {
            padding: 25px 20px;
        }

        .course-title {
            font-size: 1.5rem;
        }

        .course-stats {
            flex-direction: column;
            gap: 10px;
        }

        .week-header {
            flex-direction: column;
            gap: 10px;
        }

        .week-status {
            align-self: flex-start;
        }

        .task-title {
            font-size: 1rem;
        }

        .task-date {
            font-size: .85rem;
        }
    }

    /* Animations helpers */
    .fade-in {
        animation: fadeIn .5s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .slide-up {
        animation: slideUp .6s ease-out;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    button.img-zoom-nav.img-zoom-prev {
        color: rgb(253, 158, 2);
    }

    /* ====== Dark Mode Bridge for Task Details ====== */
    [data-theme="dark"] .td-card {
        background: var(--surface-1, #121c25);
        border-color: rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }

    [data-theme="dark"] .td-card:hover {
        border-color: var(--primary-500, #2bb3d3);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6);
        background: var(--surface-2, #18232d);
    }

    [data-theme="dark"] .td-task-list li button {
        background: var(--surface-1);
        border-color: var(--border-color);
        color: var(--text);
    }

    [data-theme="dark"] .td-task-list li button:hover {
        background: rgba(33, 158, 188, 0.15);
        border-color: var(--primary-400);
    }

    [data-theme="dark"] .td-task-list li button.active {
        background: rgba(33, 158, 188, 0.25);
        border-color: var(--primary-500);
        color: var(--primary-500);
    }
</style>
