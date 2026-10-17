<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An incomplete draft must not require an invented business category.
        Schema::table('products', fn (Blueprint $table) => $table->uuid('category_id')->nullable()->change());
        DB::statement("ALTER TABLE products ADD CONSTRAINT published_product_category CHECK (status <> 'published' OR category_id IS NOT NULL)");
        Schema::create('site_contents', function (Blueprint $table) {
            $table->string('key', 32)->primary();
            $table->boolean('published')->default(false);
            $table->json('translations');
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('products')->whereNull('category_id')->exists()) {
            throw new RuntimeException('Assign draft categories before reversing this migration.');
        }
        Schema::dropIfExists('site_contents');
        DB::statement('ALTER TABLE products DROP CHECK published_product_category');
        Schema::table('products', fn (Blueprint $table) => $table->uuid('category_id')->nullable(false)->change());
    }
};
