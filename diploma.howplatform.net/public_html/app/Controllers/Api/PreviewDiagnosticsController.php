<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * يفحص جاهزية توليد المعاينات: Imagick, PDF policy, shell_exec, مسارات الكتابة...
 */
class PreviewDiagnosticsController extends Controller
{
    public function index(): ResponseInterface
    {
        $caps = [
            'paths' => [
                'FCPATH'    => rtrim((string) FCPATH, '/\\'),
                'WRITEPATH' => rtrim((string) WRITEPATH, '/\\'),
                'ROOTPATH'  => rtrim((string) ROOTPATH, '/\\'),
                'publicRoot'=> rtrim(\dirname(rtrim((string) FCPATH, '/\\')), '/\\'),
            ],
            'writable' => [
                'write_previews'    => $this->ensureDir(WRITEPATH . 'previews'),
                'write_previews_tmp'=> $this->ensureDir(WRITEPATH . 'previews_tmp'),
            ],
            'php' => [
                'shell_exec_available' => $this->fnAvailable('shell_exec'),
                'exec_available'       => $this->fnAvailable('exec'),
                'system_available'     => $this->fnAvailable('system'),
                'proc_open_available'  => $this->fnAvailable('proc_open'),
                'curl_available'       => $this->fnAvailable('curl_init'),
                'disable_functions'    => (string) \ini_get('disable_functions'),
            ],
            'imagick' => [
                'loaded'  => \class_exists(\Imagick::class),
                'version' => null,
                'pdf_supported' => null, // هل صيغة PDF ظاهرة ضمن الفورمات؟
                'policy_pdf_read_ok' => null, // محاولة ping لملف PDF (إن توفر)
            ],
            'binaries' => [
                'soffice'     => null,
                'libreoffice' => null,
                'unoconv'     => null,
            ],
            'env' => [
                'PREVIEW_EXTRA_ROOTS'   => (string) \getenv('PREVIEW_EXTRA_ROOTS') ?: '',
                'PREVIEW_CONVERTER_URL' => (string) \getenv('PREVIEW_CONVERTER_URL') ?: '',
            ],
        ];

        // Imagick
        if ($caps['imagick']['loaded']) {
            try {
                $v = \Imagick::getVersion();
                $caps['imagick']['version'] = $v['versionString'] ?? \json_encode($v);
            } catch (\Throwable $e) {
                $caps['imagick']['version'] = 'unknown: ' . $e->getMessage();
            }
            try {
                $im = new \Imagick();
                $formats = $im->queryFormats('PDF');
                $im->clear(); $im->destroy();
                $caps['imagick']['pdf_supported'] = !empty($formats);
            } catch (\Throwable $e) {
                $caps['imagick']['pdf_supported'] = false;
            }

            // اختبار بسيط لسياسة PDF (لن ينجح إن كانت السياسة تمنع PDF)
            $caps['imagick']['policy_pdf_read_ok'] = $this->tryPdfPolicy();
        }

        // binaries (فقط إن كانت دوال الشل متاحة)
        if ($caps['php']['shell_exec_available']) {
            $caps['binaries']['soffice']     = $this->which('soffice');
            $caps['binaries']['libreoffice'] = $this->which('libreoffice');
            $caps['binaries']['unoconv']     = $this->which('unoconv');
        } else {
            $caps['binaries']['soffice']     = 'disabled';
            $caps['binaries']['libreoffice'] = 'disabled';
            $caps['binaries']['unoconv']     = 'disabled';
        }

        return $this->response->setJSON([
            'success' => true,
            'data'    => $caps,
        ]);
    }

    private function ensureDir(string $dir): array
    {
        $okCreate = true;
        if (!\is_dir($dir)) {
            $okCreate = @\mkdir($dir, 0775, true);
        }
        $isDir     = \is_dir($dir);
        $isWritable= $isDir && \is_writable($dir);
        return [
            'dir'         => rtrim($dir, '/\\'),
            'exists'      => $isDir,
            'created'     => $okCreate && $isDir,
            'is_writable' => $isWritable,
        ];
    }

    private function fnAvailable(string $fn): bool
    {
        return \function_exists($fn) && \is_callable($fn);
    }

    private function which(string $bin): ?string
    {
        $out = @\shell_exec('command -v ' . \escapeshellcmd($bin) . ' 2>/dev/null') ?: '';
        $out = \trim($out);
        if ($out !== '') return $out;
        $out = @\shell_exec('which ' . \escapeshellcmd($bin) . ' 2>/dev/null') ?: '';
        $out = \trim($out);
        return $out !== '' ? $out : null;
    }

    private function tryPdfPolicy(): ?bool
    {
        try {
            // نجرب pingImage لمسار PDF غير موجود سيعطي خطأ "no such file" وليس "not authorized"
            // إن ظهرت "not authorized" فهذه سياسة تمنع PDF.
            $im = new \Imagick();
            $im->pingImage('nonexistent.pdf'); // نتوقع استثناء "no such file"
            $im->clear(); $im->destroy();
            return true; // على الأقل لم تُمنع الصيغة نفسها
        } catch (\Throwable $e) {
            $msg = \strtolower($e->getMessage());
            if (\strpos($msg, 'not authorized') !== false || \strpos($msg, 'policy') !== false) {
                return false; // سياسة تمنع PDF
            }
            // أخطاء أخرى (ملف غير موجود) لا تعني حظر السياسة
            return true;
        }
    }
}
