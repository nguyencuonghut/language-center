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
        Schema::table('transfers', function (Blueprint $table) {
            // Audit trail columns
            $table->json('status_history')->nullable()->after('status');
            $table->json('change_log')->nullable()->after('status_history');
            $table->timestamp('last_modified_at')->nullable()->after('processed_at');
            $table->unsignedBigInteger('last_modified_by')->nullable()->after('last_modified_at');
            
            // Enhanced tracking
            $table->string('source_system')->default('manual')->after('last_modified_by');
            $table->text('admin_notes')->nullable()->after('source_system');
            $table->boolean('is_priority')->default(false)->after('admin_notes');
            
            // Foreign key for last modifier
            $table->foreign('last_modified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['last_modified_by']);
            $table->dropColumn([
                'status_history',
                'change_log', 
                'last_modified_at',
                'last_modified_by',
                'source_system',
                'admin_notes',
                'is_priority'
            ]);
        });
    }
};
