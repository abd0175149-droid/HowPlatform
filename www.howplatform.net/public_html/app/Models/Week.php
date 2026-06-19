<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Week extends Model
{
    protected $table = 'weeks';

    protected $fillable = [
        'week_number',
        'title',
        'start_date',
        'end_date',
    ];

    public $timestamps = true;
}
