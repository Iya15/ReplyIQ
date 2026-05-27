<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));

            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->uuid('chatbot_id');
            $table->foreign('chatbot_id')
                ->references('id')
                ->on('chatbots')
                ->onDelete('cascade');

            // Anonymous visitor fingerprint — widget-generated UUID stored in localStorage.
            $table->string('visitor_id', 64);

            // Optional visitor identity (Phase 4 — identity verification).
            $table->string('visitor_email')->nullable(); // CITEXT in production
            $table->string('visitor_name', 255)->nullable();

            // Request context.
            $table->text('source_url')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable(); // supports IPv6
            $table->string('country', 2)->nullable();     // ISO 3166-1 alpha-2; TODO: MaxMind GeoLite2

            $table->string('status', 20)->default('active'); // active|resolved|escalated
            $table->timestampTz('resolved_at')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        // Matches blueprint DDL index names.
        Schema::table('conversations', function (Blueprint $table) {
            $table->index(['chatbot_id', 'created_at'], 'idx_conversations_chatbot');
            $table->index('visitor_id', 'idx_conversations_visitor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
