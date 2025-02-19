<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->enum('type', [\App\Models\PersonalAccessToken::TYPES]);
                $table->foreignId('auth_token_id')->nullable()
                    ->references('id')->on('personal_access_tokens')
                    ->onDelete('cascade');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('expires_at', 3)->nullable();
                $table->timestamps(3);
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
