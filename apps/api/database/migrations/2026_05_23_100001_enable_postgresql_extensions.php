<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // uuid_generate_v4() is used as the DEFAULT on every UUID primary key.
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');

        // citext provides a case-insensitive text type used for email columns,
        // so lookups like WHERE email = 'User@Example.com' match any casing.
        DB::statement('CREATE EXTENSION IF NOT EXISTS "citext"');
    }

    public function down(): void
    {
        // CASCADE drops dependent objects (e.g. the citext column type).
        // Safe at this point because the tables that use these extensions
        // are created in later migrations and will be rolled back first.
        DB::statement('DROP EXTENSION IF EXISTS "citext" CASCADE');
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp" CASCADE');
    }
};
