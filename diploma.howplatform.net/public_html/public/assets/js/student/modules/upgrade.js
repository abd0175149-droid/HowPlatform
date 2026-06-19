// موديول قسم "الترقية/الاشتراكات"
// - يحترم نمطك القياسي: export init()
// - يربط المستمعات بعد التأكد من وجود عناصر DOM
// - سنكمل فيه لاحقًا (جلب الخطط / تحقق الفاوتشر / تفعيل)

export function init() {
  // ✅ نضمن أن العناصر صارت موجودة بعد تحميل الـ HTML
  const MAX_TRIES = 50;
  let tries = 0;
  const timer = setInterval(() => {
    tries++;
    const statusBox = document.getElementById('student-subscription-status');
    const plansList = document.getElementById('plans-list');
    const btnRefresh = document.getElementById('btn-refresh-plans');
    const selectedActions = document.getElementById('selected-plan-actions');

    if (statusBox && plansList && btnRefresh && selectedActions) {
      clearInterval(timer);

      // TODO: الخطوة القادمة — جلب الخطط عبر GET /api/plans وعرضها كبطاقات
      // TODO: تعبئة حالة اشتراك الطالب الحالي من /api/user (إذا متاح) أو من استجابة HTML
      // TODO: ربط زر "احصل على بطاقة" لعرض pos-info
      // TODO: ربط زر "فعِّل البطاقة" لبدء تدفّق التحقق

      // مثال بسيط مؤقت لتمييز التحميل اكتمل:
      statusBox.querySelector('span')?.replaceChildren('جاهز لعرض الاشتراك والخطط.');
    }

    if (tries >= MAX_TRIES) {
      clearInterval(timer);
      console.warn('[upgrade] DOM elements not found in time.');
    }
  }, 100);
}
