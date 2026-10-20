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
        Schema::table('stock_opnames', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_opnames', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
            if (!Schema::hasColumn('stock_opnames', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_opnames', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('approved_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['notes', 'approved_by', 'completed_at']);
        });
    }
};
