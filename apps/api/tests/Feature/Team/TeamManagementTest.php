<?php

// @requires PostgreSQL (CI/Docker only)

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeTeam(string $actorRole = 'owner'): array
{
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    Membership::factory()->create(['organization_id' => $org->id, 'user_id' => $owner->id,  'role' => 'owner']);
    Membership::factory()->create(['organization_id' => $org->id, 'user_id' => $member->id, 'role' => 'member']);

    // Build a separate user for the specified actor role if needed.
    $actor = match ($actorRole) {
        'owner' => $owner,
        'member' => $member,
        default => tap(User::factory()->create(), fn ($u) => Membership::factory()->create(['organization_id' => $org->id, 'user_id' => $u->id, 'role' => $actorRole])
        ),
    };

    return compact('org', 'owner', 'member', 'actor');
}

function teamHeaders(User $user): array
{
    $token = $user->createToken('test')->plainTextToken;

    return ['Authorization' => "Bearer {$token}"];
}

// ── GET /organizations/current/members ────────────────────────────────────────

it('owner can list members', function () {
    ['owner' => $owner] = makeTeam();

    $this->withHeaders(teamHeaders($owner))
        ->getJson('/api/v1/organizations/current/members')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'joined_at']]]);
});

it('member cannot list members', function () {
    // member role — policy denies viewMembers
    ['member' => $member] = makeTeam();

    $this->withHeaders(teamHeaders($member))
        ->getJson('/api/v1/organizations/current/members')
        ->assertForbidden();
});

// ── POST /organizations/current/invitations ───────────────────────────────────

it('owner can invite a new member and email is sent', function () {
    Notification::fake();
    ['owner' => $owner] = makeTeam();

    $this->withHeaders(teamHeaders($owner))
        ->postJson('/api/v1/organizations/current/invitations', [
            'email' => 'newperson@example.com',
            'role' => 'member',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'newperson@example.com');

    Notification::assertSentOnDemand(InvitationNotification::class);
});

it('invitation is idempotent — re-inviting same email replaces the old one', function () {
    Notification::fake();
    ['owner' => $owner, 'org' => $org] = makeTeam();

    $this->withHeaders(teamHeaders($owner))
        ->postJson('/api/v1/organizations/current/invitations', ['email' => 'dup@example.com', 'role' => 'member'])
        ->assertCreated();

    $this->withHeaders(teamHeaders($owner))
        ->postJson('/api/v1/organizations/current/invitations', ['email' => 'dup@example.com', 'role' => 'admin'])
        ->assertCreated();

    expect(Invitation::where('email', 'dup@example.com')->count())->toBe(1);
    expect(Invitation::where('email', 'dup@example.com')->first()->role)->toBe('admin');
});

it('cannot invite an existing member', function () {
    Notification::fake();
    ['owner' => $owner, 'member' => $member] = makeTeam();

    $this->withHeaders(teamHeaders($owner))
        ->postJson('/api/v1/organizations/current/invitations', [
            'email' => $member->email,
            'role' => 'member',
        ])
        ->assertUnprocessable();
});

it('member cannot send invitations', function () {
    Notification::fake();
    ['member' => $member] = makeTeam();

    $this->withHeaders(teamHeaders($member))
        ->postJson('/api/v1/organizations/current/invitations', [
            'email' => 'x@example.com',
            'role' => 'member',
        ])
        ->assertForbidden();
});

// ── Accept invitation — new user ──────────────────────────────────────────────

it('a new user can accept an invitation and gets a session token', function () {
    ['org' => $org, 'owner' => $inviter] = makeTeam();

    $invitation = Invitation::factory()->create([
        'organization_id' => $org->id,
        'email' => 'brand@new.com',
        'invited_by' => $inviter->id,
    ]);

    $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'name' => 'Brand New',
        'password' => 'SecurePass123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);

    expect(User::where('email', 'brand@new.com')->exists())->toBeTrue();
    expect(Membership::where('organization_id', $org->id)->count())->toBe(3); // owner + member + new
    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();
});

it('a new user cannot accept without name and password', function () {
    ['org' => $org] = makeTeam();
    $invitation = Invitation::factory()->create(['organization_id' => $org->id, 'email' => 'x@y.com']);

    $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [])
        ->assertUnprocessable();
});

// ── Accept invitation — existing user ────────────────────────────────────────

it('an existing user can accept an invitation without a password', function () {
    ['org' => $org] = makeTeam();
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = Invitation::factory()->create([
        'organization_id' => $org->id,
        'email' => 'existing@example.com',
    ]);

    $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'existing@example.com');
});

// ── Role change ───────────────────────────────────────────────────────────────

it('owner can change a members role', function () {
    ['owner' => $owner, 'member' => $member, 'org' => $org] = makeTeam();

    $this->withHeaders(teamHeaders($owner))
        ->patchJson("/api/v1/organizations/current/members/{$member->id}", ['role' => 'admin'])
        ->assertOk();

    expect(Membership::where('user_id', $member->id)->first()->role)->toBe('admin');
});

it('member cannot change roles', function () {
    ['member' => $member, 'owner' => $owner] = makeTeam();

    $this->withHeaders(teamHeaders($member))
        ->patchJson("/api/v1/organizations/current/members/{$owner->id}", ['role' => 'member'])
        ->assertForbidden();
});

// ── Remove member ─────────────────────────────────────────────────────────────

it('owner can remove a member', function () {
    ['owner' => $owner, 'member' => $member, 'org' => $org] = makeTeam();

    $this->withHeaders(teamHeaders($owner))
        ->deleteJson("/api/v1/organizations/current/members/{$member->id}")
        ->assertOk();

    expect(Membership::where('user_id', $member->id)->exists())->toBeFalse();
});

it('member can leave the organization', function () {
    ['member' => $member] = makeTeam();

    $this->withHeaders(teamHeaders($member))
        ->deleteJson("/api/v1/organizations/current/members/{$member->id}")
        ->assertOk();
});

it('cannot remove the last owner', function () {
    ['owner' => $owner] = makeTeam();

    $this->withHeaders(teamHeaders($owner))
        ->deleteJson("/api/v1/organizations/current/members/{$owner->id}")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'last_owner');
});

// ── Cancel invitation ─────────────────────────────────────────────────────────

it('owner can cancel a pending invitation', function () {
    ['owner' => $owner, 'org' => $org] = makeTeam();
    $invitation = Invitation::factory()->create(['organization_id' => $org->id]);

    $this->withHeaders(teamHeaders($owner))
        ->deleteJson("/api/v1/organizations/current/invitations/{$invitation->id}")
        ->assertOk();

    expect(Invitation::find($invitation->id))->toBeNull();
});

// ── Expired invitation ────────────────────────────────────────────────────────

it('expired invitation cannot be accepted', function () {
    ['org' => $org] = makeTeam();
    $invitation = Invitation::factory()->expired()->create([
        'organization_id' => $org->id,
        'email' => 'late@example.com',
    ]);

    $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'name' => 'Late User',
        'password' => 'SecurePass123!',
    ])->assertNotFound();
});
