<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('folder', 20)->default('inbox');
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->text('references')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('subject', 998)->nullable();
            $table->longText('text_body')->nullable();
            $table->longText('html_body')->nullable();
            $table->string('snippet', 300)->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->boolean('has_attachments')->default(false);
            $table->string('raw_path')->nullable();
            $table->timestamp('date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'folder', 'date']);
            $table->index(['user_id', 'message_id']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('content_id')->nullable();
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('settings');
    }
};
