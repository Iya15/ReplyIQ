<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));

            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            // CITEXT so invite lookup is case-insensitive
            $table->string('email', 255);
            DB::statement('ALTER TABLE invitations ALTER COLUMN email TYPE CITEXT');

            // role: owner | admin | member
            $table->string('role', 50)->default('member');

            // Opaque token sent in the invite email link
            $table->string('token', 64)->unique();

            $table->uuid('invited_by')->nullable();
            $table->foreign('invited_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
