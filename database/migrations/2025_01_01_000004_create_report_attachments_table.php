<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('path');                       // relative to the private "local" disk
            $table->string('original_name');
            $table->string('extension', 10);
            $table->string('mime', 100);
            $table->string('kind', 10)->index();          // image | video | file
            $table->unsignedBigInteger('size');           // bytes
            $table->string('caption')->nullable();
            $table->string('caption_ar')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
    }
};
