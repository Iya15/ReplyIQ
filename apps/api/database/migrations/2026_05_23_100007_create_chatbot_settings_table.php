<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_settings', function (Blueprint $table) {
            // chatbot_id IS the primary key — 1-to-1 with chatbots.
            $table->uuid('chatbot_id')->primary();
            $table->foreign('chatbot_id')
                ->references('id')
                ->on('chatbots')
                ->onDelete('cascade');

            // ── Branding ──────────────────────────────────────────────────────
            $table->text('logo_url')->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('primary_color', 7)->default('#4F46E5');
            $table->string('text_color', 7)->default('#0F172A');
            $table->string('font_family', 100)->default('Inter');

            // ── Behavior ──────────────────────────────────────────────────────
            $table->text('welcome_message')->default('Hi! How can I help you today?');
            $table->string('placeholder_text', 255)->nullable()->default('Ask me anything...');
            $table->string('ai_tone', 50)->default('professional'); // professional|friendly|casual|formal
            $table->text('ai_persona')->nullable(); // system prompt extension

            // ── Widget ────────────────────────────────────────────────────────
            $table->string('position', 20)->default('bottom-right');
            $table->string('theme', 20)->default('light'); // light|dark|auto
            $table->boolean('show_branding')->default(true);

            // ── AI Config ─────────────────────────────────────────────────────
            $table->string('model', 50)->default('gpt-4o-mini');
            $table->decimal('temperature', 3, 2)->default(0.3);
            $table->integer('max_tokens')->default(800);
            $table->decimal('similarity_threshold', 3, 2)->default(0.75);
            $table->integer('retrieval_k')->default(5);
            $table->text('fallback_message')
                ->default("I don't have information about that. Please contact our support team.");

            $table->timestampTz('updated_at')->useCurrent();
        });

        // TEXT[] is a PostgreSQL-native array type; Blueprint has no direct method.
        // Consistent with the CITEXT approach used in M1.3 migrations.
        DB::statement("ALTER TABLE chatbot_settings ADD COLUMN allowed_domains TEXT[] NOT NULL DEFAULT '{}'");
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_settings');
    }
};
