<main class="followup-main" style="font-family:'Almarai',sans-serif;">

        <?php if ($status === 'success'): ?>
        <div class="followup-container success-state">
            <i class="fas fa-check-circle status-icon success-icon"></i>
            <h2>تم التسجيل بنجاح!</h2>
            
            <div class="status-card">
                <p>أرسلنا رابط تفعيل إلى بريدك الإلكتروني. الرجاء الضغط عليه لإكمال إنشاء الحساب.</p>
            </div>
            
            <div class="email-note">
                <i class="fas fa-envelope-open-text"></i>
                <p>إذا لم تصلك الرسالة خلال دقائق، يرجى التحقق من مجلد الرسائل غير المرغوب فيها (Spam).</p>
            </div>
            
            <div class="action-buttons">
                <a href="<?= site_url('/') ?>" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    العودة للصفحة الرئيسية
                </a>
                <a href="mailto:support@howplatform.net" class="btn btn-secondary">
                    <i class="fas fa-headset"></i>
                    التواصل مع الدعم
                </a>
            </div>
        </div>
        <?php elseif ($status === 'mail_failed'): ?>
             <div class="followup-container error-state">
            <i class="fas fa-exclamation-circle status-icon error-icon"></i>
            <h2>فشل في إرسال البريد الإلكتروني</h2>
            
            <div class="status-card">
                <p>تم إنشاء الحساب، لكن لم نتمكن من إرسال رسالة التفعيل.</p>
            </div>
            
            <p>يرجى التواصل مع <a href="mailto:support@howplatform.net">الدعم الفني</a> لمساعدتك في تفعيل حسابك.</p>
            
            <div class="action-buttons">
                <a href="#" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    العودة للصفحة الرئيسية
                </a>
                <a href="mailto:support@howplatform.net" class="btn btn-secondary">
                    <i class="fas fa-headset"></i>
                    التواصل مع الدعم
                </a>
            </div>
        </div>
        <?php else: ?>
          <div class="followup-container info-state">
            <i class="fas fa-info-circle status-icon info-icon"></i>
            <h2>لا توجد معلومات لعرضها</h2>
            
            <div class="status-card">
                <p>عذراً، يبدو أن الصفحة التي تبحث عنها غير متوفرة أو لا تحتوي على معلومات.</p>
            </div>
            
            <div class="action-buttons">
                <a href="<?= site_url('/') ?>"  class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    العودة للصفحة الرئيسية
                </a>
                <a href="<?= site_url('/auth/register') ?>"  class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    الصفحة السابقة
                </a>
            </div>
        </div>
        <?php endif; ?>
</main>
<style>
            /* Main Content Styles */
        .followup-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 1s ease;
        }

        .followup-container {
            max-width: 700px;
            width: 100%;
            background: var(--background);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 3rem;
            text-align: center;
            border: 1px solid rgba(142, 202, 230, 0.3);
            position: relative;
            overflow: hidden;
        }

        .followup-container::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--accent), var(--accent-dark));
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .status-icon {
            font-size: 5rem;
            margin-bottom: 0.5rem;
            animation: pulse 2s infinite;
        }

        .success-icon {
            color: var(--approved);
        }

        .error-icon {
            color: var(--rejected);
        }

        .info-icon {
            color: var(--primary);
        }

        .followup-container h2 {
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            color: var(--primary-darker);
        }

        .success-state h2 {
            color: var(--approved-dark);
        }

        .error-state h2 {
            color: var(--rejected-dark);
        }

        .followup-container p {
            font-size: 1.25rem;
            color: var(--text-light);
            margin-bottom: 1.5rem;
            line-height: 1.7;
            max-width: 90%;
            margin-left: auto;
            margin-right: auto;
        }

        .followup-container a {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .followup-container a:hover {
            color: var(--primary-darker);
        }
        .action-buttons a {
            color: var(--background);
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2.5rem;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            z-index: 1;
            text-decoration: none;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            z-index: -1;
        }

        .btn:hover::before {
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--background);
        }

        .btn-primary:hover {
            box-shadow: 0 7px 20px rgba(33, 158, 188, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--accent-light), var(--accent));
            color: var(--primary-darker);
        }

        .btn-secondary:hover {
            box-shadow: 0 7px 20px rgba(255, 183, 3, 0.4);
        }

        .status-card {
            background: var(--approved-light);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin: 2rem 0;
            border-left: 5px solid var(--approved);
            text-align: right;
        }

        .error-state .status-card {
            background: var(--rejected-light);
            border-left: 5px solid var(--rejected);
        }

        .info-state .status-card {
            background: var(--primary-light);
            border-left: 5px solid var(--primary);
        }

        .email-note {
            background: rgba(142, 202, 230, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: center;
        }

        .email-note i {
            color: var(--accent);
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: block;
        }

</style>