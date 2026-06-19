<?php
declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AiToolPlacementModel extends Model
{
    protected $table = 'ai_tool_placements';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $returnType = 'array';
    protected $allowedFields = [
        'type_key',
        'task_type_id',
        'university_id',
        'course_id',
        'min_subscription_level',
        'button_label',
        'button_icon',
        'button_order',
        'is_enabled',
    ];

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findMatching(?int $taskTypeId, ?int $universityId, ?int $courseId): array
    {
        $rows = $this->where('is_enabled', 1)
            ->orderBy('button_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        $taskTypeId = $taskTypeId ?? 0;
        $universityId = $universityId ?? 0;
        $courseId = $courseId ?? 0;

        $filtered = array_filter($rows, function (array $row) use ($taskTypeId, $universityId, $courseId): bool {
            // task_type_id: null أو 0 يعني "ينطبق على كل أنواع المهام"
            $rowTaskTypeId = $row['task_type_id'] === null ? 0 : (int) $row['task_type_id'];
            if ($rowTaskTypeId !== 0 && $rowTaskTypeId !== $taskTypeId) {
                return false;
            }

            // university_id: null أو 0 يعني "ينطبق على كل الجامعات"
            $rowUniversityId = $row['university_id'] === null ? 0 : (int) $row['university_id'];
            if ($rowUniversityId !== 0 && $rowUniversityId !== $universityId) {
                return false;
            }

            // course_id: null أو 0 يعني "ينطبق على كل المساقات"
            $rowCourseId = $row['course_id'] === null ? 0 : (int) $row['course_id'];
            if ($rowCourseId !== 0 && $rowCourseId !== $courseId) {
                return false;
            }

            return true;
        });

        usort($filtered, function (array $a, array $b) use ($taskTypeId, $universityId, $courseId): int {
            $scoreA = $this->specificityScore($a, $taskTypeId, $universityId, $courseId);
            $scoreB = $this->specificityScore($b, $taskTypeId, $universityId, $courseId);

            return $scoreB <=> $scoreA; // higher specificity first
        });

        return array_values($filtered);
    }

    private function specificityScore(array $row, int $taskTypeId, int $universityId, int $courseId): int
    {
        $score = 0;
        
        $rowTaskTypeId = $row['task_type_id'] === null ? 0 : (int) $row['task_type_id'];
        $rowUniversityId = $row['university_id'] === null ? 0 : (int) $row['university_id'];
        $rowCourseId = $row['course_id'] === null ? 0 : (int) $row['course_id'];

        if ($rowTaskTypeId !== 0 && $rowTaskTypeId === $taskTypeId) {
            $score += 4;
        }
        if ($rowCourseId !== 0 && $rowCourseId === $courseId) {
            $score += 2;
        }
        if ($rowUniversityId !== 0 && $rowUniversityId === $universityId) {
            $score += 1;
        }
        return $score;
    }
}


