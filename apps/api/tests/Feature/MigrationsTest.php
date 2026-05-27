<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ─── Helper ──────────────────────────────────────────────────────────────────

/**
 * Returns the PostgreSQL data type for a given column, normalised to lowercase.
 * Uses information_schema so it works across Laravel versions without relying
 * on Blueprint introspection.
 */
function columnType(string $table, string $column): string
{
    $row = DB::selectOne(
        "SELECT data_type FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name   = ?
           AND column_name  = ?",
        [$table, $column]
    );

    return strtolower($row->data_type ?? '');
}

// ─── PostgreSQL extensions ────────────────────────────────────────────────────

it('enables the uuid-ossp extension', function () {
    $result = DB::selectOne(
        "SELECT extname FROM pg_extension WHERE extname = 'uuid-ossp'"
    );
    expect($result)->not->toBeNull();
});

it('enables the citext extension', function () {
    $result = DB::selectOne(
        "SELECT extname FROM pg_extension WHERE extname = 'citext'"
    );
    expect($result)->not->toBeNull();
});

// ─── organizations ────────────────────────────────────────────────────────────

it('creates the organizations table', function () {
    expect(Schema::hasTable('organizations'))->toBeTrue();
});

it('organizations has a UUID primary key', function () {
    expect(columnType('organizations', 'id'))->toBe('uuid');
});

it('organizations has the expected columns', function () {
    expect(Schema::hasColumns('organizations', [
        'id', 'name', 'slug', 'plan', 'trial_ends_at', 'settings',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('organizations.slug has a unique index', function () {
    $index = DB::selectOne(
        "SELECT indexname FROM pg_indexes
         WHERE tablename = 'organizations' AND indexdef LIKE '%slug%' AND indexdef LIKE '%unique%'"
    );
    expect($index)->not->toBeNull();
});

// ─── users ────────────────────────────────────────────────────────────────────

it('creates the users table', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
});

it('users has a UUID primary key', function () {
    expect(columnType('users', 'id'))->toBe('uuid');
});

it('users.email is citext (case-insensitive)', function () {
    expect(columnType('users', 'email'))->toBe('citext');
});

it('users has password_hash column, not password', function () {
    expect(Schema::hasColumn('users', 'password_hash'))->toBeTrue();
    expect(Schema::hasColumn('users', 'password'))->toBeFalse();
});

it('users does not have remember_token column', function () {
    expect(Schema::hasColumn('users', 'remember_token'))->toBeFalse();
});

it('users has avatar_url and last_login_at', function () {
    expect(Schema::hasColumns('users', ['avatar_url', 'last_login_at']))->toBeTrue();
});

// ─── memberships ─────────────────────────────────────────────────────────────

it('creates the memberships table', function () {
    expect(Schema::hasTable('memberships'))->toBeTrue();
});

it('memberships has a UUID primary key', function () {
    expect(columnType('memberships', 'id'))->toBe('uuid');
});

it('memberships has the expected columns', function () {
    expect(Schema::hasColumns('memberships', [
        'id', 'organization_id', 'user_id', 'role', 'created_at',
    ]))->toBeTrue();
});

it('memberships has a unique constraint on (organization_id, user_id)', function () {
    $index = DB::selectOne(
        "SELECT indexname FROM pg_indexes
         WHERE tablename = 'memberships'
           AND indexdef LIKE '%organization_id%'
           AND indexdef LIKE '%user_id%'
           AND indexdef LIKE '%unique%'"
    );
    expect($index)->not->toBeNull();
});

it('memberships.organization_id has a cascade foreign key to organizations', function () {
    $fk = DB::selectOne(
        "SELECT rc.delete_rule
         FROM information_schema.referential_constraints rc
         JOIN information_schema.key_column_usage kcu
           ON kcu.constraint_name = rc.constraint_name
         WHERE kcu.table_name   = 'memberships'
           AND kcu.column_name  = 'organization_id'"
    );
    expect(strtolower($fk->delete_rule ?? ''))->toBe('cascade');
});

it('memberships.user_id has a cascade foreign key to users', function () {
    $fk = DB::selectOne(
        "SELECT rc.delete_rule
         FROM information_schema.referential_constraints rc
         JOIN information_schema.key_column_usage kcu
           ON kcu.constraint_name = rc.constraint_name
         WHERE kcu.table_name  = 'memberships'
           AND kcu.column_name = 'user_id'"
    );
    expect(strtolower($fk->delete_rule ?? ''))->toBe('cascade');
});

// ─── invitations ─────────────────────────────────────────────────────────────

it('creates the invitations table', function () {
    expect(Schema::hasTable('invitations'))->toBeTrue();
});

it('invitations has a UUID primary key', function () {
    expect(columnType('invitations', 'id'))->toBe('uuid');
});

it('invitations has the expected columns', function () {
    expect(Schema::hasColumns('invitations', [
        'id', 'organization_id', 'email', 'role', 'token',
        'invited_by', 'expires_at', 'accepted_at', 'created_at',
    ]))->toBeTrue();
});

it('invitations.email is citext', function () {
    expect(columnType('invitations', 'email'))->toBe('citext');
});

it('invitations.token has a unique index', function () {
    $index = DB::selectOne(
        "SELECT indexname FROM pg_indexes
         WHERE tablename = 'invitations' AND indexdef LIKE '%token%' AND indexdef LIKE '%unique%'"
    );
    expect($index)->not->toBeNull();
});

it('invitations.organization_id has a cascade foreign key', function () {
    $fk = DB::selectOne(
        "SELECT rc.delete_rule
         FROM information_schema.referential_constraints rc
         JOIN information_schema.key_column_usage kcu
           ON kcu.constraint_name = rc.constraint_name
         WHERE kcu.table_name  = 'invitations'
           AND kcu.column_name = 'organization_id'"
    );
    expect(strtolower($fk->delete_rule ?? ''))->toBe('cascade');
});
