<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_monitor_alert_rule_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alert_rule_id')
                ->constrained('jobs_monitor_alert_rules')
                ->cascadeOnDelete();
            $table->string('channel_name', 50);
            $table->unsignedInteger('position')->default(0);

            $table->unique(['alert_rule_id', 'channel_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_monitor_alert_rule_channels');
    }
};
