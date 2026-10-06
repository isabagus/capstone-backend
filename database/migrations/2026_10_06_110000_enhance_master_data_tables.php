<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (!Schema::hasColumn('brands', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('brands', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
        });

        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'code')) {
                $table->string('code', 20)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('warehouses', 'short_name')) {
                $table->string('short_name', 50)->nullable()->after('name');
            }
            if (!Schema::hasColumn('warehouses', 'phone')) {
                $table->string('phone', 30)->nullable()->after('address');
            }
            if (!Schema::hasColumn('warehouses', 'pic_name')) {
                $table->string('pic_name', 100)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('warehouses', 'description')) {
                $table->text('description')->nullable()->after('pic_name');
            }
            if (!Schema::hasColumn('warehouses', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'kind')) {
                $table->string('kind', 30)->default('material')->after('name');
            }
            if (!Schema::hasColumn('categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('kind');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['kind', 'is_active']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['code', 'short_name', 'phone', 'pic_name', 'description', 'is_active']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_active']);
        });
    }
};
