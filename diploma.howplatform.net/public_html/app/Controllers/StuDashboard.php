<?php
// app/Controllers/StuDashboard.php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use App\Models\TaskModel;
use App\Models\TaskSpecialtyModel;
use App\Models\TaskFileModel;
use App\Models\FileModel;


class StuDashboard extends BaseController
{
    protected TaskModel                   $taskModel;
    protected TaskSpecialtyModel          $taskSpecModel;
    protected TaskFileModel               $taskFileModel;
    protected FileModel                   $fileModel;

    public function __construct()
    {
        $this->taskModel     = new TaskModel();
        $this->taskSpecModel = new TaskSpecialtyModel();
        $this->taskFileModel = new TaskFileModel();
        $this->fileModel     = new FileModel();
    }
    public function upcomingTasks()
    {
        try {
            $uid = session('user_id');
            if (! $uid) {
                return $this->response->setStatusCode(401)->setJSON([
                    'message' => 'يجب تسجيل الدخول.',
                ]);
            }
    
            $db = db_connect();
    
            $sql = "
                SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.due_date,
                    c.name AS course_name
                FROM tasks t
                JOIN weekly_contents wc ON wc.id = t.weekly_content_id
                JOIN course_offerings co ON co.id = wc.course_offering_id
                JOIN courses c ON c.id = co.course_id
                JOIN semesters s ON s.id = co.semester_id
                JOIN users u ON u.id = ?
                LEFT JOIN task_specialties ts ON ts.task_id = t.id
                WHERE 
                    co.university_id = u.university_id
                    AND s.academic_year_id = u.academic_year_id
                    AND (t.is_for_all_specialties = 1 OR ts.specialty_id = u.specialty_id)
                    AND t.due_date >= CURDATE()
                ORDER BY t.due_date ASC
                LIMIT 6;
            ";
            $query = $db->query($sql, [$uid]);
    
            if (! $query) {
                return $this->response->setStatusCode(500)->setJSON([
                    'message' => 'فشل الاستعلام',
                    'error'   => $db->error(),
                ]);
            }
    
            $tasks = $query->getResult();
    
            return $this->response->setStatusCode(200)->setJSON($tasks);
    
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'message' => '❌ استثناء داخلي',
                'error'   => $e->getMessage(),
            ]);
        }
    }
    public function getTaskForView(int $id): ResponseInterface
    {
        try {
            $task = $this->taskModel
                ->select([
                    'tasks.id                        AS id',
                    'tasks.title                     AS title',
                    'tasks.description               AS description',
                    'tasks.visible_from_date',
                    'tasks.due_date',
                    'tasks.is_for_all_specialties',
                    'task_types.name                 AS task_type',
                    'weekly_contents.week_number     AS week_number',
                    'courses.name                    AS course',
                    'universities.id                 AS university_id',
                    'universities.name               AS university',
                    'semesters.name                  AS semester',
                    'academic_years.label            AS academic_year',
                ])
                ->join('task_types',       'task_types.id       = tasks.task_type_id')
                ->join('weekly_contents',  'weekly_contents.id  = tasks.weekly_content_id')
                ->join('course_offerings', 'course_offerings.id = weekly_contents.course_offering_id')
                ->join('courses',          'courses.id          = course_offerings.course_id')
                ->join('universities',     'universities.id     = course_offerings.university_id')
                ->join('semesters',        'semesters.id        = course_offerings.semester_id')
                ->join('academic_years',   'academic_years.id   = semesters.academic_year_id')
                ->where('tasks.id', $id)
                ->first();
    
            if (! $task) {
                return $this->response
                            ->setStatusCode(404)
                            ->setJSON(['message' => 'المهمة غير موجودة']);
            }
    
            $specialties = $this->taskSpecModel
                ->select('specialties.id, specialties.name')
                ->join('specialties', 'specialties.id = task_specialties.specialty_id')
                ->where('task_specialties.task_id', $id)
                ->findAll();
    
            $files = $this->taskFileModel
                ->select([
                    'files.id        AS file_id',
                    'files.file_name',
                    'files.file_path',
                    'files.description',
                    'CAST(files.is_premium AS UNSIGNED) AS is_premium' ,
                ])
                ->join('files', 'files.id = task_files.file_id')
                ->where('task_files.task_id', $id)
                ->findAll();
    
            return $this->response->setJSON([
                'task'          => $task,
                'specialties'   => array_map(fn($s) => $s['name'], $specialties),
                'specialty_ids' => array_map(fn($s) => $s['id'],   $specialties),
                'files'         => $files,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'getTaskForView error: ' . $e->getMessage());
            return $this->failServerError('❌ خطأ داخلي: ' . $e->getMessage());
        }
    }

public function download(int $fileId): ResponseInterface
{
    $file = $this->fileModel->find($fileId);
    if (! $file) {
        return $this->response->setStatusCode(404)->setBody('الملف غير موجود');
    }

    // ✅ التحقق من اشتراك الطالب
    $isStudentPremium = session('is_premium') == 1;

    if ($file['is_premium'] && ! $isStudentPremium) {
        return $this->response->setStatusCode(403)->setBody('متاح فقط للمشتركين المميزين');
    }

    $filePath = ROOTPATH . 'public' . $file['file_path'];
    if (! is_file($filePath)) {
        return $this->response->setStatusCode(404)->setBody('الملف غير موجود على الخادم');
    }

    $mimeType = mime_content_type($filePath);

    return $this->response
        ->setHeader('Content-Type',        $mimeType)
        ->setHeader(
            'Content-Disposition',
            'inline; filename="' . $file['file_name'] . '"'
        )
        ->setBody(file_get_contents($filePath));
}

}
