<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Why ALTER instead of replacing the default migration:
 *
 * The default 0001_01_01_000000 migration also owns `password_reset_tokens`
 * and `sessions`. Replacing it would mean re-owning those tables and
 * entangling unrelated schema concerns in a single file. Keeping the
 * default as a stable baseline and isolating ReplyIQ-specific changes here
 * means each migration has a clear, single responsibility and rolls back
 * independently.
 *
 * Changes applied to `users`:
 *   - id:            bigint (Laravel default) → UUID (uuid_generate_v4())
 *   - email:         varchar → CITEXT (case-insensitive, requires citext extension)
 *   - password:      renamed to password_hash (matches blueprint column name)
 *   - remember_token: dropped (Sanctum cookie/token auth doesn't use it)
 *   - avatar_url:    added TEXT nullable
 *   - last_login_at: added TIMESTAMPTZ nullable
 *
 * Dependent tables fixed in the same transaction:
 *   - sessions.user_id:                       bigint → UUID (no FK constraint existed)
 *   - personal_access_tokens.tokenable_id:    bigint → varchar(36) for UUID strings
 *
 * Known gap (addressed in a future milestone):
 *   Spatie's model_has_roles / model_has_permissions use morphs('model') which
 *   generates a bigint model_id. Those will be fixed when the HasRoles trait is
 *   wired to the User model and the permission tables are re-examined.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── users ──────────────────────────────────────────────────────────

        // 1. Drop the auto-increment default and primary key so we can change
        //    the column type. Done in raw SQL because Blueprint::change() cannot
        //    convert across type families (integer → uuid).
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_pkey');
        DB::statement('ALTER TABLE users ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE users ALTER COLUMN id TYPE UUID USING (uuid_generate_v4())');
        DB::statement('ALTER TABLE users ALTER COLUMN id SET DEFAULT uuid_generate_v4()');
        DB::statement('ALTER TABLE users ADD PRIMARY KEY (id)');

        // 2. Promote email to CITEXT for case-insensitive lookups.
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE CITEXT');

        // 3. Rename password → password_hash to match the blueprint column name.
        Schema::table('users', function ($table) {
            $table->renameColumn('password', 'password_hash');
        });

        // 4. Drop remember_token — not used with Sanctum API auth.
        Schema::table('users', function ($table) {
            $table->dropColumn('remember_token');
        });

        // 5. Add blueprint columns that don't exist in the default migration.
        Schema::table('users', function ($table) {
            $table->text('avatar_url')->nullable()->after('name');
            $table->timestampTz('last_login_at')->nullable()->after('email_verified_at');
        });

        // ── sessions ───────────────────────────────────────────────────────
        // sessions.user_id was created as unsignedBigInteger by the default
        // migration without a FK constraint, so it's safe to change directly.
        DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE UUID USING NULL');

        // ── personal_access_tokens ─────────────────────────────────────────
        // tokenable_id is a morph column (unsignedBigInteger). Change to
        // varchar(36) so it can store UUID strings. No FK constraint to drop.
        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE VARCHAR(36) USING NULL');
    }

    public function down(): void
    {
        // personal_access_tokens — restore bigint morph column
        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE BIGINT USING NULL');

        // sessions — restore bigint user_id
        DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE BIGINT USING NULL');

        // users — reverse column additions/renames
        Schema::table('users', function ($table) {
            $table->dropColumn(['avatar_url', 'last_login_at']);
            $table->rememberToken();
            $table->renameColumn('password_hash', 'password');
        });

        // Restore email to plain varchar
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE VARCHAR(255)');

        // Restore id to bigint — data loss is acceptable on rollback of a fresh DB
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_pkey');
        DB::statement('ALTER TABLE users ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE users ALTER COLUMN id TYPE BIGINT USING NULL');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS users_id_seq');
        DB::statement('ALTER TABLE users ALTER COLUMN id SET DEFAULT nextval(\'users_id_seq\')');
        DB::statement('ALTER TABLE users ADD PRIMARY KEY (id)');
    }
};
