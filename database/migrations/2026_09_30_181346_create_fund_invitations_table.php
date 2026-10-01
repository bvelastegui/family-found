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
        Schema::create('fund_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->index();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('expires_at', 6);
            $table->dateTime('used_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['email', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_invitations');
    }
};
