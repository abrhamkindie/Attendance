<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();

            // Free-text date kept as VARCHAR on purpose — legacy data arrives in
            // mixed formats and is normalised in PHP via DateHelper::parseLooseDate().
            $table->string('date_holiday');

            $table->string('name')->nullable();
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
