<?php
/**
 * app/Views/emails/broadcast.php
 * بريد RTL مع محاذاة دقيقة باستخدام الجداول لضمان التوافق مع عملاء البريد.
 *
 * المتغيّرات:
 * @var string      $subject
 * @var string|null $preheader
 * @var string|null $greeting
 * @var string      $bodyHtml
 * @var string|null $ctaUrl
 * @var string|null $ctaLabel
 */

$subject   = $subject   ?? 'رسالة من منصة كيف؟ التعليمية';
$preheader = $preheader ?? 'رسالة تذكير من منصة كيف؟ التعليمية';
$greeting  = $greeting  ?? '';
$bodyHtml  = $bodyHtml  ?? '';
$ctaUrl    = $ctaUrl    ?? null;
$ctaLabel  = $ctaLabel  ?? 'تسجيل الدخول';

$logoUrl   = 'https://diploma.howplatform.net/assets/images/logo.png';
$brandBlue = '#1da1f2';
$brandHead = '#002147';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= esc($subject) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    /* إخفاء سطر المعاينة */
    .preheader {
      display: none !important; visibility: hidden !important; opacity: 0 !important;
      color: transparent !important; height: 0 !important; width: 0 !important; overflow: hidden !important;
      mso-hide: all !important;
    }
  </style>
</head>
<body style="Margin:0; padding:0; background-color:#f9f9f9; direction:rtl; text-align:right; font-family:Tahoma, Arial, 'Almarai', sans-serif;">
  <!-- سطر المعاينة -->
  <span class="preheader"><?= esc($preheader) ?></span>

  <!-- غلاف بعرض كامل + تمركز الجدول الداخلي -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse; direction:rtl;">
    <tr>
      <td align="center" style="padding:20px 12px;">

        <!-- البطاقة الداخلية (600px) -->
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
               style="max-width:600px; width:100%; background:#ffffff; border-radius:8px;">
          <!-- رأس مُوسّط -->
          <tr>
            <td align="center" dir="rtl" style="padding:24px 24px 8px 24px;">
              <img src="<?= esc($logoUrl) ?>" alt="شعار منصة كيف؟" width="120" style="display:block; border:0; outline:none; text-decoration:none; Margin:0 auto 8px auto;">
              <h2 style="Margin:0; font-weight:700; color: <?= $brandHead ?>; font-size:22px; line-height:1.3;">منصة كيف؟ التعليمية</h2>
            </td>
          </tr>

          <!-- محتوى نصي محاذي يمين (RTL) -->
          <tr>
            <td align="right" dir="rtl" style="padding:16px 24px 0 24px; color:#333333; font-size:15px; line-height:1.35; text-align:right;">
              <?php if (! empty($greeting)) : ?>
                <!-- تحية بدون هوامش إضافية -->
                <p style="Margin:0; padding:0;"><?= esc($greeting) ?></p>
              <?php endif; ?>

              <!-- محتوى HTML جاهز: ننصح بتمرير فقرات بدون margins أو مع inline style Margin:0 -->
              <div style="Margin:0; padding:0; direction:rtl; text-align:right; line-height:1.35; word-break:break-word;">
                <?= $bodyHtml ?>
              </div>
            </td>
          </tr>

          <!-- زر CTA مُوسّط (اختياري) -->
          <?php if (! empty($ctaUrl)) : ?>
            <tr>
              <td align="center" dir="rtl" style="padding:20px 24px 0 24px;">
                <!-- زر داخل جدول لضمان التوافق -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                  <tr>
                    <td align="center" bgcolor="<?= $brandBlue ?>" style="border-radius:5px;">
                      <a href="<?= esc($ctaUrl) ?>"
                         style="display:inline-block; padding:12px 24px; font-size:15px; color:#ffffff; text-decoration:none; border-radius:5px;">
                        <?= esc($ctaLabel) ?>
                      </a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- رابط بديل مُوسّط -->
            <tr>
              <td align="center" dir="rtl" style="padding:12px 24px 0 24px; font-size:14px; color:#555555; text-align:center; line-height:1.35;">
                <p style="Margin:0; padding:0;">أو انسخ الرابط التالي وافتحه يدويًا:</p>
                <p style="Margin:0; padding:0;">
                  <a href="<?= esc($ctaUrl) ?>" style="color: <?= $brandBlue ?>; text-decoration:none; word-break:break-all;"><?= esc($ctaUrl) ?></a>
                </p>
              </td>
            </tr>
          <?php endif; ?>

          <!-- فاصل -->
          <tr>
            <td align="center" style="padding:16px 24px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top:1px solid #eeeeee; height:1px; line-height:1px; font-size:0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- فوتر محاذي يمين -->
          <tr>
            <td align="right" dir="rtl" style="padding:0 24px 24px 24px; text-align:right; line-height:1.35;">
              <p style="Margin:0; font-size:13px; color:#999999;">
                إن كانت لديك أي استفسارات، راسل فريق الدعم:
                <a href="mailto:support@howplatform.net" style="color:#777777; text-decoration:none;">support@howplatform.net</a>
              </p>
              <p style="Margin:0; font-size:12px; color:#aaaaaa;">هذا البريد أُرسل تلقائيًا، لا ترد عليه.</p>
            </td>
          </tr>
        </table>
        <!-- /البطاقة -->
      </td>
    </tr>
  </table>
</body>
</html>
