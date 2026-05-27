<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// Register a disposable route that requires both Sanctum auth and tenant
// resolution. Defined in beforeEach so it is registered once per test within
// the same application instance.
beforeEach(function () {
    Route::middleware(['auth:sanctum', 'tenant'])
        ->get('/_test/tenant-protected', fn () => response()->json(['ok' => true]));
});

// ── Middleware: authentication gate ───────────────────────────────────────────

it('returns 401 for unauthenticated requests to tenant-protected routes', function () {
    $this->getJson('/_test/tenant-protected')->assertUnauthorized();
});

// ── Middleware: defensive 403 when user has no organization ───────────────────

it('returns 403 when the authenticated user belongs to no organization', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/_test/tenant-protected')
        ->assertForbidden();
});

// ── Middleware: happy path ────────────────────────────────────────────────────

it('resolves the tenant and allows the request through for a user with an organization', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/_test/tenant-protected')
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(app()->bound('currentOrganization'))->toBeTrue();
    expect(app('currentOrganization')->id)->toBe($org->id);
});

// ── TenantScope: skips when no binding is present ────────────────────────────

it('TenantScope does not add a WHERE clause when no organization is bound', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);

    // Ensure nothing is bound (simulates a console command or an unscoped worker).
    app()->forgetInstance('currentOrganization');

    $query = Membership::withoutGlobalScopes();
    (new TenantScope)->apply($query, new Membership);

    expect($query->toSql())->not->toContain('organization_id');
});

// ── TenantScope: filters to bound organization ────────────────────────────────
//
// Uses Membership as a stand-in model (it has organization_id) until Chatbot
// is created in M1.7. The full end-to-end cross-tenant API test lives in
// ChatbotTest once a real tenant-scoped route exists.

it('TenantScope filters rows to the bound organization', function () {
    [$orgA, $orgB] = [Organization::factory()->create(), Organization::factory()->create()];
    [$userA, $userB] = [User::factory()->create(), User::factory()->create()];

    Membership::create(['organization_id' => $orgA->id, 'user_id' => $userA->id, 'role' => 'owner']);
    Membership::create(['organization_id' => $orgB->id, 'user_id' => $userB->id, 'role' => 'owner']);

    // Simulate org A being the active tenant (set by middleware or a job).
    app()->instance('currentOrganization', $orgA);

    $query = Membership::withoutGlobalScopes();
    (new TenantScope)->apply($query, new Membership);
    $rows = $query->get();

    expect($rows->every(fn ($m) => $m->organization_id === $orgA->id))->toBeTrue();
    expect($rows->contains('organization_id', $orgB->id))->toBeFalse();
});

// ── Console/queue context: manual binding ─────────────────────────────────────

it('TenantScope applies when organization is manually bound (queue job context)', function () {
    [$orgA, $orgB] = [Organization::factory()->create(), Organization::factory()->create()];
    [$userA, $userB] = [User::factory()->create(), User::factory()->create()];

    Membership::create(['organization_id' => $orgA->id, 'user_id' => $userA->id, 'role' => 'owner']);
    Membership::create(['organization_id' => $orgB->id, 'user_id' => $userB->id, 'role' => 'owner']);

    // A queue job would call app()->instance('currentOrganization', $org)
    // after deserializing its payload, then query normally.
    app()->instance('currentOrganization', $orgB);

    $query = Membership::withoutGlobalScopes();
    (new TenantScope)->apply($query, new Membership);

    $rows = $query->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->organization_id)->toBe($orgB->id);
});
