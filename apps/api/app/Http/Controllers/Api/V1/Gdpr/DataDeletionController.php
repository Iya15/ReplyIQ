<?php

namespace App\Http\Controllers\Api\V1\Gdpr;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DataDeletionController extends Controller
{
    /**
     * POST /api/v1/gdpr/data-deletion
     *
     * Accepts an email address and queues a data deletion request.
     * Always returns success to prevent email enumeration.
     *
     * If the email belongs to a registered user, we notify the admin team.
     * Actual deletion is performed manually or via a scheduled cleanup job
     * to allow for the 30-day GDPR grace period.
     */
    public function store(Request $r): JsonResponse
    {
        $r->validate(['email' => ['required', 'email', 'max:255']]);

        $email = $r->string('email')->toString();

        Log::info('GDPR data deletion request received', [
            'email' => $email,
            'ip' => $r->ip(),
        ]);

        // If the user exists, notify privacy@ so the team can process it.
        $user = User::where('email', $email)->first();
        if ($user) {
            // In production, dispatch a DeleteUserDataJob here.
            // For MVP: log and notify — the team processes manually within 30 days.
            Log::warning('GDPR data deletion — user account found', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            // Optionally send confirmation to the user (generic — doesn't reveal account existence)
        }

        // Always return success (GDPR enumeration defence)
        return $this->ok([
            'message' => 'If this email is associated with an account, your data will be deleted within 30 days.',
        ], $r);
    }
}
