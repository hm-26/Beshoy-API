<?php

use App\Support\DataVault;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An encrypted value is far longer than the text it hides (and JSON columns only accept valid JSON), so the
        // columns that will hold it become LONGTEXT. MySQL converts JSON -> LONGTEXT keeping the content.
        Schema::table('reports', function (Blueprint $table) {
            foreach (['platform', 'program_type', 'bounty', 'asset', 'weakness', 'endpoint', 'steps_to_reproduce',
                'supporting_references', 'impact', 'recommendation', 'request', 'response', 'ar'] as $col) {
                $table->longText($col)->nullable()->change();
            }
            $table->longText('title')->change();
            $table->longText('summary')->change();
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->longText('name')->change();
            foreach (['role', 'bio', 'location', 'skills', 'contact', 'ar'] as $col) {
                $table->longText($col)->nullable()->change();
            }
        });

        Schema::table('report_attachments', function (Blueprint $table) {
            $table->text('original_name')->change();
            $table->text('caption')->nullable()->change();
            $table->text('caption_ar')->nullable()->change();
        });

        // Encrypt what is already stored. A problem here must never stop a deploy: `php artisan data:encrypt` can be re-run.
        try {
            DataVault::encryptTables();
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        // Encryption is not undone (the data would have to be written back as plain text).
    }
};
