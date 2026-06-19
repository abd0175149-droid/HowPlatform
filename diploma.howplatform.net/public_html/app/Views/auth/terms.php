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
            <h1 id="terms-title">الشروط والأحكام العامة</h1>
            <div class="effective-date">تاريخ النفاذ: 3 أغسطس 2025</div>
        </div>
            <div id="terms-content" class="terms-content">
                <div class="important-note">
                    <h3><i class="fas fa-exclamation-circle"></i> ملاحظة هامة</h3>
                    <p>يرجى قراءة هذه الشروط والأحكام بعناية قبل استخدام المنصة. إن دخولك إلى المنصة أو استخدام أي من خدماتها يُعد قبولًا قانونيًا صريحًا منك بجميع الشروط، والالتزام بها دون قيد أو شرط.</p>
                </div>

                <h2 class="section-title">المادة (1): تعريف المنصة</h2>
                <p>تُعرف منصة "كيف التعليمية" بأنها: <strong>منصة إلكترونية تعليمية أردنية</strong> تقدم خدمات تعليمية متنوعة منها خدمات داعمة لطلبة <strong>الدبلوم العالي لاعداد وتأهيل المعلمين (قبل الخدمة) والدبلوم المهني في التعليم (خلال الخدمة)، </strong>  المسجلين في الجامعات والمعاهد الأردنية، وتشمل هذه الخدمات :</p>
                <ul>
                    <li>محتوى تعليمي داعم للمساقات التربوية</li>
                    <li>تحليل المهام والتقييمات</li>
                    <li>خدمات مراجعة لغوية وأكاديمية</li>
                    <li>أدوات تعليمية للاستخدام الصفي</li>
                    <li>ملفات مرئية وصوتية ومكتوبة</li>
                    <li>خدمات استشارية تعتمد على الذكاء الاصطناعي تحت اسم "فطين"</li>
                </ul>

                <h2 class="section-title">المادة (2): أهلية استخدام المنصة</h2>
                <ul>
                    <li>يُحظر تمامًا على أي شخص <strong>لم يبلغ سن 18 عامًا</strong> استخدام المنصة أو أي من خدماتها.</li>
                    <li>يُشترط أن يكون المستخدم شخصًا طبيعيًا بالغًا ذا أهلية قانونية، مسجلاً في أحد برامج الدبلوم أو ذا صلة مباشرة بالعملية التعليمية.</li>
                    <li>تحتفظ المنصة بحقها المطلق في إلغاء أو تعليق أي حساب يثبت أنه خالف هذا الشرط دون إشعار مسبق.</li>
                </ul>

                <h2 class="section-title">المادة (3): إنشاء الحساب واستخدامه</h2>
                <ul>
                    <li>يلتزم المستخدم بتقديم بيانات دقيقة، كاملة، وصحيحة عند إنشاء الحساب.</li>
                    <li>يتحمل المستخدم المسؤولية الكاملة عن سرية اسم المستخدم وكلمة المرور، وأي استخدام غير مشروع يتم من خلال حسابه.</li>
                    <li>يحظر إنشاء أكثر من حساب واحد للشخص نفسه، أو انتحال هوية الغير، أو تقديم بيانات مزيفة.</li>
                </ul>

                <h2 class="section-title">المادة (4): طبيعة الخدمات والغرض منها</h2>
                <ul>
                    <li>تقدم المنصة خدمات تعليمية مساندة <strong>لا تحل محل الطالب ولا تقوم بأداء واجباته الأكاديمية</strong>.</li>
                    <li>كافة المواد والخدمات المقدمة تهدف إلى <strong>تعزيز فهم الطالب للمحتوى الأكاديمي ومهاراته التربوية</strong>، ولا يجوز تقديمها بأي شكل إلى الجهات الأكاديمية أو الجامعات على أنها إنتاج الطالب نفسه.</li>
                    <li>تُخلي المنصة مسؤوليتها الكاملة عن <strong>أي إساءة استخدام</strong>، بما في ذلك مشاركة الملفات كما هي أو تقديمها للجامعات أو المؤسسات التعليمية الرسمية دون تعديل.</li>
                </ul>

                <h2 class="section-title">المادة (5): الترخيص والاستخدام المقبول</h2>
                <ul>
                    <li>تمنح المنصة للمستخدم ترخيصًا محدودًا، غير حصري، غير قابل للتحويل، لاستخدام خدماتها للأغراض التعليمية فقط.</li>
                    <li>يُحظر صراحةً:
                        <ul>
                            <li>نسخ أو توزيع أو إعادة إنتاج أي جزء من المحتوى دون إذن خطي مسبق</li>
                            <li>الاستخدام التجاري للمحتوى أو إعادة بيعه</li>
                            <li>التلاعب بمنصات التقييم أو الدخول غير المشروع إلى أنظمة التشغيل</li>
                            <li>تحميل ملفات ضارة أو غير مشروعة</li>
                        </ul>
                    </li>
                </ul>

                <h2 class="section-title">المادة (6): حقوق الملكية الفكرية</h2>
                <p>جميع المحتويات والبيانات والخدمات المعروضة على المنصة (بما في ذلك النصوص، الشعارات، الرسومات، ملفات الفيديو، الصوت، البرمجيات، الخدمات الذكية) هي ملك حصري للمنصة أو للجهات المرخصة لها، وتخضع للحماية بموجب قوانين حقوق المؤلف والعلامات التجارية الأردنية.</p>

                <h2 class="section-title">المادة (7): الخدمات المدفوعة وسياسة الاسترداد</h2>
                <ul>
                    <li>توفر المنصة اشتراكات مدفوعة متفاوتة حسب المدة والمزايا.</li>
                    <li>يمكن للمستخدم إلغاء الاشتراك واسترداد الرسوم خلال مدة أقصاها <strong>خمسة (5) أيام تقويمية</strong> من تاريخ الدفع.</li>
                    <li>بعد انقضاء المدة المذكورة، <strong>لا تُقبل أي طلبات استرداد</strong> إلا في حالات استثنائية، وبعد تقييم داخلي.</li>
                    <li>عند الموافقة على الاسترداد، تُخصم نسبة <strong>20%</strong> من المبلغ المدفوع كرسوم خدمات إدارية.</li>
                    <li>تتم عمليات الاسترداد فقط عبر طرق معتمدة:
                        <ul>
                            <li>التحويل البنكي (رقم IBAN)</li>
                            <li>خدمة CliQ (رقم الهاتف أو الاسم المختصر Alias)</li>
                            <li>التحويل عبر شركات صرافة أردنية معتمدة (بموجب الرقم الوطني والاسم الرباعي)</li>
                        </ul>
                    </li>
                    <li><strong>لا تطلب المنصة أي بيانات مالية أو مصرفية</strong> خارج هذا السياق.</li>
                </ul>

                <div class="highlight-box">
                    <h4><i class="fas fa-money-bill-wave"></i> سياسة الاسترداد</h4>
                    <p>يجب أن تكون جميع طلبات الاسترداد مرفقة بمستندات داعمة وتتم خلال 5 أيام من تاريخ الدفع. بعد هذه الفترة، لا يمكن معالجة أي طلبات استرداد.</p>
                </div>

                <h2 class="section-title">المادة (8): تعليق الحساب أو إنهاؤه</h2>
                <p>يحق للمنصة تعليق أو إنهاء حساب أي مستخدم دون إشعار مسبق، في الحالات التالية:</p>
                <ul>
                    <li>مخالفة الشروط القانونية</li>
                    <li>إساءة استخدام المنصة أو خدماتها</li>
                    <li>تقديم بيانات غير صحيحة أو انتحال الهوية</li>
                    <li>استخدام الذكاء الاصطناعي لتجاوز المسؤوليات الأكاديمية</li>
                </ul>
                <p>ولا تترتب أي مسؤولية قانونية على المنصة في حال إلغاء الحساب نتيجة لهذه المخالفات.</p>

                <h2 class="section-title">المادة (9): حدود المسؤولية</h2>
                <ul>
                    <li>تقدم المنصة المحتوى والخدمات "كما هي"، دون أي ضمان صريح أو ضمني بخصوص نتائج استخدامها.</li>
                    <li>لا تتحمل المنصة أي التزام تجاه أداء المستخدم الأكاديمي أو النتائج التعليمية.</li>
                    <li>المنصة غير مسؤولة عن تصرفات المستخدم المتعلقة بإساءة استخدام خدمات الذكاء الاصطناعي أو تقديم المحتوى كما هو للجامعات أو المؤسسات.</li>
                </ul>

                <h2 class="section-title">المادة (10): التعديلات على الشروط</h2>
                <p>تحتفظ المنصة بحق تعديل هذه الشروط في أي وقت. يتم إعلام المستخدم بالتعديلات الجديدة <strong>من خلال إشعار على المنصة أو عبر البريد الإلكتروني</strong>. تُعتبر التعديلات سارية ونافذة <strong>بعد مرور سبعة (7) أيام تقويمية</strong> من تاريخ الإشعار، ما لم يُبدِ المستخدم اعتراضًا صريحًا.</p>
            </div>
    </div>
