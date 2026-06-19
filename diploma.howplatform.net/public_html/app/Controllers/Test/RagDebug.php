<?php

namespace App\Controllers\Test;

use App\Controllers\BaseController;

/**
 * RagDebug Controller
 * يعرض صفحة اختبار RAG Debug
 */
class RagDebug extends BaseController
{
    /**
     * GET /test/rag-debug
     * عرض صفحة اختبار RAG Debug
     */
    public function index()
    {
        $htmlPath = APPPATH . 'TEST_RAG_DEBUG.html';
        
        if (!file_exists($htmlPath)) {
            return $this->response->setStatusCode(404)->setBody('صفحة الاختبار غير موجودة');
        }
        
        $html = file_get_contents($htmlPath);
        
        return $this->response
            ->setContentType('text/html; charset=utf-8')
            ->setBody($html);
    }
}

