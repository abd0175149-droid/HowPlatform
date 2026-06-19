<style>
/* ==================== Rewards Page Styles - Modern Redesign ==================== */

/* متغيرات الألوان */
:root {
    --primary-gradient: linear-gradient(135deg, #ff9800 0%, #ff6b00 100%);
    --primary-gradient-hover: linear-gradient(135deg, #ff6b00 0%, #ff5722 100%);
    --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
    --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    --card-shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.12);
    --card-shadow-active: 0 12px 40px rgba(255, 152, 0, 0.2);
    --border-radius: 20px;
    --border-radius-small: 12px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* الصفحة الرئيسية */
.rewards-page-new {
    padding: 32px 24px;
    max-width: 1400px;
    margin: 0 auto;
    min-height: 100vh;
    background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
}

/* رأس الصفحة */
.rewards-header {
    margin-bottom: 40px;
    padding: 24px 0;
}

.rewards-header__title {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.rewards-header__title h1 {
    margin: 0;
    font-size: 36px;
    font-weight: 800;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.5px;
}

.rewards-header__title i {
    font-size: 40px;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    filter: drop-shadow(0 2px 8px rgba(255, 152, 0, 0.3));
}

.rewards-badge {
    background: var(--primary-gradient);
    color: white;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* البطاقات */
.rewards-card {
    background: #ffffff;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    margin-bottom: 32px;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: var(--transition);
    position: relative;
}

.rewards-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
    opacity: 0;
    transition: var(--transition);
}

.rewards-card:hover {
    box-shadow: var(--card-shadow-hover);
    transform: translateY(-4px);
}

.rewards-card:hover::before {
    opacity: 1;
}

.rewards-card__header {
    padding: 24px 32px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.rewards-card__header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    display: flex;
    align-items: center;
    gap: 12px;
}

.rewards-card__header h2 i {
    font-size: 24px;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.rewards-card__body {
    padding: 32px;
}

/* بطاقة النقاط الإجمالية */
.rewards-total-points {
    background: linear-gradient(135deg, #ffffff 0%, #fff8f0 100%);
    border: 2px solid rgba(255, 152, 0, 0.1);
}

.rewards-total-points .total-points-display {
    text-align: center;
    padding: 48px 0;
    position: relative;
}

.rewards-total-points .total-points-display::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(255, 152, 0, 0.1) 0%, transparent 70%);
    border-radius: 50%;
    z-index: 0;
}

.points-value {
    display: block;
    font-size: 72px;
    font-weight: 800;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
    margin-bottom: 12px;
    position: relative;
    z-index: 1;
    text-shadow: 0 4px 20px rgba(255, 152, 0, 0.2);
}

.points-label {
    display: block;
    font-size: 22px;
    color: #666;
    font-weight: 600;
    position: relative;
    z-index: 1;
}

/* مسار الأيام المتتالية */
.streak-subtitle {
    margin: 12px 0 0 0;
    font-size: 15px;
    color: #666;
    font-weight: 500;
}

.streak-path-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 40px 0;
    position: relative;
    flex-wrap: wrap;
    gap: 16px;
}

.streak-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 2;
    flex: 1;
    min-width: 80px;
}

.streak-step__icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e5e5e5 0%, #d0d0d0 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #999;
    transition: var(--transition);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    position: relative;
}

.streak-step__icon::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    background: var(--primary-gradient);
    opacity: 0;
    transition: var(--transition);
    z-index: -1;
}

.streak-step.active .streak-step__icon {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 8px 24px rgba(255, 152, 0, 0.4);
    transform: scale(1.1);
}

.streak-step.active .streak-step__icon::after {
    opacity: 0.3;
    animation: ripple 1.5s ease-out infinite;
}

@keyframes ripple {
    0% {
        transform: scale(1);
        opacity: 0.3;
    }
    100% {
        transform: scale(1.5);
        opacity: 0;
    }
}

