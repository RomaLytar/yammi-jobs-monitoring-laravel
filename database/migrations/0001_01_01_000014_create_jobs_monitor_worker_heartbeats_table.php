<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_monitor_worker_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->string('worker_id', 191);
            $table->string('connection', 64);
            $table->string('queue', 255);
            $table->string('host', 255);
            $table->unsignedInteger('pid');
            $table->dateTimeTz('last_seen_at', 6);
            $table->dateTimeTz('stopped_at', 6)->nullable();
            $table->timestamps();

            $table->unique('worker_id', 'jm_wh_worker_id_unique');
            $table->index(['connection', 'queue', 'last_seen_at'], 'jm_wh_queue_last_seen');
            $table->index('last_seen_at', 'jm_wh_last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_monitor_worker_heartbeats');
    }
};
