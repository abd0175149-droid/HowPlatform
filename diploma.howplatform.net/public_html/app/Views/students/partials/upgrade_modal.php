<style id="upgrade-modal-style">
  /* ✅ ستايل محصور داخل #upgradeModal بمتغيرات upg- لمنع أي تداخل عام */
  #upgradeModal{
    --upg-primary: #219ebc;
    --upg-primary-dark: #0f6f87;
    --upg-primary-darker: #023047;
    --upg-accent: #fd9e02;
    --upg-accent-dark: #fb8500;
    --upg-surface-0: #fff;
    --upg-surface-1: #f8f9fa;
    --upg-surface-2: #e9ecef;
    --upg-surface-3: #dee2e6;
    --upg-text: #212529;
    --upg-success: #2a9d8f;
    --upg-bronze: #cd7f32;
    --upg-silver: #a1a1a1;
    --upg-gold: #e6be8a;
    --upg-card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    --upg-transition: all 0.3s ease;
  }
  
  #upgradeModal.upgrade-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(6px);
    font-family: "Almarai", sans-serif;
    animation: upg-fadeIn 0.3s ease;
  }
  
  #upgradeModal.upgrade-modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  #upgradeModal .upgrade-modal-content {
    background: var(--upg-surface-0);
    border-radius: 24px;
    width: 90%;
    max-width: 1200px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    transform: translateY(30px) scale(0.96);
    opacity: 0;
    transition: transform 0.4s ease, opacity 0.4s ease;
  }
  
  #upgradeModal.upgrade-modal.show .upgrade-modal-content {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
  
  #upgradeModal .upgrade-modal-header {
    background: linear-gradient(135deg, var(--upg-primary-darker), var(--upg-primary-dark));
    color: #fff;
    padding: 28px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top-left-radius: 24px;
    border-top-right-radius: 24px;
    position: relative;
    overflow: hidden;
  }
  
  #upgradeModal .upgrade-modal-header::before {
    content: "";
    position: absolute;
    inset: 0;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="20" cy="20" r="5" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="8" fill="rgba(255,255,255,0.1)"/><circle cx="100" cy="30" r="4" fill="rgba(255,255,255,0.1)"/></svg>');
    opacity: 0.6;
    pointer-events: none;
  }
  
  #upgradeModal .upgrade-modal-header h2 {
    margin: 0;
    font-size: 28px;
    display: flex;
    gap: 16px;
    align-items: center;
    font-weight: 800;
  }
  
  #upgradeModal .close-modal {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: #fff;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--upg-transition);
    z-index: 2;
  }
  
  #upgradeModal .close-modal:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
  }
  
  #upgradeModal .upgrade-modal-body {
    padding: 32px;
    background: var(--upg-surface-1);
    max-height: calc(90vh - 150px);
    overflow-y: auto;
  }
  
  #upgradeModal .upgrade-modal-body::-webkit-scrollbar {
    width: 10px;
  }
  
  #upgradeModal .upgrade-modal-body::-webkit-scrollbar-thumb {
    background: var(--upg-primary);
    border-radius: 5px;
  }
  
  #upgradeModal .upg-plans {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-bottom: 40px;
  }
  
  #upgradeModal .upg-card {
    background: linear-gradient(145deg, #ffffff 0%, #f9f9f9 100%);
    border-radius: 20px;
    padding: 32px;
    box-shadow: var(--upg-card-shadow);
    transition: var(--upg-transition);
    display: flex;
    flex-direction: column;
    height: 100%;
    position: relative;
    overflow: hidden;
    border: 1px solid transparent;
  }
  
  #upgradeModal .upg-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
  }
  
  #upgradeModal .upg-card.bronze {
    border-top: 5px solid var(--upg-bronze);
  }
  
  #upgradeModal .upg-card.silver {
    border-top: 5px solid var(--upg-silver);
  }
  
  #upgradeModal .upg-card.gold {
    border-top: 5px solid var(--upg-gold);
  }
  
  #upgradeModal .upg-header {
    text-align: center;
    margin-bottom: 24px;
    position: relative;
  }
  
  #upgradeModal .upg-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 80px;
    opacity: 0.1;
    z-index: 0;
  }
  
  #upgradeModal .upg-card.bronze::before {
    background-color: var(--upg-bronze);
  }
  
  #upgradeModal .upg-card.silver::before {
    background-color: var(--upg-silver);
  }
  
  #upgradeModal .upg-card.gold::before {
    background-color: var(--upg-gold);
  }
  
  #upgradeModal .upg-name {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 8px;
    color: var(--upg-primary-darker);
    position: relative;
    z-index: 1;
  }
  
  #upgradeModal .upg-duration {
    color: #666;
    font-size: 16px;
    position: relative;
    z-index: 1;
  }
  
  #upgradeModal .upg-price {
    text-align: center;
    margin-bottom: 28px;
    padding: 16px;
    background: rgba(33, 158, 188, 0.05);
    border-radius: 16px;
    position: relative;
    z-index: 1;
  }
  
  #upgradeModal .upg-price-amt {
    font-size: 42px;
    font-weight: 800;
    line-height: 1;
    color: var(--upg-primary-darker);
  }
  
  #upgradeModal .upg-price-cur {
    font-size: 18px;
    font-weight: 600;
    color: #666;
  }
  
  #upgradeModal .upg-features {
    flex-grow: 1;
    margin-bottom: 28px;
    position: relative;
    z-index: 1;
  }
  
  #upgradeModal .upg-feature {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
    font-size: 16px;
    padding: 8px 0;
    border-bottom: 1px dashed #eee;
  }
  
  #upgradeModal .upg-feature:last-child {
    border-bottom: none;
  }
  
  #upgradeModal .upg-feature i {
    margin-left: 12px;
    font-size: 18px;
    width: 24px;
    text-align: center;
  }
  
  #upgradeModal .bronze .upg-feature i {
    color: var(--upg-bronze);
  }
  
  #upgradeModal .silver .upg-feature i {
    color: var(--upg-silver);
  }
  
  #upgradeModal .gold .upg-feature i {
    color: var(--upg-gold);
  }
  
  #upgradeModal .upg-actions {
    text-align: center;
    margin-top: auto;
    position: relative;
    z-index: 1;
  }
  
  #upgradeModal .upg-btn {
    color: white;
    border: none;
    padding: 16px 32px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    width: 100%;
    transition: var(--upg-transition);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
  }
  
  #upgradeModal .upg-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
  }
  
  #upgradeModal .bronze .upg-btn {
    background: linear-gradient(135deg, var(--upg-bronze), #b3691f);
  }
  
  #upgradeModal .silver .upg-btn {
    background: linear-gradient(135deg, var(--upg-silver), #8a8a8a);
  }
  
  #upgradeModal .gold .upg-btn {
    background: linear-gradient(135deg, var(--upg-gold), #d4af37);
  }
  
  #upgradeModal .features-section {
    background: var(--upg-surface-0);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 32px;
    box-shadow: var(--upg-card-shadow);
  }
  
  #upgradeModal .section-title {
    font-size: 24px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--upg-surface-2);
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--upg-primary-darker);
    font-weight: 700;
  }
  
  #upgradeModal .features-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
  }
  
  #upgradeModal .feature-card {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
    background: var(--upg-surface-1);
    border-radius: 16px;
    transition: var(--upg-transition);
  }
  
  #upgradeModal .feature-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--upg-card-shadow);
  }
  
  #upgradeModal .feature-icon {
    background: rgba(33, 158, 188, 0.1);
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  
  #upgradeModal .feature-icon i {
    color: var(--upg-primary);
    font-size: 28px;
  }
  
  #upgradeModal .feature-content h4 {
    margin-bottom: 8px;
    font-size: 18px;
    color: var(--upg-primary-darker);
  }
  
  #upgradeModal .feature-content p {
    font-size: 16px;
    color: #666;
    line-height: 1.5;
  }
  
  #upgradeModal .upgrade-modal-footer {
    padding: 24px 32px;
    background: var(--upg-surface-0);
    border-top: 1px solid var(--upg-surface-2);
    border-bottom-left-radius: 24px;
    border-bottom-right-radius: 24px;
    display: flex;
    justify-content: flex-end;
  }
  
  #upgradeModal .btn-cancel {
    background: var(--upg-surface-2);
    color: var(--upg-text);
    border: none;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    margin-left: 16px;
    transition: var(--upg-transition);
  }
  
  #upgradeModal .btn-cancel:hover {
    background: var(--upg-surface-3);
  }
  
  /* أنيميشن */
  @keyframes upg-fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  
  /* Responsive */
  @media (max-width: 992px) {
    #upgradeModal .upg-plans {
      grid-template-columns: 1fr;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }
    
    #upgradeModal .features-grid {
      grid-template-columns: 1fr;
    }
  }
  
  @media (max-width: 768px) {
    #upgradeModal .upgrade-modal-content {
      width: 95%;
      margin: 10px;
    }
    
    #upgradeModal .upgrade-modal-header {
      padding: 24px;
    }
    
    #upgradeModal .upgrade-modal-header h2 {
      font-size: 24px;
    }
    
    #upgradeModal .upgrade-modal-body {
      padding: 24px;
    }
    
    #upgradeModal .upg-card {
      padding: 24px;
    }
    
    #upgradeModal .upg-price-amt {
      font-size: 36px;
    }
    
    #upgradeModal .features-section {
      padding: 24px;
    }
  }
