<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add nullable so existing rows can be backfilled without a default.
        Schema::table('chatbot_settings', function (Blueprint $table) {
            $table->char('widget_secret', 64)->nullable()->after('allowed_domains');
        });

        // Step 2: generate a unique random secret for every existing settings row.
        DB::table('chatbot_settings')
            ->whereNull('widget_secret')
            ->each(function (object $row): void {
                DB::table('chatbot_settings')
                    ->where('chatbot_id', $row->chatbot_id)
                    ->update(['widget_secret' => bin2hex(random_bytes(32))]);
            });

        // Step 3: enforce NOT NULL — all rows are now populated.
        DB::statement('ALTER TABLE chatbot_settings ALTER COLUMN widget_secret SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('chatbot_settings', function (Blueprint $table) {
            $table->dropColumn('widget_secret');
        });
    }
};
