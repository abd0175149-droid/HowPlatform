<style>
    /* ===== صفحة كل المهام (NotebookLM flat) ===== */
    .tasks-page {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding: 1.5rem 0.5rem;
        max-width: 960px;
        margin: 0 auto;
    }

    .tasks-page-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        background: var(--surface-0);
        border: 0.8px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 17.6px 19.2px;
    }

    .tasks-page-head h1 {
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--text-strong);
        margin: 0 0 4.8px;
        display: flex;
        align-items: center;
        gap: 9.6px;
    }

    .tasks-page-head h1 i {
        color: var(--primary-500);
    }

    .tp-sub {
        color: var(--text-muted);
        font-size: .98rem;
        margin: 0;
    }

    .tp-back {
        background: var(--surface-1);
        border: 0.8px solid var(--border-color);
        color: var(--text-strong);
        border-radius: 799.2px;
        padding: 7.2px 12.8px;
        font-weight: 600;
        font-size: .9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6.4px;
        transition: border-color .18s ease, background .18s ease;
        flex-shrink: 0;
    }

    .tp-back:hover {
        background: var(--surface-2);
        border-color: var(--primary-300);
    }

    /* تبويبات الفصول */
    .tasks-tabs {
        display: flex;
        gap: 6.4px;
        flex-wrap: wrap;
    }

    .tasks-tabs:empty {
        display: none;
    }

    .sem-tab {
        background: var(--surface-0);
        border: 0.8px solid var(--border-color);
        color: var(--text-muted);
        border-radius: 799.2px;
        padding: 8px 16px;
        font-weight: 700;
        font-size: .92rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6.4px;
        transition: background .18s ease, color .18s ease, border-color .18s ease;
    }

    .sem-tab:hover {
        border-color: var(--primary-300);
        color: var(--text-strong);
    }

    .sem-tab.active {
        background: var(--primary-500);
        color: #fff;
        border-color: transparent;
    }

    .sem-tab .sem-tab-count {
        background: rgba(255, 255, 255, .25);
        border-radius: 799.2px;
        padding: 0.8px 7.2px;
        font-size: .78rem;
        min-width: 17.6px;
        text-align: center;
    }

    .sem-tab:not(.active) .sem-tab-count {
        background: var(--surface-2);
        color: var(--text-muted);
    }

    /* شريط الفلترة/الترتيب */
    .tasks-filter {
        display: flex;
        align-items: center;
        gap: 6.4px;
        flex-wrap: wrap;
    }

    .tf-label {
        font-size: .85rem;
        font-weight: 700;
        color: var(--text-muted);
        display: inline-flex;
        align-items: center;
        gap: 4.8px;
        margin-inline-end: 3.2px;
    }

    .tf-btn {
        background: var(--surface-0);
        border: 0.8px solid var(--border-color);
        color: var(--text-muted);
        border-radius: 799.2px;
        padding: 6.4px 12px;
        font-weight: 700;
        font-size: .85rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5.6px;
        transition: background .16s ease, color .16s ease, border-color .16s ease;
        font-family: inherit;
    }

    .tf-btn i {
        font-size: .8rem;
    }

    .tf-btn:hover {
        border-color: var(--primary-300);
        color: var(--text-strong);
    }

    .tf-btn.active {
        background: var(--primary-50);
        color: var(--primary-700);
        border-color: transparent;
    }

    /* قسم الفصل */
    .sem-section {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .sem-head {
        display: flex;
        align-items: center;
        gap: 9.6px;
        padding-bottom: .7rem;
        border-bottom: 0.8px solid var(--border-color);
    }

    .sem-head h2 {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-strong);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sem-head h2 i {
        color: var(--primary-500);
    }

    .sem-count {
        background: var(--primary-50);
        color: var(--primary-700);
        border-radius: 799.2px;
        padding: 2.4px 9.6px;
        font-size: .8rem;
        font-weight: 700;
    }

    .tasks-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(232px, 1fr));
        gap: 1.1rem;
    }

    /* بطاقة مهمة */
    .t-card {
        background: var(--surface-0);
        border: 0.8px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1.2rem 1.3rem;
        display: flex;
        flex-direction: column;
        gap: .85rem;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        text-decoration: none;
        color: inherit;
    }

    .t-card:hover {
        transform: translateY(-1.6px);
        border-color: var(--primary-300);
        box-shadow: var(--shadow-sm);
    }

    .t-card-title {
        font-weight: 800;
        font-size: 1.08rem;
        color: var(--text-strong);
        line-height: 1.45;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .t-card-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6.4px;
    }

    .t-chip {
        display: inline-flex;
        align-items: center;
        gap: 4.8px;
        font-size: .8rem;
        font-weight: 600;
        padding: 3.2px 8.8px;
        border-radius: 799.2px;
        border: 0.8px solid var(--border-color);
        background: var(--surface-1);
        color: var(--text-muted);
    }

    .t-chip.course {
        background: var(--surface-1);
        color: var(--text-muted);
        border-color: var(--border-color);
    }

    .t-chip.course i,
    .t-chip i {
        font-size: .75rem;
    }

    .t-chip.course i,
    .t-chip.week i {
        color: var(--primary-500);
    }

    .t-card-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: auto;
        padding-top: .7rem;
        border-top: 0.8px dashed var(--border-color);
    }

    .t-due {
        display: inline-flex;
        align-items: center;
        gap: 4.8px;
        font-size: .85rem;
        color: var(--text-muted);
    }

    .t-due.overdue {
        color: var(--error-600);
    }

    .t-due i {
        font-size: .8rem;
    }

    .t-open {
        display: inline-flex;
        align-items: center;
        gap: 4.8px;
        font-size: .85rem;
        font-weight: 700;
        color: var(--primary-600);
    }

    .t-card:hover .t-open {
        color: var(--primary-700);
    }

    /* حالات */
    .tasks-state {
        text-align: center;
        padding: 38.4px 16px;
        color: var(--text-muted);
        background: var(--surface-0);
        border: 0.8px solid var(--border-color);
        border-radius: var(--border-radius);
    }

    .tasks-state i {
        font-size: 2.6rem;
        color: var(--primary-300);
        margin-bottom: 11.2px;
    }

    .tasks-state h3 {
        color: var(--text-strong);
        font-size: 1.2rem;
        margin: 0 0 4.8px;
    }

    .tasks-skel {
        height: 112px;
        border-radius: var(--border-radius);
        border: 0.8px solid var(--border-color);
        background: linear-gradient(90deg, var(--surface-1) 25%, var(--surface-2) 37%, var(--surface-1) 63%);
        background-size: 400% 100%;
        animation: taskskel 1.3s ease infinite;
    }

    @keyframes taskskel {
        0% { background-position: 100% 0; }
        100% { background-position: 0 0; }
    }

    @media (max-width: 768px) {
        .tasks-grid { grid-template-columns: 1fr; }
        .tasks-page-head h1 { font-size: 1.35rem; }
    }
