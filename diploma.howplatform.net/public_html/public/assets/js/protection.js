// ========================
// 📦 screen_protect.js
// ========================

// ① CSS blur عبر كلاس
function blurContent() {
  document.body.classList.add('blurred');
}

// ② إزالة التمويه
function unblurContent() {
  document.body.classList.remove('blurred');
}

// ③ تفعيل التمويه عند مغادرة الصفحة أو فقدان التركيز
document.addEventListener('visibilitychange', () => {
  if (document.hidden) blurContent();
  else unblurContent();
});

// ④ تفعيل التمويه عند فقدان تركيز النافذة
// window.addEventListener('blur', blurContent);
// window.addEventListener('focus', unblurContent);

// ⑤ منع النقر اليمين ونسخ النصوص
// document.addEventListener('contextmenu', e => e.preventDefault());
// document.addEventListener('selectstart', e => e.preventDefault());
// document.addEventListener('dragstart', e => e.preventDefault());

// ⑥ كشف أدوات المطور (DevTools)
let devToolsOpened = false;
const checkDevTools = () => {
  const threshold = 160;
  if (
    window.outerWidth - window.innerWidth > threshold ||
    window.outerHeight - window.innerHeight > threshold
  ) {
    if (!devToolsOpened) {
      devToolsOpened = true;
      alert('🚫 تم الكشف عن أدوات المطور! يرجى إغلاقها فورًا.');
      // يمكنك حذف أو إخفاء المحتوى إن أردت:
      // document.body.innerHTML = '<h1>🚫 الوصول غير مصرح به</h1>';
    }
  } else {
    devToolsOpened = false;
  }
};
setInterval(checkDevTools, 1000);
document.addEventListener('keydown', function (e) {
  if ((e.shiftKey || e.metaKey) && e.key.toLowerCase() === "s") {
    e.preventDefault(); // منع وظيفة حفظ الصفحة

    alert("🚫 لا يُسمح بحفظ هذه الصفحة أو تصوير محتواها.");
    
    // مثال: تظليل الصفحة مؤقتًا
    document.body.style.filter = "blur(10px)";
    setTimeout(() => {
      document.body.style.filter = "none";
    }, 3000);
  }
});
