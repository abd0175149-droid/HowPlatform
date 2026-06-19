<?php

declare(strict_types=1);

namespace App\Models\Survey\Pivot;

use CodeIgniter\Model;

class ResponseContentTypesModel extends Model
{
    // ربط: استجابة ← تفضيلات محتوى
    protected $table      = 'survey_alumni_response_content_types';
    protected $primaryKey = null;
    protected $returnType = 'array';
    public $timestamps    = false;

    protected $allowedFields = ['response_id','content_type_id'];

    /**
     * إدخال دفعي لربط تفضيلات المحتوى (حد أقصى يطبَّق في الكنترولر/الواجهة)
     * @param int $responseId
     * @param int[] $ids
     */
    public function bulkAttach(int $responseId, array $ids): void
    {
        $rows = [];
        foreach (array_unique(array_filter($ids)) as $id) {
            $rows[] = ['response_id' => $responseId, 'content_type_id' => (int) $id];
        }
        if ($rows) {
            $this->insertBatch($rows);
        }
    }
}
