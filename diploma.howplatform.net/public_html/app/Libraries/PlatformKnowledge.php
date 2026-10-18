<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * معرفة المنصّة لمساعدي الذكاء (فَطين / رد الخدمة).
 *
 * الهدف: تخفيف الحمولة المُرسَلة للـ AI. بدل حشو كل المعرفة في كل نداء:
 *  - وصف أساسي عن المنصّة يُرسَل دائمًا (env: AI_PLATFORM_CONTEXT).
 *  - عناصر معرفة تُجمَّع من مصادر متعددة (حاليًا: أدوات ai_job_types؛ قابلة للتوسّع لاحقًا:
 *    أسئلة شائعة، سياسات، مقاطع محتوى… أضِفها في items()).
 *  - عند تمرير $query (كموضوع/وصف طلب أو رسالة الطالب) نُرسِل فقط العناصر الأوثق صلة
 *    عبر بحث كلمات مفتاحية عربي مبسّط. عند غياب $query تُعاد المعرفة كاملة (توافقًا مع
 *    شات فطين العام).
 *
 * مسار الترقية المستقبلي: استبدال rank() ببحث متجهات عبر App\Services\Rag\EmbeddingService
 * دون تغيير المستدعين (نفس واجهة context()).
 */
class PlatformKnowledge
{
    /** أقصى عدد عناصر معرفة تُرسَل عند تفعيل البحث. */
    private const MAX_ITEMS = 5;

    /** أقصى طول لوصف العنصر عند العرض. */
    private const DESC_MAX = 140;

