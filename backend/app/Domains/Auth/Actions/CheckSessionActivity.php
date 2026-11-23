<?php

namespace App\Domains\Auth\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class CheckSessionActivity
{
    public const IDLE_MINUTES = 30;

    public function execute(PersonalAccessToken $token, ?int $idleSeconds = null): bool
    {
        return DB::transaction(function () use ($token, $idleSeconds) {
            $row = DB::table('personal_access_tokens')->where('id', $token->getKey())->lockForUpdate()->first();
            if (! $row) {
                return false;
            }
            $last = CarbonImmutable::parse($row->last_activity_at ?? $row->created_at);
            if ($last->lessThanOrEqualTo(now()->subMinutes(self::IDLE_MINUTES))) {
                DB::table('personal_access_tokens')->where('id', $row->id)->delete();

                return false;
            }
            if ($idleSeconds !== null) {
                $activity = CarbonImmutable::now()->subSeconds($idleSeconds);
                if ($activity->greaterThan($last)) {
                    DB::table('personal_access_tokens')->where('id', $row->id)->update(['last_activity_at' => $activity]);
                }
            }

            return true;
        }, 3);
    }
}
