<?php

declare(strict_types=1);

namespace App\Models\Survey\Pivot;

use CodeIgniter\Model;

class ResponseFeaturesModel extends Model
{
    // ربط: استجابة ← ميزات مطلوبة
    protected $table      = 'survey_alumni_response_features';
    protected $primaryKey = null;
    protected $returnType = 'array';
    public $timestamps    = false;

    protected $allowedFields = ['response_id','feature_id'];

    /**
     * إدخال دفعي لربط الميزات (حد أقصى يطبَّق في الكنترولر/الواجهة)
     * @param int $responseId
     * @param int[] $ids
     */
    public function bulkAttach(int $responseId, array $ids): void
    {
        $rows = [];
        foreach (array_unique(array_filter($ids)) as $id) {
            $rows[] = ['response_id' => $responseId, 'feature_id' => (int) $id];
        }
        if ($rows) {
            $this->insertBatch($rows);
        }
    }
}
