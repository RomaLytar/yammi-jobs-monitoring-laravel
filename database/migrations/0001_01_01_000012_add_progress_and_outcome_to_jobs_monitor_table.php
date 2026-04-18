<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs_monitor', function (Blueprint $table): void {
            $table->unsignedInteger('progress_current')->nullable()->after('payload');
            $table->unsignedInteger('progress_total')->nullable()->after('progress_current');
            $table->string('progress_description', 255)->nullable()->after('progress_total');
            $table->dateTimeTz('progress_updated_at', 6)->nullable()->after('progress_description');

            $table->unsignedInteger('outcome_processed')->nullable()->after('progress_updated_at');
            $table->unsignedInteger('outcome_skipped')->nullable()->after('outcome_processed');
            $table->unsignedInteger('outcome_warnings_count')->nullable()->after('outcome_skipped');
            $table->string('outcome_status', 20)->nullable()->after('outcome_warnings_count');
        });
    }

    public function down(): void
    {
        Schema::table('jobs_monitor', function (Blueprint $table): void {
            $table->dropColumn([
                'progress_current',
                'progress_total',
                'progress_description',
                'progress_updated_at',
                'outcome_processed',
                'outcome_skipped',
                'outcome_warnings_count',
                'outcome_status',
            ]);
        });
    }
};
