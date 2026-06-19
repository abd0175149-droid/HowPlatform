<?php
// app/Controllers/StudentDashboard.php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class StudentDashboard extends ResourceController
{
    protected $format = 'json';

    public function upcomingTasks()
    {
        $uid = session('user_id');
        if (! $uid) return $this->failUnauthorized('يجب تسجيل الدخول.');

        $db = db_connect();

        $query = $db->query("
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
            JOIN users u ON u.id = ?
            LEFT JOIN task_specialties ts ON ts.task_id = t.id
            WHERE co.university_id = u.university_id
              AND (t.is_for_all_specialties = 1 OR ts.specialty_id = u.specialty_id)
              AND t.due_date >= CURDATE()
            ORDER BY t.due_date ASC
            LIMIT 6
        ", [$uid]);

        $tasks = $query->getResult();

        return $this->respond($tasks);
    }
}
