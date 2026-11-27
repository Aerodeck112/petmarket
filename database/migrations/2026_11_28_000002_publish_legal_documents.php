<?php

use App\Support\LegalDocuments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Actualizarea 0.1.2: documentele legale noi (termeni, retur + formular de retragere, livrare, confidențialitate, cookies).
 * Se înlocuiesc doar paginile care nu au o versiune de document (adică textele preluate din magazinul vechi);
 * un text scris deja din admin cu o versiune completată rămâne neschimbat. Pagina „Formular de retragere” se creează.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('pages')) {
            return;
        }
        foreach (LegalDocuments::all() as $slug => $doc) {
            $page = DB::table('pages')->where('slug', $slug)->first();
            if (! $page) {
                DB::table('pages')->insert(['slug' => $slug, 'title' => $doc['title'], 'template' => 'legal', 'status' => 'published', 'body' => $doc['body'],
                    'document_version' => LegalDocuments::VERSION, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            } elseif (blank($page->document_version)) {
                DB::table('pages')->where('id', $page->id)->update(['title' => $doc['title'], 'body' => $doc['body'],
                    'document_version' => LegalDocuments::VERSION, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void {}
};
