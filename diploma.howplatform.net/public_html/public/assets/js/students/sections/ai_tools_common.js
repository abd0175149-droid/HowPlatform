/**
 * ai_tools_common.js - JavaScript مشترك لجميع أدوات الذكاء الاصطناعي
 * يحتوي على: الأزرار، الحالة، التحقق، التنزيل
 */

const AI_TOOLS_COMMON = {
  /**
   * تهيئة أداة في root element
   */
  init(root, config) {
    if (!root) return;
    
    root.dataset.typeKey = config.typeKey || '';
    root.dataset.taskId = config.taskId || '';
    root.dataset.userId = config.userId || '';
    
    this.setupButtons(root);
    this.checkStatus(root, { silent: true });
  },

  /**
   * إعداد الأزرار
   */
  setupButtons(root) {
    const openBtn = document.getElementById('btnOpenModal');
    const downloadBtn = document.getElementById('btnDownload');
    const statusBadge = document.getElementById('statusBadge');

    if (openBtn) {
      openBtn.addEventListener('click', () => {
        const typeKey = root.dataset.typeKey;
        if (window.AI_TOOLS && window.AI_TOOLS.openModal) {
          window.AI_TOOLS.openModal(typeKey, root);
        }
      });
    }

    if (downloadBtn) {
      downloadBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const url = downloadBtn.href;
        if (url) {
          window.open(url, '_blank');
        }
      });
    }
  },

  /**
   * التحقق من حالة الطلب
   */
  async checkStatus(root, opts = {}) {
    const { silent = false } = opts;
    const typeKey = root.dataset.typeKey;
    const taskId = root.dataset.taskId;

    if (!typeKey || !taskId) return;

    try {
      const qs = new URLSearchParams({ type_key: typeKey, task_id: taskId });
      const res = await fetch(`/students/ai-tools/status?${qs.toString()}`, {
        credentials: 'include'
      });
      const json = await res.json();

      if (json.ok) {
        this.updateUI(root, json.state, json.download_url);
      }
    } catch (e) {
      if (!silent) console.error('Status check failed:', e);
    }
  },

  /**
   * تحديث واجهة المستخدم حسب الحالة
   */
  updateUI(root, state, downloadUrl) {
    const openBtn = document.getElementById('btnOpenModal');
    const downloadBtn = document.getElementById('btnDownload');
    const statusBadge = document.getElementById('statusBadge');

    switch (state) {
      case 'done':
        if (openBtn) openBtn.disabled = true;
        if (downloadBtn && downloadUrl) {
          downloadBtn.href = downloadUrl;
          downloadBtn.style.display = 'inline-flex';
        }
        if (statusBadge) {
          statusBadge.textContent = 'تم الإنشاء';
          statusBadge.style.display = 'inline-flex';
        }
        break;

      case 'processing':
      case 'pending':
        if (openBtn) openBtn.disabled = true;
        if (statusBadge) {
          statusBadge.textContent = state === 'processing' ? 'قيد المعالجة...' : 'في الانتظار...';
          statusBadge.style.display = 'inline-flex';
        }
        // إعادة التحقق بعد 30 ثانية
        setTimeout(() => this.checkStatus(root, { silent: true }), 30000);
        break;

      case 'failed':
        if (openBtn) openBtn.disabled = false;
        if (statusBadge) {
          statusBadge.textContent = 'فشل سابق';
          statusBadge.style.display = 'inline-flex';
        }
        break;

      default:
        if (openBtn) openBtn.disabled = false;
        if (statusBadge) statusBadge.style.display = 'none';
    }
  },

  /**
   * تنسيق عرض النقاط
   */
  formatPoints(points) {
    if (typeof points !== 'number') return '0';
    return new Intl.NumberFormat('ar-SA').format(points);
  },

  /**
   * جلب رصيد النقاط الحالي من API
   */
  async getUserPoints() {
    try {
      const res = await fetch('/students/rewards/stats', {
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      
      if (!res.ok) {
        const errorText = await res.text().catch(() => '');
        return 0;
      }
      
      const json = await res.json();
      
      if (json.success && typeof json.points === 'number') {
        return json.points;
      }
      
      return 0;
    } catch (e) {
      return 0;
    }
  },

  /**
   * التحقق من حالة الاشتراك
   */
  async checkSubscriptionStatus() {
    try {
      const res = await fetch('/api/subscription/plan', {
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      return {
        active: json.active === true,
        level: json.level || 0,
        plan: json.plan || null
      };
    } catch (e) {
      console.warn('[ai-tools-common] Failed to fetch subscription status:', e);
      return {
        active: false,
        level: 0,
        plan: null
      };
    }
  }
};

// تصدير للاستخدام
if (typeof window !== 'undefined') {
  window.AI_TOOLS_COMMON = AI_TOOLS_COMMON;
}

export default AI_TOOLS_COMMON;

