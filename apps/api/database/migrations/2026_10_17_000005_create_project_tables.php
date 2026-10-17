<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 120)->unique();
            $table->boolean('active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->timestamps();
            $table->index(['active', 'sort_order']);
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 32)->unique();
            $table->string('slug', 120)->unique();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('location', 200)->nullable();
            $table->decimal('area', 12, 2)->nullable();
            $table->string('area_unit', 8)->nullable();
            $table->foreignUuid('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'published_at', 'sort_order']);
        });
        foreach (['project_category_translations' => ['project_category_id', 'project_categories'], 'project_translations' => ['project_id', 'projects']] as $name => [$foreign, $owner]) {
            Schema::create($name, function (Blueprint $table) use ($foreign, $owner, $name) {
                $table->id();
                $table->foreignUuid($foreign)->constrained($owner)->cascadeOnDelete();
                $table->string('locale', 35);
                $table->string($name === 'project_translations' ? 'title' : 'name', 160);
                $table->text('description')->nullable();
                if ($name === 'project_translations') {
                    $table->string('summary', 500)->nullable();
                    $table->string('area_display', 200)->nullable();
                }
                $table->string('seo_title', 160)->nullable();
                $table->string('seo_description', 320)->nullable();
                $table->timestamps();
                $table->unique([$foreign, 'locale']);
            });
        }
        Schema::create('project_category_project', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_category_id')->constrained()->restrictOnDelete();
            $table->primary(['project_id', 'project_category_id']);
        });
        Schema::create('project_products', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['project_id', 'product_id']);
        });
        Schema::create('project_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('media_asset_id')->constrained()->restrictOnDelete();
            $table->string('type', 12);
            $table->unsignedInteger('sort_order');
            $table->json('captions');
            $table->foreignUuid('poster_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->unique(['project_id', 'media_asset_id']);
            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        foreach (['project_gallery_items', 'project_products', 'project_category_project', 'project_translations', 'project_category_translations', 'projects', 'project_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
