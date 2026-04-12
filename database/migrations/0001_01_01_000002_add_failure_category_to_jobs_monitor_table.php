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
            $table->string('failure_category', 16)->nullable()->after('exception');
            $table->index(['failure_category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('jobs_monitor', function (Blueprint $table): void {
            $table->dropIndex(['failure_category', 'created_at']);
            $table->dropColumn('failure_category');
        });
    }
};
