<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The default number of devices is now 4. Rows that still hold the old default (1) are moved to 4.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_devices')->default(4)->change();
        });
        DB::table('users')->where('max_devices', 1)->update(['max_devices' => 4]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_devices')->default(1)->change();
        });
    }
};
