<?php
$session  = session();
$authUser = (array) ($session->get('auth_user') ?? []);
$uid      = (int) ($authUser['id'] ?? $session->get('user_id') ?? 0);
$uname    = (string) ($authUser['name'] ?? $session->get('user_name') ?? 'طالب');
?>
<style>
  /* ============ صفحة طلبات الخدمة — إعادة بناء (NotebookLM flat) ============ */
  /* ثبّت ارتفاع منطقة المحتوى لهذه الصفحة فقط فتملأ الشاشة بلا فجوة */
  .content-area:has(#svc-root) { height: calc(100vh - var(--navbar-height)); min-height: 0; overflow: hidden; padding-top: 0; padding-bottom: 0; }
  .svc { --rail: 272px; box-sizing: border-box; display: grid; grid-template-columns: var(--rail) 1fr; gap: 12.8px;
         height: 100%; padding: 6.4px 1.6px; }
  .svc * { box-sizing: border-box; }

  /* ---------- اللوحة اليمنى: قائمة الطلبات ---------- */
  .svc-aside { background: var(--surface-0); border: 0.8px solid var(--border-color);
               border-radius: var(--border-radius); display: flex; flex-direction: column; overflow: hidden; }
  .svc-aside-head { padding: 12.8px; border-bottom: 0.8px solid var(--border-color); }
  .svc-aside-title { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 9.6px; }
  .svc-aside-title h2 { margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-strong);
                        display: flex; align-items: center; gap: 6.4px; }
  .svc-aside-title h2 i { color: var(--primary-500); }
  .svc-new-btn { background: var(--accent-500); color: #fff; border: none; border-radius: 799.2px;
                 padding: 6.4px 11.2px; font-weight: 700; font-size: .85rem; cursor: pointer;
                 display: inline-flex; align-items: center; gap: 4.8px; transition: background .16s; white-space: nowrap; }
  .svc-new-btn:hover { background: var(--accent-600); }

  .svc-search { position: relative; }
  .svc-search i { position: absolute; inset-inline-start: 9.6px; top: 50%; transform: translateY(-50%);
                  color: var(--text-muted); font-size: .85rem; }
  .svc-search input { width: 100%; padding: 7.2px 9.6px 7.2px 27.2px; border: 0.8px solid var(--border-color);
                      border-radius: 799.2px; background: var(--surface-1); color: var(--text-strong); font: inherit; font-size: .9rem; }
  .svc-search input:focus { outline: none; border-color: var(--primary-400); box-shadow: var(--focus-ring); }

  .svc-tabs { display: flex; flex-wrap: wrap; gap: 4.8px; padding: 8px 11.2px; border-bottom: 0.8px solid var(--border-color); }
  .svc-tab { background: var(--surface-1); border: 0.8px solid var(--border-color); color: var(--text-muted);
             border-radius: 799.2px; padding: 4px 8.8px; font-weight: 700; font-size: .78rem; cursor: pointer; white-space: nowrap;
             display: inline-flex; align-items: center; gap: 4px; transition: .15s; }
  .svc-tab.active { background: var(--primary-50); color: var(--primary-700); border-color: transparent; }
  .svc-tab .cnt { background: var(--surface-2); border-radius: 799.2px; padding: 0 5.6px; font-size: .72rem; }
  .svc-tab.active .cnt { background: var(--primary-100); color: var(--primary-700); }

  .svc-list { flex: 1; overflow-y: auto; padding: 6.4px; }
  .svc-item { padding: 9.6px; border-radius: 9.6px; border: 0.8px solid transparent; cursor: pointer; transition: .15s; margin-bottom: 3.2px; }
  .svc-item:hover { background: var(--surface-1); }
  .svc-item.active { background: var(--primary-50); border-color: var(--primary-200); }
  .svc-item-top { display: flex; align-items: center; justify-content: space-between; gap: 6.4px; }
  .svc-item-subj { font-weight: 700; color: var(--text-strong); font-size: .95rem; line-height: 1.35;
                   display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
  .svc-item-time { font-size: .72rem; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }
  .svc-item-sub { display: flex; align-items: center; gap: 6.4px; margin-top: 4.8px; }
  .svc-item-course { font-size: .76rem; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 1;
                     -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
  .svc-unread { background: var(--error-500); color: #fff; border-radius: 799.2px; min-width: 14.4px; height: 14.4px;
                padding: 0 4px; font-size: .7rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
  .svc-status { font-size: .7rem; font-weight: 700; padding: 1.6px 7.2px; border-radius: 799.2px; border: 0.8px solid transparent; }
  .st-pending     { background: var(--warning-soft); color: var(--warning-600); border-color: var(--warning-border); }
  .st-in_progress { background: var(--info-soft);    color: var(--info-600);    border-color: var(--info-border); }
  .st-resolved    { background: var(--success-soft); color: var(--success-600); border-color: var(--success-border); }
  .st-closed      { background: var(--surface-2);    color: var(--text-muted);  border-color: var(--border-color); }
  .st-cancelled   { background: var(--error-soft);   color: var(--error-600);   border-color: var(--error-border); }

  /* ---------- اللوحة اليسرى: المحادثة ---------- */
  .svc-main { background: var(--surface-0); border: 0.8px solid var(--border-color); border-radius: var(--border-radius);
              display: flex; flex-direction: column; overflow: hidden; position: relative; }
  .svc-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
               text-align: center; color: var(--text-muted); padding: 24px; gap: 6.4px; }
  .svc-empty .ic { width: 57.6px; height: 57.6px; border-radius: 17.6px; background: var(--primary-50); color: var(--primary-500);
                   display: grid; place-items: center; font-size: 1.9rem; margin-bottom: 6.4px; }
  .svc-empty h3 { margin: 0; color: var(--text-strong); font-size: 1.15rem; }

  .svc-chat { display: none; flex-direction: column; height: 100%; }
  .svc-chat.show { display: flex; }
  .svc-chead { display: flex; align-items: center; gap: 9.6px; padding: 11.2px 12.8px; border-bottom: 0.8px solid var(--border-color); }
  .svc-back { display: none; background: var(--surface-1); border: 0.8px solid var(--border-color); color: var(--text-strong);
              width: 28.8px; height: 28.8px; border-radius: 50%; cursor: pointer; align-items: center; justify-content: center; flex-shrink: 0; }
  .svc-chead-info { flex: 1; min-width: 0; }
  .svc-chead-info h3 { margin: 0; font-size: 1.02rem; font-weight: 800; color: var(--text-strong);
                       display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
  .svc-chead-meta { display: flex; align-items: center; gap: 6.4px; margin-top: 2.4px; font-size: .78rem; color: var(--text-muted); flex-wrap: wrap; }
  .svc-chead-actions { display: flex; gap: 4.8px; flex-shrink: 0; }
  .svc-icon-btn { width: 28.8px; height: 28.8px; border-radius: 50%; border: 0.8px solid var(--border-color); background: var(--surface-1);
                  color: var(--text-muted); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: .15s; }
  .svc-icon-btn:hover { border-color: var(--primary-300); color: var(--primary-600); }

  .svc-msgs { flex: 1; overflow-y: auto; padding: 14.4px 12.8px; display: flex; flex-direction: column; gap: 9.6px;
              background: var(--surface-1); scroll-behavior: smooth; }
  .svc-load-older { align-self: center; background: var(--surface-0); border: 0.8px solid var(--border-color);
                    color: var(--text-muted); border-radius: 799.2px; padding: 4px 11.2px; font-size: .8rem; cursor: pointer; }

  .svc-msg { display: flex; flex-direction: column; max-width: 78%; }
  .svc-msg.me { align-self: flex-end; align-items: flex-end; }
  .svc-msg.them { align-self: flex-start; align-items: flex-start; }
  .svc-bubble { border-radius: 11.2px; padding: 8px 10.4px; border: 0.8px solid var(--border-color); background: var(--surface-0); }
  .svc-msg.me .svc-bubble { background: var(--primary-50); border-color: var(--primary-200); }
  .svc-msg.ai .svc-bubble { background: var(--accent-50); border-color: var(--accent-300); }
  [data-theme="dark"] .svc-msg.ai .svc-bubble { background: rgba(253,158,2,.10); border-color: rgba(253,158,2,.35); }
  /* الدارك مود: تصحيح كل خلفية شبه-بيضاء (primary-50/100) على صفحة الخدمة */
  [data-theme="dark"] .svc-msg.me .svc-bubble { background: rgba(43,179,211,.14); border-color: rgba(43,179,211,.30); }
  [data-theme="dark"] .svc-tab.active { background: rgba(43,179,211,.16); color: var(--primary-400); }
  [data-theme="dark"] .svc-tab.active .cnt { background: rgba(43,179,211,.24); color: var(--primary-400); }
  [data-theme="dark"] .svc-empty .ic { background: rgba(43,179,211,.14); color: var(--primary-400); }
  .svc-msg-head { display: flex; align-items: center; gap: 6.4px; margin-bottom: 4px; font-size: .76rem; }
  .svc-sender { font-weight: 800; color: var(--text); }
  .svc-msg.ai .svc-sender { color: var(--accent-600); display: inline-flex; align-items: center; gap: 4px; }
  .svc-time { color: var(--text-muted); }
  .svc-text { margin: 0; color: var(--text); line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
  .svc-files { display: flex; flex-wrap: wrap; gap: 6.4px; margin-top: 6.4px; }
  .svc-file { display: inline-flex; align-items: center; gap: 6.4px; background: var(--surface-1); border: 0.8px solid var(--border-color);
              border-radius: 8px; padding: 5.6px 8.8px; text-decoration: none; color: var(--text); font-size: .82rem; max-width: 184px; }
  .svc-file:hover { border-color: var(--primary-300); }
  .svc-file i { color: var(--primary-500); }
  .svc-file .nm { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .svc-file img.thumb { max-width: 160px; max-height: 160px; border-radius: 8px; display: block; }

  /* المُؤلِّف */
  .svc-composer { border-top: 0.8px solid var(--border-color); padding: 9.6px 11.2px; background: var(--surface-0); }
  .svc-attached { display: flex; flex-wrap: wrap; gap: 6.4px; margin-bottom: 6.4px; }
  .svc-attached:empty { display: none; }
  .svc-chip { display: inline-flex; align-items: center; gap: 4.8px; background: var(--surface-1); border: 0.8px solid var(--border-color);
              border-radius: 799.2px; padding: 3.2px 4.8px 3.2px 8.8px; font-size: .8rem; color: var(--text); }
  .svc-chip button { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: .9rem; line-height: 1; }
  .svc-compose-row { display: flex; align-items: flex-end; gap: 6.4px; }
  .svc-compose-row textarea { flex: 1; resize: none; max-height: 112px; min-height: 35.2px; padding: 8.8px 11.2px;
                              border: 0.8px solid var(--border-color); border-radius: 11.2px; background: var(--surface-1);
                              color: var(--text-strong); font: inherit; font-size: .95rem; line-height: 1.5; }
  .svc-compose-row textarea:focus { outline: none; border-color: var(--primary-400); box-shadow: var(--focus-ring); }
  .svc-attach-btn, .svc-send-btn { width: 35.2px; height: 35.2px; border-radius: 50%; border: none; cursor: pointer; flex-shrink: 0;
                                   display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; transition: .15s; }
  .svc-attach-btn { background: var(--surface-1); border: 0.8px solid var(--border-color); color: var(--text-muted); }
  .svc-attach-btn:hover { border-color: var(--primary-300); color: var(--primary-600); }
  .svc-send-btn { background: var(--primary-500); color: #fff; }
  .svc-send-btn:hover { background: var(--primary-600); }
  .svc-send-btn:disabled { opacity: .55; cursor: not-allowed; }
  .svc-closed-note { text-align: center; color: var(--text-muted); font-size: .85rem; padding: 8px; }

  .svc-state { padding: 24px; text-align: center; color: var(--text-muted); }
  .svc-skel { height: 51.2px; border-radius: 9.6px; margin-bottom: 4.8px;
              background: linear-gradient(90deg, var(--surface-1) 25%, var(--surface-2) 37%, var(--surface-1) 63%);
              background-size: 400% 100%; animation: svcskel 1.3s ease infinite; }
  @keyframes svcskel { 0%{background-position:100% 0} 100%{background-position:0 0} }

  /* تجاوب */
  @media (max-width: 920px) {
    .svc { grid-template-columns: 1fr; height: auto; }
    .svc-aside { height: calc(100vh - var(--navbar-height) - 19.2px); }
    .svc-main { display: none; }
    .svc.in-chat .svc-aside { display: none; }
    .svc.in-chat .svc-main { display: flex; height: calc(100vh - var(--navbar-height) - 19.2px); }
    .svc-back { display: inline-flex; }
    .svc-msg { max-width: 88%; }
  }
</style>

<div class="svc" id="svc-root" data-uid="<?= $uid ?>" data-uname="<?= esc($uname) ?>">
  <!-- قائمة الطلبات -->
  <aside class="svc-aside">
    <div class="svc-aside-head">
      <div class="svc-aside-title">
        <h2><i class="fas fa-headset"></i> طلبات الخدمة</h2>
        <button type="button" class="svc-new-btn" id="svc-new-btn"><i class="fas fa-plus"></i> طلب جديد</button>
      </div>
      <div class="svc-search">
        <i class="fas fa-search"></i>
        <input type="search" id="svc-search" placeholder="ابحث في طلباتك…" autocomplete="off">
      </div>
    </div>
    <div class="svc-tabs" id="svc-tabs"></div>
    <div class="svc-list" id="svc-list">
      <div class="svc-skel"></div><div class="svc-skel"></div><div class="svc-skel"></div>
    </div>
  </aside>

  <!-- المحادثة -->
  <section class="svc-main">
    <div class="svc-empty" id="svc-empty">
      <div class="ic"><i class="fas fa-comments"></i></div>
      <h3>اختر طلباً لعرض المحادثة</h3>
      <p>أو ابدأ <strong>طلب خدمة جديد</strong> وسيردّ عليك «فَطين» مبدئياً ثم يتابعك الفريق الأكاديمي.</p>
    </div>

    <div class="svc-chat" id="svc-chat">
      <div class="svc-chead">
        <button type="button" class="svc-back" id="svc-back"><i class="fas fa-arrow-right"></i></button>
        <div class="svc-chead-info">
          <h3 id="svc-chead-subj">—</h3>
          <div class="svc-chead-meta" id="svc-chead-meta"></div>
        </div>
        <div class="svc-chead-actions">
          <button type="button" class="svc-icon-btn" id="svc-cancel-btn" title="إلغاء الطلب"><i class="fas fa-ban"></i></button>
        </div>
      </div>

      <div class="svc-msgs" id="svc-msgs"></div>

      <div class="svc-composer" id="svc-composer">
        <div class="svc-attached" id="svc-attached"></div>
        <div class="svc-compose-row">
          <button type="button" class="svc-attach-btn" id="svc-attach-btn" title="إرفاق ملف"><i class="fas fa-paperclip"></i></button>
          <input type="file" id="svc-file-input" multiple hidden>
          <textarea id="svc-input" rows="1" placeholder="اكتب رسالتك…"></textarea>
          <button type="button" class="svc-send-btn" id="svc-send-btn" title="إرسال"><i class="fas fa-paper-plane"></i></button>
        </div>
      </div>
    </div>
  </section>
</div>
