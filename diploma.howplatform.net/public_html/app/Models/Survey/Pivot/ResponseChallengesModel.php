<?php

declare(strict_types=1);

namespace App\Models\Survey\Pivot;

use CodeIgniter\Model;

class ResponseChallengesModel extends Model
{
    protected $table      = 'survey_alumni_response_challenges';
    protected $primaryKey = null;
    protected $returnType = 'array';
    protected $allowedFields = ['response_id','challenge_id'];
    public $timestamps = false;

    /** @param int $responseId @param int[] $ids */
    public function bulkAttach(int $responseId, array $ids): void
    {
        // إدخال مجمّع للتسريع
        $rows = [];
        foreach (array_unique(array_filter($ids)) as $id) {
            $rows[] = ['response_id' => $responseId, 'challenge_id' => (int) $id];
        }
        if ($rows) {
            $this->insertBatch($rows);
        }
    }
}
