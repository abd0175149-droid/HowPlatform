
<style>

    /* Main Content Styles */
    .support-container {
        max-width: 1200px;
        margin: 3rem auto;
        padding: 0 2rem;
    }

    .support-header {
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
    }

    .support-header h1 {
        font-size: 2.8rem;
        margin-bottom: 1rem;
        color: var(--primary-darker);
        background: linear-gradient(to right, var(--primary-darker), var(--primary-dark));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .support-header p {
        font-size: 1.2rem;
        color: var(--text-light);
        max-width: 800px;
        margin: 0 auto;
        line-height: 1.7;
    }

    .support-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2.5rem;
    }

    @media (max-width: 900px) {
        .support-content {
            grid-template-columns: 1fr;
        }
    }

    .contact-info-pg {
        background: rgba(142, 202, 230, 0.1);
        border-radius: var(--border-radius);
        padding: 2rem;
        border-left: 5px solid var(--primary);
        position: relative;
        overflow: hidden;
        flex-direction: column;

    }

    .contact-info-pg::before {
        content: '✉️';
        position: absolute;
        top: -30px;
        left: -30px;
        font-size: 8rem;
        opacity: 0.05;
        transform: rotate(15deg);
    }

    .contact-info-pg h2 {
        color: var(--primary-darker);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .contact-info-pg h2 i {
        color: var(--accent);
    }

    .contact-methods {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .contact-card {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        padding: 1.5rem;
        background: var(--background);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        border: 1px solid rgba(142, 202, 230, 0.3);
        transition: var(--transition);
    }

    .contact-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .contact-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        flex-shrink: 0;
    }

    .contact-details h3 {
        color: var(--primary-darker);
        margin-bottom: 0.5rem;
    }

    .contact-details p {
        color: var(--text-light);
        line-height: 1.6;
    }

    .contact-details a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 700;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 0.5rem;
    }

    .contact-details a:hover {
        color: var(--primary-dark);
    }

    .working-hours {
        background: rgba(255, 183, 3, 0.1);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-top: 1.5rem;
        border-left: 3px solid var(--accent);
    }

    .working-hours h4 {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--accent-dark);
        margin-bottom: 0.5rem;
    }

    .contact-form-section {
        background: var(--background);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 2.5rem;
        border: 1px solid rgba(142, 202, 230, 0.3);
        position: relative;
        overflow: hidden;
    }

    .contact-form-section::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(to right, var(--accent), var(--accent-dark));
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .contact-form-section h2 {
        font-size: 1.8rem;
        color: var(--primary-darker);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 15px;
    }

        .form-description {
        color: var(--text-light);
        margin-bottom: 1.5rem;
        line-height: 1.7;
        text-align: center;
    }

    .form-group {
        margin-bottom: 1.8rem;
        position: relative;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.8rem;
        font-weight: 700;
        color: var(--primary-darker);
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 1rem;
        border: 1px solid rgba(142, 202, 230, 0.5);
        border-radius: var(--border-radius);
        font-size: 1rem;
        transition: var(--transition);
        background: rgba(142, 202, 230, 0.05);
        color: var(--text);
    }

    .form-group select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23126782' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 16px 16px;
        padding-right: 2.5rem;
        cursor: pointer;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(33, 158, 188, 0.2);
    }

    .form-group textarea {
        min-height: 150px;
        resize: vertical;
    }
    .submit-btn {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: var(--background);
        border: none;
        padding: 1rem 2.5rem;
        font-size: 1.1rem;
        font-weight: 700;
        border-radius: var(--border-radius);
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        position: relative;
        overflow: hidden;
        z-index: 1;
    }

    .submit-btn::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 0;
        height: 100%;
        background: rgba(255, 255, 255, 0.2);
        transition: width 0.5s ease;
        z-index: -1;
    }

    .submit-btn:hover::before {
        width: 100%;
    }

    .submit-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 7px 20px rgba(33, 158, 188, 0.4);
    }
    .response-info {
        background: rgba(142, 202, 230, 0.15);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-top: 2rem;
        text-align: right;
        border-left: 3px solid var(--primary);
    }

    .response-info p {
        color: var(--text-light);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: start;
        gap: 10px;
    }
    .important-notes {
        background: rgba(231, 76, 60, 0.05);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-top: 2rem;
        border-left: 5px solid var(--rejected);
        grid-column: 1 / -1;
    }

    .important-notes h3 {
        color: var(--rejected-dark);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .important-notes ul {
        margin: 1rem 0;
        padding-right: 1.5rem;
        list-style-type: none;
    }

    .important-notes li {
        margin-bottom: 0.8rem;
        position: relative;
        padding-right: 1.5rem;
        line-height: 1.7;
    }

    .important-notes li::before {
        content: '';
        position: absolute;
        top: 0.7rem;
        right: 0;
        width: 8px;
        height: 8px;
        background: var(--rejected);
        border-radius: 50%;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .support-header h1 {
            font-size: 2.2rem;
        }
        
        .contact-card {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .contact-form-section {
            padding: 1.5rem;
        }
    }

    @media (max-width: 480px) {
        .support-header h1 {
            font-size: 1.8rem;
        }
        
        .logo-text {
            font-size: 1.4rem;
        }
        
        .contact-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }
    }
</style>


<div class="support-container">
    <div class="support-header">
        <h1>الدعم الفني والمساندة</h1>
        <p>مرحبًا بك في قسم الدعم الفني والمساندة! نحن هنا لمساعدتك على مدار الساعة في حال واجهت أي مشكلة أثناء استخدام المنصة، أو كان لديك استفسار، اقتراح، أو طلب دعم فني أو أكاديمي.</p>
    </div>

    <div class="support-content">
        <div class="contact-info-pg">
            <h2><i class="fas fa-headset"></i> معلومات التواصل</h2>
            
            <div class="contact-methods">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-details">
                        <h3>البريد الإلكتروني المباشر</h3>
                        <p>تواصل معنا عبر البريد الإلكتروني للحصول على دعم فوري</p>
                        <a href="mailto:support@howplatform.com">
                            <i class="fas fa-paper-plane"></i>
                            support@howplatform.com
                        </a>
                    </div>
                </div>
                
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="contact-details">
                        <h3>أوقات العمل الرسمية</h3>
                        <p>الأحد – الخميس، من الساعة 9:00 صباحًا وحتى 5:00 مساءً</p>
                        <p>(بتوقيت الأردن)</p>
                    </div>
                </div>
                
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="contact-details">
                        <h3>مقر العمل</h3>
                        <p>عمان – الأردن (للأغراض الإدارية فقط)</p>
                        <a href="#">
                            <i class="fas fa-map"></i>
                            عرض على الخريطة
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="working-hours">
                <h4><i class="fas fa-info-circle"></i> ملاحظة هامة</h4>
                <p>يرجى استخدام البريد المسجّل في حسابك عند مراسلتنا لضمان سرعة الرد.</p>
            </div>
        </div>
        
        <div class="contact-form-section">
            <h2><i class="fas fa-comment-dots"></i> نموذج الاتصال المباشر</h2>
            <p class="form-description">إذا رغبت بإرسال رسالة مباشرة، يرجى تعبئة النموذج التالي:</p>
            
            <form id="support-request">
                <div class="form-group">
                    <label for="name">الاسم الكامل:</label>
                    <input type="text" id="name" name="name" required placeholder="أدخل اسمك الكامل">
                </div>
                
                <div class="form-group">
                    <label for="email">البريد الإلكتروني:</label>
                    <input type="email" id="email" name="email" required placeholder="أدخل بريدك الإلكتروني">
                </div>
                
                <div class="form-group">
                <label for="subject"><i class="fas fa-tag"></i> الموضوع:</label>
                <select id="subject" name="subject" required>
                  <option value="" disabled selected>اختر الموضوع المناسب</option>
                  <option value="مشاكل في تسجيل الدخول">مشاكل في تسجيل الدخول</option>
                  <option value="استفسار حول الاشتراك">استفسار حول الاشتراك</option>
                  <option value="طلب استرداد مالي">طلب استرداد مالي</option>
                  <option value="مشكلة فنية في المحتوى">مشكلة فنية في المحتوى</option>
                  <option value="اقتراح لتحسين المنصة">اقتراح لتحسين المنصة</option>
                  <option value="أخرى">أخرى</option>
                </select>
                </div>
                <div class="form-group">
                    <label for="message">نص الرسالة:</label>
                    <textarea id="message" name="message" required placeholder="أدخل تفاصيل رسالتك هنا..."></textarea>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    إرسال الرسالة
                </button>
            </form>
            
            <div class="response-info">
            <p><i class="fas fa-info-circle"></i> سيتم تحويل رسالتك تلقائيًا إلى فريق الدعم عبر البريد الإلكتروني</p>
            <p><i class="fas fa-clock"></i> وقت الاستجابة المتوقع: 24 إلى 48 ساعة عمل</p>
        </div>
        </div>
        
        <div class="important-notes">
            <h3><i class="fas fa-exclamation-triangle"></i> ملاحظات هامة</h3>
            <ul>
                <li>يرجى عدم إرسال معلومات سرية أو مالية عبر الرسائل، باستثناء ما يُطلب صراحة في حالات استرداد المبالغ.</li>
                <li>يتم التعامل مع جميع الرسائل بسرية تامة وفقًا لسياسة الخصوصية المعتمدة على المنصة.</li>
                <li>تأكد من أن رسالتك تحتوي على معلومات كافية لتسهيل المتابعة (مثل اسمك، نوع الاشتراك، وصف المشكلة).</li>
                <li>لضمان سرعة الاستجابة، يرجى تقديم معلومات مثل التخصص واسم الجامعة في حال كانت المشكلة متعلقة بمحتوى أكاديمي.</li>
            </ul>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('support-request');

    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(form);

        // رسالة جاري الإرسال
        const loading = Swal.fire({
            title: 'جاري الإرسال...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const response = await fetch('/submit-request', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            Swal.close();

            if (result.success) {
                showSuccess(result.message || 'تم إرسال الرسالة بنجاح.', 'تم', true);
                form.reset();
            } else {
                // إذا وُجدت أخطاء تفصيلية
                if (result.errors) {
                    const errorsList = Object.values(result.errors)
                        .map(msg => `<li>${msg}</li>`)
                        .join('');
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'تحقق من الحقول التالية:',
                        html: `<ul style="text-align:right; direction:rtl">${errorsList}</ul>`,
                        confirmButtonText: 'حسنًا',
                        confirmButtonColor: 'var(--rejected)'
                    });
                } else {
                    showError(result.message || 'حدث خطأ أثناء التحقق من البيانات.');
                }
            }

        } catch (error) {
            Swal.close();
            console.error(error);
            showError('فشل الاتصال بالخادم. حاول لاحقًا.', 'فشل الاتصال');
        }
    });
});

</script>
