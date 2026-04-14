<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_monitor_alert_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('trigger', 50);
            $table->string('window', 16)->nullable();
            $table->unsignedInteger('threshold');
            $table->unsignedInteger('cooldown_minutes');
            $table->unsignedInteger('min_attempt')->nullable();
            $table->string('trigger_value', 255)->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('overrides_built_in', 100)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['enabled', 'position']);
            $table->index('overrides_built_in');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_monitor_alert_rules');
    }
};
