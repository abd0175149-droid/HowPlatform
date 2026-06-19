<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\SupportRequestModel;
use CodeIgniter\HTTP\ResponseInterface;

class SupportController extends BaseController
{
    public function submit(): ResponseInterface
    {
        $request = service('request');

        // التحقق من البيانات
        $rules = [
            'name'    => 'required|string|min_length[3]',
            'email'   => 'required|valid_email',
            'subject' => 'required|string',
            'message' => 'required|string|min_length[10]',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'يرجى تعبئة جميع الحقول بشكل صحيح.',
                'errors'  => $this->validator->getErrors()
            ]);
        }

        $model = new SupportRequestModel();

        $model->insert([
            'full_name' => $request->getPost('name'),
            'email'     => $request->getPost('email'),
            'subject'   => $request->getPost('subject'),
            'message'   => $request->getPost('message'),
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'تم إرسال رسالتك بنجاح. سيتم الرد عليك قريبًا.'
        ]);
    }
}
