<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_qr_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('type', ['in', 'out']);

            $table->string('signed_url', 500);

            // PNG bytes (or HTML fallback) encoded as base64.
            // Nullable because the row is inserted before generateQrCode() runs.
            $table->longText('qr_base64')->nullable();

            $table->timestamp('expires_at');

            $table->timestamps();

            // One QR row per (user, direction). Required for updateOrCreate()
            // in regenerateFor() and prevents duplicate rows on concurrent
            // requests.
            $table->unique(['user_id', 'type'], 'uq_attendance_qr_user_type');

            // Speeds up fresh() reuse check and the prune command.
            $table->index('expires_at', 'idx_attendance_qr_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_qr_codes');
    }
};
