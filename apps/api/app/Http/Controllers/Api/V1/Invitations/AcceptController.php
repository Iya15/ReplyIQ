<?php

namespace App\Http\Controllers\Api\V1\Invitations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AcceptInvitationRequest;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Services\Team\AcceptInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AcceptController extends Controller
{
    /**
     * GET /invitations/{token}
     *
     * Returns invitation metadata for the accept page (org name, email, role)
     * without marking the invitation as accepted.
     */
    public function show(Request $r, string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with(['organization', 'invitedBy'])
            ->first();

        if (! $invitation) {
            return $this->error('invitation_not_found', 'This invitation is invalid or has expired.', $r, Response::HTTP_NOT_FOUND);
        }

        return $this->ok([
            'email'             => $invitation->email,
            'role'              => $invitation->role,
            'organization_name' => $invitation->organization->name,
            'invited_by'        => $invitation->invitedBy?->name,
            'expires_at'        => $invitation->expires_at,
        ], $r);
    }

    /**
     * POST /invitations/{token}/accept
     *
     * Accept an invitation. For new users, requires {name, password} in body.
     * Returns a session token so the user is immediately logged in.
     */
    public function store(AcceptInvitationRequest $r, string $token, AcceptInvitationService $svc): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invitation) {
            return $this->error('invitation_not_found', 'This invitation is invalid or has expired.', $r, Response::HTTP_NOT_FOUND);
        }

        $result = $svc->execute($invitation, $r->only('name', 'password'));

        return $this->ok([
            'token' => $result['token'],
            'user'  => UserResource::make($result['user']),
        ], $r);
    }
}
