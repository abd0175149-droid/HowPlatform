/**
 * Modern Survey Alumni Script - Responsive + Slider Bubble Fix (RTL-aware)
 */
class ModernSurvey {
  constructor() {
    this.currentStep = 1;
    this.totalSteps = 9;
    this.isSubmitting = false;

    // Elements
    this.form = document.getElementById('alumniSurveyForm');
    this.steps = document.querySelectorAll('.survey-step');
    this.prevBtn = document.getElementById('prevBtn');
    this.nextBtn = document.getElementById('nextBtn');
    this.submitBtn = document.getElementById('submitBtn');

    // Progress
    this.progressFill = document.querySelector('.progress-fill');
    this.progressStepText = document.getElementById('progressStep');
    this.progressTotalText = document.getElementById('progressTotal');

    // UI
    this.loadingOverlay = document.getElementById('loadingOverlay');
    this.toastContainer = document.getElementById('toastContainer');

    // For resize observers
    this._sliderObservers = [];

    this.init();
  }

  init() {
    window.modernSurvey = this;
    if (this.progressTotalText) this.progressTotalText.textContent = this.totalSteps;

    this.setupEventListeners();
    this.buildRatingScales();
    this.setupChipLimits();
    this.setupConsentGate();
    this.setupSliders();              // <-- يشمل تموضع الفقاعة بدقة
    this.updateProgress();
    this.updateNavigation();
    this.initAutoSave();

    if (window.lucide) lucide.createIcons();

    this.validateCurrentStep();
    this.updateNextDisabledByValidity();
  }

  /* -------------------- Events -------------------- */
  setupEventListeners() {
    this.prevBtn.addEventListener('click', () => this.previousStep());
    this.nextBtn.addEventListener('click', () => this.nextStep());
    this.submitBtn.addEventListener('click', (e) => this.handleSubmit(e));

    document.querySelectorAll('.step-dot').forEach((dot, index) => {
      dot.addEventListener('click', () => this.goToStep(index + 1));
    });

    this.form.addEventListener('submit', (e) => this.handleSubmit(e));

    document.querySelectorAll('input[name="consent"]').forEach(radio => {
      radio.addEventListener('change', () => this.handleConsentChange());
    });

    const validityHandler = () => {
      this.validateCurrentStep();
      this.updateNextDisabledByValidity();
    };
    this.form.addEventListener('input', validityHandler);
    this.form.addEventListener('change', validityHandler);

    // Reposition slider bubbles on window resize / orientation change
    window.addEventListener('resize', () => this._repositionAllBubbles());
  }

  /* -------------------- Builders -------------------- */
  buildRatingScales() {
    document.querySelectorAll('.rating-scale[data-name]').forEach(scale => {
      const name = scale.dataset.name;
      if (!name) return;
      scale.innerHTML = '';
      for (let i = 1; i <= 5; i++) {
        const label = document.createElement('label');
        label.innerHTML = `
          <input type="radio" name="${name}" value="${i}">
          <div class="rating-option"><span>${i}</span></div>
        `;
        scale.appendChild(label);
      }
    });
  }

  setupChipLimits() {
    document.querySelectorAll('.limit-selection').forEach(container => {
      const max = parseInt(container.dataset.max || '0', 10);
      const inputs = container.querySelectorAll('input[type="checkbox"]');
      let counter = null;
      const next = container.nextElementSibling;
      if (next && next.classList.contains('selection-counter')) counter = next;

      const updateSelection = () => {
        const selected = Array.from(inputs).filter(i => i.checked);
        if (counter) counter.textContent = `${selected.length}/${max} محدد`;
        if (max > 0 && selected.length >= max) {
          inputs.forEach(i => { if (!i.checked) { i.disabled = true; i.setAttribute('aria-disabled','true'); }});
        } else {
          inputs.forEach(i => { i.disabled = false; i.removeAttribute('aria-disabled'); });
        }
      };

      inputs.forEach(input => input.addEventListener('change', updateSelection));
      updateSelection();
    });
  }

  setupConsentGate() { this.handleConsentChange(); }

