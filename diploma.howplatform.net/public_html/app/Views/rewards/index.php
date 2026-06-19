<?php
/**
 * Rewards System Main Page — Redesigned
 * يعتمد على تصميم المكافآت الجديد
 */

// جلب الدعوات إذا لم تكن موجودة (لأن RewardsController لا يمررها)
if (!isset($referrals)) {
    $currentUserId = $userId ?? (session()->get('auth_user')['id'] ?? 0);
    $referralsModel = model(\App\Models\RewardsReferralModel::class);
    $referrals = $referralsModel ? $referralsModel->where('referrer_id', $currentUserId)->orderBy('created_at', 'desc')->findAll() : [];
}

$currentStreak = $currentStreak ?? ($stats['current_streak'] ?? 0);

echo view('students/pages/rewards', [
    'userId' => $userId ?? null,
    'points' => $points ?? 0,
    'stats' => $stats ?? [],
    'referralCode' => $referralCode ?? '',
    'referrals' => $referrals,
    'currentStreak' => $currentStreak,
    'isPublic' => $isPublic ?? false,
    'unavailable' => $unavailable ?? false
]);
?>
