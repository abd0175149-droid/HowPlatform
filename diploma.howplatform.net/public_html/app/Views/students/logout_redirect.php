<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الخروج...</title>
</head>
<body>
    <form id="logoutForm" method="POST" action="https://auth.howplatform.net/logout">
        <input type="hidden" name="redirect" value="<?= esc($redirect) ?>">
        <?= csrf_field() ?> <!-- optional إذا أردت توليد CSRF من Laravel -->
    </form>
    <script>
        document.getElementById('logoutForm').submit();
    </script>
</body>
</html>
