<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Usuario que dio de alta al cliente.
            $table->unsignedBigInteger('created_by')->nullable()->after('account_manager_id')->index();

            // Autorización de contactos repetidos: null = sin contactos repetidos,
            // Pendiente / Autorizado / Rechazado.
            $table->string('duplicate_contacts_status', 20)->nullable()->after('created_by')->index();
            $table->text('duplicate_contacts_reason')->nullable()->after('duplicate_contacts_status');
            $table->json('duplicate_contacts_ids')->nullable()->after('duplicate_contacts_reason');
            $table->timestamp('duplicate_contacts_requested_at')->nullable()->after('duplicate_contacts_ids');
            $table->timestamp('duplicate_contacts_resolved_at')->nullable()->after('duplicate_contacts_requested_at');
            $table->unsignedBigInteger('duplicate_contacts_resolved_by')->nullable()->after('duplicate_contacts_resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'created_by',
                'duplicate_contacts_status',
                'duplicate_contacts_reason',
                'duplicate_contacts_ids',
                'duplicate_contacts_requested_at',
                'duplicate_contacts_resolved_at',
                'duplicate_contacts_resolved_by',
            ]);
        });
    }
};
