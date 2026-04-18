<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_monitor_duration_baselines', function (Blueprint $table): void {
            $table->id();
            $table->string('job_class', 255);
            $table->unsignedInteger('samples_count');
            $table->unsignedInteger('p50_ms');
            $table->unsignedInteger('p95_ms');
            $table->unsignedInteger('min_ms');
            $table->unsignedInteger('max_ms');
            $table->dateTimeTz('computed_over_from', 6);
            $table->dateTimeTz('computed_over_to', 6);
            $table->timestamps();

            $table->unique('job_class', 'jm_dbl_job_class_unique');
        });

        Schema::create('jobs_monitor_duration_anomalies', function (Blueprint $table): void {
            $table->id();
            $table->string('job_uuid', 64);
            $table->unsignedInteger('attempt');
            $table->string('job_class', 255);
            $table->string('kind', 10);
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('baseline_p50_ms');
            $table->unsignedInteger('baseline_p95_ms');
            $table->unsignedInteger('samples_count');
            $table->dateTimeTz('detected_at', 6);
            $table->timestamps();

            $table->index(['job_class', 'detected_at'], 'jm_da_class_detected');
            $table->index(['detected_at'], 'jm_da_detected');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_monitor_duration_anomalies');
        Schema::dropIfExists('jobs_monitor_duration_baselines');
    }
};
