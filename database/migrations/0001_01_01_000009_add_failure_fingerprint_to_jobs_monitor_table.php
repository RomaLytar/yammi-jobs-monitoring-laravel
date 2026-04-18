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
            $table->char('failure_fingerprint', 16)->nullable()->after('failure_category');
            $table->index(['failure_fingerprint', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('jobs_monitor', function (Blueprint $table): void {
            $table->dropIndex(['failure_fingerprint', 'created_at']);
            $table->dropColumn('failure_fingerprint');
        });
    }
};
