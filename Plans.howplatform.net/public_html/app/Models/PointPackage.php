<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointPackage extends Model
{
    protected $table = 'point_packages';

    protected $fillable = ['label', 'points', 'price_amount', 'price_currency', 'sort_order', 'is_active'];

    protected $casts = [
        'points'       => 'int',
        'price_amount' => 'decimal:2',
        'sort_order'   => 'int',
        'is_active'    => 'bool',
    ];

    /** اسم عرضي مضمون (يبني واحدًا من النقاط/السعر إن لم يُحدَّد). */
    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }
        return number_format($this->points) . ' نقطة — ' . rtrim(rtrim(number_format((float) $this->price_amount, 2), '0'), '.') . ' ' . $this->price_currency;
    }
}
