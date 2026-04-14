<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_monitor_built_in_rule_state', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->boolean('enabled');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_monitor_built_in_rule_state');
    }
};
