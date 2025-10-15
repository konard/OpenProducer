<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_id')->unique()->index();
            $table->string('repository');
            $table->unsignedBigInteger('trigger_issue_number');
            $table->string('status')->default('pending'); // pending, running, completed, failed, cancelled
            $table->json('configuration');
            $table->boolean('dry_run')->default(false);
            $table->boolean('confirmed')->default(false);
            $table->integer('issues_created')->default(0);
            $table->integer('issues_planned')->default(0);
            $table->text('error_message')->nullable();
            $table->json('log_data')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['repository', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_runs');
    }
};
