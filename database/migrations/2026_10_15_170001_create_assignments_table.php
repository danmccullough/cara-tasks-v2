<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chore_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kid_id')->constrained('users')->cascadeOnDelete();
            $table->json('days_of_week'); // ISO weekdays 1 (Mon) .. 7 (Sun)
            $table->time('due_time');
            $table->unsignedSmallInteger('remind_before_min')->default(15);
            $table->unsignedSmallInteger('nag_every_min')->default(10);
            $table->unsignedSmallInteger('max_nags')->default(3);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
