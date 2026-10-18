// ======================================================================
//  قسم فَطين الكامل (SPA) — ينقل نافذة فَطين (#chatbot) إلى منطقة المحتوى
//  ليعمل كصفحة كاملة مثل بقية أقسام الموقع، ويعيدها إلى body عند المغادرة.
//  كل منطق الدردشة/السجل يبقى في FateenChatBot.js (نفس العقدة، نفس المستمعين).
// ======================================================================

function mount(container) {
  const host =
    (container && container.querySelector('#fateen-section-host')) ||
    document.getElementById('fateen-section-host');
  const chatbot = document.getElementById('chatbot');
  const initiator = document.getElementById('chat-initiator');

  if (!host || !chatbot) return;

  // أخفِ زر "اسأل فَطين" العائم ما دمنا داخل القسم
  if (initiator) initiator.style.display = 'none';
  document.body.classList.remove('no-scroll');

  // وضع القسم الكامل (تخطيط عمودين: السجل + الدردشة)
  chatbot.classList.remove('maximized', 'show-history');
  chatbot.classList.add('active', 'in-section');

  // انقل العقدة نفسها (تحافظ على كل مستمعي الأحداث والحالة)
  host.appendChild(chatbot);

  // حدّث قائمة السجل إن توفّرت الدالة
  if (typeof window.__fateenRenderHistory === 'function') {
    try { window.__fateenRenderHistory(); } catch (e) {}
  }
}

function unmount() {
  const chatbot = document.getElementById('chatbot');
  const initiator = document.getElementById('chat-initiator');

  if (chatbot) {
    chatbot.classList.remove('in-section', 'active', 'maximized', 'show-history');
    // أعِد العقدة إلى body
    document.body.appendChild(chatbot);

    // لو طُلب التصغير: افتح النافذة العائمة بدل إغلاقها
    if (window.__fateenWantFloating) {
      window.__fateenWantFloating = false;
      chatbot.classList.add('active');
      if (initiator) initiator.style.display = 'none';
      return;
    }
  }
  if (initiator) initiator.style.display = 'flex';
}

export function init() { mount(); }
export default { mount, unmount };