  handleConsentChange() {
    const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
    const isAgreed = consentValue === '1';

    const gatedSteps = Array.from(this.steps).slice(1);
    gatedSteps.forEach(step => {
      const inputs = step.querySelectorAll('input, select, textarea, button');
      inputs.forEach(el => { el.disabled = !isAgreed; });
      step.style.opacity = isAgreed ? '1' : '0.5';
      step.style.pointerEvents = isAgreed ? 'auto' : 'none';
    });

    this.updateNavigation();
    this.updateNextDisabledByValidity();

    if (consentValue === '0') this.showToast('لا يمكن متابعة الاستبيان بدون الموافقة', 'warning');
  }

  /* -------------------- Sliders (bubble exact position) -------------------- */
  setupSliders() {
    // Clean previous observers
    this._sliderObservers.forEach(obs => obs.disconnect?.());
    this._sliderObservers = [];

    const pairs = [
      { slider: document.getElementById('usage_probability'), bubble: document.getElementById('usageValue') },
      { slider: document.getElementById('recommend_likelihood'), bubble: document.getElementById('recommendValue') },
    ];

    pairs.forEach(({slider, bubble}) => {
      if (!(slider && bubble)) return;

      const update = () => this._positionBubble(slider, bubble);
      slider.addEventListener('input', update);
      slider.addEventListener('change', update);

      // Observe size changes to keep alignment perfect
      const obs = new ResizeObserver(() => update());
      obs.observe(slider);
      obs.observe(slider.parentElement); // .slider-container
      this._sliderObservers.push(obs);

      update(); // initial
    });
  }

  _positionBubble(slider, bubble) {
    const min = parseFloat(slider.min) || 0;
    const max = parseFloat(slider.max) || 100;
    const val = parseFloat(slider.value);

    // نسبة التقدم
    let percent = (val - min) / (max - min);
    percent = Math.max(0, Math.min(1, percent));

    // إن كان الاتجاه RTL وبعض المتصفحات تعكس مسار السلايدر بصريًا
    const isRTL =
      getComputedStyle(slider).direction === 'rtl' ||
      getComputedStyle(slider.parentElement).direction === 'rtl' ||
      document.documentElement.dir === 'rtl';
    const visualPercent = isRTL ? (1 - percent) : percent;

    // مقاسات دقيقة
    const sliderRect = slider.getBoundingClientRect();
    const containerRect = slider.parentElement.getBoundingClientRect();

    // نفس قياس الـ thumb في CSS
    const THUMB = 24;

    // موقع مركز الـ thumb على المسار
    const xWithinSlider = visualPercent * (sliderRect.width - THUMB) + THUMB / 2;

    // نحوله إلى إحداثي داخل الحاوية (relative to .slider-container)
    const leftPx = (sliderRect.left - containerRect.left) + xWithinSlider;

    // حواف أمنة: لا تخرج الفقاعة خارج الحاوية
    const leftClamped = Math.max(THUMB / 2, Math.min(containerRect.width - THUMB / 2, leftPx));

    bubble.textContent = String(val);
    bubble.style.left = `${leftClamped}px`;
  }

  _repositionAllBubbles() {
    // يعيد تموضع كل الفقاعات عند تغيير الحجم
    const pairs = [
      { slider: document.getElementById('usage_probability'), bubble: document.getElementById('usageValue') },
      { slider: document.getElementById('recommend_likelihood'), bubble: document.getElementById('recommendValue') },
    ];
    pairs.forEach(({slider, bubble}) => {
      if (slider && bubble) this._positionBubble(slider, bubble);
    });
  }

  /* -------------------- Validation & Nav -------------------- */
  validateCurrentStep() {
    const stepEl = this.steps[this.currentStep - 1];
    if (!stepEl) return true;

    const requiredInputs = stepEl.querySelectorAll('[required]');
    let isValid = true;

    stepEl.querySelectorAll('[aria-invalid="true"]').forEach(el => el.setAttribute('aria-invalid', 'false'));

    requiredInputs.forEach(input => {
      let ok = true;
      if (input.type === 'radio') {
        const group = stepEl.querySelectorAll(`[name="${input.name}"]`);
        ok = Array.from(group).some(r => r.checked);
        group.forEach(r => r.setAttribute('aria-invalid', ok ? 'false' : 'true'));
      } else {
        ok = !!String(input.value || '').trim();
        input.setAttribute('aria-invalid', ok ? 'false' : 'true'));
      }
      if (!ok) isValid = false;
    });

