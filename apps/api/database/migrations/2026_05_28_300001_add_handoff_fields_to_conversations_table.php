<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->uuid('agent_id')->nullable()->after('resolved_at');
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('escalated_at')->nullable()->after('agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn(['agent_id', 'escalated_at']);
        });
    }
};
