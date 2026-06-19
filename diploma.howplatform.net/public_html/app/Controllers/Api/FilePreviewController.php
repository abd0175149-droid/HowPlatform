<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Libraries\JwtHelper;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * يولّد صور معاينة لملفات PDF و Office (Word/Excel/PowerPoint...) مع كاش على القرص.
 * يعتمد Imagick و(اختياريًا) LibreOffice/unoconv لتحويل Office إلى PDF.
 *
 * المعيار:
 *   GET/HEAD /api/files/{id}/preview?page=1..n&fmt=jpg|webp&w=1000
 *      - page: رقم الصفحة (1..n) — الافتراضي 1
 *      - fmt : jpg | webp        — الافتراضي jpg
 *      - w   : عرض الصورة بكسل   — الافتراضي 1000 (حد أقصى 1600)
 *
 * يعتمد صلاحيات العرض عبر service('fileAccessPolicy') (وضع stream).
 */
class FilePreviewController extends Controller
{
    /** @var \CodeIgniter\Database\BaseConnection */
    private $db;

    /** @var \CodeIgniter\Cache\CacheInterface */
    private $cache;

    /** مدة كاش المتصفح (ثواني) */
    private const BROWSER_CACHE_TTL = 604800; // 7 أيام

    /** الحد الأقصى للعرض */
    private const MAX_WIDTH = 1600;

    /** جودة JPEG/WEBP */
    private const JPEG_QUALITY = 86;
    private const WEBP_QUALITY = 82;

    public function __construct()
    {
        $this->db    = \Config\Database::connect();
        $this->cache = \Config\Services::cache();
    }

