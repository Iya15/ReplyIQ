<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 255);

            // SHA-256 hex of the full key — 64 chars. Unique so lookup is O(1).
            $table->string('key_hash', 64)->unique();

            // First 12 chars of the plain key (e.g. "rk_live_XXXX") for display.
            $table->string('prefix', 12);

            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
