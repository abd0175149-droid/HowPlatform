<?php

declare(strict_types=1);

namespace App\Models\Survey\Pivot;

use CodeIgniter\Model;

class ResponseSupportChannelsModel extends Model
{
    // ربط: استجابة ← قنوات دعم
    protected $table      = 'survey_alumni_response_support_channels';
    protected $primaryKey = null;
    protected $returnType = 'array';
    public $timestamps    = false;

    protected $allowedFields = ['response_id','channel_id'];

    /**
     * إدخال دفعي لربط قنوات الدعم
     * @param int $responseId
     * @param int[] $ids
     */
    public function bulkAttach(int $responseId, array $ids): void
    {
        $rows = [];
        foreach (array_unique(array_filter($ids)) as $id) {
            $rows[] = ['response_id' => $responseId, 'channel_id' => (int) $id];
        }
        if ($rows) {
            $this->insertBatch($rows);
        }
    }
}
