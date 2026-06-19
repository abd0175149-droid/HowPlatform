<?php
/**
 * Rewards Page (Students) — Redesigned exactly like Task Details
 */
$unavailable = $unavailable ?? false;
$referralUrl = 'https://auth.howplatform.net/register?ref=' . urlencode($referralCode ?? '');
$currentStreak = (int) ($currentStreak ?? ($stats['current_streak'] ?? 0));
?>

<section id="rewards-page" class="task-details-page rewards-page-new" data-component="rewards" dir="rtl">
    <?php if ($unavailable): ?>
        <div class="premium-card">
            <div style="text-align: center; padding: 40px;">
                <div class="icon-box"
                    style="margin: 0 auto 20px; background: #fffbeb; color: #f59e0b; width: 64px; height: 64px; font-size: 2rem;">
                    <i class="fas fa-flask"></i>
                </div>
                <h3 style="font-size: 1.5rem; font-weight: 800; color: var(--text-strong);">الميزة تحت التطوير</h3>
                <p style="color: var(--text-muted); font-size: 1.1rem; margin-top: 10px;">نظام المكافآت متاح حاليًا لمجموعة
                    محدودة من المستخدمين. تابعنا قريبًا!</p>
            </div>
        </div>
    <?php else: ?>
        <!-- 🛰️ Blue Sleek Header -->
        <header class="td-header-compact-blue">
            <div class="td-header-main">
                <div class="icon-box"
                    style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); color: #fff; width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="td-title-area">
                    <h1 class="td-compact-title">نظام المكافآت</h1>
                    <div class="td-compact-meta">استخدم المنصة وادعُ أصدقائك للحصول على النقاط</div>
                </div>
            </div>
            <div class="td-header-nav">
                <?php if (!($isPublic ?? false)): ?>
                    <span
                        style="background: rgba(255,255,255,0.2); padding: 6px 16px; border-radius: 20px; font-weight: bold; font-size: 0.85rem; color: #fff; letter-spacing: 0.5px;">BETA</span>
                <?php endif; ?>
            </div>
        </header>

        <div class="td-workspace-grid">
            <!-- 🚀 Main Content -->
            <main class="td-content-area">

                <!-- 👥 Referrals -->
                <div class="premium-card">
                    <div class="card-header-premium"
                        style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="icon-box" style="background: #e0f2fe; color: #0284c7;"><i
                                    class="fas fa-user-plus"></i></div>
                            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 900; color: var(--text-strong);">دعوات
                                الأصدقاء</h3>
                        </div>
                        <button class="btn-compact-back-blue" style="background: var(--blue-primary); border: none;"
                            data-action="open-invite-modal">
                            <i class="fas fa-paper-plane"></i>
                            <span>إرسال دعوة</span>
                        </button>
                    </div>

                    <?php if (empty($referrals ?? [])): ?>
                        <div
                            style="text-align: center; padding: 40px 20px; background: var(--surface-1); border-radius: var(--p-radius-sm); border: 1px dashed var(--border-color);">
                            <i class="fas fa-inbox"
                                style="font-size: 3rem; color: var(--border-color); margin-bottom: 15px; display: block;"></i>
                            <h4 style="margin: 0 0 10px; color: var(--text-strong);">لا توجد دعوات حتى الآن</h4>
                            <p style="color: var(--text-muted); margin: 0 0 20px;">قم بدعوة أصدقائك واحصل على 100 نقطة لكل صديق
                                يسجل ويكمل بروفايله.</p>
                            <button class="btn-compact-back-blue"
                                style="margin: 0 auto; background: var(--blue-dark); border: none;"
                                data-action="open-invite-modal">
                                <i class="fas fa-plus"></i>
                                <span>أرسل أول دعوة</span>
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="roadmap-list-modern" style="gap: 12px;">
                            <?php foreach ($referrals as $referral): ?>
                                <?php
                                $isCompleted = in_array($referral['status'], ['completed', 'rewarded']);
                                $isPending = $referral['status'] === 'pending';
                                $displayDate = $isCompleted ? ($referral['rewarded_at'] ?? $referral['completed_at']) : $referral['created_at'];
                                ?>
                                <div class="td-file"
                                    style="padding: 16px; border-radius: var(--p-radius-sm); background: var(--surface-1); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 16px; transition: all 0.2s ease;">
                                    <div class="icon"
                                        style="background: <?= $isCompleted ? '#dcfce7' : '#f1f5f9' ?>; color: <?= $isCompleted ? '#16a34a' : '#64748b' ?>; font-size: 1.4rem;">
                                        <i class="fas <?= $isCompleted ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                                    </div>
                                    <div class="grow">
                                        <div class="name" style="font-weight: 800; font-size: 1.1rem; color: var(--text-strong);">
                                            <?= esc($referral['name']) ?></div>
                                        <div class="meta" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                                            <i class="fas <?= $isCompleted ? 'fa-gift' : 'fa-calendar' ?>"></i>
                                            <?= $isCompleted ? 'حصلت على 100 نقطة' : 'في انتظار إكمال التسجيل' ?> •
                                            <?= date('Y-m-d', strtotime($displayDate)) ?>
                                        </div>
                                    </div>
                                    <?php if ($isCompleted): ?>
                                        <div
                                            style="font-weight: 900; color: #16a34a; font-size: 1.1rem; background: #dcfce7; padding: 6px 12px; border-radius: 8px;">
                                            +100</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 📜 Transactions -->
                <div class="premium-card">
                    <div class="card-header-premium">
                        <div class="icon-box" style="color: #9333ea;"><i class="fas fa-history"></i>
                        </div>
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 900; color: var(--text-strong);">سجل النقاط
                        </h3>
                    </div>

                    <div class="transactions-container" data-ref="transactionsContainer">
                        <div class="transactions-loading"
                            style="display: none; justify-content: center; padding: 40px; color: var(--text-muted);">
                            <div class="pulse-loader" style="margin: 0 auto;"></div>
                        </div>

                        <div class="transactions-table-wrapper"
                            style="border-radius: var(--p-radius-sm); border: 1px solid var(--border-color); overflow: hidden;">
                            <table class="transactions-table" id="transactionsTable"
                                style="width: 100%; border-collapse: collapse; text-align: right; background: var(--surface-0);">
                                <thead style="background: var(--surface-1); border-bottom: 2px solid var(--border-color);">
                                    <tr>
                                        <th style="padding: 16px; color: var(--text-muted); font-weight: 800;">النوع</th>
                                        <th style="padding: 16px; color: var(--text-muted); font-weight: 800;">الوصف</th>
                                        <th
                                            style="padding: 16px; color: var(--text-muted); font-weight: 800; text-align: center;">
                                            النقاط</th>
                                        <th style="padding: 16px; color: var(--text-muted); font-weight: 800;">التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody id="transactionsList">
                                    <!-- JS injected -->
                                </tbody>
                            </table>
                            <div class="transactions-empty" id="transactionsEmpty"
                                style="display: none; text-align: center; padding: 40px; background: var(--surface-1);">
                                <i class="fas fa-inbox"
                                    style="font-size: 3rem; color: var(--border-color); margin-bottom: 15px;"></i>
                                <p style="color: var(--text-muted); font-weight: 700; margin: 0;">لا توجد معاملات حتى الآن
                                </p>
                            </div>
                            <div class="transactions-pagination" id="transactionsPagination"
                                style="display: none; padding: 16px; background: var(--surface-1); border-top: 1px solid var(--border-color); justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <!-- JS injected -->
                            </div>
                        </div>
                    </div>
                </div>

            </main>

            <!-- 📊 Sidebar -->
            <aside class="td-sidebar-modern">

                <!-- ⭐ Total Points -->
                <div class="premium-card sidebar-widget" style="text-align: center; padding: 40px 20px;">
                    <div class="icon-box" style="color: #219ebc; font-size: 2rem; margin: 0 auto 20px;">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 900; color: var(--text-strong);">نقاطك
                        الإجمالية</h3>
                    <div
                        style="font-size: 4rem; font-weight: 900; color: var(--blue-primary); line-height: 1; text-shadow: 0 4px 12px rgba(33, 158, 188, 0.2);">
                        <?= number_format($points ?? 0) ?></div>
                    <div style="font-size: 1.1rem; color: var(--text-muted); margin-top: 10px; font-weight: 700;">نقطة
                        مكافأة</div>
                </div>

                <!-- 🔥 Streak -->
                <div class="premium-card sidebar-widget">
                    <div class="card-header-premium">
                        <div class="icon-box" style="color: #ea580c;"><i class="fas fa-fire"></i></div>
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 900; color: var(--text-strong);">الأيام
                            المتتالية</h3>
                    </div>

                    <div
                        style="background: var(--surface-1); border: 1px solid var(--border-color); border-radius: var(--p-radius-sm); padding: 24px;">
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: 24px; position: relative;">
                            <?php for ($i = 1; $i <= 5; $i++):
                                $isActive = $currentStreak >= $i; ?>
                                <div
                                    style="position: relative; z-index: 2; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; background: <?= $isActive ? 'var(--blue-primary)' : 'var(--surface-0)' ?>; color: <?= $isActive ? '#fff' : 'var(--text-muted)' ?>; border: 2px solid <?= $isActive ? 'var(--blue-primary)' : 'var(--border-color)' ?>; box-shadow: <?= $isActive ? '0 4px 10px rgba(33, 158, 188, 0.3)' : 'none' ?>;">
                                    <?= $isActive ? '<i class="fas fa-check"></i>' : $i ?>
                                </div>
                                <?php if ($i < 5): ?>
                                    <div
                                        style="position: absolute; z-index: 1; top: 50%; transform: translateY(-50%); height: 4px; border-radius: 2px; right: <?= ($i - 1) * 25 + 10 ?>%; left: <?= 100 - ($i * 25) + 10 ?>%; background: <?= ($currentStreak > $i) ? 'var(--blue-primary)' : 'var(--border-color)' ?>;">
                                    </div>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <!-- Background line placeholder for empty space -->
                            <div
                                style="position: absolute; z-index: 0; top: 50%; transform: translateY(-50%); height: 4px; border-radius: 2px; right: 18px; left: 18px; background: var(--border-color);">
                            </div>
                        </div>

                        <div style="text-align: center;">
                            <div
                                style="font-weight: 800; color: var(--text-strong); font-size: 1.1rem; margin-bottom: 8px;">
                                رصيد الأيام: <span
                                    style="color: var(--blue-primary); font-size: 1.3rem;"><?= $currentStreak ?></span>
                            </div>
                            <?php if ($currentStreak < 5): ?>
                                <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">استخدم المنصة لـ 5
                                    أيام متتالية لتحصل على مكافأة 50 نقطة.</div>
                            <?php else: ?>
                                <div style="font-size: 0.85rem; color: #16a34a; font-weight: 700;">أتممت 5 أيام! استمر في
                                    الإبداع.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </aside>
        </div>
    <?php endif; ?>

    <!-- Modal الدعوة -->
    <div id="inviteModal" class="glass-modal" aria-hidden="true">
        <div class="modal-blur-bg" data-action="close-invite-modal-overlay"></div>
        <div class="modal-content-wrap" style="max-width: 500px; height: auto; max-height: 90vh;">
            <div class="modal-header">
                <h4 style="margin:0; font-size: 1.2rem; font-weight: 800; display:flex; align-items:center; gap: 10px;">
                    <i class="fas fa-paper-plane" style="color: var(--blue-primary);"></i>
                    إرسال دعوة
                </h4>
                <button class="modal-close-btn" data-action="close-invite-modal"
                    style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding: 24px; overflow-y: auto;">
                <div class="invite-message"
                    style="background: var(--surface-1); padding: 20px; border-radius: var(--p-radius-sm); border: 1px solid var(--border-color); color: var(--text-strong); line-height: 1.6; font-size: 0.95rem; margin-bottom: 20px;">
                    <p style="margin-top:0;">👋 مرحبًا،</p>
                    <p>أحب أن أدعوك لتجربة منصة كيف التعليمية 🎓</p>
                    <p>منصة مخصصة لدعم طلاب الدبلوم العالي، تقدم شروحات منظمة، مواد مساندة، وأدوات ذكاء اصطناعي تساعدك
                        على فهم وإنجاز جميع مهام الدبلوم بثقة.</p>
                    <p>تجربة مفيدة توفر وقتًا وجهدًا 👌</p>
                    <p style="margin-bottom: 5px; font-weight: 800;">رابط المنصة:</p>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="inviteLinkInput" value="<?= esc($referralUrl) ?>" readonly
                            style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--surface-0); color: var(--text-strong); font-family: monospace; outline: none;">
                    </div>
                </div>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <button class="btn-compact-back-blue"
                        style="flex: 1; background: var(--blue-dark); border: none; justify-content: center;"
                        data-action="copy-invite-link">
                        <i class="fas fa-copy"></i>
                        <span>نسخ الرسالة</span>
                    </button>
                    <button class="btn-compact-back-blue"
                        style="flex: 1; background: #25D366; border: none; justify-content: center; color: #fff;"
                        data-action="share-whatsapp">
                        <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i>
                        <span>مشاركة واتساب</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- تحميل SweetAlert2 إذا لم يكن محمّلاً -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>

