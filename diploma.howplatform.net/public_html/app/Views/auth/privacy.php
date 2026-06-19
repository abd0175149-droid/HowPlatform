    <style>
        .terms-container {
            max-width: 900px;
            margin: 0 auto 4rem;
            padding: 0 2rem;
        }

        .terms-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .terms-header h1 {
            font-size: 2.8rem;
            margin-bottom: 1rem;
            color: var(--primary-darker);
            background: linear-gradient(to right, var(--primary-darker), var(--primary-dark));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .terms-header .effective-date {
            background: var(--accent-light);
            display: inline-block;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            font-weight: 700;
            color: var(--primary-darker);
            box-shadow: 0 4px 10px rgba(255, 183, 3, 0.2);
        }

        .terms-content {
            background: var(--background);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 3rem;
            border: 1px solid rgba(142, 202, 230, 0.3);
            position: relative;
            overflow: hidden;
        }

        .terms-content::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--accent), var(--accent-dark));
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .section-title {
            font-size: 1.8rem;
            margin: 2.5rem 0 1.5rem;
            color: var(--primary-darker);
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title::before {
            content: '';
            width: 8px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary), var(--primary-dark));
            border-radius: 10px;
        }

        .terms-content p {
            font-size: 1.15rem;
            line-height: 1.8;
            color: var(--text);
            margin-bottom: 1.2rem;
            text-align: justify;
        }

        .terms-content ul {
            margin: 1.5rem 0;
            padding-right: 1.5rem;
            list-style-type: none;
            
        }

        .terms-content li {
            margin-bottom: 1rem;
            line-height: 1.7;
            position: relative;
            padding-right: 1.5rem;
        }

        .terms-content li::before {
            content: '';
            position: absolute;
            top: 0.7rem;
            right: 0;
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: 50%;
        }

        .important-note {
            background: var(--accent-light);
            border-left: 4px solid var(--accent-dark);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin: 2rem 0;
            position: relative;
            overflow: hidden;
        }

        .important-note::before {
            content: '!';
            position: absolute;
            top: -20px;
            left: -20px;
            font-size: 8rem;
            font-weight: 800;
            color: rgba(253, 158, 2, 0.2);
            z-index: 0;
        }

        .important-note h3 {
            position: relative;
            z-index: 1;
            color: var(--primary-darker);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .highlight-box {
            background: rgba(142, 202, 230, 0.15);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin: 2rem 0;
            border: 1px solid rgba(33, 158, 188, 0.2);
        }

        .highlight-box h4 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
            color: var(--primary-dark);
        }

        .contact-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
            margin-top: 0.5rem;
        }

        .contact-link:hover {
            color: var(--primary-darker);
            transform: translateX(-5px);
        }

        .section-divider {
            height: 2px;
            background: linear-gradient(to right, transparent, var(--primary-light), transparent);
            margin: 3rem 0;
            border: none;
        }

        /* Footer Styles */
        footer {
            background: linear-gradient(to right, var(--primary-darker), var(--primary-dark));
            color: var(--background);
            padding: 3rem 5% 2rem;
            margin-top: auto;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-logo {
            flex: 1;
            min-width: 250px;
        }

        .footer-logo h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .footer-links {
            flex: 1;
            min-width: 200px;
        }

        .footer-links h4 {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .footer-links h4::after {
            content: '';
            position: absolute;
            bottom: -8px;
            right: 0;
            width: 50px;
            height: 3px;
            background: var(--accent);
            border-radius: var(--border-radius);
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: var(--primary-light);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .footer-links a:hover {
            color: var(--accent);
            transform: translateX(5px);
        }

        .copyright {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 252, 0.2);
            font-size: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .terms-header h1 {
                font-size: 2.2rem;
            }
            
            .terms-content {
                padding: 2rem 1.5rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
        }

        @media (max-width: 480px) {
            .terms-header h1 {
                font-size: 1.8rem;
            }
            
            .terms-content {
                padding: 1.5rem 1rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .terms-content p {
                font-size: 1rem;
            }
            
            .logo-text {
                font-size: 1.4rem;
            }
        }
    </style>
    <div class="terms-container">
        <div class="terms-header">
            <h1 id="terms-title">سياسة الخصوصية</h1>
            <div class="effective-date">تاريخ النفاذ: 3 أغسطس 2025</div>
        </div>
        <div id="terms-content" class="terms-content">
            <div id="privacy-content">
                <div class="important-note">
                    <h3><i class="fas fa-shield-alt"></i> حماية بياناتك</h3>
                    <p>تلتزم منصة "كيف التعليمية" بحماية خصوصية مستخدميها وفقًا للتشريعات الأردنية النافذة، وتوضح السياسة التالية أسس جمع البيانات واستخدامها وحمايتها.</p>
                </div>

                <h2 class="section-title">المادة (1): البيانات التي يتم جمعها</h2>
                <p>تشمل البيانات التي قد نجمعها:</p>
                <ul>
                    <li>معلومات التعريف: الاسم، البريد الإلكتروني، رقم الهاتف، التخصص، الجامعة، سنة الالتحاق.</li>
                    <li>بيانات النشاط: المهام التي تم التفاعل معها، الوقت والتاريخ، الصفحات التي تم تصفحها</li>
                    <li>معلومات الجهاز: عنوان IP، نوع الجهاز، نظام التشغيل</li>
                    <li>بيانات غير تعريفية متعلقة باستخدام خدمات الذكاء الاصطناعي</li>
                </ul>

                <h2 class="section-title">المادة (2): أسس المعالجة القانونية</h2>
                <p>تعالج المنصة البيانات بناءً على:</p>
                <ul>
                    <li><strong>موافقة المستخدم الصريحة</strong> (عند التسجيل أو الاشتراك بالخدمات)</li>
                    <li><strong>تنفيذ التزامات تعاقدية</strong> (مثل تفعيل الاشتراك أو الرد على استفسارات)</li>
                    <li><strong>مصلحة مشروعة</strong> في تحسين تجربة المستخدم وتطوير الخدمات</li>
                    <li><strong>الامتثال للقوانين الأردنية</strong> فيما يتعلق بحفظ السجلات أو كشف المعلومات عند الضرورة القانونية</li>
                </ul>

                <h2 class="section-title">المادة (3): استخدام البيانات</h2>
                <p>تُستخدم البيانات للأغراض التالية:</p>
                <ul>
                    <li>تخصيص تجربة المستخدم</li>
                    <li>إرسال التنبيهات أو التحديثات بموافقته</li>
                    <li>مراقبة وتحسين جودة الخدمات</li>
                    <li>إدارة الاشتراكات والخدمات المدفوعة</li>
                    <li>تحليل التفاعل وتحسين الأداء الأكاديمي</li>
                </ul>

                <h2 class="section-title">المادة (4): مشاركة البيانات</h2>
                <ul>
                    <li><strong>لا تشارك المنصة أي بيانات شخصية</strong> مع جهات خارجية إلا عند الضرورة القصوى لتقديم الخدمة.</li>
                    <li>تشمل الأطراف المسموح لها:
                        <ul>
                            <li>بوابات الدفع الإلكترونية</li>
                            <li>شركات الاتصال لإرسال التنبيهات</li>
                            <li>مزودي خدمة الذكاء الاصطناعي</li>
                        </ul>
                    </li>
                    <li>البيانات التي يتم إرسالها إلى مزودي الذكاء الاصطناعي (مثل Google Gemini) <strong>تُرسل بشكل مجزأ وغير تعريفية</strong> وتخضع بالكامل <strong>لشروط وأحكام مزود الخدمة</strong>.</li>
                    <li>لا يتم استخدام هذه البيانات في أي تدريب خارجي أو لأغراض تسويقية.</li>
                </ul>

                <div class="highlight-box">
                    <h4><i class="fas fa-lock"></i> حماية البيانات</h4>
                    <p>جميع البيانات التي تشاركها مع مزودي الذكاء الاصطناعي يتم تجزئتها وإزالة المعلومات التعريفية منها لحماية خصوصيتك.</p>
                </div>

                <h2 class="section-title">المادة (5): ملفات تعريف الارتباط (Cookies)</h2>
                <p>تستخدم المنصة ملفات تعريف الارتباط لتحسين تجربة الاستخدام، وتحليل الأداء. يمكن للمستخدم تعطيلها من إعدادات المتصفح، علمًا أن بعض الخصائص قد تتأثر.</p>

                <h2 class="section-title">المادة (6): أمن المعلومات</h2>
                <ul>
                    <li>تُشفّر كلمات المرور باستخدام تقنيات لا يمكن عكسها</li>
                    <li>تُخزّن البيانات في بيئة مؤمنة، مزودة بجدران نارية وتقييدات وصول</li>
                    <li>تُستخدم بروتوكولات HTTPS في جميع الاتصالات</li>
                </ul>

                <h2 class="section-title">المادة (7): خصوصية الأطفال</h2>
                <p>يُحظر بشكل صارم تسجيل أو استخدام المنصة من قبل أي شخص دون سن <strong>الثامنة عشرة (18)</strong>. يتم حذف أي حساب يتم إنشاؤه خلافًا لذلك فور اكتشافه.</p>

                <h2 class="section-title">المادة (8): مدة الاحتفاظ بالبيانات</h2>
                <ul>
                    <li>يتم الاحتفاظ بالبيانات طوال مدة النشاط التعليمي للمستخدم.</li>
                    <li>في حال عدم النشاط لمدة <strong>3 سنوات متواصلة</strong>، يتم حذف البيانات تلقائيًا، ما لم توجد التزامات قانونية بخلاف ذلك.</li>
                </ul>

                <h2 class="section-title">المادة (9): حقوق المستخدم</h2>
                <p>يحق للمستخدم:</p>
                <ul>
                    <li>طلب نسخة من بياناته</li>
                    <li>تعديل أو تصحيح بياناته</li>
                    <li>حذف حسابه</li>
                    <li>سحب الموافقة على المعالجة</li>
                    <li>تقديم شكوى إلى الجهة التنظيمية المختصة في الأردن</li>
                </ul>
                <p>للتواصل بشأن الحقوق: <br>
                <a href="mailto:support@howplatform.com" class="contact-link">
                    <i class="fas fa-envelope"></i> support@howplatform.com
                </a></p>

                <h2 class="section-title">المادة (10): تعديل سياسة الخصوصية</h2>
                <p>يجوز للمنصة تعديل هذه السياسة في أي وقت. يتم إشعار المستخدم بأي تعديل عبر المنصة أو البريد الإلكتروني، وتدخل التعديلات حيز التنفيذ <strong>بعد مرور سبعة (7) أيام</strong> من تاريخ النشر.</p>
            </div>
        </div>
    </div>