    /**
     * GET/HEAD /api/files/{id}/preview
     */
    public function image($fileId = null): ResponseInterface
    {
        $fileId = (int) $fileId;
        if ($fileId <= 0) {
            return $this->fail(404, 'File not found');
        }

        $method = strtoupper($this->request->getMethod());

        // 🧾 معلمات الاستعلام
        $page = max(1, (int) $this->request->getGet('page'));
        $fmt  = strtolower((string) ($this->request->getGet('fmt') ?? 'jpg'));
        $w    = (int) ($this->request->getGet('w') ?? 1000);
        $w    = ($w > 0) ? min($w, self::MAX_WIDTH) : 1000;
        if (!in_array($fmt, ['jpg', 'jpeg', 'webp'], true)) {
            $fmt = 'jpg';
        }
        $extOut = ($fmt === 'webp') ? 'webp' : 'jpg';

        // 🔍 Debug اختياري: ?debug=1 يضيف هيدرات تشخيصية
        $debug = (string) $this->request->getGet('debug') === '1';

        // 🧑‍💻 تحديد المستخدم من JWT أو Session
        $userId = null;
        try { $userId = JwtHelper::currentUserId($this->request); } catch (\Throwable $e) {}
        if (!$userId) {
            $session = session();
            $userId  = (int) ($session->get('user_id') ?? 0);
        }
        if (!$userId) {
            return $this->fail(401, 'Unauthorized');
        }

        // 🧩 Imagick متطلب أساسي
        if (!class_exists(\Imagick::class)) {
            return $this->fail(501, 'Imagick not available on server');
        }

        // 📄 جلب بيانات الملف
        try {
            $q = $this->db->table('files')
                ->select('id, file_name, mime_type, file_size_bytes, is_premium, allow_public_view, file_path, uploaded_at')
                ->where('id', $fileId)
                ->get();
        } catch (\Throwable $e) {
            log_message('error', '[preview] DB select exception for files.id={id}: {err}', ['id' => $fileId, 'err' => $e->getMessage()]);
            return $this->fail(500, 'DB error');
        }
        if ($q === false) {
            log_message('error', '[preview] DB select failed for files.id={id}', ['id' => $fileId]);
            return $this->fail(500, 'DB error');
        }
        $row = $q->getRowArray();
        if (!$row) {
            return $this->fail(404, 'File not found');
        }

        $mime     = strtolower((string) ($row['mime_type'] ?? ''));
        $fileName = (string) ($row['file_name'] ?? '');
        $isOffice = $this->isOfficeLike($mime, $fileName);

        // 🔐 فحص صلاحية العرض (stream) — نفس منطق الواجهة
        $meta = [
            'id'                => (int) $row['id'],
            'mime_type'         => (string) $row['mime_type'],
            'is_premium'        => (int) ($row['is_premium'] ?? 0),
            'allow_public_view' => $row['allow_public_view'] ?? 0,
        ];
        $policy    = service('fileAccessPolicy');
        $checkView = $policy->canAccess($userId, $meta, false);
        if (!($checkView['allowed'] ?? false)) {
            return $this->fail(403, 'Preview not allowed');
        }

        // 📦 إيجاد المسار الفيزيائي للملف (حلّ 404 بسبب اختلاف مواقع التخزين على Hostinger)
        [$srcPath, $candidatesDebug] = $this->resolveFilePath($row, true);
        if (!$srcPath || !is_file($srcPath)) {
            if ($debug) {
                $this->response->setHeader('X-Preview-Debug', 'miss-path');
                $this->response->setHeader('X-Preview-FilePath', (string) ($row['file_path'] ?? ''));
                $this->response->setHeader('X-Roots-Tried', implode(' | ', $candidatesDebug));
            }
            return $this->fail(404, 'Storage file not found');
        }
        if ($debug) {
            $this->response->setHeader('X-Preview-Debug', 'hit-path');
            $this->response->setHeader('X-Resolved-Path', $srcPath);
        }

        // 🗝️ بصمة للكاش تعتمد uploaded_at و mtime/size لتفريغ تلقائي عند التغيير
        $hash = $this->makeContentHash($srcPath, (string) ($row['uploaded_at'] ?? ''));

        // 📁 مجلد كاش الإخراج
        $outDir = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'previews' . DIRECTORY_SEPARATOR . $fileId . DIRECTORY_SEPARATOR . $hash;
        if (!is_dir($outDir) && !@mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            return $this->fail(500, 'Failed to prepare cache directory');
        }

        // ملف الصورة الهدف
        $outPath = $outDir . DIRECTORY_SEPARATOR . 'page-' . $page . '.' . $extOut;

        // ✅ إن كانت الصورة موجودة مسبقًا
        if (is_file($outPath)) {
            return $this->respondImage($outPath, $extOut, $srcPath, true);
        }

        // 🌀 قفل بسيط لتفادي السباقات
        $lockPath = $outDir . DIRECTORY_SEPARATOR . '.__lock';
        $lock     = @fopen($lockPath, 'c+');
        if ($lock) { @flock($lock, LOCK_EX); }

        try {
            // قد تكون تولّدت أثناء انتظار القفل
            if (is_file($outPath)) {
                if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                return $this->respondImage($outPath, $extOut, $srcPath, true);
            }

            // ===== توليد حسب النوع =====
            if ($mime === 'application/pdf' || (!$isOffice && $this->looksLikePdfByExt($fileName))) {
                try {
                    $ok = $this->renderPdfPage($srcPath, $page, $outPath, $w, $extOut);
                } catch (\ImagickException $e) {
                    // شائع على الاستضافات المشتركة: policy "not authorized" لقراءة PDF
                    $msg = strtolower($e->getMessage());
                    if (strpos($msg, 'not authorized') !== false || strpos($msg, 'policy') !== false || strpos($msg, 'pdf') !== false) {
                        log_message('error', '[preview] PDF policy prevented rasterizing: {m}', ['m' => $e->getMessage()]);
                        return $this->fail(501, 'PDF preview disabled by ImageMagick policy');
                    }
                    throw $e;
                }

                if (!$ok) {
                    return $this->fail(404, 'Page not found');
                }
            } else {
                // Word/Excel/PowerPoint → PDF → Image
                $pdfPath = $this->convertOfficeToPdf($srcPath);
                if (!$pdfPath) {
                    return $this->fail(501, 'Office converter not available');
                }

                $ok = $this->renderPdfPage($pdfPath, $page, $outPath, $w, $extOut);
                if (!$ok) {
                    return $this->fail(404, 'Page not found');
                }
            }
        } catch (\ImagickException $e) {
            log_message('error', '[preview] Imagick error: {m}', ['m' => $e->getMessage()]);
            return $this->fail(500, 'Imagick error');
        } catch (\Throwable $e) {
            log_message('error', '[preview] Generation error: {m}', ['m' => $e->getMessage()]);
            return $this->fail(500, 'Failed to generate preview');
        } finally {
            if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
        }

        // 🔁 الرد النهائي
        return $this->respondImage($outPath, $extOut, $srcPath, false);
    }

