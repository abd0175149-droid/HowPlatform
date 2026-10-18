<style>
    /* Student Dashboard (uses new tokens: surface/text/border/shadows) */
    .student-dashboard {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .view-all {
        text-decoration: none;
        color: var(--link);
        font-weight: 500;
        transition: var(--transition);
    }

    .view-all:hover {
        font-weight: 700;
        transform: translateX(4px);
        color: var(--link-hover);
    }

    .dashboard-section {
        padding: 1rem;
        padding-bottom: 0rem;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.2rem;
        padding-bottom: 0.8rem;
        border-bottom: 0.8px solid var(--border-color);
    }

    .section-header h3 {
        font-size: 1.4rem;
        color: var(--text-strong);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-header i {
        color: var(--btn-primary-bg);
    }

    /* ===== Tasks Grid ===== */
    .tasks-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1.5rem;
        padding: 1rem 0;
    }

    .task-card {
        background: var(--surface-0);
        border-radius: var(--border-radius);
        padding: 1.4rem 1.5rem;
        box-shadow: none;
        border: 0.8px solid var(--border-color);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 1rem;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .task-card:hover {
        transform: translateY(-1.6px);
        border-color: var(--primary-300);
        box-shadow: var(--shadow-sm);
    }

    .task-header {
        display: flex;
        flex-direction: column;
        gap: .3rem;
    }

    .task-title {
        font-weight: 800;
        font-size: 1.2rem;
        color: var(--text-strong);
        line-height: 1.4;
    }

    .task-date {
        font-size: 0.9rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 4.8px;
        justify-content: flex-end;
    }

    /* Status chips (soft backgrounds) */
    .task-lable {
        align-self: flex-start;
        padding: 0.35rem 0.9rem;
        border-radius: 24px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4.8px;
        border: 0.8px solid transparent;
    }

    .course-name {
        background: var(--warning-soft);
        border-color: var(--warning-border);
    }

    .task-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .3rem;
    }

    .action-btn {
        flex: 1 1 45%;
        padding: .55rem .6rem;
        font-weight: 600;
        font-size: .7rem;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6.4px;
        text-align: center;
    }

    .view-btn {
        background: var(--info-soft);
        color: var(--link);
        border: 0.8px solid var(--info-border);
    }

    .view-btn:hover {
        background: rgba(79, 175, 222, .12);
    }

    .submit-btn {
        background: var(--accent-500);
        color: var(--text-inverse);
    }

    .submit-btn:hover {
        background: var(--accent-600);
    }

    /* ✅ Mobile */
    @media (max-width: 768px) {
        .tasks-grid {
            grid-template-columns: 1fr;
        }

        .task-card {
            padding: 1.2rem;
        }

        .task-title {
            font-size: 1.05rem;
        }

        .task-actions {
            flex-direction: column;
        }

        .action-btn {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .task-title {
            font-size: 1rem;
        }

        .task-date {
            font-size: .85rem;
        }
    }

    /* ===== Quick Actions ===== */
    .content-container .section-header .section-title {
        color: var(--text-strong);
    }

    .quick-actions-container {
        position: relative;
        overflow: hidden;
        padding: 8px 0 20px;
    }

    .quick-actions-scroll {
        display: flex;
        gap: 20px;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--primary-300) transparent;
        padding: 20px 12px 16px;
        scroll-behavior: smooth;
        direction: rtl;
        background: var(--surface-1);
        border: 0.8px solid var(--border-color);
        border-radius: var(--border-radius);
    }

    .quick-actions-scroll::-webkit-scrollbar {
        height: 6.4px;
    }

    .quick-actions-scroll::-webkit-scrollbar-track {
        background: var(--surface-1);
        border-radius: 8px;
    }

    .quick-actions-scroll::-webkit-scrollbar-thumb {
        background: var(--primary-300);
        border-radius: 8px;
    }

    .scroll-indicator {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 48px;
        pointer-events: none;
        opacity: 0;
        transition: opacity .3s;
        z-index: 5;
    }

    .scroll-indicator.left {
        left: 0;
        background: linear-gradient(to right, var(--surface-1), transparent);
    }

    .scroll-indicator.right {
        right: 0;
        background: linear-gradient(to left, var(--surface-1), transparent);
    }

    .quick-actions-scroll-container:hover .scroll-indicator {
        opacity: 1;
    }

    .action-card ,.action-card-active {
        background: var(--surface-0);
        border-radius: var(--border-radius);
        padding: 17.6px 16px;
        text-align: center;
        box-shadow: none;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        border: 0.8px solid var(--border-color);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 184px;
        max-width: 184px;
        flex-shrink: 0;
    }

    /* روابط الأدوات الديناميكية = عناصر flex في الشريط: لا تنكمش */
    .quick-actions-scroll > a {
        flex-shrink: 0;
        display: block;
    }

    .action-card:hover ,.action-card-active:hover {
        transform: translateY(-2.4px);
        border-color: var(--primary-300);
        box-shadow: var(--shadow-sm);
    }

    .action-icon {
        width: 44.8px;
        height: 44.8px;
        border-radius: 11.2px;
        background: var(--primary-50);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: var(--primary-600);
        margin-bottom: 12.8px;
        border: none;
    }

    .action-title {
        font-weight: 700;
        color: var(--text-strong);
        font-size: 1.05rem;
        margin-bottom: 6.4px;
    }

    .action-desc {
        color: var(--text-muted);
        font-size: .95rem;
        line-height: 1.5;
        /* أقصى 3 أسطر مع «…»، واحجز ارتفاع 3 أسطر ثابت لتتساوى كل الكروت */
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        overflow: hidden;
        min-height: calc(1.5em * 3);
    }



    /* Secondary info header row */
    .secondary-info-grid-header {
        display: flex;
        gap: 12px;
    }

    .info-item-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6.4px 0;
        color: var(--text-inverse);
    }

    .info-item-header i {
        color: #fff;
        font-size: 1.1rem;
        width: 19.2px;
        text-align: center;
    }

    .info-item-header .value {
        font-weight: 600;
        color: #eef;
    }

    /* Secondary info grid */
    .secondary-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6.4px 9.6px;
        border-radius: 6.4px;
        background: var(--surface-0);
        box-shadow: var(--shadow-sm);
        border: 0.8px solid var(--border-color);
    }

    .info-item i {
        color: var(--btn-primary-bg);
        font-size: 1.1rem;
        width: 19.2px;
        text-align: center;
    }

    .info-item .label {
        color: var(--text-muted);
        font-size: .9rem;
        margin-left: 4px;
    }

    .info-item .value {
        font-weight: 600;
        color: var(--text-strong);
    }

    /* Description */
    .description-section {
        background: linear-gradient(to left, var(--surface-1), var(--info-soft));
        border-radius: 9.6px;
        padding: 8px;
        margin-bottom: 24px;
        text-align: right;
        box-shadow: var(--shadow-sm);
        position: relative;
        overflow: hidden;
        border: 0.8px solid var(--primary-500);
    }

    .description-section::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(to right, var(--accent-500), var(--accent-600));
    }

    .description-section h3 {
        color: var(--accent-600);
        margin-bottom: 16px;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .description-section p {
        font-size: 1.05rem;
        line-height: 1.8;
        color: var(--text-strong);
        margin-top: 12px;
        padding: 12px;
        background: var(--surface-0);
        border-radius: 8px;
        border: 0.8px solid var(--border-color);
    }

    /* Files */
    .files-section {
        margin-bottom: 24px;
    }

    .section-title {
        font-size: 1.3rem;
        color: var(--text-strong);
        margin-bottom: 16px;
        padding-right: 12px;
        border-right: 4px solid var(--btn-primary-bg);
        display: inline-flex;
        align-items: center;
        gap: 9.6px;
    }

    .files-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
    }

    .file-card {
        border-radius: 9.6px;
        padding: 16px;
        text-align: right;
        box-shadow: var(--shadow-sm);
        border: 0.8px solid var(--primary-500);
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    .file-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }

    .file-card::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 100%;
        height: 3.2px;
        background: var(--btn-primary-bg);
    }

    .file-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .file-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--surface-1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--btn-primary-bg);
        flex-shrink: 0;
        border: 0.8px solid var(--border-color);
    }

    .file-info {
        flex-grow: 1;
        text-align: right;
    }

    .file-name {
        font-weight: 700;
        color: var(--text-strong);
        margin-bottom: 4px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .file-actions {
        display: flex;
        justify-content: flex-end;
        gap: 9.6px;
        margin-top: 12px;
    }

    .file-action-btn {
        padding: 6.4px 12.8px;
        border-radius: 4.8px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: .9rem;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 6.4px;
    }

    .preview-btn {
        background: var(--surface-1);
        color: var(--text-strong);
        border: 0.8px solid var(--border-color);
    }

    .preview-btn:hover {
        background: var(--surface-2);
    }

    .file-action-btn.btn-secondary.locked {
        cursor: not-allowed;
        opacity: .6;
    }

    .file-actions a {
        text-decoration: none;
    }

    /* View modal footer button */
    .action-btn {
        padding: 9.6px 20px;
        border-radius: 6.4px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: .9rem;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .close-btn-task {
        background: var(--btn-primary-bg);
        color: var(--text-inverse);
        max-width: 200px;
    }

    .close-btn-task:hover {
        background: var(--btn-primary-hover);
        box-shadow: var(--shadow-sm);
    }

    /* Form controls (shared) */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6.4px;
    }

    .form-group label {
        font-weight: 600;
        color: var(--text-strong);
    }

    .form-control {
        padding: 9.6px 12px;
        border-radius: 6.4px;
        border: 0.8px solid var(--border-color);
        background: var(--surface-0);
        font-size: 1rem;
        transition: var(--transition);
        color: var(--text-strong);
    }

    .form-control:focus {
        border-color: var(--btn-primary-bg);
        outline: none;
        box-shadow: var(--focus-ring);
    }

    .form-control:disabled {
        background: var(--surface-1);
        color: var(--text-muted);
    }

    /* Specialty selectable chips */
    .specialty-options {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .spec-card {
        flex: 1 1 auto;
        padding: 8px 12px;
        border: 0.8px solid var(--btn-primary-bg);
        border-radius: 6.4px;
        text-align: center;
        cursor: pointer;
        user-select: none;
        transition: background .2s, color .2s, border-color .2s;
        background: var(--surface-1);
        color: var(--text-strong);
    }

    .spec-card.selected,
    .spec-card:hover {
        background: var(--btn-primary-bg);
        color: var(--text-inverse);
        border-color: var(--btn-primary-hover);
    }

    /* Upload zone */
    .file-upload-section {
        border: 1.6px dashed var(--btn-primary-bg);
        border-radius: 8px;
        padding: 1.5rem;
        text-align: center;
        transition: background .2s, border-color .2s;
        position: relative;
        background: var(--surface-0);
    }

    .file-upload-section.dragover {
        background: var(--info-soft);
        border-color: var(--accent-500);
    }

    .file-upload-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .5rem;
    }

    .upload-hint {
        font-size: .9rem;
        color: var(--text-muted);
        margin: 0;
    }

    .file-upload-btn {
        margin-top: 1rem;
        background: var(--accent-500);
        color: var(--text-inverse);
        border: none;
        padding: .6rem 1.2rem;
        border-radius: 4.8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: .5rem;
    }

    .file-upload-btn:hover {
        background: var(--accent-600);
    }

    .file-preview-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
        gap: 9.6px;
        margin-top: 1.2rem;
        justify-items: center;
    }

    .file-card {
        background: var(--surface-3);
        border-radius: 9.6px;
        box-shadow: var(--shadow-sm);
        padding: .8rem;
        width: 100%;
        max-width: 208px;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        border: 0.8px solid var(--primary-500);
        justify-content: space-around;
    }

    .file-card .file-icon {
        font-size: 2.2rem;
        margin-bottom: .5rem;
        color: var(--btn-primary-bg);
    }

    .file-card .file-name {
        font-size: .85rem;
        font-weight: 600;
        text-align: center;
        margin-bottom: .5rem;
        word-break: break-all;
        color: var(--text-strong);
    }

    .file-card .file-desc {
        width: 100%;
        height: 2.4rem;
        padding: .3rem;
        border: 0.8px solid var(--border-color);
        border-radius: 3.2px;
        font-size: .8rem;
        resize: vertical;
        margin-bottom: .5rem;
        background: var(--surface-0);
        color: var(--text-strong);
    }

    .file-card .remove-file {
        position: absolute;
        top: 4.8px;
        right: 4.8px;
        background: var(--surface-1);
        border: 0.8px solid var(--border-color);
        border-radius: 50%;
        width: 1.6rem;
        height: 1.6rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: var(--text-muted);
        cursor: pointer;
        transition: background .2s, color .2s;
    }

    .file-card .remove-file:hover {
        background: var(--error-500);
        color: #fff;
        border-color: transparent;
    }

    .modal-footer {
        padding-top: 16px;
        display: flex;
        justify-content: space-between;
    }

    .no-results {
        text-align: center;
        padding: 32px;
        color: var(--text-muted);
    }

    .no-results i {
        font-size: 5rem;
        color: var(--primary-300);
        margin-bottom: 16px;
    }

    .no-results h3 {
        font-size: 1.8rem;
        margin-bottom: 12px;
        color: var(--text-strong);
    }

    /* Responsive tweaks */
    @media (max-width: 768px) {
        .secondary-info-grid {
            grid-template-columns: 1fr;
        }

        .files-container {
            grid-template-columns: 1fr;
        }

        .modal-header h2 {
            font-size: 1.4rem;
        }

        .description-section h3 {
            font-size: 1.2rem;
        }

        .secondary-info-grid-header {
            display: flex;
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .modal-body {
            padding: 16px;
        }

        .file-header {
            text-align: center;
        }

        .file-actions {
            justify-content: center;
        }
    }

    @media (max-width: 900px) {
        .modal-footer {
            flex-direction: column;
            gap: 8px;
        }
    }
</style>
<div class="dashboard-container">
    <main class="main-content">
        <div class="student-dashboard">
            <section class="dashboard-section">
                <div class="section-header">
                    <h3><i class="fas fa-tasks"></i> المهام القادمة</h3>
                    <a href="#/tasks" class="view-all" data-section="tasks"
                       onclick="event.preventDefault(); (window.loadSection ? window.loadSection('tasks') : (location.hash='#/tasks'));">
                        عرض الكل <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                <button id="svcNewRequestBtn" class="btn-service-request" onclick="ServiceRequestModal.open()">
                    <i class="fas fa-headset"></i>
                    <span>طلب خدمة متخصصة</span>
                </button>
                <div class="tasks-grid" id="upcomingTasks"></div>
            </section>
            <div class="content-container">
                <div class="section-header">
                    <h3 class="section-title">الأدوات التعليمية</h3>
                </div>
                <div class="quick-actions-container">
                    <div class="quick-actions-scroll">
                        <?php $eduTools = $eduTools ?? []; ?>
                        <?php if (empty($eduTools)): ?>
                            <!-- تعذّر جلب الأدوات من منصّة الأدوات حاليًا -->
                            <div class="action-card">
                                <div class="action-icon"><i class="fas fa-toolbox"></i></div>
                                <div class="action-title">الأدوات التعليمية</div>
                                <div class="action-desc">سيتم عرض الأدوات المتاحة قريبًا.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($eduTools as $tool): ?>
                                <a href="<?= esc($tool['href'], 'attr') ?>" target="_blank" rel="noopener" style="text-decoration: none; color: inherit;">
                                    <div class="action-card-active">
                                        <div class="action-icon"<?= !empty($tool['icon_color']) ? ' style="color: ' . esc($tool['icon_color'], 'attr') . '"' : '' ?>>
                                            <i class="<?= esc(($tool['icon_class'] ?? '') ?: 'fas fa-wrench', 'attr') ?>"></i>
                                        </div>
                                        <div class="action-title"><?= esc($tool['name']) ?></div>
                                        <div class="action-desc"><?= esc($tool['description'] ?? '') ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="scroll-indicator left"></div>
                    <div class="scroll-indicator right"></div>
                </div>

            </div>
        </div>
    </main>
</div>