<?php
/**
 * Referral Section Partial
 */
?>
<section class="rewards-card rewards-referral-card">
    <div class="rewards-card__head">
        <i class="fas fa-user-plus"></i>
        <h3>دعوة الأصدقاء</h3>
    </div>
    <div class="rewards-card__body">
        <div class="rewards-referral-info">
            <p class="rewards-info-text">
                <i class="fas fa-info-circle"></i>
                احصل على 100 نقطة لكل صديق يسجل ويكمل بروفايله!
            </p>
        </div>

        <div class="rewards-referral-code">
            <label>كود الدعوة الخاص بك:</label>
            <div class="rewards-code-display">
                <input type="text" 
                       id="referralCodeInput" 
                       value="<?= esc($referralCode ?? '') ?>" 
                       readonly 
                       class="rewards-code-input">
                <button onclick="copyReferralCode()" class="rewards-copy-btn">
                    <i class="fas fa-copy"></i> نسخ
                </button>
            </div>
        </div>

        <div class="rewards-referral-stats">
            <div class="rewards-stat-item">
                <span class="rewards-stat-label">إجمالي الدعوات:</span>
                <span class="rewards-stat-value"><?= $referralStats['total'] ?? 0 ?></span>
            </div>
            <div class="rewards-stat-item">
                <span class="rewards-stat-label">مكتملة:</span>
                <span class="rewards-stat-value"><?= $referralStats['completed'] ?? 0 ?></span>
            </div>
            <div class="rewards-stat-item">
                <span class="rewards-stat-label">قيد الانتظار:</span>
                <span class="rewards-stat-value"><?= $referralStats['pending'] ?? 0 ?></span>
            </div>
        </div>

        <div class="rewards-referral-link">
            <label>رابط الدعوة:</label>
            <div class="rewards-link-display">
                <input type="text" 
                       id="referralLinkInput" 
                       value="<?= esc('https://auth.howplatform.net/register?ref=' . urlencode($referralCode ?? '')) ?>" 
                       readonly 
                       class="rewards-link-input">
                <button onclick="copyReferralLink()" class="rewards-copy-btn">
                    <i class="fas fa-copy"></i> نسخ
                </button>
            </div>
        </div>
    </div>
</section>

<style>
.rewards-referral-info {
    margin-bottom: 20px;
}

.rewards-info-text {
    padding: 10px;
    background: #e3f2fd;
    border-radius: 4px;
    color: #1976d2;
    margin: 0;
}

.rewards-referral-code,
.rewards-referral-link {
    margin-bottom: 20px;
}

.rewards-referral-code label,
.rewards-referral-link label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

.rewards-code-display,
.rewards-link-display {
    display: flex;
    gap: 10px;
}

.rewards-code-input,
.rewards-link-input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
}

.rewards-copy-btn {
    padding: 10px 20px;
    background: #4caf50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s;
}

.rewards-copy-btn:hover {
    background: #45a049;
}

.rewards-referral-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 4px;
}

.rewards-stat-item {
    text-align: center;
}

.rewards-stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.rewards-stat-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #4caf50;
}
</style>