    return isValid;
  }

  updateNextDisabledByValidity() {
    if (this.currentStep < this.totalSteps) {
      if (this.currentStep === 1) {
        const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
        this.nextBtn.disabled = consentValue !== '1';
        return;
      }
      const hasRequired = this.steps[this.currentStep - 1].querySelector('[required]') !== null;
      this.nextBtn.disabled = hasRequired ? !this.validateCurrentStep() : false;
    } else {
      this.nextBtn.disabled = false;
    }
  }

  updateProgress() {
    const dots = document.querySelectorAll('.step-dot');
    dots.forEach((dot, idx) => {
      dot.classList.remove('active', 'completed');
      dot.removeAttribute('aria-current');
      if (idx + 1 === this.currentStep) {
        dot.classList.add('active'); dot.setAttribute('aria-current', 'step');
      } else if (idx + 1 < this.currentStep) {
        dot.classList.add('completed');
      }
    });

    const pct = Math.round((this.currentStep / this.totalSteps) * 100);
    if (this.progressFill) {
      this.progressFill.style.setProperty('--progress', pct + '%');
      this.progressFill.style.width = pct + '%';
    }
    if (this.progressStepText) this.progressStepText.textContent = this.currentStep;
  }

  updateNavigation() {
    const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
    const isAgreed = consentValue === '1';

    this.prevBtn.disabled = this.currentStep === 1;

    if (this.currentStep === this.totalSteps) {
      this.nextBtn.style.display = 'none';
      this.submitBtn.classList.add('show'); // يظهر زر الإرسال
    } else {
      this.nextBtn.style.display = 'inline-flex';
      this.submitBtn.classList.remove('show');
    }

    if (this.currentStep === 1) this.nextBtn.disabled = !isAgreed;
  }

  goToStep(stepNumber) {
    if (stepNumber < 1 || stepNumber > this.totalSteps) return;

    const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
    if (stepNumber > 1 && consentValue !== '1') {
      this.showToast('يجب الموافقة أولاً للمتابعة', 'warning');
      return;
    }

    this.steps[this.currentStep - 1]?.classList.remove('active');
    this.currentStep = stepNumber;
    this.steps[this.currentStep - 1]?.classList.add('active');

    this.updateProgress();
    this.updateNavigation();
    this.updateNextDisabledByValidity();

    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (window.lucide) lucide.createIcons();

    // عند تبديل الخطوات، أعد تموضع فقاعات السلايدر الظاهرة
    this._repositionAllBubbles();
  }

  nextStep() {
    if (this.currentStep >= this.totalSteps) return;

    if (!this.validateCurrentStep()) {
      this.showToast('يرجى إكمال جميع الحقول المطلوبة في هذه الخطوة', 'error');
      const stepEl = this.steps[this.currentStep - 1];
      const firstInvalid = stepEl.querySelector('[aria-invalid="true"]');
      if (firstInvalid && typeof firstInvalid.focus === 'function') firstInvalid.focus({ preventScroll: false });
      return;
    }
    this.goToStep(this.currentStep + 1);
  }

  previousStep() {
    if (this.currentStep <= 1) return;
    this.goToStep(this.currentStep - 1);
  }

  /* -------------------- Submit -------------------- */
  async handleSubmit(e) {
    e.preventDefault();
    if (this.isSubmitting) return;

    const honeypot = document.querySelector('.honey-pot');
    if (honeypot && honeypot.value.trim() !== '') return;

    const consentValue = document.querySelector('input[name="consent"]:checked')?.value;
    if (consentValue !== '1') {
      this.showToast('يجب الموافقة على المشاركة لإرسال الاستبيان', 'error');
      this.goToStep(1);
      return;
    }

    if (!this.form.reportValidity()) {
      this.showToast('يرجى التحقق من جميع الحقول المطلوبة', 'error');
      return;
    }

    if (!window.fetch) { this.form.submit(); return; }

    this.isSubmitting = true;
    this.showLoading(true);

    try {
      const formData = new FormData(this.form);
      const response = await fetch(this.form.action, {
        method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const newToken = response.headers.get('X-CSRF-TOKEN');
      if (newToken) window.CSRF_TOKEN = newToken;

      let result;
      const ct = response.headers.get('content-type') || '';
      result = ct.includes('application/json') ? await response.json() : { ok: response.ok };

      if (response.ok && result.ok !== false) {
        this.showToast(result.message || 'تم إرسال الاستبيان بنجاح! شكراً لمشاركتكم 🎉', 'success');
        this.clearAutoSave();
        if (result.redirect) setTimeout(()=>{ window.location.href = result.redirect; }, 1200);
        else setTimeout(()=>{ this.resetForm(); }, 1500);
      } else {
        this.showToast(result.message || `حدث خطأ أثناء إرسال الاستبيان (${response.status})`, 'error');
      }
    } catch (err) {
      console.error(err);
      let msg = 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.';
      if (err?.name === 'TypeError' && String(err.message||'').includes('fetch')) msg = 'خطأ في الاتصال بالشبكة. يرجى التحقق من الإنترنت.';
      else if (err?.name === 'AbortError') msg = 'انتهت مهلة الطلب. يرجى المحاولة مرة أخرى.';
      this.showToast(msg, 'error');
    } finally {
      this.isSubmitting = false;
      this.showLoading(false);
    }
  }

  resetForm() {
    this.form.reset();
    this.goToStep(1);
    this.buildRatingScales();
    this.setupChipLimits();
    this.setupSliders();
    this.handleConsentChange();
    this.updateNextDisabledByValidity();
  }

  /* -------------------- Helpers -------------------- */
  showLoading(show) { this.loadingOverlay?.classList.toggle('show', !!show); }

  showToast(message, type='info') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    this.toastContainer.appendChild(toast);
    setTimeout(()=>toast.classList.add('show'), 30);
    setTimeout(()=>{
      toast.classList.remove('show');
      setTimeout(()=>toast.remove(), 300);
    }, 5000);
  }

  /* -------------------- Auto Save -------------------- */
  initAutoSave() {
    if (!window.localStorage) return;
    const KEY = 'how_survey_autosave';

    try {
      const saved = localStorage.getItem(KEY);
      if (saved) this.loadFormData(JSON.parse(saved));
    } catch(e){ console.warn('Failed to load auto-save', e); }

    let t;
    const trigger = (d)=>{ clearTimeout(t); t = setTimeout(()=>this.autoSave(), d); };
    this.form.addEventListener('change', ()=>trigger(600));
    this.form.addEventListener('input', ()=>trigger(900));
  }
  autoSave() {
    if (!window.localStorage) return;
    try { localStorage.setItem('how_survey_autosave', JSON.stringify(this.getFormData())); }
    catch(e){ console.warn('Failed to auto-save', e); }
  }
  clearAutoSave() { try { localStorage.removeItem('how_survey_autosave'); } catch(e){} }
  getFormData() {
    const data = {};
    const fd = new FormData(this.form);
    for (const [k,v] of fd.entries()) {
      if (k.includes('[]')) {
        const ck = k.replace('[]',''); if (!data[ck]) data[ck]=[]; data[ck].push(v);
      } else if (k !== 'csrf_test_name' && !k.includes('website')) {
        data[k]=v;
      }
    }
    return data;
  }
  loadFormData(data) {
    Object.entries(data).forEach(([k,v])=>{
      if (Array.isArray(v)) {
        v.forEach(val=>{
          const input = this.form.querySelector(`[name="${k}[]"][value="${val}"]`);
          if (input) input.checked = true;
        });
      } else {
        const input = this.form.querySelector(`[name="${k}"]`);
        if (input) {
          if (input.type === 'radio' || input.type === 'checkbox') {
            const ii = this.form.querySelector(`[name="${k}"][value="${v}"]`);
            if (ii) ii.checked = true;
          } else { input.value = v; }
        }
      }
    });
    this.setupChipLimits();
    this.setupSliders();
    this.handleConsentChange();
    this.updateNextDisabledByValidity();
  }
}

/* Boot */
document.addEventListener('DOMContentLoaded', ()=>{ new ModernSurvey(); });
document.addEventListener('visibilitychange', ()=>{
  if (document.visibilityState === 'hidden' && window.modernSurvey) window.modernSurvey.autoSave();
});
