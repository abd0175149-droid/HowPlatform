<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name','slug'];

    public function tools()
    {
        return $this->belongsToMany(Tool::class, 'tool_category');
    }
}
