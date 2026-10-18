<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'name', 'slug', 'level', 'price', 'currency', 'duration_days',
        'grant_points', 'is_active', 'sort_order', 'description',
    ];

    protected $casts = [
        'level'         => 'int',
        'price'         => 'decimal:2',
        'duration_days' => 'int',
        'grant_points'  => 'int',
        'is_active'     => 'bool',
        'sort_order'    => 'int',
    ];

    public function features()
    {
        return $this->hasMany(PlanFeature::class, 'plan_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
