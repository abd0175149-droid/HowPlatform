<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureCatalog extends Model
{
    protected $table = 'feature_catalog';

    protected $fillable = [
        'platform', 'feature_key', 'label', 'value_type', 'unit',
        'options', 'default_value', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'options'   => 'array',
        'is_active' => 'bool',
        'sort_order'=> 'int',
    ];
}
