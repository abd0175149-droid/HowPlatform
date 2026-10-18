<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    protected $table = 'plan_features';

    protected $fillable = [
        'plan_id', 'platform', 'feature_key', 'label', 'value', 'value_json',
    ];

    protected $casts = [
        'plan_id'    => 'int',
        'value_json' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
