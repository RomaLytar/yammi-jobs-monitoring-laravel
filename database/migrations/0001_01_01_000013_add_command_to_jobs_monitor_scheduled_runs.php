<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs_monitor_scheduled_runs', function (Blueprint $table): void {
            $table->string('command', 1024)->nullable()->after('task_name');
        });
    }

    public function down(): void
    {
        Schema::table('jobs_monitor_scheduled_runs', function (Blueprint $table): void {
            $table->dropColumn('command');
        });
    }
};
