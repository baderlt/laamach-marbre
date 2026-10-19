<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->decimal('width', 12, 4)->nullable()->change();
            $table->decimal('length', 12, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('product_sizes')->whereNull('width')->orWhereNull('length')->exists()) {
            throw new RuntimeException('Partial dimensions exist. Complete or remove those entries before reverting this migration.');
        }
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->decimal('width', 12, 4)->nullable(false)->change();
            $table->decimal('length', 12, 4)->nullable(false)->change();
        });
    }
};
