<style>
    /* ===== News CSS (RTL) ===== */
.news-hero {
    display: flex;
    text-align: center;
    padding: 2.5rem 1rem 1rem;
    background: linear-gradient(180deg, rgba(142, 202, 230, 0.12), transparent);
    border-radius: var(--border-radius, 14px);
    gap: 10px;
    align-items: center;
    justify-content: space-around;
}
.news-badge {
    display: inline-block;
    padding: .35rem .8rem;
    background: rgba(33, 158, 188, 0.12);
    color: var(--primary-darker, #126782);
    border: 1px solid rgba(33,158,188,.25);
    border-radius: 999px;
    font-weight: 700;
    margin-bottom: .75rem;
}
.news-title {
    font-size: clamp(1.8rem, 2.5vw, 2.4rem);
    margin: 0 0 .25rem;
    background: linear-gradient(to left, var(--primary-darker, #126782), var(--primary-dark, #177a98));
    -webkit-background-clip: text; background-clip: text; color: transparent;
}
.news-subtitle { color: var(--text-light, #667); margin: 0 0 .75rem; }
.news-meta {
    display: inline-flex; gap: .75rem; align-items: center; color: var(--text-light, #667);
    font-size: .95rem;
}
.meta-sep { opacity: .6; }

.news-article-container, .news-list-container {
    max-width: 1000px; margin: 2rem auto; padding: 0 1rem;
}

.news-article { background: var(--background, #fff); border: 1px solid rgba(142,202,230,.3);
    border-radius: var(--border-radius, 14px); padding: 1.25rem 1.25rem 1.5rem; box-shadow: var(--shadow, 0 6px 16px rgba(0,0,0,.06)); }

.news-body p { line-height: 1.9; color: var(--text, #111); margin: 1rem 0; }
.news-body .sources { margin-top: 1.5rem; background: rgba(142,202,230,.08); border-radius: 12px; padding: 1rem 1rem; border-inline-start: 4px solid var(--primary, #219EBC); }
.news-body .sources h3 { margin: 0 0 .75rem; color: var(--primary-darker, #126782); font-size: 1.1rem; display: flex; align-items: center; gap: .5rem; }
.news-body .links { list-style: none; padding: 0; margin: 0; }
.news-body .links li { margin: .5rem 0; color: var(--text, #111); }
.news-body a { color: var(--primary, #219EBC); font-weight: 700; text-decoration: none; }
.news-body a:hover { color: var(--primary-dark, #177a98); text-decoration: underline; }

.news-footer { display: flex; justify-content: flex-start; margin-top: 1rem; }
.btn-outline {
    display: inline-flex; align-items: center; gap: .5rem; padding: .6rem 1rem;
    border: 1px solid rgba(33,158,188,.4); border-radius: 10px; color: var(--primary-darker, #126782);
    background: transparent; font-weight: 700; text-decoration: none; transition: .2s ease;
}
.btn-outline:hover { background: rgba(33,158,188,.08); transform: translateY(-1px); }

.share-btn, .copy-btn {
    border: 1px solid rgba(33,158,188,.35);
    background: rgba(33,158,188,.06);
    color: var(--primary-darker, #126782);
    border-radius: 999px; padding: .35rem .75rem; cursor: pointer; font-weight: 700;
}
.share-btn:hover, .copy-btn:hover { background: rgba(33,158,188,.12); }

/* List/Grid */
.news-list-header h1 { margin: 0 0 .25rem; color: var(--primary-darker, #126782); }
.news-list-header .muted { color: var(--text-light, #667); margin: 0 0 1rem; }
.news-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
.news-card { border: 1px solid rgba(142,202,230,.35); border-radius: 14px; background: var(--background, #fff); box-shadow: var(--shadow, 0 6px 16px rgba(0,0,0,.06)); }
.news-card-body { padding: 1rem 1.1rem; }
.news-card-title { margin: .25rem 0 .25rem; font-size: 1.25rem; }
.news-card-title a { color: var(--primary-darker, #126782); text-decoration: none; }
.news-card-title a:hover { text-decoration: underline; }
.news-card-excerpt { color: var(--text, #222); line-height: 1.8; margin: .5rem 0 .9rem; }
.news-card-meta { display: flex; justify-content: space-between; align-items: center; color: var(--text-light, #667); }
.news-card-meta .read-more { display: inline-flex; gap: .4rem; align-items: center; text-decoration: none; font-weight: 700; color: var(--primary, #219EBC); }
.news-card-meta .read-more:hover { color: var(--primary-dark, #177a98); }

@media (max-width: 640px) {
    .news-meta { flex-wrap: wrap; justify-content: center; }
    .news-hero {flex-direction: column;}
}

</style>
<div class="news-article-container" dir="rtl">
    <div class="news-hero">
        <div class="news-badge">الأخبار</div>
        <h1 class="news-title">بيان توضيحي</h1>
        <p class="news-subtitle">رسالة إلى طلبة الدبلوم</p>

        <div class="news-meta">
            <span class="meta-item"><i class="far fa-calendar-alt"></i> <time datetime="<?= date('Y-m-d'); ?>"><?= date('Y-m-d'); ?></time></span>
            <span class="meta-sep">•</span>
            <button class="share-btn" id="shareBtn" type="button" aria-label="مشاركة">
                <i class="fas fa-share-alt"></i> مشاركة
            </button>
            <button class="copy-btn" id="copyLinkBtn" type="button" aria-label="نسخ الرابط">
                <i class="far fa-copy"></i> نسخ الرابط
            </button>
        </div>
    </div>

    <article class="news-article" itemscope itemtype="https://schema.org/Article">
        <meta itemprop="headline" content="بيان توضيحي">
        <meta itemprop="inLanguage" content="ar">
        <meta itemprop="datePublished" content="<?= date('c'); ?>">
        <meta itemprop="dateModified" content="<?= date('c'); ?>">

        <div class="news-body" itemprop="articleBody">
            <p>إلى طلبة الدبلوم،</p>

            <p>نكتب إليكم اليوم حرصًا على أن تبقى الحقيقة بسيطة وواضحة، وأن تصل المعلومة إليكم من مصدرها بلا ضجيج. اخترنا منذ البدء أن نعمل وفق خطة لا وفق انفعال: نهيّئ المحتوى مبكرًا، نراجعه، ثم ننشره بالتزامن على جميع قنواتنا الرسمية، ويظلّ مرجعنا الثابت منشوراتنا وروابط المصادر الرسمية التي تجمع التفاصيل وتحدّد المواعيد والمتطلبات كما وردت من الجهات المختصة. بهذه الطريقة، لا تضيع الرسالة بين اجتهادات متفرّقة ولا تتبدّد في تفسيرات متعارضة.</p>

            <p>فلسفتنا في الخدمة بسيطة: كل سؤال يستحق إجابة سريعة ومحترفة، وكل تكرار للسؤال حقّ لا يُستهجن. ما يهمّنا أن يغادر الطالب محادثته وهو مطمئن؛ لذلك التزم فريق الدعم —ومعه خدمة الذكاء الاصطناعي “فَطين”— بالرد المتزن واللبق على مدى الفترة الماضية، وتعامل مع آلاف الاستفسارات عبر منصّتنا وقنواتنا، بإيقاعٍ واحدٍ يقدّم المعلومة قبل الانطباع، والهدوء قبل الاندفاع.</p>

            <p>ونودّ هنا إضاءة نقطة جوهرية: ما نقوم به هو مساندة للطلبة مثل ما تبذله جهات رسمية أو شبه رسمية أو مبادرات فردية مشكورة؛ غير أنّنا انطلقنا لمعالجة ما خلّفه هذا المشهد من تشتّت عبر عملٍ جماعيٍّ مهنيٍّ غير رسمي، يلتزم ألّا يستقصي المعلومة إلا من مصادرها. لا نبحث عن سبقٍ صحفي؛ نبحث عن خدمةٍ واضحةٍ ومناسبة. ونرفض أي ادّعاء بلا دليل أو إساءة من أي شخصٍ أو جهةٍ كانت؛ وكما يُقال: «كلُّ مُعمِّمٍ جاهل». ونشكر كل من يبذل جهده في خدمة الطلبة، فكلٌّ يعمل بقدر استطاعته.</p>

            <p>نعي أنّ المعلومات حين تتناثر تُربك أصحابها قبل أن تفيدهم؛ لذا حرصنا على وحدة المصدر ووحدة الرسالة: منشور موحّد التوقيت على القنوات، وروابط موثّقة يَسهُل تتبّعها. ولسنا جهةً ناطقة باسم جامعة أو مؤسسة، ولا ندّعي ذلك؛ دورنا أن نُحسن القراءة من المصادر، وأن نقدّم الإرشاد العملي الذي يختصر على الطالب الطريق، وأن نُشير دائمًا إلى الأصل الذي استقينا منه. ما كان واضحًا نُثبته، وما كان ملتبسًا نراجعه ونصحّحه علنًا متى لزم، لأن الخطأ يُصلَح بالمصدر لا بالصوت المرتفع.</p>

            <p>خصوصيتكم ليست بندًا جانبيًا. رسائلكم الخاصة تظلّ خاصة، وبياناتكم لا تُنشر ولا تُتداول خارج نطاق الخدمة. وحين نختلف مع أسلوبٍ يربك أو يُشتّت، نختار أن نُظهر بديلًا منظمًا بدل أن نُسمّي أو نحذّر بلا سند. هدفنا خدمة مجتمع الدبلوم لا المنافسة على مساحته؛ لذلك سنبقى نمتنع عن الإساءة، ونُؤثر التقدير لمن يعمل بإخلاص، ونفتح قنواتنا لأي ملاحظة موثّقة تصلنا لنراجعها بجدية ومسؤولية.</p>

            <p>إن احتجتم إلى أصل معلومة أو توضيح إجراء، فمرجعكم الدائم منشوراتنا وروابط المصادر المعلنة على قنواتنا الرسمية. وإن كان لديكم استفسار شخصي، فنحن أقرب ممّا تظنون: رسالة واحدة على الخاص تكفي لنبدأ من حيث أنتم، وبالوتيرة التي تناسبكم. إننا نختار النظام على الارتجال، والوضوح على التشتيت، والاحترام على الخصومة؛ وهذه اختيارات سنتمسّك بها ما بقينا نخدم هذا المجتمع.</p>

            <section class="sources">
                <h3><i class="fas fa-link"></i> روابطنا الرسمية  </h3>
                <ul class="links">
                    <li><strong>فيسبوك:</strong> 
                        <a href="https://web.facebook.com/people/%D9%85%D9%86%D8%B5%D8%A9-%D9%83%D9%8A%D9%81-%D8%A7%D9%84%D8%AA%D8%B9%D9%84%D9%8A%D9%85%D9%8A%D8%A9-%D8%A7%D9%84%D8%AF%D8%A8%D9%84%D9%88%D9%85-%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%8A/61579341362175/?_rdc=1&_rdr#" target="_blank" rel="noopener">الصفحة الرسمية</a>
                    </li>
                    <li><strong>إنستغرام:</strong> 
                        <a href="https://www.instagram.com/diploma_how?utm_source=ig_web_button_share_sheet&igsh=Z2RyM3V1djF5cnlm" target="_blank" rel="noopener">@diploma_how</a>
                    </li>
                    <li><strong>تيليغرام:</strong> 
                        <a href="https://t.me/howplatform_diploma" target="_blank" rel="noopener">@howplatform_diploma</a>
                    </li>
                    <li><strong>قناة الواتساب:</strong> 
                        <a href="https://www.whatsapp.com/channel/0029Vb6wfhp84OmA60KB2h2Q" target="_blank" rel="noopener">HOW Platform – Channel</a>
                    </li>
                    <li><strong>الواتساب المباشر:</strong> 
                        <a href="http://wa.me/962782550901" target="_blank" rel="noopener">00962782550901</a>
                    </li>
                </ul>
            </section>
        </div>

        <footer class="news-footer">
            <a class="btn-outline" href="/" aria-label="العودة إلى صفحة الرئيسية">
                <i class="fas fa-long-arrow-alt-right"></i> العودة للصفحة الرئيسية
            </a>
        </footer>
    </article>
</div>
<script>
    /* واجهة المشاركة/النسخ لصفحة الأخبار (RTL) */
// ملاحظة: لا يعتمد على SweetAlert. يقدّم Toast بسيط بدون أي مكتبات.
(function () {
    'use strict';

    // عنصر Toast بسيط
    function toast(msg) {
        const t = document.createElement('div');
        t.setAttribute('dir', 'rtl');
        t.textContent = msg;
        Object.assign(t.style, {
            position: 'fixed',
            bottom: '16px',
            right: '16px',
            background: 'rgba(18, 103, 130, .95)',
            color: '#fff',
            padding: '10px 14px',
            borderRadius: '10px',
            fontWeight: '700',
            zIndex: 9999,
            boxShadow: '0 6px 16px rgba(0,0,0,.2)'
        });
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 1800);
        setTimeout(() => t.remove(), 2200);
    }

    function currentUrl() {
        return window.location.href;
    }

    function share() {
        const url = currentUrl();
        const title = document.querySelector('.news-title')?.textContent?.trim() || 'خبر';
        const text = 'اطّلع على هذا الخبر من منصة "كيف" التعليمية: ' + title;

        if (navigator.share) {
            navigator.share({ title, text, url }).catch(() => {});
            return;
        }

        // روابط مشاركة بديلة
        const encoded = encodeURIComponent(url);
        const q = encodeURIComponent(title);
        const links = [
            `https://www.facebook.com/sharer/sharer.php?u=${encoded}`,
            `https://t.me/share/url?url=${encoded}&text=${q}`,
            `https://api.whatsapp.com/send?text=${q}%20${encoded}`
        ];
        window.open(links[0], '_blank'); // افتراضي: فيسبوك
        toast('تم فتح نافذة المشاركة.');
    }

    function copyLink() {
        const url = currentUrl();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => toast('تم نسخ الرابط.'));
            return;
        }
        //Fallback
        const tmp = document.createElement('input');
        tmp.value = url;
        document.body.appendChild(tmp);
        tmp.select(); document.execCommand('copy');
        document.body.removeChild(tmp);
        toast('تم نسخ الرابط.');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const shareBtn = document.getElementById('shareBtn');
        const copyBtn = document.getElementById('copyLinkBtn');
        if (shareBtn) shareBtn.addEventListener('click', share);
        if (copyBtn) copyBtn.addEventListener('click', copyLink);
    });
})();

</script>

