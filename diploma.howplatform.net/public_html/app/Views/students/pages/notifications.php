<style>
  .np { max-width: 688px; margin: 0 auto; padding: 1.5rem 0.5rem; display: flex; flex-direction: column; gap: 1.2rem; }
  .np-head { display: flex; align-items: center; justify-content: space-between; gap: 9.6px; flex-wrap: wrap;
    background: var(--surface-0); border: 0.8px solid var(--border-color); border-radius: var(--border-radius); padding: 14.4px 17.6px; }
  .np-head h1 { margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--text-strong); display: flex; align-items: center; gap: 8px; }
  .np-head h1 i { color: var(--primary-500); }
  .np-actions { display: flex; gap: 6.4px; flex-wrap: wrap; }
  .np-btn { background: var(--surface-1); border: 0.8px solid var(--border-color); color: var(--text-strong); border-radius: 799.2px;
    padding: 6.4px 11.2px; font-weight: 700; font-size: .85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 4.8px; transition: .15s; }
  .np-btn:hover { border-color: var(--primary-300); }
  .np-btn.danger:hover { border-color: var(--error-500); color: var(--error-600); }

  .np-list { background: var(--surface-0); border: 0.8px solid var(--border-color); border-radius: var(--border-radius); overflow: hidden; }
  .np-item { display: flex; gap: 11.2px; padding: 12.8px 16px; border-bottom: 0.8px solid var(--border-color); cursor: pointer; transition: background .15s; position: relative; }
  .np-item:last-child { border-bottom: none; }
  .np-item:hover { background: var(--surface-1); }
  .np-item.unread { background: var(--unread-bg); }
  .np-ic { width: 33.6px; height: 33.6px; border-radius: 9.6px; background: var(--primary-50); color: var(--primary-600);
    display: grid; place-items: center; font-size: 1.05rem; flex-shrink: 0; }
  .np-body { flex: 1; min-width: 0; }
  .np-title { font-weight: 700; color: var(--text-strong); display: flex; align-items: center; gap: 6.4px; }
  .np-dot { width: 6.4px; height: 6.4px; border-radius: 50%; background: var(--accent-500); flex-shrink: 0; }
  .np-msg { color: var(--text-muted); font-size: .9rem; margin-top: 3.2px; line-height: 1.55; }
  .np-time { color: var(--text-muted); font-size: .78rem; margin-top: 4.8px; display: inline-flex; align-items: center; gap: 4px; }
  .np-del { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: .95rem; padding: 4.8px; border-radius: 6.4px; align-self: flex-start; flex-shrink: 0; transition: .15s; }
  .np-del:hover { background: var(--error-soft); color: var(--error-600); }

  .np-more { align-self: center; }
  .np-state { padding: 38.4px 16px; text-align: center; color: var(--text-muted); }
  .np-state i { font-size: 2.6rem; color: var(--primary-300); margin-bottom: 9.6px; }
  .np-state h3 { color: var(--text-strong); margin: 0 0 3.2px; font-size: 1.15rem; }
  .np-skel { height: 56px; border-bottom: 0.8px solid var(--border-color);
    background: linear-gradient(90deg, var(--surface-1) 25%, var(--surface-2) 37%, var(--surface-1) 63%); background-size: 400% 100%; animation: npskel 1.3s ease infinite; }
  @keyframes npskel { 0%{background-position:100% 0} 100%{background-position:0 0} }
</style>

<div class="np" id="notif-page">
  <header class="np-head">
    <h1><i class="fas fa-bell"></i> الإشعارات</h1>
    <div class="np-actions">
      <button type="button" class="np-btn" id="np-mark-all"><i class="fas fa-check-double"></i> تعليم الكل كمقروء</button>
      <button type="button" class="np-btn danger" id="np-clear-all"><i class="fas fa-trash"></i> حذف الكل</button>
    </div>
  </header>

  <div class="np-list" id="np-list">
    <div class="np-skel"></div><div class="np-skel"></div><div class="np-skel"></div>
  </div>

  <button type="button" class="np-btn np-more" id="np-more" style="display:none;">تحميل المزيد</button>
</div>
