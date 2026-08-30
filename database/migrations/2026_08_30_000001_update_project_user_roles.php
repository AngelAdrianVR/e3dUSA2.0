<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renombra los roles de los miembros del proyecto:
     * 'viewer'       -> 'Colaborador'
     * 'editor'       -> 'Administrador'
     */
    public function up(): void
    {
        // 1. Ampliar el enum para poder actualizar los datos existentes
        DB::statement("ALTER TABLE project_user MODIFY role ENUM('viewer','editor','Colaborador','Administrador') NOT NULL DEFAULT 'Colaborador'");

        // 2. Migrar los valores existentes
        DB::table('project_user')->where('role', 'viewer')->update(['role' => 'Colaborador']);
        DB::table('project_user')->where('role', 'editor')->update(['role' => 'Administrador']);

        // 3. Dejar únicamente los nuevos valores
        DB::statement("ALTER TABLE project_user MODIFY role ENUM('Colaborador','Administrador') NOT NULL DEFAULT 'Colaborador'");
    }

    public function down(): void
    {
        // 1. Ampliar el enum para poder restaurar los valores anteriores
        DB::statement("ALTER TABLE project_user MODIFY role ENUM('Colaborador','Administrador','viewer','editor') NOT NULL DEFAULT 'Colaborador'");

        // 2. Restaurar los valores
        DB::table('project_user')->where('role', 'Colaborador')->update(['role' => 'viewer']);
        DB::table('project_user')->where('role', 'Administrador')->update(['role' => 'editor']);

        // 3. Volver al enum original
        DB::statement("ALTER TABLE project_user MODIFY role ENUM('viewer','editor') NOT NULL DEFAULT 'viewer'");
    }
};
