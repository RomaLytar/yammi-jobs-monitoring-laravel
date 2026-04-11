<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_monitor', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('job_class');
            $table->string('connection');
            $table->string('queue');
            $table->string('status', 16);
            $table->unsignedInteger('attempt');
            $table->dateTimeTz('started_at', 6);
            $table->dateTimeTz('finished_at', 6)->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->text('exception')->nullable();
            $table->timestamps();

            $table->unique(['uuid', 'attempt']);
            $table->index(['status', 'created_at']);
            $table->index(['job_class', 'created_at']);
            $table->index(['queue', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_monitor');
    }
};
