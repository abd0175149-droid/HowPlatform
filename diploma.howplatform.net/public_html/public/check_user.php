<?php
/**
 * ملف مؤقت للتحقق من user_id
 * احذف هذا الملف بعد الاختبار!
 */

session_start();

echo "<html><head><meta charset='UTF-8'></head><body dir='rtl' style='font-family:Arial;padding:20px;'>";
echo "<h2>معلومات المستخدم الحالي</h2>";

echo "<div style='background:#f5f7fb;padding:15px;border:1px solid #ddd;border-radius:8px;'>";

// من session
echo "<h3>من Session:</h3>";
echo "<pre>";
echo "user_id من session: " . ($_SESSION['user_id'] ?? 'غير موجود') . "\n";
echo "Session data: " . json_encode($_SESSION, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo "</pre>";

// من .env
echo "<h3>من .env:</h3>";
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/ARTICLE_EVALUATOR_ALLOWED_USERS\s*=\s*(.+)/m', $envContent, $matches)) {
        $allowedUsers = trim($matches[1]);
        echo "<p><strong>ARTICLE_EVALUATOR_ALLOWED_USERS:</strong> {$allowedUsers}</p>";
        
        $currentUserId = $_SESSION['user_id'] ?? null;
        if ($currentUserId) {
            $allowedArray = array_map('intval', explode(',', $allowedUsers));
            $isAllowed = in_array((int)$currentUserId, $allowedArray, true);
            
            if ($isAllowed) {
                echo "<p style='color:green;font-weight:bold;'>✅ أنت مسموح لك باستخدام الأداة</p>";
            } else {
                echo "<p style='color:red;font-weight:bold;'>❌ أنت غير مسموح لك باستخدام الأداة</p>";
                echo "<p>لإضافة نفسك، أضف user_id الخاص بك ({$currentUserId}) إلى القائمة في .env</p>";
            }
        }
    } else {
        echo "<p style='color:orange;'>⚠️ المتغير ARTICLE_EVALUATOR_ALLOWED_USERS غير موجود في .env</p>";
        echo "<p>أضف هذا السطر في .env:</p>";
        echo "<code>ARTICLE_EVALUATOR_ALLOWED_USERS=974,500,501,1021</code>";
    }
} else {
    echo "<p style='color:red;'>❌ ملف .env غير موجود</p>";
}

echo "</div>";

echo "<hr>";
echo "<p><strong style='color:red;'>⚠️ احذف هذا الملف بعد التحقق!</strong></p>";
echo "</body></html>";
?>

