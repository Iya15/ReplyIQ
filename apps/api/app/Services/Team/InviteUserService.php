<?php

namespace App\Services\Team;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteUserService
{
    /**
     * Create (or refresh) an invitation and dispatch the email.
     *
     * @throws ValidationException
     */
    public function execute(Organization $org, User $inviter, string $email, string $role): Invitation
    {
        // Guard: already a member?
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $isMember = Membership::where('organization_id', $org->id)
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($isMember) {
                throw ValidationException::withMessages([
                    'email' => 'This person is already a member of the organization.',
                ]);
            }
        }

        // Delete any existing non-accepted invitation for this email + org.
        Invitation::where('organization_id', $org->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = Invitation::create([
            'organization_id' => $org->id,
            'email'           => $email,
            'role'            => $role,
            'token'           => Str::random(64),
            'invited_by'      => $inviter->id,
            'expires_at'      => now()->addDays(7),
        ]);

        Notification::route('mail', $email)
            ->notify(new InvitationNotification($invitation, $org, $inviter));

        return $invitation;
    }
}
