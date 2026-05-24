<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_schedules', function (Blueprint $table) {
            $table->unsignedInteger('pre_queue_minutes')
                ->default(0)
                ->after('closes_at')
                ->comment('How many minutes before the schedule opens the queue may already be taken.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_schedules', function (Blueprint $table) {
            $table->dropColumn('pre_queue_minutes');
        });
    }
};
