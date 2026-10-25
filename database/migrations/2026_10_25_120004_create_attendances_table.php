<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One attendance row per employee per day. Includes the punch data, the
     * break/overtime tallies and the geofence audit columns that the
     * controllers and the Attendance model read/write.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Day the row belongs to.
            $table->date('date');

            // Punch data.
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->text('clock_in_image')->nullable();
            $table->text('clock_out_image')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->decimal('break_hours', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->string('status')->default('present');
            $table->text('notes')->nullable();

            // Punch context (coarse network location + text label).
            $table->string('ip_address')->nullable();
            $table->string('location')->nullable();

            // Geofence: the site this employee was expected at, and the
            // distance/accuracy measured at each punch.
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('assigned_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('clock_in_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('clock_out_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedInteger('clock_in_distance_m')->nullable();
            $table->unsignedInteger('clock_out_distance_m')->nullable();
            $table->decimal('clock_in_accuracy_m', 6, 2)->nullable();
            $table->decimal('clock_out_accuracy_m', 6, 2)->nullable();
            $table->string('clock_in_match')->nullable();
            $table->string('clock_out_match')->nullable();
            $table->timestamp('auto_closed_at')->nullable();

            // Out-of-office duty tracking.
            $table->foreignId('duty_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->text('duty_note')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'date'], 'uq_attendance_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
