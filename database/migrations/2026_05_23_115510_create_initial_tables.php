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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('tts_language')->comment('Language code for text-to-speech, e.g., en-US, zh-CN, id-ID')->default('id-ID');
            $table->string('tts_template')->comment('Text voice that will be played when a ticket is called. There are 2 variables: {queue}, {counter}');
            $table->foreignId('owner_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', 'id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->enum('role', ['admin', 'staff'])->default('staff');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ticket_prefix')->comment("example: CS for customer service, IT for IT support, etc.");
            $table->boolean('is_login_required')->default(false);
            $table->unique(['tenant_id', 'ticket_prefix']);
            $table->timestamps();
        });

        Schema::create('service_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->integer('day')->comment('0 = Monday, 1 = Tuesday, ..., 6 = Sunday');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->integer('max_tickets')->nullable()->comment('Maximum number of tickets that can be issued for this service on this day. Null means no limit.');
            $table->boolean('is_available')->default(true)
                ->comment('If false, the service is not available on this day. Schedule is open based on "available==true && opens_at <= now <= closes_at"');
            $table->unique(['service_id', 'day', 'opens_at']);
            $table->timestamps();
        });

        Schema::create('counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment("Counter A, Room 2, etc.");
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('counter_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->unique(['counter_id', 'staff_id']);
        });

        Schema::create('tickets', function(Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants', 'id')->cascadeOnDelete();
            $table->foreignId('service_schedule_id')->constrained('service_schedules', 'id')->cascadeOnDelete();
            $table->enum('status', ['waiting', 'called', 'serving', 'completed', 'skipped', 'cancelled'])->default('waiting');
            $table->integer('sequence');
            $table->date('service_date');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('serving_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->foreignId('counter_id')->nullable()->constrained('counters', 'id')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_schedule_id', 'service_date', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('counter_staff');
        Schema::dropIfExists('counters');
        Schema::dropIfExists('service_schedules');
        Schema::dropIfExists('services');
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
