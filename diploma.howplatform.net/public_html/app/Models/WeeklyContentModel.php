<?php
namespace App\Models;

use CodeIgniter\Model;

/**
 * موديل جدول المحتوى الأسبوعي
 * جدول weekly_contents:
 * - id bigint PK
 * - course_offering_id bigint
 * - week_number int
 * - title varchar(255)
 * - publish_date date
 */
class WeeklyContentModel extends Model
{
    protected $table         = 'weekly_contents';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['course_offering_id', 'week_number', 'title', 'publish_date'];
    protected $returnType    = 'array';
    public    $useTimestamps = false;
}
