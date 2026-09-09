<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_records', function (Blueprint $table) {
            $table->dropUnique(['sale_document_id', 'record_type']);
            $table->unique(['sale_document_id', 'record_type', 'environment'], 'fiscal_records_doc_type_env_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_records', function (Blueprint $table) {
            $table->dropUnique('fiscal_records_doc_type_env_unique');
            $table->unique(['sale_document_id', 'record_type']);
        });
    }
};
