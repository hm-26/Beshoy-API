<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Public contact details the admin chooses to show: email, phone, WhatsApp, Telegram, social links ...
        Schema::table('profiles', function (Blueprint $table) {
            $table->json('contact')->nullable()->after('skills');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('contact');
        });
    }
};
