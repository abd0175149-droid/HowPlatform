<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseOfferingModel extends Model
{
    protected $table         = 'course_offerings';  
    protected $primaryKey    = 'id';                
    protected $allowedFields = ['course_id','semester_id','university_id']; // إزالة specialty_id
    protected $returnType    = 'array';
    public    $useTimestamps = false;               // لا توجد حقول created_at/updated_at
    // العلاقات مُعرفة عبر FK في قاعدة البيانات:
    // fk_offerings_course, fk_offerings_semester, fk_offerings_university :contentReference[oaicite:9]{index=9}
}