</style>

<div class="tasks-page" id="tasks-page">
    <header class="tasks-page-head">
        <div>
            <h1><i class="fas fa-list-check"></i> كل المهام</h1>
            <p class="tp-sub">جميع مهام مساقاتك مرتّبة حسب الفصل الدراسي</p>
        </div>
        <button type="button" class="tp-back" id="tasksBackBtn">
            <i class="fas fa-arrow-right"></i> لوحة التحكم
        </button>
    </header>

    <div class="tasks-tabs" id="tasks-tabs" role="tablist"></div>

    <div class="tasks-filter" id="tasks-filter" style="display:none">
        <span class="tf-label"><i class="fas fa-sliders"></i> ترتيب:</span>
        <button type="button" class="tf-btn active" data-mode="all"><i class="fas fa-layer-group"></i> الكل</button>
        <button type="button" class="tf-btn" data-mode="alpha"><i class="fas fa-arrow-down-a-z"></i> أبجدي (أ–ي)</button>
        <button type="button" class="tf-btn" data-mode="date"><i class="fas fa-calendar-day"></i> حسب التاريخ</button>
        <button type="button" class="tf-btn" data-mode="overdue"><i class="fas fa-clock-rotate-left"></i> المتأخرة</button>
    </div>

    <div id="tasks-list">
        <div class="tasks-grid">
            <div class="tasks-skel"></div>
            <div class="tasks-skel"></div>
            <div class="tasks-skel"></div>
        </div>
    </div>
</div>