.streak-step__label {
    font-size: 13px;
    color: #666;
    font-weight: 600;
    text-align: center;
}

.streak-step.active .streak-step__label {
    color: #ff9800;
    font-weight: 700;
}

.streak-connector {
    flex: 1;
    height: 4px;
    background: linear-gradient(90deg, #e5e5e5 0%, #d0d0d0 100%);
    border-radius: 2px;
    margin: 0 12px;
    position: relative;
    top: -32px;
    transition: var(--transition);
    min-width: 40px;
}

.streak-connector.active {
    background: var(--primary-gradient);
    box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
}

.streak-reward {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    margin-right: 20px;
    padding: 16px;
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.1) 0%, rgba(255, 107, 0, 0.05) 100%);
    border-radius: var(--border-radius-small);
    border: 2px solid rgba(255, 152, 0, 0.2);
}

.streak-reward__icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    box-shadow: 0 6px 20px rgba(255, 152, 0, 0.4);
    animation: bounce 2s ease-in-out infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

.streak-reward__points {
    font-size: 20px;
    font-weight: 800;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.streak-reward__label {
    font-size: 13px;
    color: #666;
    font-weight: 600;
}

.streak-info {
    text-align: center;
    padding-top: 24px;
    border-top: 2px solid rgba(0, 0, 0, 0.06);
    margin-top: 24px;
}

.current-streak-text {
    font-size: 16px;
    color: #666;
    font-weight: 500;
}

.streak-hint {
    font-size: 14px;
    color: #888;
    margin: 8px 0 0;
}

.current-streak-text strong {
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 20px;
    font-weight: 800;
}

/* قسم الدعوات */
.btn-invite {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: var(--border-radius-small);
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    box-shadow: 0 4px 16px rgba(255, 152, 0, 0.3);
    position: relative;
    overflow: hidden;
}

.btn-invite::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-invite:hover {
    background: var(--primary-gradient-hover);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255, 152, 0, 0.4);
}

.btn-invite:hover::before {
    width: 300px;
    height: 300px;
}

.btn-invite:active {
    transform: translateY(0);
}

.empty-state {
    text-align: center;
    padding: 64px 24px;
    color: #666;
}

.empty-state i {
    font-size: 64px;
    background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 24px;
    display: block;
}

.empty-state p {
    margin: 0 0 32px 0;
    font-size: 18px;
    font-weight: 600;
    color: #888;
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: var(--border-radius-small);
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    box-shadow: 0 4px 16px rgba(255, 152, 0, 0.3);
}

.btn-primary:hover {
    background: var(--primary-gradient-hover);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255, 152, 0, 0.4);
}

.referrals-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.referral-item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    border-radius: var(--border-radius-small);
    border: 2px solid rgba(0, 0, 0, 0.06);
    transition: var(--transition);
    cursor: pointer;
    background: #ffffff;
    position: relative;
    overflow: hidden;
}

.referral-item::before {
    content: '';
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--primary-gradient);
    opacity: 0;
    transition: var(--transition);
}

.referral-item:hover {
    transform: translateX(-8px);
    box-shadow: var(--card-shadow-hover);
    border-color: rgba(255, 152, 0, 0.3);
}

.referral-item:hover::before {
    opacity: 1;
}

.referral-item.completed {
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.08) 0%, rgba(76, 175, 80, 0.03) 100%);
    border-color: rgba(76, 175, 80, 0.3);
}

.referral-item.pending {
    background: #fafafa;
    border-color: rgba(0, 0, 0, 0.06);
    opacity: 0.8;
}

.referral-item__icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
    transition: var(--transition);
}

.referral-item.completed .referral-item__icon {
    background: var(--success-gradient);
    color: white;
    box-shadow: 0 4px 16px rgba(76, 175, 80, 0.3);
}

