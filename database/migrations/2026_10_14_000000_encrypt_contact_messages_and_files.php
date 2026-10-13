<?php

use App\Support\DataVault;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An encrypted value is much longer than the text it hides, so the columns must not be short varchars anymore.
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->text('name')->change();
            $table->text('email')->change();
            $table->text('phone')->nullable()->change();
            $table->text('subject')->nullable()->change();
        });

        // Convert what is already stored. A problem here must never stop a deploy: `php artisan data:encrypt` can be re-run.
        try {
            DataVault::encryptMessages();
            DataVault::encryptAttachments();
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        // Encryption is not undone (the data would have to be written back as plain text).
    }
};
