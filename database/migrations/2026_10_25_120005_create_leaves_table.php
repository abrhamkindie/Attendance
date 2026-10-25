<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('leave_type')->nullable();

            // Span fallback used when date_details JSON is empty.
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();

            // Half-day flag (mirrors the per-day half_day_type in date_details).
            $table->boolean('is_half_day')->default(false);

            // Per-day detail: JSON array of
            // { "date": "Y-m-d", "half_day_type": "morning|afternoon|null" }.
            $table->json('date_details')->nullable();

            $table->string('status')->default('pending')->index();
            $table->text('reason')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