<script>
    // دوال نظام المكافآت
    (function () {
        'use strict';

        function showSuccessAlert(title, text) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: title || 'تم بنجاح!',
                    text: text || '',
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: 'var(--blue-primary)',
                    timer: 2000,
                    timerProgressBar: true,
                    customClass: { popup: 'swal2-rtl' }
                });
            } else {
                alert(title + (text ? '\n' + text : ''));
            }
        }

        if (!window.RewardsModal) {
            window.RewardsModal = {
                async copyInviteLink() {
                    const input = document.getElementById('inviteLinkInput');
                    const messageContainer = document.querySelector('.invite-message');
                    if (!input) return;

                    let fullText = '';
                    if (messageContainer) {
                        const paragraphs = messageContainer.querySelectorAll('p');
                        paragraphs.forEach((p, index) => {
                            const text = p.textContent.trim();
                            if (text && !text.includes('رابط المنصة:')) {
                                fullText += text;
                                if (index < paragraphs.length - 1) fullText += '\n\n';
                            }
                        });
                        if (fullText && !fullText.endsWith('\n')) fullText += '\n\n';
                        fullText += input.value;
                    } else {
                        fullText = '👋 مرحبًا،\n\nأحب أن أدعوك لتجربة منصة كيف التعليمية 🎓\n\n' + input.value;
                    }

                    try {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            await navigator.clipboard.writeText(fullText);
                            showSuccessAlert('تم النسخ بنجاح!', 'تم نسخ الرسالة والرابط إلى الحافظة');
                        } else {
                            const textarea = document.createElement('textarea');
                            textarea.value = fullText;
                            document.body.appendChild(textarea);
                            textarea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textarea);
                            showSuccessAlert('تم النسخ بنجاح!', 'تم نسخ الرسالة والرابط إلى الحافظة');
                        }
                    } catch (err) { }
                },

                shareViaWhatsApp() {
                    const input = document.getElementById('inviteLinkInput');
                    if (!input) return;
                    const link = input.value;
                    const message = encodeURIComponent('👋 مرحبًا،\n\nأحب أن أدعوك لتجربة منصة كيف التعليمية 🎓\n\nمنصة مخصصة لدعم طلاب الدبلوم العالي، تقدم شروحات منظمة، مواد مساندة، وأدوات ذكاء اصطناعي تساعدك على فهم وإنجاز جميع مهام الدبلوم بثقة.\n\nتجربة مفيدة توفر وقتًا وجهدًا 👌\n\nرابط المنصة:\n' + link);
                    window.open(`https://wa.me/?text=${message}`, '_blank');
                },

                openInviteModal() {
                    const modal = document.getElementById('inviteModal');
                    if (modal) modal.setAttribute('aria-hidden', 'false');
                },

                closeInviteModal(event) {
                    const modal = document.getElementById('inviteModal');
                    if (!modal) return;
                    modal.setAttribute('aria-hidden', 'true');
                }
            };
        }

        function initRewardsEvents() {
            if (window._rewardsEventHandler) {
                document.body.removeEventListener('click', window._rewardsEventHandler, true);
            }

            window._rewardsEventHandler = function (e) {
                const actionBtn = e.target.closest('[data-action]');
                if (!actionBtn) return;
                const action = actionBtn.getAttribute('data-action');
                if (!action) return;

                const rewardsPage = actionBtn.closest('.rewards-page-new');
                const isInModal = actionBtn.closest('#inviteModal');
                if (!rewardsPage && !isInModal) return;

                e.preventDefault(); e.stopPropagation();

                switch (action) {
                    case 'open-invite-modal': window.RewardsModal.openInviteModal(); break;
                    case 'close-invite-modal': window.RewardsModal.closeInviteModal(e); break;
                    case 'close-invite-modal-overlay': window.RewardsModal.closeInviteModal(e); break;
                    case 'copy-invite-link': window.RewardsModal.copyInviteLink(); break;
                    case 'share-whatsapp': window.RewardsModal.shareViaWhatsApp(); break;
                }
            };
            document.body.addEventListener('click', window._rewardsEventHandler, true);
        }

        initRewardsEvents();
    })();

    // تحميل سجل النقاط مع Pagination
    (function () {
        'use strict';
        let currentPage = 1;
        const limit = 10;

        function loadTransactions(page = 1) {
            const container = document.querySelector('[data-ref="transactionsContainer"]');
            if (!container) return;

            const loading = container.querySelector('.transactions-loading');
            const table = document.getElementById('transactionsTable');
            const tbody = document.getElementById('transactionsList');
            const empty = document.getElementById('transactionsEmpty');
            const pagination = document.getElementById('transactionsPagination');

            if (!loading || !tbody || !empty || !pagination) return;

            loading.style.display = 'flex';
            table.style.display = 'none';
            empty.style.display = 'none';
            pagination.style.display = 'none';
            currentPage = page;

            fetch(`/students/rewards/transactions?limit=${limit}&page=${page}`)
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    loading.style.display = 'none';
                    tbody.innerHTML = '';

                    if (data.success && data.transactions && data.transactions.length > 0) {
                        table.style.display = 'table';
                        data.transactions.forEach(transaction => {
                            const points = parseInt(transaction.points) || 0;
                            const isPositive = points > 0;
                            const isUsage = !isPositive;

                            let typeLabel, typeIcon, typeColor;
                            if (isUsage && transaction.reward_type === 'ai_tool_usage') {
                                typeLabel = 'استخدام'; typeIcon = 'fas fa-robot'; typeColor = '#ef4444';
                            } else {
                                typeLabel = 'مكافأة'; typeIcon = 'fas fa-gift'; typeColor = '#10b981';
                            }

                            let descriptionHtml = transaction.description || '-';
                            if (isUsage && transaction.task_id && transaction.task_title) {
                                const taskUrl = `#/task-details?task=${transaction.task_id}`;
                                descriptionHtml = `استخدام أداة ذكاء اصطناعي: <a href="${taskUrl}" style="color: var(--blue-primary); font-weight:700;">${escapeHtml(transaction.task_title)}</a>`;
                            }

                            const date = new Date(transaction.created_at).toLocaleDateString('ar-SA', {
                                year: 'numeric', month: 'long', day: 'numeric'
                            });

                            const tr = document.createElement('tr');
                            tr.style.borderBottom = '1px solid var(--border-color)';
                            tr.innerHTML = `
                            <td style="padding: 16px;">
                                <div style="display:flex; align-items:center; gap:10px; font-weight:700; color:var(--text-strong);">
                                    <div style="width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:${typeColor}20; color:${typeColor};">
                                        <i class="${typeIcon}"></i>
                                    </div>
                                    ${typeLabel}
                                </div>
                            </td>
                            <td style="padding: 16px; color: var(--text-muted); line-height: 1.5;">${descriptionHtml}</td>
                            <td style="padding: 16px; text-align: center; font-weight: 800; font-size: 1.1rem; color: ${isPositive ? '#10b981' : '#ef4444'};">
                                ${isPositive ? '+' : ''}${points}
                            </td>
                            <td style="padding: 16px; color: var(--text-muted); font-size: 0.9rem;">${date}</td>
                        `;
                            tbody.appendChild(tr);
                        });

                        if (data.pagination && data.pagination.total_pages > 1) {
                            renderPagination(data.pagination, pagination);
                            pagination.style.display = 'flex';
                        }
                    } else {
                        empty.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error(err);
                    loading.style.display = 'none';
                    empty.style.display = 'block';
                    const errorMsg = empty.querySelector('p');
                    if (errorMsg) errorMsg.textContent = 'حدث خطأ أثناء تحميل المعاملات';
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div'); div.textContent = text; return div.innerHTML;
        }

        function renderPagination(pagination, container) {
            const { current_page, total_pages, total_count } = pagination;
            let html = `<div style="color:var(--text-muted); font-weight:600; font-size:0.9rem;">صفحة ${current_page} من ${total_pages}</div>`;
            html += `<div style="display:flex; gap:6px;">`;

            const btnStyle = `padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--surface-0); color: var(--text-strong); font-weight: 700; cursor: pointer; transition: all 0.2s;`;
            const activeStyle = `padding: 6px 12px; border-radius: 6px; border: 1px solid var(--blue-primary); background: var(--blue-primary); color: #fff; font-weight: 700;`;
            const disabledStyle = `padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--surface-1); color: var(--text-muted); opacity: 0.5; cursor: not-allowed;`;

            if (current_page > 1) {
                html += `<button class="page-btn" data-page="${current_page - 1}" style="${btnStyle}"><i class="fas fa-chevron-right"></i> السابق</button>`;
            } else {
                html += `<button disabled style="${disabledStyle}"><i class="fas fa-chevron-right"></i> السابق</button>`;
            }

            if (current_page < total_pages) {
                html += `<button class="page-btn" data-page="${current_page + 1}" style="${btnStyle}">التالي <i class="fas fa-chevron-left"></i></button>`;
            } else {
                html += `<button disabled style="${disabledStyle}">التالي <i class="fas fa-chevron-left"></i></button>`;
            }

            html += `</div>`;
            container.innerHTML = html;

            container.querySelectorAll('.page-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    loadTransactions(parseInt(this.dataset.page));
                });
            });
        }

        // Auto load
        setTimeout(() => {
            loadTransactions(1);
        }, 500);
    })();
