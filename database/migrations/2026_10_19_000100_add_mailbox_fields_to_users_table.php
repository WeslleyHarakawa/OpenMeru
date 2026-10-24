<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_admin');
            $table->string('recovery_email')->nullable()->after('is_active');
            $table->text('signature')->nullable()->after('recovery_email');
            $table->timestamp('last_login_at')->nullable()->after('signature');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'is_active', 'recovery_email', 'signature', 'last_login_at']);
        });
    }
};
