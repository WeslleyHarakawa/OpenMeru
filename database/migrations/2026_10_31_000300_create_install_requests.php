<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Install it for me" orders from the openmeru.com landing. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('install_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('domain')->nullable();
            $table->string('panel', 40)->nullable();
            $table->text('notes')->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('install_requests');
    }
};