    /**
     * POST /api/files/{id}/preview/purge  — مسح كاش المعاينات لهذا الملف
     */
    public function purge($fileId = null): ResponseInterface
    {
        $fileId = (int) $fileId;
        if ($fileId <= 0) return $this->fail(404, 'File not found');

        $dir = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'previews' . DIRECTORY_SEPARATOR . $fileId;
        $deleted = 0;

        if (is_dir($dir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $path = $f->getPathname();
                try {
                    if ($f->isDir()) { if (@rmdir($path)) $deleted++; }
                    else { if (@unlink($path)) $deleted++; }
                } catch (\Throwable $e) { /* تجاهل */ }
            }
        }

        return $this->response->setJSON(['success' => true, 'deleted' => $deleted]);
    }

    /* ============================================================
     *                     Helpers (Core)
     * ==========================================================*/

    /** يبني بصمة للمحتوى لتفريغ الكاش عند التغيير */
    private function makeContentHash(string $filePath, string $uploadedAt = ''): string
    {
        $st = @stat($filePath) ?: [];
        $seed = implode('|', [
            $uploadedAt,
            (string) ($st['mtime'] ?? 0),
            (string) ($st['size']  ?? 0),
            basename($filePath),
        ]);
        return substr(sha1($seed), 0, 16);
    }

    /**
     * يحدد مسار الملف الفيزيائي بناءً على سجل DB (سيناريوهات Hostinger)
     * - تُعيد [path|null, candidates[]] عندما $withDebug=true
     */
    private function resolveFilePath(array $row, bool $withDebug = false): array
    {
        $candidatesDebug = [];
        $p = trim((string) ($row['file_path'] ?? ''));
        if ($p === '') {
            return [null, $candidatesDebug];
        }

        // 🧭 تمييز "مطلق فعلي" مقابل "نسبي مع سلاش بادئ"
        $isWindowsAbs = (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $p);
        $isUnixAbs    = (strlen($p) > 0 && $p[0] === '/');

        // إن كان مطلقًا ويشير لملف موجود — أعده مباشرة
        if (($isWindowsAbs || $isUnixAbs) && is_file($p)) {
            return [$p, $candidatesDebug];
        }

        // ⚠️ إن كان يبدأ بسلاش لكنه غير موجود => نتعامل معه كمسار "نسبي" تحت جذور الويب
        $pRel = ltrim($p, "/\\");

        // جذور شائعة: parent(FCPATH) = public_html، ثم FCPATH (public/)، ثم WRITEPATH و ROOTPATH
        $publicRoot = rtrim(dirname(rtrim(FCPATH, DIRECTORY_SEPARATOR)), DIRECTORY_SEPARATOR);

        $roots = [
            $publicRoot,
            rtrim(FCPATH, DIRECTORY_SEPARATOR),
            rtrim(WRITEPATH, DIRECTORY_SEPARATOR),
            rtrim(ROOTPATH, DIRECTORY_SEPARATOR),
        ];

        // جذور إضافية من البيئة: PREVIEW_EXTRA_ROOTS="/home/xxx/storage,/home/xxx/public_html/uploads"
        $extraRoots = (string) getenv('PREVIEW_EXTRA_ROOTS') ?: '';
        foreach (array_filter(array_map('trim', explode(',', $extraRoots))) as $root) {
            if ($root !== '') {
                $roots[] = rtrim($root, DIRECTORY_SEPARATOR);
            }
        }

        foreach ($roots as $base) {
            $abs = $base . DIRECTORY_SEPARATOR . $pRel;
            $candidatesDebug[] = $abs;
            if (is_file($abs)) {
                return [$abs, $candidatesDebug];
            }
        }

        // محاولة أخيرة: إن كان المسار الأصلي (حتى لو بسلاش بادئ) أصبح ملفًا بعد دمج base
        return [null, $candidatesDebug];
    }

