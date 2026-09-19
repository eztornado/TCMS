<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Hash sha256 del fichero de media: permite detectar "ya lo tengo" entre
 * central y devices sin comparar bytes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media') || Schema::hasColumn('media', 'hash')) {
            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->string('hash', 64)->nullable()->index();
        });

        // Backfill para media existente (el fichero sigue en su disco).
        DB::table('media')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $disk = Storage::disk($row->disk ?: 'public');

                if (! $disk->exists($row->path)) {
                    continue;
                }

                DB::table('media')->where('id', $row->id)->update([
                    'hash' => hash_file('sha256', $disk->path($row->path)),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('media') && Schema::hasColumn('media', 'hash')) {
            Schema::table('media', function (Blueprint $table) {
                $table->dropColumn('hash');
            });
        }
    }
};
