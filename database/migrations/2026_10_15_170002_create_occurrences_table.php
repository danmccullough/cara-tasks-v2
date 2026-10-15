<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chore_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kid_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('due_at'); // UTC
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('reminders_sent')->default(0);
            $table->dateTime('last_reminded_at')->nullable();
            $table->dateTime('parents_alerted_at')->nullable(); // "not done yet" alert sent at the due time
            $table->dateTime('started_at')->nullable(); // optional "I'm starting" tap
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'due_at']);
            $table->index(['status', 'due_at']);
            $table->index(['kid_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrences');
    }
};
