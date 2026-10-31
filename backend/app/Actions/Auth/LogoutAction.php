<?php

namespace App\Actions\Auth;

use App\Models\User;

class LogoutAction
{
    public function execute(User $user): void
    {
        \Illuminate\Support\Facades\DB::table('staff_push_devices')->where('user_id', $user->id)->update(['enabled' => false, 'updated_at' => now()]);
        $user->currentAccessToken()?->delete();
    }
}
