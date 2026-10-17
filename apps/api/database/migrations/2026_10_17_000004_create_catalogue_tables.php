<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 120)->unique();
            $table->foreignUuid('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->boolean('active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->timestamps();
            $table->index(['active', 'sort_order']);
        });
        Schema::create('finishes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 120)->unique();
            $table->boolean('active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->timestamps();
            $table->index(['active', 'sort_order']);
        });
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 32)->unique();
            $table->string('slug', 120)->unique();
            $table->foreignUuid('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->boolean('custom_size_allowed')->default(false);
            $table->string('custom_measurement_unit', 8)->nullable();
            foreach (['min_width', 'max_width', 'min_length', 'max_length'] as $column) {
                $table->decimal($column, 12, 4)->nullable();
            }
            $table->timestamps();
            $table->index(['status', 'published_at']);
            $table->index(['featured', 'sort_order']);
        });
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            foreach (['width', 'length', 'height', 'thickness'] as $column) {
                $table->decimal($column, 12, 4)->nullable($column === 'height' || $column === 'thickness');
            }
            $table->string('measurement_unit', 8);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'sort_order']);
        });
        foreach (['product_category_translations' => ['product_category_id', 'product_categories'], 'product_translations' => ['product_id', 'products'], 'finish_translations' => ['finish_id', 'finishes'], 'product_size_translations' => ['product_size_id', 'product_sizes']] as $name => [$foreign, $parent]) {
            Schema::create($name, function (Blueprint $table) use ($foreign, $parent, $name) {
                $table->id();
                $table->foreignUuid($foreign)->constrained($parent)->cascadeOnDelete();
                $table->string('locale', 35);
                $table->string($name === 'product_size_translations' ? 'label' : 'name', 160);
                if ($name !== 'product_size_translations') {
                    $table->text('description')->nullable();
                }
                if (in_array($name, ['product_category_translations', 'product_translations'], true)) {
                    $table->string('seo_title', 160)->nullable();
                    $table->string('seo_description', 320)->nullable();
                }
                if ($name === 'product_translations') {
                    $table->string('colour', 160)->nullable();
                    $table->string('origin', 160)->nullable();
                    $table->json('technical_information')->nullable();
                }
                $table->timestamps();
                $table->unique([$foreign, 'locale']);
            });
        }
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('unit', 16);
            $table->unique(['product_id', 'unit']);
        });
        Schema::create('product_finishes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('finish_id')->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['product_id', 'finish_id']);
        });
        DB::statement('ALTER TABLE product_categories ADD CONSTRAINT category_not_self CHECK (parent_id IS NULL OR parent_id <> id)');
        DB::statement("ALTER TABLE products ADD CONSTRAINT product_status_valid CHECK (status IN ('draft', 'published'))");
        DB::statement('ALTER TABLE products ADD CONSTRAINT custom_bounds_valid CHECK ((custom_size_allowed = 0 AND custom_measurement_unit IS NULL AND min_width IS NULL AND max_width IS NULL AND min_length IS NULL AND max_length IS NULL) OR (custom_size_allowed = 1 AND custom_measurement_unit IS NOT NULL AND min_width > 0 AND max_width >= min_width AND min_length > 0 AND max_length >= min_length AND min_width IS NOT NULL AND max_width IS NOT NULL AND min_length IS NOT NULL AND max_length IS NOT NULL))');
        DB::statement('ALTER TABLE product_sizes ADD CONSTRAINT size_dimensions_positive CHECK (width > 0 AND length > 0 AND (height IS NULL OR height > 0) AND (thickness IS NULL OR thickness > 0))');
    }

    public function down(): void
    {
        foreach (['product_finishes', 'product_units', 'product_size_translations', 'finish_translations', 'product_translations', 'product_category_translations', 'product_sizes', 'products', 'finishes', 'product_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
