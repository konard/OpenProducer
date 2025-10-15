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
        Schema::create('bot_created_issues', function (Blueprint $table) {
            $table->id();
            $table->string('run_id')->index();
            $table->string('repository');
            $table->unsignedBigInteger('issue_number');
            $table->string('issue_url');
            $table->string('issue_title');
            $table->text('issue_body')->nullable();
            $table->string('hash')->unique()->index(); // for deduplication
            $table->json('labels')->nullable();
            $table->string('status')->default('created'); // created, deleted, failed
            $table->timestamps();

            $table->foreign('run_id')->references('run_id')->on('bot_runs')->onDelete('cascade');
            $table->index(['repository', 'issue_number']);
            $table->index(['run_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_created_issues');
    }
};
