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
        Schema::table('users', function (Blueprint $table) {
            // system_admin accounts keep client_id null; client_user accounts
            // are each tied to exactly one client (CLIENTS-001).
            $table->foreignId('client_id')->nullable()->after('role')
                ->constrained()->nullOnDelete();
            // Lets an admin disable a client user's access without deleting it.
            $table->boolean('is_active')->default(true)->after('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn(['client_id', 'is_active']);
        });
    }
};