    /** يردّ الصورة مع هيدرات كاش مناسبة + X-Preview-Page-Count إذا توفّر */
    private function respondImage(string $path, string $ext, string $srcPath, bool $fromCache): ResponseInterface
    {
        $mime = ($ext === 'webp') ? 'image/webp' : 'image/jpeg';

        $stSrc = @stat($srcPath) ?: [];
        $stOut = @stat($path)    ?: [];
        $last  = gmdate('D, d M Y H:i:s', (int) ($stOut['mtime'] ?? time())) . ' GMT';
        $etag  = '"' . substr(sha1(($stSrc['mtime'] ?? 0) . '|' . ($stOut['mtime'] ?? 0) . '|' . basename($path)), 0, 20) . '"';

        // عدّاد الصفحات إن كان مخزونًا
        $countFile = dirname($path) . DIRECTORY_SEPARATOR . '.__count';
        $pageCount = is_file($countFile) ? (int) @file_get_contents($countFile) : 0;

        // شرطية If-None-Match / If-Modified-Since
        $reqEtag = trim($this->request->getHeaderLine('If-None-Match')) ?: '';
        $reqMod  = trim($this->request->getHeaderLine('If-Modified-Since')) ?: '';
        if ($reqEtag === $etag || $reqMod === $last) {
            $resp304 = $this->response
                ->setStatusCode(304)
                ->setHeader('ETag', $etag)
                ->setHeader('Last-Modified', $last)
                ->setHeader('Cache-Control', 'public, max-age=' . self::BROWSER_CACHE_TTL)
                ->setHeader('X-Preview-From-Cache', $fromCache ? '1' : '0');

            if ($pageCount > 0) {
                $resp304->setHeader('X-Preview-Page-Count', (string) $pageCount);
            }
            return $resp304;
        }

        $resp = $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Length', (string) filesize($path))
            ->setHeader('ETag', $etag)
            ->setHeader('Last-Modified', $last)
            ->setHeader('Cache-Control', 'public, max-age=' . self::BROWSER_CACHE_TTL)
            ->setHeader('X-Preview-From-Cache', $fromCache ? '1' : '0');

        if ($pageCount > 0) {
            $resp->setHeader('X-Preview-Page-Count', (string) $pageCount);
        }

        // HEAD => هيدرات فقط
        if (strtoupper($this->request->getMethod()) === 'HEAD') {
            return $resp->setStatusCode(200);
        }

        return $resp->setStatusCode(200)->setBody(file_get_contents($path) ?: '');
    }

    /**
     * يرسم صفحة واحدة من PDF كصورة.
     * يعيد true عند النجاح، false إن كانت الصفحة خارج النطاق.
     */
    private function renderPdfPage(string $pdfPath, int $page, string $outPath, int $width, string $ext): bool
    {
        if (!is_file($pdfPath)) return false;

        // نحتاج عدد الصفحات للتحقق
        $pgCount = $this->getPdfPageCount($pdfPath);
        if ($pgCount === 0 || $page > $pgCount) {
            return false;
        }

        // ⚙️ ضبط الدقة قبل القراءة ليخرج حجم جيد
        $im = new \Imagick();
        $im->setResolution(144, 144); // DPI
        $im->readImage(sprintf('%s[%d]', $pdfPath, $page - 1)); // صفحة محددة (0-based)
        $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $im->setImageBackgroundColor('white');
        $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

        // تصغير للحجم المطلوب
        $im->thumbnailImage($width, 0, true, true);

        // تنسيق الإخراج
        if ($ext === 'webp') {
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality(self::WEBP_QUALITY);
        } else {
            $im->setImageFormat('jpeg');
            $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $im->setImageCompressionQuality(self::JPEG_QUALITY);
        }

        // حفظ
        $ok = $im->writeImage($outPath);
        $im->clear();
        $im->destroy();

        // تخزين عدد الصفحات لمصلحة HEAD لاحقًا
        if ($ok) {
            @file_put_contents(dirname($outPath) . DIRECTORY_SEPARATOR . '.__count', (string) $pgCount, LOCK_EX);
        }

        return (bool) $ok;
    }

