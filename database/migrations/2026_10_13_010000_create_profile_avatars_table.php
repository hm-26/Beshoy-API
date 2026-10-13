<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The site has a single profile picture. It is kept in the database (base64) instead of on disk, so it
        // survives redeploys on hosts with an ephemeral file system (Railway). `version` changes with every new picture
        // and is part of the image URL, which lets browsers cache it for a long time.
        Schema::create('profile_avatars', function (Blueprint $table) {
            $table->id();
            $table->string('mime', 30);
            $table->string('version', 16);
            $table->longText('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_avatars');
    }
};
