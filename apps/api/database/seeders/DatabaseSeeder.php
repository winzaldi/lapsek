<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roleDefinitions = [
            'system_admin' => 'Admin Sistem',
            'puskesmas_operator' => 'Operator Puskesmas',
            'school_operator' => 'Operator Sekolah/Guru',
            'student' => 'Siswa',
        ];

        foreach ($roleDefinitions as $name => $label) {
            Role::updateOrCreate(['name' => $name], ['label' => $label]);
        }

        $permissionDefinitions = [
            'schools.view' => 'Melihat sekolah sesuai cakupan',
            'academic.view' => 'Melihat periode dan kelas sesuai cakupan',
            'students.view' => 'Melihat siswa sesuai cakupan',
            'students.manage' => 'Mengelola siswa sesuai cakupan',
            'enrollments.manage' => 'Mengelola enrollment sesuai cakupan',
            'student-links.verify' => 'Memverifikasi tautan akun siswa sesuai cakupan',
            'imports.manage' => 'Mengimpor siswa sesuai cakupan',
            'audit.view' => 'Melihat audit log',
            'examinations.view' => 'Melihat pemeriksaan sesuai cakupan',
            'examinations.manage' => 'Membuat dan mengubah draf pemeriksaan',
            'examinations.finalize' => 'Memfinalisasi pemeriksaan',
            'examinations.publish' => 'Mempublikasikan rapor pemeriksaan',
            'examinations.correct' => 'Mengoreksi pemeriksaan final dengan alasan',
        ];

        foreach ($permissionDefinitions as $name => $label) {
            Permission::updateOrCreate(['name' => $name], ['label' => $label]);
        }

        $puskesmasPermissions = [
            'schools.view', 'academic.view', 'students.view', 'students.manage',
            'enrollments.manage', 'student-links.verify', 'imports.manage',
            'examinations.view', 'examinations.manage', 'examinations.finalize',
            'examinations.publish', 'examinations.correct',
        ];
        $schoolPermissions = [
            'schools.view', 'academic.view', 'students.view', 'students.manage',
            'enrollments.manage', 'student-links.verify', 'imports.manage', 'examinations.view',
        ];

        DB::transaction(function () use ($puskesmasPermissions, $schoolPermissions): void {
            Role::where('name', 'puskesmas_operator')->firstOrFail()
                ->permissions()->sync(Permission::whereIn('name', $puskesmasPermissions)->pluck('id'));
            Role::where('name', 'school_operator')->firstOrFail()
                ->permissions()->sync(Permission::whereIn('name', $schoolPermissions)->pluck('id'));
            Role::where('name', 'student')->firstOrFail()->permissions()->sync([]);
            Role::where('name', 'system_admin')->firstOrFail()->permissions()->sync([]);
        });
    }
}
