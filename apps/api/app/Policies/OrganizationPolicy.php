<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /** Any member can view the team list. */
    public function viewMembers(User $user, Organization $org): bool
    {
        return $user->organizations()->where('organizations.id', $org->id)->exists();
    }

    /** Owners and admins can invite new members. */
    public function invite(User $user, Organization $org): bool
    {
        return $user->organizations()
            ->where('organizations.id', $org->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    /** Owners and admins can remove members; members can remove themselves (leave). */
    public function removeMember(User $user, Organization $org): bool
    {
        return $user->organizations()
            ->where('organizations.id', $org->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    /** Only owners can change roles. */
    public function changeRole(User $user, Organization $org): bool
    {
        return $user->organizations()
            ->where('organizations.id', $org->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    /** Owners and admins can cancel pending invitations. */
    public function cancelInvitation(User $user, Organization $org): bool
    {
        return $this->invite($user, $org);
    }
}
