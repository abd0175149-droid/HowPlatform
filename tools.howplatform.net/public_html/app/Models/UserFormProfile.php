<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFormProfile extends Model
{
    protected $table = 'user_form_profiles';
    protected $guarded = [];
    protected $casts = [
        'values' => 'array',
    ];

    public static function forUser(int $userId)
    {
        return static::where('user_id', $userId);
    }

    public static function remember(int $userId, string $scope, array $values): self
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'scope' => $scope],
            ['values'  => $values]
        );
    }

    public function getDefaults(): array
    {
        return $this->values ?? [];
    }
}
