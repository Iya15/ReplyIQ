<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateMemberRoleRequest;
use App\Http\Resources\MemberResource;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MembersController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        /** @var Organization $org */
        $org = app('currentOrganization');

        $this->authorize('viewMembers', $org);

        $users = $org->users()
            ->withPivot('role', 'created_at')
            ->orderByPivot('created_at')
            ->get();

        return $this->ok(MemberResource::collection($users), $r);
    }

    public function update(UpdateMemberRoleRequest $r, string $userId): JsonResponse
    {
        /** @var Organization $org */
        $org = app('currentOrganization');

        $this->authorize('changeRole', $org);

        $membership = Membership::where('organization_id', $org->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Guard: cannot demote the last owner.
        if ($membership->role === 'owner' && $r->input('role') !== 'owner') {
            $ownerCount = Membership::where('organization_id', $org->id)
                ->where('role', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                return $this->error('last_owner', 'Cannot remove the last owner.', $r, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $membership->update(['role' => $r->validated('role')]);

        return $this->ok(['message' => 'Role updated.'], $r);
    }

    public function destroy(Request $r, string $userId): JsonResponse
    {
        /** @var Organization $org */
        $org = app('currentOrganization');

        // Members can remove themselves (leave); owners/admins can remove others.
        /** @var \App\Models\User $actor */
        $actor = $r->user();

        if ((string) $actor->id !== $userId) {
            $this->authorize('removeMember', $org);
        }

        $membership = Membership::where('organization_id', $org->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Guard: cannot remove the last owner.
        if ($membership->role === 'owner') {
            $ownerCount = Membership::where('organization_id', $org->id)
                ->where('role', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                return $this->error('last_owner', 'Cannot remove the last owner.', $r, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $membership->delete();

        return $this->ok(['message' => 'Member removed.'], $r);
    }
}
