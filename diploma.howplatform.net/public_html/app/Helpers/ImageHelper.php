<?php
// app/Helpers/image_helper.php

declare(strict_types=1);

/**
 * مُخرِج <img> موحّد مع أبعاد ثابتة ومنع CLS + كاش أبعاد الصورة.
 *
 * @param string $src     مسار الصورة (نسبي من الجذر العام أو URL مطلق)
 * @param array  $attrs   خصائص العنصر <img> مثل ['alt' => '...', 'class' => '...']
 * @param array  $options خيارات إضافية:
 *   - 'lazy' (bool)            : true افتراضيًا للصور غير الحرجة
 *   - 'decoding' (string)      : 'async' افتراضيًا
 *   - 'fetchpriority' (string) : 'high' لصورة LCP
 *   - 'responsive' (bool)      : true لإضافة .responsive-img
 *   - 'fallbackWidth' (int)    : 1200 افتراضيًا عند تعذّر قراءة الأبعاد
 *   - 'fallbackHeight' (int)   : 800 افتراضيًا
 *   - 'srcset' (array)         : مثال ['img-640.jpg 640w','img-1280.jpg 1280w']
 *   - 'sizes'  (string)
 *   - 'appendVersion' (bool)   : true افتراضيًا (يُلحق ?v=mtime للملفات المحليّة)
 *   - 'cacheTtl' (int)         : ثواني كاش الأبعاد (افتراضي 7 أيام)
 */
if (! function_exists('ci_img')) {
    function ci_img(string $src, array $attrs = [], array $options = []): string
    {
        // ===================== الإعدادات الافتراضية =====================
        $opt = array_merge([
            'lazy'           => true,
            'decoding'       => 'async',
            'fetchpriority'  => null,
            'responsive'     => true,
            'fallbackWidth'  => 1200,
            'fallbackHeight' => 800,
            'srcset'         => null,
            'sizes'          => null,
            'appendVersion'  => true,
            'cacheTtl'       => 604800, // 7 أيام
        ], $options);

        // alt إجباري لأجل الوصول
        if (! isset($attrs['alt'])) {
            $attrs['alt'] = '';
        }

        // ===================== تحديد نوع المسار =====================
        $isExternal = (bool) preg_match('#^(https?:)?//#i', $src);
        $absFsPath  = null;
        $mtime      = null;

        if (! $isExternal) {
            // نحاول اكتشاف جذر الويب (FCPATH أو public_html/ أو public/)
            $publicRoots = [];
            if (defined('FCPATH')) {
                $publicRoots[] = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
            $publicRoots[] = rtrim(ROOTPATH . 'public_html', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $publicRoots[] = rtrim(ROOTPATH . 'public', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            $cleanRel = ltrim($src, "/\\");
            foreach ($publicRoots as $root) {
                $try = $root . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cleanRel);
                if (is_file($try)) {
                    $absFsPath = $try;
                    $mtime     = @filemtime($absFsPath) ?: null;
                    break;
                }
            }
        }

        // ===================== قراءة الأبعاد + الكاش =====================
        $width  = null;
        $height = null;

        if ($absFsPath) {
            // مفتاح كاش يحتوي المسار + mtime لضمان إبطال تلقائي عند تغيير الملف
            $key   = 'img:dim:' . md5($absFsPath . '|' . (string) $mtime);
            $cache = cache();

            $dims = $cache ? $cache->get($key) : null;
            if (is_array($dims) && count($dims) === 2) {
                [$width, $height] = $dims;
            } else {
                $info = @getimagesize($absFsPath);
                if (is_array($info) && isset($info[0], $info[1])) {
                    $width  = (int) $info[0];
                    $height = (int) $info[1];
                    if ($cache) {
                        $cache->save($key, [$width, $height], $opt['cacheTtl']);
                    }
                }
            }

            // إلحاق نسخة للـ src لكسر كاش المتصفح عند التحديث (مهم للأفاتار)
            if ($opt['appendVersion'] && $mtime) {
                // لا نكرر ?v= لو موجود مسبقًا
                if (strpos($src, '?') === false) {
                    $src .= '?v=' . $mtime;
                }
            }
        }

        // Fallback عند الفشل أو عند الصور الخارجية
        if (! $width || ! $height) {
            $width  = (int) $opt['fallbackWidth'];
            $height = (int) $opt['fallbackHeight'];
        }

        // ===================== تجميـع الـ class =====================
        $classes = [];
        if (! empty($attrs['class'])) {
            $classes[] = trim((string) $attrs['class']);
        }
        if ($opt['responsive']) {
            $classes[] = 'responsive-img';
        }
        $attrs['class'] = trim(implode(' ', array_filter($classes)));

        // ===================== سمات الوصول والأداء =====================
        $attrs['width']  = (string) $width;
        $attrs['height'] = (string) $height;

        if ($opt['lazy']) {
            $attrs['loading'] = 'lazy';
        }
        if (! empty($opt['decoding'])) {
            $attrs['decoding'] = $opt['decoding']; // 'async'
        }
        if (! empty($opt['fetchpriority'])) {
            $attrs['fetchpriority'] = $opt['fetchpriority']; // 'high' لصورة LCP
        }

        if (! empty($opt['srcset']) && is_array($opt['srcset'])) {
            $attrs['srcset'] = implode(', ', $opt['srcset']);
            if (! empty($opt['sizes'])) {
                $attrs['sizes'] = $opt['sizes'];
            }
        }

        $attrs['src'] = $src;

        // ===================== تحويل المصفوفة إلى HTML =====================
        $htmlAttrs = [];
        foreach ($attrs as $k => $v) {
            $k = htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8');
            $v = htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            $htmlAttrs[] = "{$k}=\"{$v}\"";
        }

        return '<img ' . implode(' ', $htmlAttrs) . ' />';
    }
}
