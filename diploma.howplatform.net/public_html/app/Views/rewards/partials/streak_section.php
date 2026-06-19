<?php
/**
 * Streak Section Partial
 */
?>
<section class="rewards-card rewards-streak-card">
    <div class="rewards-card__head">
        <i class="fas fa-fire"></i>
        <h3>الأيام المتتالية</h3>
    </div>
    <div class="rewards-card__body">
        <div class="rewards-streak-info">
            <p class="rewards-info-text">
                <i class="fas fa-info-circle"></i>
                استخدم المنصة لـ 5 أيام متتالية واحصل على 50 نقطة!
            </p>
        </div>

        <div class="rewards-streak-display">
            <div class="rewards-streak-value">
                <span class="rewards-streak-number"><?= $stats['current_streak'] ?? 0 ?></span>
                <span class="rewards-streak-label">يوم متتالي</span>
            </div>
        </div>

        <div class="rewards-streak-progress">
            <div class="rewards-progress-bar">
                <?php
                $currentStreak = $stats['current_streak'] ?? 0;
                $nextMilestone = 5;
                if ($currentStreak >= 5) {
                    $nextMilestone = (int)(ceil($currentStreak / 5) * 5);
                }
                $progress = min(100, ($currentStreak % 5) * 20);
                ?>
                <div class="rewards-progress-fill" style="width: <?= $progress ?>%"></div>
            </div>
            <div class="rewards-progress-text">
                <?php if ($currentStreak < 5): ?>
                    <?= 5 - $currentStreak ?> أيام متبقية للوصول للهدف القادم
                <?php else: ?>
                    <?= $nextMilestone - $currentStreak ?> أيام متبقية للوصول للهدف القادم
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
.rewards-streak-info {
    margin-bottom: 20px;
}

.rewards-streak-display {
    text-align: center;
    padding: 20px;
    margin-bottom: 20px;
}

.rewards-streak-number {
    font-size: 48px;
    font-weight: bold;
    color: #ff9800;
    display: block;
}

.rewards-streak-label {
    font-size: 18px;
    color: #666;
    display: block;
    margin-top: 10px;
}

.rewards-streak-progress {
    margin-top: 20px;
}

.rewards-progress-bar {
    width: 100%;
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.rewards-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff9800, #ff5722);
    transition: width 0.3s;
}

.rewards-progress-text {
    text-align: center;
    color: #666;
    font-size: 14px;
}
</style>

