<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TaskTypesSeeder extends Seeder
{
    public function run()
    {
        $this->seedArticleEvaluatorTaskType();
    }

    protected function seedArticleEvaluatorTaskType(): void
    {
        $builder = $this->db->table('task_types');
        
        // التحقق من وجود task_type_id = 44
        $existing = $builder->where('id', 44)->get()->getRowArray();
        
        if (!$existing) {
            $builder->insert([
                'id' => 44,
                'name' => 'مقيم المقالات',
            ]);
        }
    }
}

