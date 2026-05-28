<?php

namespace App\Services\Team;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitationService
{
    /**
     * Accept an invitation.
     *
     * - If the email already has an account: add membership immediately.
     * - If not: create the account from $userData (requires 'name' and 'password').
     *
     * Returns the user and a fresh Sanctum token.
     *
     * @param  array{name?: string, password?: string}  $userData
     * @return array{user: User, token: string}
     * @throws ValidationException
     */
    public function execute(Invitation $invitation, array $userData = []): array
    {
        return DB::transaction(function () use ($invitation, $userData): array {
            $user = User::where('email', $invitation->email)->first();

            if (! $user) {
                if (empty($userData['name']) || empty($userData['password'])) {
                    throw ValidationException::withMessages([
                        'name'     => ['Your name is required to create an account.'],
                        'password' => ['A password is required to create an account.'],
                    ]);
                }

                $user = User::create([
                    'name'  => $userData['name'],
                    'email' => $invitation->email,
                ]);
                $user->password_hash = $userData['password'];
                $user->markEmailAsVerified(); // Invitation is proof of email ownership.
                $user->save();
            }

            // Add membership (idempotent — skip if already a member).
            Membership::firstOrCreate(
                ['organization_id' => $invitation->organization_id, 'user_id' => $user->id],
                ['role'            => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);

            $token = $user->createToken('api-token')->plainTextToken;

            return ['user' => $user, 'token' => $token];
        });
    }
}
