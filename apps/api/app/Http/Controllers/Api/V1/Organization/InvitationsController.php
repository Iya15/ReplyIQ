<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\InviteMemberRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Services\Team\InviteUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvitationsController extends Controller
{
    /** GET /organizations/current/invitations — pending invitations. */
    public function index(Request $r): JsonResponse
    {
        /** @var Organization $org */
        $org = app('currentOrganization');

        $this->authorize('invite', $org);

        $invitations = Invitation::where('organization_id', $org->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with('invitedBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->ok(InvitationResource::collection($invitations), $r);
    }

    /** POST /organizations/current/invitations — send an invitation. */
    public function store(InviteMemberRequest $r, InviteUserService $svc): JsonResponse
    {
        /** @var Organization $org */
        $org = app('currentOrganization');

        $this->authorize('invite', $org);

        /** @var User $inviter */
        $inviter = $r->user();

        $invitation = $svc->execute(
            org: $org,
            inviter: $inviter,
            email: (string) $r->validated('email'),
            role: (string) $r->validated('role'),
        );

        return $this->ok(
            InvitationResource::make($invitation->load('invitedBy')),
            $r,
            Response::HTTP_CREATED,
        );
    }

    /** DELETE /organizations/current/invitations/{id} — cancel a pending invitation. */
    public function destroy(Request $r, string $id): JsonResponse
    {
        /** @var Organization $org */
        $org = app('currentOrganization');

        $this->authorize('cancelInvitation', $org);

        $invitation = Invitation::where('organization_id', $org->id)
            ->where('id', $id)
            ->whereNull('accepted_at')
            ->firstOrFail();

        $invitation->delete();

        return $this->ok(['message' => 'Invitation cancelled.'], $r);
    }
}