.referral-item.pending .referral-item__icon {
    background: linear-gradient(135deg, #e5e5e5 0%, #d0d0d0 100%);
    color: #999;
}

.referral-item__content {
    flex: 1;
    min-width: 0;
}

.referral-item__name {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.referral-item__date {
    font-size: 14px;
    color: #666;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.referral-item__date i {
    font-size: 14px;
    color: #ff9800;
}

.referral-item__date strong {
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
}

.referral-item__points {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.points-badge {
    background: var(--primary-gradient);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 16px;
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
}

/* سجل النقاط */
.rewards-transactions {
    overflow: hidden;
}

.transactions-container {
    position: relative;
    min-height: 200px;
}

.transactions-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 48px;
    color: #666;
    font-size: 16px;
    font-weight: 600;
}

.transactions-loading i {
    font-size: 24px;
    color: #ff9800;
}

.transactions-table-wrapper {
    overflow-x: auto;
    border-radius: var(--border-radius-small);
    border: 1px solid rgba(0, 0, 0, 0.06);
}

.transactions-table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
}

.transactions-table thead {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-bottom: 2px solid rgba(0, 0, 0, 0.06);
}

.transactions-table th {
    padding: 16px 20px;
    text-align: right;
    font-size: 14px;
    font-weight: 700;
    color: #1a1a1a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.transactions-table tbody tr {
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
    transition: var(--transition);
}

.transactions-table tbody tr:hover {
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.05) 0%, rgba(255, 152, 0, 0.02) 100%);
    transform: scale(1.01);
}

.transactions-table tbody tr:last-child {
    border-bottom: none;
}

.transactions-table td {
    padding: 20px;
    font-size: 15px;
    color: #333;
}

.transaction-type {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
}

.transaction-type i {
    font-size: 20px;
    color: #ff9800;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 152, 0, 0.1);
    border-radius: 8px;
}

.transaction-description {
    color: #666;
    max-width: 400px;
    line-height: 1.6;
}

.transaction-description .task-link {
    color: #ff9800;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
}

.transaction-description .task-link:hover {
    color: #ff6b00;
    text-decoration: underline;
}

.transaction-type.reward i {
    color: #4caf50;
    background: rgba(76, 175, 80, 0.1);
}

.transaction-type.usage i {
    color: #f44336;
    background: rgba(244, 67, 54, 0.1);
}

.transaction-points {
    font-size: 18px;
    font-weight: 800;
    text-align: center;
}

.transaction-points.positive {
    color: #4caf50;
}

.transaction-points.negative {
    color: #f44336;
}

.transaction-date {
    color: #888;
    font-size: 14px;
    white-space: nowrap;
}

.transactions-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 20px;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    background: #fafafa;
    flex-wrap: wrap;
    gap: 16px;
}

.pagination-info {
    color: #666;
    font-size: 14px;
    font-weight: 500;
}

.pagination-buttons {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.pagination-btn {
    padding: 8px 16px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    background: #ffffff;
    color: #333;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pagination-btn:hover:not(:disabled) {
    background: #ff9800;
    color: #ffffff;
    border-color: #ff9800;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
}

.pagination-btn.active {
    background: #ff9800;
    color: #ffffff;
    border-color: #ff9800;
    cursor: default;
}

.pagination-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-ellipsis {
    padding: 8px 4px;
    color: #999;
}

.transactions-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 64px 24px;
    color: #999;
    text-align: center;
}

.transactions-empty i {
    font-size: 64px;
    margin-bottom: 24px;
    background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.transactions-empty p {
    font-size: 18px;
    font-weight: 600;
    color: #888;
    margin: 0;
}

/* المودال */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: var(--transition);
    padding: 20px;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background: #ffffff;
    border-radius: var(--border-radius);
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.9) translateY(20px);
    transition: var(--transition);
}

.modal-overlay.active .modal-content {
    transform: scale(1) translateY(0);
}

