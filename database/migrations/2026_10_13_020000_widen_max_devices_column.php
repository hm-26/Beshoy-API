<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The admin may now type any number (up to a billion) or choose "unlimited" (stored as 0), so the column
        // has to be a full integer instead of a tiny one.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('max_devices')->default(4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_devices')->default(4)->change();
        });
    }
};
