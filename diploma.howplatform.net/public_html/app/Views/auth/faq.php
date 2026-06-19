
<style>
    .faq-container {
        max-width: 900px;
        margin: 0 auto 4rem;
        padding: 0 2rem;
    }

    .faq-header {
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
    }

    .faq-header h1 {
        font-size: 2.8rem;
        margin-bottom: 1rem;
        color: var(--primary-darker);
        background: linear-gradient(to right, var(--primary-darker), var(--primary-dark));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .faq-header p {
        font-size: 1.2rem;
        color: var(--text-light);
        max-width: 700px;
        margin: 0 auto;
        line-height: 1.7;
    }

    .faq-intro {
        background: rgba(142, 202, 230, 0.15);
        border-radius: var(--border-radius);
        padding: 2rem;
        margin-bottom: 2rem;
        border-left: 5px solid var(--primary);
        position: relative;
        overflow: hidden;
    }

    .faq-intro::before {
        content: '❓';
        position: absolute;
        top: -30px;
        left: -30px;
        font-size: 10rem;
        opacity: 0.05;
        transform: rotate(15deg);
    }

    .faq-intro h2 {
        color: var(--primary-darker);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .faq-intro h2 i {
        color: var(--accent);
    }

    .faq-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .faq-item {
        background: var(--background);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        border: 1px solid rgba(142, 202, 230, 0.3);
        overflow: hidden;
        transition: var(--transition);
    }

    .faq-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .faq-question {
        background: linear-gradient(to right, var(--primary), var(--primary-dark));
        color: var(--background);
        padding: 1.2rem;
        font-size: 1.2rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .faq-question .icon {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .faq-question .toggle {
        font-size: 1.2rem;
        transition: var(--transition);
    }

    .faq-item.active .faq-question .toggle {
        transform: rotate(180deg);
    }

    .faq-answer {
        padding: 0 1.2rem;
        max-height: 0;
        overflow: hidden;
        transition: var(--transition);
    }

    .faq-item.active .faq-answer {
        padding: 1.2rem;
        max-height: 1000px;
    }

    .faq-answer p {
        margin-bottom: 1rem;
        line-height: 1.7;
        color: var(--text);
    }

    .faq-answer ul {
        margin: 1rem 0;
        padding-right: 1.5rem;
        list-style-type: none;
    }

    .faq-answer li {
        margin-bottom: 0.8rem;
        position: relative;
        padding-right: 1.5rem;
    }

    .faq-answer li::before {
        content: '';
        position: absolute;
        top: 0.7rem;
        right: 0;
        width: 8px;
        height: 8px;
        background: var(--primary);
        border-radius: 50%;
    }

    .highlight-box {
        background: rgba(255, 183, 3, 0.1);
        border-radius: var(--border-radius);
        padding: 1rem;
        margin: 1rem 0;
        border-left: 3px solid var(--accent);
    }

    .highlight-box h4 {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--accent-dark);
        margin-bottom: 0.5rem;
    }

    .subscription-table {
        width: 100%;
        border-collapse: collapse;
        margin: 1.5rem 0;
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .subscription-table th {
        background: var(--primary);
        color: white;
        padding: 1rem;
        text-align: right;
    }

    .subscription-table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(142, 202, 230, 0.3);
    }

    .subscription-table tr:last-child td {
        border-bottom: none;
    }

    .subscription-table tr:nth-child(even) {
        background: rgba(142, 202, 230, 0.1);
    }

    .contact-methods {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin: 2rem 0;
    }

    .contact-card {
        flex: 1;
        min-width: 250px;
        background: var(--background);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 1.5rem;
        border: 1px solid rgba(142, 202, 230, 0.3);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: var(--transition);
    }

    .contact-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .contact-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .contact-info h4 {
        color: var(--primary-darker);
        margin-bottom: 0.5rem;
    }

    .contact-info a {
        color: var(--primary);
        text-decoration: none;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .contact-info a:hover {
        color: var(--primary-dark);
    }

    .additional-notes {
        background: rgba(231, 76, 60, 0.05);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-top: 3rem;
        border-left: 5px solid var(--rejected);
    }

    .additional-notes h3 {
        color: var(--rejected-dark);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .additional-notes ul {
        margin: 1rem 0;
        padding-right: 1.5rem;
        list-style-type: none;
    }

    .additional-notes li {
        margin-bottom: 0.8rem;
        position: relative;
        padding-right: 1.5rem;
        line-height: 1.7rem;
    }

    .additional-notes li::before {
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
        .faq-header h1 {
            font-size: 2.2rem;
        }
        
        .faq-grid {
            grid-template-columns: 1fr;
        }
        
        .subscription-table {
            font-size: 0.9rem;
        }
    }

    @media (max-width: 480px) {
        .faq-header h1 {
            font-size: 1.8rem;
        }
        
        .faq-question {
            font-size: 1.1rem;
            padding: 1rem;
        }
    }
</style>
<div class="faq-container">
    <div class="faq-header">
        <h1>الأسئلة الشائعة</h1>
        <p>مرحبًا بك في قسم "الأسئلة الشائعة". ستجد هنا إجابات لأكثر الاستفسارات تكرارًا من مستخدمي منصة "كيف التعليمية". نهدف إلى تسهيل تجربتك التعليمية وتوفير الدعم الكامل لك خلال رحلتك في الدبلوم العالي.</p>
    </div>

    <div class="faq-intro">
        <h2><i class="fas fa-lightbulb"></i> كيف يمكنك الاستفادة من هذه الصفحة؟</h2>
        <p>استخدم مربع البحث أدناه للعثور على أسئلة محددة، أو تصفح الأسئلة حسب الفئة. يمكنك النقر على أي سؤال لعرض إجابته بالكامل. إذا لم تجد إجابة لسؤالك، يمكنك التواصل مع فريق الدعم عبر الوسائل المتاحة في نهاية الصفحة.</p>
    </div>

    <div class="faq-grid">
        <!-- Question 1 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-lock"></i></div>
                <span>كيف يمكنني إنشاء حساب على المنصة؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>يمكنك إنشاء حساب بطريقتين:</p>
                <ul>
                    <li><strong>عبر حساب Google:</strong> اضغط على زر "تسجيل الدخول باستخدام Google"، واختر بريدك الجامعي أو الشخصي المرتبط بحسابك.</li>
                    <li><strong>عبر نموذج التسجيل اليدوي:</strong> انتقل إلى صفحة إنشاء حساب وأدخل البيانات المطلوبة: الاسم الكامل، البريد الإلكتروني، كلمة المرور، ثم اضغط "تسجيل".</li>
                </ul>
                <div class="highlight-box">
                    <h4><i class="fas fa-exclamation-circle"></i> ملاحظة هامة</h4>
                    <p>يجب أن تكون فوق سن 18 عامًا لإنشاء حساب.</p>
                </div>
            </div>
        </div>

        <!-- Question 2 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-credit-card"></i></div>
                <span>ما هي أنواع الاشتراكات المتوفرة؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>تقدم منصة "كيف التعليمية" ثلاث خطط اشتراك مرنة حسب مدة الاستخدام:</p>
                
                <table class="subscription-table">
                    <thead>
                        <tr>
                            <th>نوع الاشتراك</th>
                            <th>المدة</th>
                            <th>الأنسب لـ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>اشتراك شهري</td>
                            <td>30 يومًا</td>
                            <td>للمراجعة أو الاستخدام المؤقت</td>
                        </tr>
                        <tr>
                            <td>اشتراك فصلي</td>
                            <td>فصل دراسي</td>
                            <td>للطلبة المسجلين في الفصل الحالي</td>
                        </tr>
                        <tr>
                            <td>اشتراك سنوي</td>
                            <td>عام دراسي كامل</td>
                            <td>للاستفادة الشاملة من جميع محتويات الدبلوم</td>
                        </tr>
                    </tbody>
                </table>
                
                <p><strong>جميع الخطط تشمل:</strong> دعم الذكاء الاصطناعي، تحميل المحتوى، والخدمات التخصصية.</p>
            </div>
        </div>

        <!-- Question 3 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-key"></i></div>
                <span>نسيت كلمة المرور، ماذا أفعل؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>لاستعادة كلمة المرور:</p>
                <ul>
                    <li>انتقل إلى صفحة استعادة كلمة المرور.</li>
                    <li>أدخل بريدك الإلكتروني المسجّل.</li>
                    <li>سيتم إرسال رابط إعادة التعيين مرفقًا برمز تحقق إلى بريدك.</li>
                    <li>اضغط على الرابط، أدخل الرمز، ثم أنشئ كلمة مرور جديدة.</li>
                </ul>
            </div>
        </div>

        <!-- Question 4 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-shield-alt"></i></div>
                <span>ما شروط اختيار كلمة مرور قوية؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>نوصي بأن تحتوي كلمة المرور على:</p>
                <ul>
                    <li>8 أحرف على الأقل</li>
                    <li>حرف كبير واحد على الأقل (A-Z)</li>
                    <li>حرف صغير واحد على الأقل (a-z)</li>
                    <li>رقم واحد على الأقل (0-9)</li>
                    <li>رمز خاص واحد على الأقل (مثل: ! @ # $ %)</li>
                </ul>
                <div class="highlight-box">
                    <h4><i class="fas fa-check-circle"></i> مثال آمن</h4>
                    <p>Edulearn@2025</p>
                </div>
            </div>
        </div>

        <!-- Question 5 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-robot"></i></div>
                <span>ما هي خدمة "فطين" الذكية؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>"فطين" هو مساعد ذكي مبني على تقنيات الذكاء الاصطناعي، يقدّم دعمًا مخصصًا للمعلمين في:</p>
                <ul>
                    <li>مراجعة المقالات والتقارير</li>
                    <li>تحليل المهام</li>
                    <li>المساعدة في فهم المتطلبات التقييمية</li>
                </ul>
                <div class="highlight-box">
                    <h4><i class="fas fa-info-circle"></i> ملاحظة</h4>
                    <p>الردود آلية بالكامل، ويجب دائمًا التحقق من دقتها قبل استخدامها.</p>
                </div>
            </div>
        </div>

        <!-- Question 6 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-file-alt"></i></div>
                <span>هل يمكنني استخدام المحتوى كما هو في الجامعة؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p><strong>لا.</strong> جميع الملفات والخدمات المقدمة من المنصة مخصصة لأغراض الدعم والتوجيه فقط.</p>
                <p>يُمنع مشاركة المواد كما هي أو تقديمها كإنتاج شخصي للجامعات أو المؤسسات التعليمية.</p>
            </div>
        </div>

        <!-- Question 7 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                <span>كيف أطلب استرداد المبلغ بعد الاشتراك؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>يمكنك طلب الاسترداد خلال 5 أيام فقط من تاريخ الاشتراك من خلال صفحة الدعم أو التواصل معنا عبر البريد الإلكتروني.</p>
                <p>بعد الموافقة، يُطلب منك إحدى طرق الدفع المعتمدة:</p>
                <ul>
                    <li>رقم IBAN للتحويل البنكي</li>
                    <li>رقم الهاتف أو الاسم المختصر لحساب CliQ</li>
                    <li>الرقم الوطني والاسم الرباعي للتحويل عبر شركة صرافة أردنية معتمدة</li>
                </ul>
            </div>
        </div>

        <!-- Question 8 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-envelope"></i></div>
                <span>لم يصلني بريد التفعيل أو الاسترداد، ماذا أفعل؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>يرجى التأكد من فحص مجلد "الرسائل غير المرغوب فيها" (Spam أو Junk).</p>
                <p>إذا لم تصلك الرسالة خلال 10 دقائق، يرجى التواصل معنا عبر:</p>
                <div class="highlight-box">
                    <h4><i class="fas fa-envelope"></i> البريد الإلكتروني</h4>
                    <p>support@howplatform.com</p>
                </div>
            </div>
        </div>

        <!-- Question 9 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-mobile-alt"></i></div>
                <span>هل يمكنني استخدام المنصة من الهاتف المحمول؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p><strong>نعم.</strong> المنصة مصممة لتعمل بكفاءة على جميع الأجهزة، سواء كانت:</p>
                <ul>
                    <li>حواسيب (Windows / Mac)</li>
                    <li>هواتف ذكية (iOS / Android)</li>
                    <li>أجهزة لوحية (Tablets)</li>
                </ul>
                <div class="highlight-box">
                    <h4><i class="fas fa-sync-alt"></i> تجربة متكاملة</h4>
                    <p>سيتم حفظ تقدمك ومحتواك تلقائيًا عبر جميع الأجهزة التي تستخدم نفس الحساب.</p>
                </div>
            </div>
        </div>

        <!-- Question 10 -->
        <div class="faq-item">
            <div class="faq-question">
                <div class="icon"><i class="fas fa-headset"></i></div>
                <span>هل هناك دعم مباشر أو تواصل مع الفريق؟</span>
                <div class="toggle"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p><strong>نعم</strong>، يمكنك التواصل معنا من خلال:</p>
                <ul>
                    <li>مركز الدعم الفني داخل حسابك</li>
                    <li>البريد الإلكتروني: support@howplatform.com</li>
                    <li>نموذج "اتصل بنا" في صفحة الدعم</li>
                </ul>
                
                <div class="contact-methods">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="contact-info">
                            <h4>الدعم الفني</h4>
                            <p>متاح من الساعة 9 صباحًا إلى 5 مساءً</p>
                        </div>
                    </div>
                    
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-info">
                            <h4>البريد الإلكتروني</h4>
                            <a href="mailto:support@howplatform.com">support@howplatform.com</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="additional-notes">
        <h3><i class="fas fa-exclamation-triangle"></i> ملاحظات إضافية</h3>
        <ul>
            <li>لا تطلب المنصة منك أي بيانات مالية أو مصرفية إلا عند تنفيذ طلب استرداد رسمي.</li>
            <li>لا نستخدم بياناتك في الإعلانات أو لأي غرض تجاري.</li>
            <li>لا نقدم مهام جاهزة ولا نمثل الطالب، بل نساعده فقط على التطوير والفهم.</li>
        </ul>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Accordion functionality
        const faqItems = document.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            
            question.addEventListener('click', () => {
                // Close all other items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });
                
                // Toggle current item
                item.classList.toggle('active');
            });
        });
        
        // Open first FAQ item by default
        if (faqItems.length > 0) {
            faqItems[0].classList.add('active');
        }
        
        // Add hover effects to links
        const links = document.querySelectorAll('a');
        links.forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(-5px)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
        });
    });
</script>