.modal-header {
    padding: 24px 32px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.modal-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #1a1a1a;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #666;
    cursor: pointer;
    padding: 8px;
    line-height: 1;
    transition: var(--transition);
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.modal-close:hover {
    color: #1a1a1a;
    background: rgba(0, 0, 0, 0.05);
    transform: rotate(90deg);
}

.modal-body {
    padding: 32px;
}

.invite-message {
    line-height: 2;
    color: #333;
    font-size: 16px;
}

.invite-message p {
    margin: 0 0 16px 0;
}

.invite-link-container {
    margin-top: 24px;
}

.invite-link-container input {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-radius: var(--border-radius-small);
    font-size: 15px;
    background: #f8f9fa;
    color: #1a1a1a;
    transition: var(--transition);
    font-family: 'Courier New', monospace;
}

.invite-link-container input:focus {
    outline: none;
    border-color: #ff9800;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(255, 152, 0, 0.1);
}

.modal-footer {
    padding: 24px 32px;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    flex-wrap: wrap;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.btn-secondary {
    background: #ffffff;
    color: #1a1a1a;
    border: 2px solid rgba(0, 0, 0, 0.1);
    padding: 12px 24px;
    border-radius: var(--border-radius-small);
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
}

.btn-secondary:hover {
    background: #f8f9fa;
    border-color: rgba(0, 0, 0, 0.2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-whatsapp {
    background: linear-gradient(135deg, #25d366 0%, #20ba5a 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: var(--border-radius-small);
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    box-shadow: 0 4px 16px rgba(37, 211, 102, 0.3);
}

.btn-whatsapp:hover {
    background: linear-gradient(135deg, #20ba5a 0%, #1da851 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37, 211, 102, 0.4);
}

/* التنبيهات */
.rewards-alert {
    display: flex;
    gap: 20px;
    padding: 24px;
    border-radius: var(--border-radius);
    margin-bottom: 32px;
    border: 2px solid;
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-color: #ffc107;
    box-shadow: var(--card-shadow);
}

.rewards-alert__icon {
    font-size: 32px;
    color: #ff9800;
    flex-shrink: 0;
}

.rewards-alert__body {
    flex: 1;
}

.rewards-alert__title {
    font-weight: 700;
    font-size: 18px;
    margin-bottom: 8px;
    color: #856404;
}

.rewards-alert__desc {
    color: #856404;
    font-size: 15px;
    line-height: 1.6;
}

/* الوضع الداكن */
[data-theme="dark"] .rewards-page-new {
    background: linear-gradient(180deg, #0f1720 0%, #1a1f2e 100%);
    --card-bg: #1a1f2e;
    --card-header-bg: #0f1720;
    --text-primary: #cfe0e7;
    --text-secondary: #a6c4d2;
    --text-tertiary: #666;
    --border-color: rgba(255, 255, 255, 0.1);
    --step-inactive: rgba(255, 255, 255, 0.1);
    --input-bg: #0f1720;
    --primary-orange: #ff9800;
}

[data-theme="dark"] .rewards-card {
    background: var(--card-bg);
    border-color: var(--border-color);
}

[data-theme="dark"] .rewards-card__header {
    background: linear-gradient(135deg, #0f1720 0%, #1a1f2e 100%);
    border-color: var(--border-color);
}

[data-theme="dark"] .rewards-card__header h2 {
    color: var(--text-primary);
}

[data-theme="dark"] .referral-item.pending {
    background: var(--card-header-bg);
    opacity: 0.7;
}

[data-theme="dark"] .modal-content {
    background: var(--card-bg);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
}

[data-theme="dark"] .modal-header,
[data-theme="dark"] .modal-footer {
    background: linear-gradient(135deg, #0f1720 0%, #1a1f2e 100%);
    border-color: var(--border-color);
}

[data-theme="dark"] .modal-header h3 {
    color: var(--text-primary);
}

[data-theme="dark"] .modal-close {
    color: var(--text-secondary);
}

[data-theme="dark"] .modal-close:hover {
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .invite-message {
    color: var(--text-primary);
}

[data-theme="dark"] .invite-link-container input {
    background: var(--input-bg);
    border-color: var(--border-color);
    color: var(--text-primary);
}

[data-theme="dark"] .btn-secondary {
    background: var(--input-bg);
    color: var(--text-primary);
    border-color: var(--border-color);
}

[data-theme="dark"] .btn-secondary:hover {
    background: var(--card-header-bg);
    border-color: var(--primary-orange);
    color: var(--primary-orange);
}

[data-theme="dark"] .modal-overlay {
    background: rgba(0, 0, 0, 0.8);
}

[data-theme="dark"] .transactions-table {
    background: var(--card-bg);
}

[data-theme="dark"] .transactions-table thead {
    background: linear-gradient(135deg, #0f1720 0%, #1a1f2e 100%);
    border-color: var(--border-color);
}

[data-theme="dark"] .transactions-table th {
    color: var(--text-primary);
}

[data-theme="dark"] .transactions-table tbody tr {
    border-color: var(--border-color);
}

[data-theme="dark"] .transactions-table tbody tr:hover {
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.1) 0%, rgba(255, 152, 0, 0.05) 100%);
}

[data-theme="dark"] .transactions-table td {
    color: var(--text-primary);
}

[data-theme="dark"] .transaction-description {
    color: var(--text-secondary);
}

[data-theme="dark"] .transaction-date {
    color: var(--text-secondary);
}

/* التصميم المتجاوب */
@media (max-width: 1024px) {
    .rewards-page-new {
        padding: 24px 20px;
    }
    
    .rewards-card__body {
        padding: 24px;
    }
    
    .rewards-card__header {
        padding: 20px 24px;
    }
}

@media (max-width: 768px) {
    .rewards-page-new {
        padding: 20px 16px;
    }
    
    .rewards-header__title h1 {
        font-size: 28px;
    }
    
    .rewards-header__title i {
        font-size: 32px;
    }
    
    .points-value {
        font-size: 56px;
    }
    
    .points-label {
        font-size: 18px;
    }
    
    .streak-path-container {
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px;
        padding: 32px 0;
    }
    
    .streak-connector {
        display: none;
    }
    
    .streak-step {
        flex: 0 0 calc(20% - 12px);
        min-width: 60px;
    }
    
    .streak-step__icon {
        width: 56px;
        height: 56px;
        font-size: 24px;
    }
    
    .streak-reward {
        margin-right: 0;
        margin-top: 16px;
        width: 100%;
    }
    
    .rewards-card__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .rewards-card__header h2 {
        font-size: 20px;
    }
    
    .btn-invite {
        width: 100%;
        justify-content: center;
    }
    
    .referral-item {
        flex-wrap: wrap;
        gap: 16px;
    }
    
    .referral-item__content {
        flex: 1 1 100%;
    }
    
    .transactions-table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .transactions-table {
        min-width: 600px;
    }
    
    .transactions-table th,
    .transactions-table td {
        padding: 12px 16px;
        font-size: 14px;
    }
    
    .modal-content {
        margin: 20px;
        max-width: calc(100% - 40px);
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 20px;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .btn-secondary,
    .btn-whatsapp {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .rewards-page-new {
        padding: 16px 12px;
    }
    
    .rewards-header__title h1 {
        font-size: 24px;
    }
    
    .points-value {
        font-size: 48px;
    }
    
    .streak-step {
        flex: 0 0 calc(18% - 8px);
        min-width: 50px;
    }
    
    .streak-step__icon {
        width: 48px;
        height: 48px;
        font-size: 20px;
    }
    
    .streak-step__label {
        font-size: 11px;
    }
    
    .rewards-card__body {
        padding: 20px;
    }
    
    .transaction-type {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
    
    .transaction-description {
        max-width: 200px;
    }
}
</style>
