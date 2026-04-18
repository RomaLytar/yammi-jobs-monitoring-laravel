<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_monitor_settings', static function (Blueprint $table): void {
            $table->id();
            $table->string('group', 50);
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20);
            $table->timestamps();

            $table->unique(['group', 'key'], 'jm_settings_group_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_monitor_settings');
    }
};
