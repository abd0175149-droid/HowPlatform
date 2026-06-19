<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetProfileVerified
{
    public function handle(Verified $event): void
    {
        $userId = $event->user->id;

        // أعمدة اختيارية (نحدّثها فقط إذا موجودة)
        $updates = [];

        if (Schema::hasColumn('users', 'is_verified')) {
            $updates['is_verified'] = 1;
        }
        if (Schema::hasColumn('users', 'profile_verified_at')) {
            $updates['profile_verified_at'] = now();
        }

        if ($updates) {
            DB::table('users')->where('id', $userId)->update($updates);
        }
    }
}