</style>

<div id="upgradeModal" class="upgrade-modal" aria-hidden="true">
  <div class="upgrade-modal-content" role="dialog" aria-modal="true" aria-labelledby="upgradeModalTitle">
    <div class="upgrade-modal-header">
      <h2 id="upgradeModalTitle"><i class="fas fa-crown"></i> ترقية إلى حساب مميز</h2>
      <button class="close-modal" type="button" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="upgrade-modal-body">
      <div class="upg-plans" data-slot="plans"></div>
      <div class="features-section">
        <h3 class="section-title"><i class="fas fa-star"></i> ماذا يشمل كل اشتراك؟</h3>
        <div class="features-grid">
          <div class="feature-card"><div class="feature-icon"><i class="fas fa-brain"></i></div><div class="feature-content"><h4>ذكاء اصطناعي غير محدود</h4><p>استخدم الذكاء الاصطناعي بدون قيود لجميع احتياجاتك التعليمية</p></div></div>
          <div class="feature-card"><div class="feature-icon"><i class="fas fa-file-alt"></i></div><div class="feature-content"><h4>ملفات حصرية</h4><p>احصل على ملفات المهام الحصرية والشروحات الإضافية</p></div></div>
          <div class="feature-card"><div class="feature-icon"><i class="fas fa-tools"></i></div><div class="feature-content"><h4>أدوات تعليمية</h4><p>اختبارات تفاعلية، جداول، وسقوف أعلى في الباقات</p></div></div>
          <div class="feature-card"><div class="feature-icon"><i class="fas fa-headset"></i></div><div class="feature-content"><h4>دعم فني مفاضل</h4><p>أولوية في الدعم الفني وفق نوع الباقة</p></div></div>
        </div>
      </div>
    </div>
    <div class="upgrade-modal-footer">
      <button class="btn-cancel" type="button">إلغاء</button>
    </div>
  </div>
</div>