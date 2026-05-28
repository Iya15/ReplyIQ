<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('metric', 50);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('value')->default(0);
            $table->timestampTz('recorded_at')->useCurrent();

            $table->unique(['organization_id', 'metric', 'period_start']);
            $table->index(['organization_id', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
