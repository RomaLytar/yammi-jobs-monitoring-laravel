<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_monitor_failure_groups', function (Blueprint $table): void {
            $table->id();
            $table->char('fingerprint', 16);
            $table->dateTimeTz('first_seen_at', 6);
            $table->dateTimeTz('last_seen_at', 6);
            $table->unsignedBigInteger('occurrences');
            $table->json('affected_job_classes');
            $table->uuid('last_job_uuid');
            $table->string('sample_exception_class');
            $table->text('sample_message');
            $table->mediumText('sample_stack_trace');
            $table->timestamps();

            $table->unique('fingerprint');
            $table->index('last_seen_at');
            $table->index('first_seen_at');
            $table->index('occurrences');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_monitor_failure_groups');
    }
};
