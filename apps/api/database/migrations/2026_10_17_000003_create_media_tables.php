<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20)->default('image');
            $table->string('original_disk', 32);
            $table->string('original_key', 255)->unique();
            $table->char('checksum', 64)->unique();
            $table->string('mime', 80);
            $table->string('original_filename');
            $table->string('source_path', 1024);
            $table->boolean('client_supplied')->default(false);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration', 12, 3)->nullable();
            $table->string('processing_state', 20)->default('pending');
            $table->string('approval_state', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->char('active_revision', 64)->nullable();
            $table->timestamp('imported_at');
            $table->timestamps();
        });
        Schema::create('media_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('media_asset_id')->constrained()->restrictOnDelete();
            $table->string('disk', 32);
            $table->string('state', 20)->default('private');
            $table->string('profile', 40);
            $table->string('profile_version', 80);
            $table->string('format', 12);
            $table->string('object_key', 255);
            $table->char('checksum', 64);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('revision', 64)->index();
            $table->char('publication_revision', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata');
            $table->timestamps();
            $table->unique(['disk', 'object_key']);
            $table->index(['media_asset_id', 'revision']);
        });
        Schema::create('media_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 35);
            $table->string('alt', 500)->nullable();
            $table->text('caption')->nullable();
            $table->string('credit', 500)->nullable();
            $table->timestamps();
            $table->unique(['media_asset_id', 'locale']);
        });
        // Per-source ledger instead of batches: durable retries, duplicate tracking and staged mappings.
        Schema::create('media_imports', function (Blueprint $table) {
            $table->id();
            $table->char('root_hash', 64);
            $table->string('source', 512);
            $table->char('checksum', 64);
            $table->foreignUuid('media_asset_id')->constrained()->restrictOnDelete();
            $table->json('mapping');
            $table->timestamps();
            $table->unique(['root_hash', 'source']);
        });
        // Future owners register references here within their own transaction, locking the asset first.
        Schema::create('media_references', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('media_asset_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('media_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('owner_type', 80);
            $table->string('owner_key', 120);
            $table->string('slot', 80);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_key', 'slot']);
        });
    }

    public function down(): void
    {
        foreach (['media_references', 'media_imports', 'media_translations', 'media_variants', 'media_assets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
