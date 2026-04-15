<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_monitor_scheduled_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('mutex', 64);
            $table->string('task_name', 255);
            $table->string('expression', 100);
            $table->string('timezone', 64)->nullable();
            $table->string('status', 20);
            $table->dateTimeTz('started_at', 6);
            $table->dateTimeTz('finished_at', 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('output')->nullable();
            $table->text('exception')->nullable();
            $table->string('host', 255)->nullable();
            $table->timestamps();

            $table->index(['mutex', 'started_at'], 'jm_sr_mutex_started_at');
            $table->index(['status', 'started_at'], 'jm_sr_status_started_at');
            $table->index('task_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_monitor_scheduled_runs');
    }
};
