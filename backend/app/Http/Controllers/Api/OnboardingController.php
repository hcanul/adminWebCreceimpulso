<?php

namespace App\Http\Controllers\Api;

use App\Domains\Onboarding\Actions\RegisterOnboardingLoginAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class OnboardingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 401);
        $result = DB::transaction(function () use ($request, $token) {
            $visit = DB::table('user_onboarding_visits')
                ->where('user_id', $request->user()->id)->where('token_id', $token->id)
                ->where('tour', RegisterOnboardingLoginAction::TOUR)->lockForUpdate()->first();
            if (! $visit || $visit->started_at) return ['show' => false];
            $claimed = DB::table('user_onboarding_visits')->where('id', $visit->id)
                ->whereNull('started_at')->update(['started_at' => now()]);
            return ['show' => $claimed === 1, 'login_number' => $visit->login_number];
        });
        return response()->json($result)->header('Cache-Control', 'no-store');
    }
}
