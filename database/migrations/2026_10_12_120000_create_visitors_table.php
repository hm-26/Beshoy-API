<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per unique visitor (browser). Only a keyed hash of a random id is stored: no IP, no user agent.
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