</script>

<style>
    /* CSS Variables & Layout definitions (extracted from task details to match aesthetic) */
    :root {
        --p-radius: 12px;
        --p-radius-sm: 8px;
        --blue-primary: #219ebc;
        --blue-dark: #023047;
        --header-bg: linear-gradient(135deg, #219ebc 0%, #023047 100%);
        --surface-0: #ffffff;
        --surface-1: #f8fafc;
        --text-strong: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --ai-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
    }

    [data-theme="dark"] {
        --surface-0: #1e293b;
        --surface-1: #0f172a;
        --text-strong: #f1f5f9;
        --text-muted: #94a3b8;
        --border-color: #334155;
    }

    .task-details-page {
        display: flex;
        flex-direction: column;
        gap: 24px;
        padding: 24px;
    }

    /* 🛰️ Blue Header */
    .td-header-compact-blue {
        background: var(--header-bg);
        color: #fff;
        border-radius: var(--p-radius);
        padding: 14px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 12px rgba(2, 48, 71, 0.15);
    }

    .td-header-main {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .btn-compact-back-blue {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        transition: all 0.2s ease;
    }

    .btn-compact-back-blue:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-2px);
    }

    .td-compact-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 800;
    }

    .td-compact-meta {
        font-size: 0.9rem;
        opacity: 0.8;
    }

    .td-header-nav {
        display: flex;
        gap: 8px;
    }

    /* 🏗️ Grid & Layout - Expanded Dimensions */
    .td-workspace-grid {
        display: grid;
        grid-template-columns: 1fr 420px;
        gap: 40px;
        align-items: start;
    }

    .td-content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .td-sidebar-modern {
        display: flex;
        flex-direction: column;
        gap: 40px;
    }

    .premium-card {
        background: var(--surface-0);
        border: 1px solid var(--border-color);
        border-radius: var(--p-radius);
        padding: 24px;
    }

    .sidebar-widget {
        padding: 24px;
    }

    .card-header-premium {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 24px;
    }

    /* Roadmap & Files styling */
    .roadmap-list-modern {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .td-file {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 20px;
        background: var(--surface-1);
        border: 1px solid var(--border-color);
        border-radius: var(--p-radius-sm);
        transition: all 0.2s ease;
    }

    .td-file:hover {
        background: var(--surface-0);
        border-color: var(--blue-primary);
        transform: translateX(-5px);
    }

    .td-file .icon {
        font-size: 1.4rem;
        color: var(--blue-primary);
        background: #fff;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    .td-file .grow {
        flex: 1;
        min-width: 0;
    }

    .td-file .name {
        font-weight: 800;
        font-size: 1rem;
        color: var(--text-strong);
    }

    .td-file .meta {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    /* Transactions Table Enhancements */
    .transactions-table tbody tr:hover {
        background: var(--surface-1);
    }

    /* Modal */
    .glass-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .glass-modal[aria-hidden="false"] {
        display: flex;
    }

    .modal-blur-bg {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
    }

    .modal-content-wrap {
        position: relative;
        width: 90%;
        background: var(--surface-0);
        border-radius: var(--p-radius);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    @media (max-width: 1100px) {
        .td-workspace-grid {
            grid-template-columns: 1fr;
        }
    }

    .pulse-loader {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--blue-primary);
        animation: pulse 1.5s infinite ease-in-out;
    }

    @keyframes pulse {
        0% {
            transform: scale(0.8);
            opacity: 0.5;
        }

        50% {
            transform: scale(1.2);
            opacity: 1;
        }

        100% {
            transform: scale(0.8);
            opacity: 0.5;
        }
    }

    .page-btn:hover {
        border-color: var(--blue-primary) !important;
        color: var(--blue-primary) !important;
    }
</style>