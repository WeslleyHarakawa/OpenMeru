<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** People who asked openmeru.com to tell them when the managed cloud version launches. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_waitlist', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 8)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_waitlist');
    }
};
