<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbots', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));

            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->string('name', 255);
            $table->string('public_id', 32)->unique(); // used in <script> embed
            $table->string('status', 20)->default('draft'); // draft|active|paused
            $table->string('language', 10)->default('en');

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        // Blueprint index() on an existing column; named to match blueprint DDL.
        Schema::table('chatbots', function (Blueprint $table) {
            $table->index('organization_id', 'idx_chatbots_org');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbots');
    }
};