    /**
     * يبني كتلة سياق نصية: الوصف الأساسي + عناصر المعرفة (كاملة أو الأوثق صلة بالاستعلام).
     */
    public static function context(string $query = ''): string
    {
        $base  = self::base();
        $items = self::items();

        if ($query !== '') {
            $items = self::rank($items, $query, self::MAX_ITEMS);
        }
        if (empty($items)) {
            return $base;
        }

        $lines = [];
        foreach ($items as $it) {
            $title = trim((string) ($it['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $desc = trim((string) preg_replace('/\s+/u', ' ', (string) ($it['body'] ?? '')));
            if (mb_strlen($desc) > self::DESC_MAX) {
                $desc = mb_substr($desc, 0, self::DESC_MAX) . '…';
            }
            $lines[] = '• ' . $title . ($desc !== '' ? ' — ' . $desc : '');
        }
        if (empty($lines)) {
            return $base;
        }

        return $base
            . "\n\nأدوات الذكاء الاصطناعي المتاحة للطلبة حالياً (وجّه الطالب إلى الأداة الأنسب لحاجته بالاسم، واشرح فائدتها بإيجاز):\n"
            . implode("\n", $lines);
    }

    /** الوصف الأساسي عن المنصّة (قابل للتخصيص عبر AI_PLATFORM_CONTEXT). */
    private static function base(): string
    {
        $base = trim((string) (getenv('AI_PLATFORM_CONTEXT') ?: ''));
        if ($base === '') {
            $base = 'سياق المنصّة: «منصة كيف التعليمية» منصّة دبلوم مهني لإعداد وتأهيل المعلمين. '
                . 'توفّر للطلبة (المعلمين المتدربين): محتوى المساقات ومهامها وواجباتها؛ نظام نقاط ومكافآت؛ '
                . 'خدمة دعم/طلبات خدمة متخصّصة يتابعها الفريق الأكاديمي (يمكن للطالب إنشاء «طلب خدمة» من قسم الخدمة)؛ '
                . 'ومجموعة أدوات ذكاء اصطناعي تساعد الطالب في إنجاز مهامه التربوية.';
        }
        return $base;
    }

    /**
     * تجميع عناصر المعرفة من كل المصادر.
     * ➜ لتوسيع المعرفة مستقبلًا: أضِف مصادر جديدة هنا، مثل:
     *    return array_merge(self::toolItems(), self::faqItems(), self::knowledgeItems());
     * كل عنصر: ['title' => string, 'body' => string].
     *
     * @return array<int, array{title:string, body:string}>
     */
    private static function items(): array
    {
        return self::toolItems();
    }

    /** مصدر معرفة: أدوات الطلاب المفعّلة (وغير المخصّصة للأدمن) من ai_job_types. */
    private static function toolItems(): array
    {
        $out = [];
        try {
            $db = \Config\Database::connect();
            if (! $db->tableExists('ai_job_types')) {
                return $out;
            }

            $b = $db->table('ai_job_types')->select('title, description')->where('is_enabled', 1);
            if ($db->fieldExists('is_admin_only', 'ai_job_types')) {
                $b->where('(is_admin_only = 0 OR is_admin_only IS NULL)', null, false);
            }
            $rows = $b->orderBy('id', 'ASC')->get()->getResultArray();

            foreach ($rows as $r) {
                $title = trim((string) ($r['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $out[] = ['title' => $title, 'body' => trim((string) ($r['description'] ?? ''))];
            }
        } catch (\Throwable $e) {
            // غير قاتل
        }
        return $out;
    }

    /**
     * ترتيب العناصر حسب صلتها بالاستعلام عبر بحث كلمات مفتاحية عربي مبسّط.
     * مطابقة العنوان أوزن من مطابقة الوصف. تُعاد العناصر ذات الدرجة > 0 فقط.
     *
     * @param array<int, array{title:string, body:string}> $items
     * @return array<int, array{title:string, body:string}>
     */
    private static function rank(array $items, string $query, int $limit): array
    {
        $qTokens = self::tokens($query);
        if (empty($qTokens)) {
            return array_slice($items, 0, $limit);
        }

        $scored = [];
        foreach ($items as $idx => $it) {
            $nt    = self::normalize((string) ($it['title'] ?? ''));
            $nb    = self::normalize((string) ($it['body'] ?? ''));
            $score = 0;
            foreach ($qTokens as $t) {
                if ($nt !== '' && mb_strpos($nt, $t) !== false) {
                    $score += 3; // مطابقة في العنوان
                } elseif ($nb !== '' && mb_strpos($nb, $t) !== false) {
                    $score += 1; // مطابقة في الوصف
                }
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 'idx' => $idx, 'item' => $it];
            }
        }

        // الأعلى درجةً أولًا، وعند التعادل نحافظ على الترتيب الأصلي.
        usort($scored, static function ($a, $b) {
            return ($b['score'] <=> $a['score']) ?: ($a['idx'] <=> $b['idx']);
        });

        return array_map(static fn ($x) => $x['item'], array_slice($scored, 0, $limit));
    }

    /** تطبيع نص عربي: أحرف موحّدة، بلا تشكيل/تطويل، بلا رموز. */
    private static function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        // إزالة التشكيل والعلامات فوق/تحت الحرف + التطويل (ـ)
        $s = (string) preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
        // توحيد صور الحروف المتقاربة
        $s = strtr($s, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ي', 'ئ' => 'ي', 'ة' => 'ه', 'ؤ' => 'و',
        ]);
        // إبقاء الحروف والأرقام والمسافات فقط
        $s = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /** استخراج كلمات مفتاحية دلالية (طول ≥ 3، بلا كلمات وقف). */
    private static function tokens(string $s): array
    {
        $s = self::normalize($s);
        if ($s === '') {
            return [];
        }
        $stop = self::stopwords();
        $out  = [];
        foreach (explode(' ', $s) as $w) {
            if (mb_strlen($w) < 3) {
                continue;
            }
            if (isset($stop[$w])) {
                continue;
            }
            $out[$w] = true; // إزالة التكرار
        }
        return array_keys($out);
    }

    /** كلمات وقف عربية شائعة (مطبَّعة) لتقليل ضوضاء المطابقة. */
    private static function stopwords(): array
    {
        static $set = null;
        if ($set !== null) {
            return $set;
        }
        $words = [
            'من', 'في', 'على', 'الى', 'عن', 'مع', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي',
            'ما', 'ماذا', 'كيف', 'هل', 'انا', 'اريد', 'احتاج', 'لدي', 'عندي', 'يوجد',
            'كان', 'لكن', 'ايضا', 'كذلك', 'بعض', 'كل', 'عند', 'بين', 'ثم', 'قد', 'لقد',
            'هناك', 'حول', 'عبر', 'بشكل', 'ممكن', 'مساعده', 'طلب', 'خدمه', 'فضلك', 'لو',
            'اذا', 'الرجاء', 'شكرا', 'وهو', 'وهي', 'التربوي', 'التربويه',
        ];
        $set = [];
        foreach ($words as $w) {
            $set[self::normalize($w)] = true;
        }
        return $set;
    }
}
