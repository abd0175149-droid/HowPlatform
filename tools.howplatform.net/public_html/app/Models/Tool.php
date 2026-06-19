<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    protected $fillable = [
        'name','slug','url','image_url','icon_class','icon_color',
        'gradient','status','is_featured','is_active','description'
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'tool_category');
    }

    public function stats()
    {
        return $this->hasOne(ToolStat::class, 'tool_id', 'id');
    }
}