    /** يُعيد عدد صفحات PDF بسرعة (باستخدام pingImage) */
    private function getPdfPageCount(string $pdfPath): int
    {
        try {
            $im = new \Imagick();
            $im->pingImage($pdfPath); // أسرع (بدون rasterize)
            $n = $im->getNumberImages();
            $im->clear();
            $im->destroy();
            return (int) $n;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * يحاول تحويل مستند Office إلى PDF باستخدام soffice أو unoconv.
     * يعيد مسار PDF أو null إن تعذّر.
     */
    private function convertOfficeToPdf(string $srcPath): ?string
    {
        // مجلد عمل مؤقت لكل طلب/ملف
        $tmp = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'previews_tmp';
        if (!is_dir($tmp)) { @mkdir($tmp, 0775, true); }
        $outDir = $tmp . DIRECTORY_SEPARATOR . 'ofc_' . substr(sha1($srcPath), 0, 10);
        if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }

        $bin = $this->findBinary(['soffice', 'libreoffice', 'unoconv']);
        if (!$bin) {
            return null; // لا محوّل متاح على الخادم
        }

        $base    = pathinfo($srcPath, PATHINFO_FILENAME);
        $pdfPath = $outDir . DIRECTORY_SEPARATOR . $base . '.pdf';

        // إن كان سبق تحويله
        if (is_file($pdfPath)) {
            return $pdfPath;
        }

        // أمر التحويل
        if ($bin === 'unoconv') {
            $cmd = sprintf(
                'unoconv -f pdf -o %s %s 2>&1',
                escapeshellarg($pdfPath),
                escapeshellarg($srcPath)
            );
        } else {
            // soffice/libreoffice (headless)
            $cmd = sprintf(
                '%s --headless --nologo --nolockcheck --norestore --convert-to pdf --outdir %s %s 2>&1',
                escapeshellcmd($bin),
                escapeshellarg($outDir),
                escapeshellarg($srcPath)
            );
        }

        // احتياطات موارد
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $output = @shell_exec($cmd);
        if (!is_file($pdfPath)) {
            log_message('error', '[preview] Office->PDF failed: {cmd} :: {out}', ['cmd' => $cmd, 'out' => $output]);
            return null;
        }
        return $pdfPath;
    }

    /** يحاول إيجاد مسار تنفيذ لأحد الأوامر المعطاة (يرجع الاسم إن لم يحصل على المسار) */
    private function findBinary(array $names): ?string
    {
        foreach ($names as $n) {
            $out = @shell_exec('command -v ' . escapeshellcmd($n) . ' 2>/dev/null') ?: '';
            if (trim($out) !== '') return $n;
            $out = @shell_exec('which ' . escapeshellcmd($n) . ' 2>/dev/null') ?: '';
            if (trim($out) !== '') return $n;
        }
        return null;
    }

    /* ============================================================
     *                     Helpers (Classification)
     * ==========================================================*/

    /** يحدّد إن كان الامتداد يشير لـ PDF فقط */
    private function looksLikePdfByExt(string $fileName): bool
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return ($ext === 'pdf');
    }

    /** هل الملف من نوع Office (Word/Excel/PowerPoint/ODF/RTF)؟ */
    private function isOfficeLike(string $mime, string $fileName): bool
    {
        $mime = strtolower($mime);
        $ext  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // فحص بالـ MIME
        if (
            str_contains($mime, 'msword') ||
            str_contains($mime, 'officedocument') ||
            str_contains($mime, 'mspowerpoint') ||
            str_contains($mime, 'vnd.ms-powerpoint') ||
            str_contains($mime, 'vnd.ms-excel') ||
            str_contains($mime, 'spreadsheetml') ||
            str_contains($mime, 'presentationml') ||
            str_contains($mime, 'opendocument') ||
            str_contains($mime, 'rtf')
        ) {
            return true;
        }

        // فحص بالامتداد
        return in_array($ext, [
            'doc','docx','dot','dotx',
            'xls','xlsx','xlsb','xlsm',
            'ppt','pptx','pps','ppsx',
            'odt','ods','odp','rtf'
        ], true);
    }

    /* ============================================================
     *                     Helpers (Responses)
     * ==========================================================*/

    private function fail(int $code, string $message): ResponseInterface
    {
        // HEAD => هيدرات فقط بدون جسم
        if (strtoupper($this->request->getMethod()) === 'HEAD') {
            return $this->response->setStatusCode($code)
                ->setHeader('Cache-Control', 'no-store')
                ->setHeader('X-Preview-Error', $message);
        }
        return $this->response->setStatusCode($code)->setJSON([
            'success' => false,
            'message' => $message,
        ]);
    }
}
