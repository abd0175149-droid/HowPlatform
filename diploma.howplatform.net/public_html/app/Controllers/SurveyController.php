<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\SurveyResponseModel;

class SurveyController extends BaseController
{
    public function index()
    {
        // التأكد من أن المستخدم مسجّل دخوله
        if (!session('user_id')) {
            return redirect()->to(site_url('auth/login'));
        }

        return view('survey/index'); // صفحة الاستبيان
    }

    public function submit()
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'طلب غير صالح.']);
        }

        $data = [
            'user_id'             => session('user_id'),
            'how_known_source'    => $this->request->getPost('how_known'),
            'other_source_text'   => $this->request->getPost('other_source'),
            'join_reasons'        => implode(',', (array) $this->request->getPost('reasons')),
            'other_reason_text'   => $this->request->getPost('other_reason_text'),
            'suggested_services'  => implode(',', (array) $this->request->getPost('services')),
            'device_used'         => $this->request->getPost('device'),
            'satisfaction_level'  => $this->request->getPost('satisfaction')
        ];

        $model = new SurveyResponseModel();
        if ($model->insert($data)) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'شكرًا لك على مشاركتك!',
                'redirect' => site_url('/student/dashboard') // عدل الوجهة حسب النظام
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'فشل حفظ البيانات. حاول مرة أخرى.']);
    }
}
