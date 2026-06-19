<?php
/**
 * Rewards System Unavailable View
 */
?>
<div class="rewards-unavailable" dir="rtl">
    <div class="rewards-alert rewards-alert--warning">
        <div class="rewards-alert__icon">
            <i class="fas fa-flask"></i>
        </div>
        <div class="rewards-alert__body">
            <div class="rewards-alert__title"><?= esc($title ?? 'الميزة تحت التطوير') ?></div>
            <div class="rewards-alert__desc"><?= esc($message ?? 'نظام المكافآت متاح حاليًا لمجموعة محدودة من المستخدمين. تابعنا قريبًا!') ?></div>
        </div>
    </div>
</div>

