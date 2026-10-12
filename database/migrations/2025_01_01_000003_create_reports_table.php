<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 60)->nullable();
            $table->string('program_type', 60)->nullable();
            $table->string('bounty', 60)->nullable();
            $table->string('asset')->nullable();
            $table->string('weakness')->nullable();
            $table->string('title');
            $table->text('summary');
            $table->string('severity', 20)->default('Review');
            $table->text('steps_to_reproduce')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->text('supporting_references')->nullable(); // "references" is a reserved SQL word
            $table->string('endpoint')->nullable();
            $table->text('impact')->nullable();
            $table->text('recommendation')->nullable();
            $table->json('ar')->nullable();
            $table->boolean('published')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
