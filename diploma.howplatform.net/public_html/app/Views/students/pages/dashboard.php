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
        transform: translateX(5px);
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
        border-bottom: 1px solid var(--border-color);
    }

    .section-header h3 {
        font-size: 1.4rem;
        color: var(--text-strong);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-header i {
        color: var(--btn-primary-bg);
    }

    /* ===== Tasks Grid ===== */
    .tasks-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        padding: 1rem 0;
    }

    .task-card {
        background: var(--surface-0);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        border-inline-start: 6px solid var(--btn-primary-bg);
        border: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 1rem;
        transition: var(--transition);
    }

    .task-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-md);
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
        gap: 6px;
        justify-content: flex-end;
    }

    /* Status chips (soft backgrounds) */
    .task-lable {
        align-self: flex-start;
        padding: 0.35rem 0.9rem;
        border-radius: 30px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid transparent;
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
        border-radius: 10px;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-align: center;
    }

    .view-btn {
        background: var(--info-soft);
        color: var(--link);
        border: 1px solid var(--info-border);
    }

    .view-btn:hover {
        background: rgba(79, 175, 222, .12);
    }

    .submit-btn {
        background: linear-gradient(to right, var(--accent-500), var(--accent-600));
        color: var(--text-inverse);
    }

    .submit-btn:hover {
        opacity: .95;
        transform: translateY(-2px);
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
        padding: 10px 0 25px;
    }

    .quick-actions-scroll {
        display: flex;
        gap: 25px;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--primary-300) transparent;
        padding: 25px 15px 20px;
        scroll-behavior: smooth;
        direction: rtl;
        background: var(--surface-1);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
    }

    .quick-actions-scroll::-webkit-scrollbar {
        height: 8px;
    }

    .quick-actions-scroll::-webkit-scrollbar-track {
        background: var(--surface-1);
        border-radius: 10px;
    }

    .quick-actions-scroll::-webkit-scrollbar-thumb {
        background: var(--primary-300);
        border-radius: 10px;
    }

    .scroll-indicator {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 60px;
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
        padding: 25px;
        text-align: center;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid var(--border-color);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 250px;
        flex-shrink: 0;
    }

    .action-card:hover ,.action-card-active:hover {
        transform: translateY(-8px);
        border-color: var(--btn-primary-bg);
        box-shadow: var(--shadow-md);
        background: linear-gradient(135deg, var(--surface-0), var(--surface-1));
    }

    .action-icon {
        width: 70px;
        height: 70px;
        border-radius: 20px;
        background: var(--info-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: var(--link);
        margin-bottom: 20px;
        border: 1px solid var(--info-border);
    }

    .action-title {
        font-weight: 700;
        color: var(--text-strong);
        font-size: 1.2rem;
        margin-bottom: 10px;
    }

    .action-desc {
        color: var(--text-muted);
        font-size: .95rem;
        line-height: 1.5;
    }



    /* Secondary info header row */
    .secondary-info-grid-header {
        display: flex;
        gap: 15px;
    }

    .info-item-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        color: var(--text-inverse);
    }

    .info-item-header i {
        color: #fff;
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
    }

    .info-item-header .value {
        font-weight: 600;
        color: #eef;
    }

    /* Secondary info grid */
    .secondary-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        background: var(--surface-0);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .info-item i {
        color: var(--btn-primary-bg);
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
    }

    .info-item .label {
        color: var(--text-muted);
        font-size: .9rem;
        margin-left: 5px;
    }

    .info-item .value {
        font-weight: 600;
        color: var(--text-strong);
    }

    /* Description */
    .description-section {
        background: linear-gradient(to left, var(--surface-1), var(--info-soft));
        border-radius: 12px;
        padding: 10px;
        margin-bottom: 30px;
        text-align: right;
        box-shadow: var(--shadow-sm);
        position: relative;
        overflow: hidden;
        border: 1px solid var(--primary-500);
    }

    .description-section::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(to right, var(--accent-500), var(--accent-600));
    }

    .description-section h3 {
        color: var(--accent-600);
        margin-bottom: 20px;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .description-section p {
        font-size: 1.05rem;
        line-height: 1.8;
        color: var(--text-strong);
        margin-top: 15px;
        padding: 15px;
        background: var(--surface-0);
        border-radius: 10px;
        border: 1px solid var(--border-color);
    }

    /* Files */
    .files-section {
        margin-bottom: 30px;
    }

    .section-title {
        font-size: 1.3rem;
        color: var(--text-strong);
        margin-bottom: 20px;
        padding-right: 15px;
        border-right: 5px solid var(--btn-primary-bg);
        display: inline-flex;
        align-items: center;
        gap: 12px;
    }

    .files-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .file-card {
        border-radius: 12px;
        padding: 20px;
        text-align: right;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--primary-500);
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    .file-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }

    .file-card::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 100%;
        height: 4px;
        background: var(--btn-primary-bg);
    }

    .file-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .file-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background: var(--surface-1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--btn-primary-bg);
        flex-shrink: 0;
        border: 1px solid var(--border-color);
    }

    .file-info {
        flex-grow: 1;
        text-align: right;
    }

    .file-name {
        font-weight: 700;
        color: var(--text-strong);
        margin-bottom: 5px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .file-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 15px;
    }

    .file-action-btn {
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: .9rem;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .preview-btn {
        background: var(--surface-1);
        color: var(--text-strong);
        border: 1px solid var(--border-color);
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
        padding: 12px 25px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: .9rem;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .close-btn-task {
        background: var(--btn-primary-bg);
        color: var(--text-inverse);
        max-width: 250px;
    }

    .close-btn-task:hover {
        background: var(--btn-primary-hover);
        box-shadow: var(--shadow-sm);
    }

    /* Form controls (shared) */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group label {
        font-weight: 600;
        color: var(--text-strong);
    }

    .form-control {
        padding: 12px 15px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
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
        gap: 10px;
        margin-bottom: 20px;
    }

    .spec-card {
        flex: 1 1 auto;
        padding: 10px 15px;
        border: 1px solid var(--btn-primary-bg);
        border-radius: 8px;
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
        border: 2px dashed var(--btn-primary-bg);
        border-radius: 10px;
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
        border-radius: 6px;
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
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
        margin-top: 1.2rem;
        justify-items: center;
    }

    .file-card {
        background: var(--surface-3);
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        padding: .8rem;
        width: 100%;
        max-width: 260px;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        border: 1px solid var(--primary-500);
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
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-size: .8rem;
        resize: vertical;
        margin-bottom: .5rem;
        background: var(--surface-0);
        color: var(--text-strong);
    }

    .file-card .remove-file {
        position: absolute;
        top: 6px;
        right: 6px;
        background: var(--surface-1);
        border: 1px solid var(--border-color);
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
        padding-top: 20px;
        display: flex;
        justify-content: space-between;
    }

    .no-results {
        text-align: center;
        padding: 40px;
        color: var(--text-muted);
    }

    .no-results i {
        font-size: 5rem;
        color: var(--primary-300);
        margin-bottom: 20px;
    }

    .no-results h3 {
        font-size: 1.8rem;
        margin-bottom: 15px;
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
            padding: 20px;
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
            gap: 10px;
        }
    }
</style>
<div class="dashboard-container">
    <main class="main-content">
        <div class="student-dashboard">
            <section class="dashboard-section">
                <div class="section-header">
                    <h3><i class="fas fa-tasks"></i> المهام القادمة</h3>
                    <a href="#" class="view-all">
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
                        
                        <!-- اصدار باجات الدبلوم -->
                        <a href="https://tools.howplatform.net/tools/id-card" target="_blank" style="text-decoration: none; color: inherit;">
                            <div class="action-card-active">
                                <div class="action-icon"><i class="fas fa-id-card"></i></div>
                                <div class="action-title">إصدار باجات الدبلوم</div>
                                <div class="action-desc">إنشاء وطباعة باجات خاصة بطلاب الدبلوم.</div>
                            </div>
                        </a>
                        <!-- العجلة العشوائية (Spinner) -->
                        <div class="action-card" data-tool="random-wheel">
                            <div class="action-icon"><i class="fas fa-shuffle"></i></div>
                            <div class="action-title">العجلة العشوائية (Spinner)</div>
                            <div class="action-desc">اختيار طالب/سؤال/فريق بشكل عشوائي.</div>
                        </div>

                        <!-- ALL2PDF -->
                        <div class="action-card" data-tool="all2pdf">
                            <div class="action-icon"><i class="fas fa-file-pdf"></i></div>
                            <div class="action-title">ALL2PDF</div>
                            <div class="action-desc">دمج وتحويل مختلف الملفات إلى PDF.</div>
                        </div>

                        <!-- الكلمات المتقاطعة -->
                        <div class="action-card" data-tool="crossword">
                            <div class="action-icon"><i class="fas fa-puzzle-piece"></i></div>
                            <div class="action-title">الكلمات المتقاطعة</div>
                            <div class="action-desc">أنشئ لعبة كلمات متقاطعة مخصصة لطلابك.</div>
                        </div>

                        <!-- مؤقّت الحصة -->
                        <div class="action-card" data-tool="class-timer">
                            <div class="action-icon"><i class="fas fa-stopwatch"></i></div>
                            <div class="action-title">مؤقّت الحصة</div>
                            <div class="action-desc">عدٌّ تنازلي للأنشطة وإدارة الزمن داخل الصف.</div>
                        </div>

                        <!-- فرق عشوائية -->
                        <div class="action-card" data-tool="random-teams">
                            <div class="action-icon"><i class="fas fa-users"></i></div>
                            <div class="action-title">تجميع فرق عشوائية</div>
                            <div class="action-desc">تقسيم الطلاب لفرق متوازنة بضغطة زر.</div>
                        </div>

                        <!-- مولّد QR -->
                        <div class="action-card" data-tool="qr-generator">
                            <div class="action-icon"><i class="fas fa-qrcode"></i></div>
                            <div class="action-title">مولّد أكواد QR</div>
                            <div class="action-desc">مشاركة روابط الدروس والواجبات بسرعة.</div>
                        </div>

                        <!-- بطاقات فلاش -->
                        <div class="action-card" data-tool="flashcards">
                            <div class="action-icon"><i class="fas fa-clone"></i></div>
                            <div class="action-title">بطاقات فلاش</div>
                            <div class="action-desc">مفردات ومفاهيم بمراجعة تكرار متباعد.</div>
                        </div>

                        <!-- بنك أسئلة (سحب عشوائي) -->
                        <div class="action-card" data-tool="question-draw">
                            <div class="action-icon"><i class="fas fa-circle-question"></i></div>
                            <div class="action-title">سحب سؤال عشوائي</div>
                            <div class="action-desc">اختيار سؤال من بنك الأسئلة فورياً.</div>
                        </div>

                        <!-- تذكرة خروج -->
                        <div class="action-card" data-tool="exit-ticket">
                            <div class="action-icon"><i class="fas fa-ticket"></i></div>
                            <div class="action-title">تذكرة خروج</div>
                            <div class="action-desc">تقييم سريع لنواتج التعلّم قبل نهاية الحصة.</div>
                        </div>

                        <!-- ساعة رملية -->
                        <div class="action-card" data-tool="hourglass">
                            <div class="action-icon"><i class="fas fa-hourglass-half"></i></div>
                            <div class="action-title">ساعة رملية</div>
                            <div class="action-desc">تعزيز إدارة الوقت في نشاطات قصيرة.</div>
                        </div>

                        <!-- قائمة مهام الصف -->
                        <div class="action-card" data-tool="class-todo">
                            <div class="action-icon"><i class="fas fa-tasks"></i></div>
                            <div class="action-title">قائمة مهام الصف</div>
                            <div class="action-desc">تعقّب التقدّم وإنجاز الأنشطة خلال الأسبوع.</div>
                        </div>

                    </div>
                    <div class="scroll-indicator left"></div>
                    <div class="scroll-indicator right"></div>
                </div>

            </div>
        </div>
    </main>
</div>