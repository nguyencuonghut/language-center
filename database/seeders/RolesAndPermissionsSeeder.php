<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Xoá cache permission để tránh “kỷ niệm”
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Nhóm permission (tối thiểu cho MVP, bạn có thể mở rộng)
        $permissions = [
            // Branches
            'branch.view', 'branch.create', 'branch.update', 'branch.delete',

            // Rooms
            'room.view', 'room.create', 'room.update', 'room.delete',

            // Courses & Classes
            'course.view', 'course.create', 'course.update', 'course.delete',
            'class.view', 'class.create', 'class.update', 'class.close',

            // Students & Enrollments
            'student.view', 'student.create', 'student.update',
            'enrollment.view', 'enrollment.create', 'enrollment.update',

            // Tuition
            'invoice.view', 'invoice.create', 'invoice.update',
            'payment.view', 'payment.create',

            // Attendance & Timesheet
            'attendance.view', 'attendance.update',
            'timesheet.view', 'timesheet.submit', 'timesheet.approve', 'timesheet.lock',

            // Reports (tuỳ chọn)
            'report.view',
        ];

        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }

        // Roles
        $admin   = Role::findOrCreate('admin', 'web');
        $manager = Role::findOrCreate('manager', 'web');
        $teacher = Role::findOrCreate('teacher', 'web');

        // Gán quyền cho vai trò
        // Admin: full quyền
        $admin->syncPermissions(Permission::all());

        // Manager: quyền theo chi nhánh (logic branch-scope nằm ở Policy/Middleware, còn permission chỉ là “loại” hành động)
        $managerPerms = [
            'branch.view',
            'room.view','room.create','room.update',
            'course.view',
            'class.view','class.create','class.update','class.close',
            'student.view','student.create','student.update',
            'enrollment.view','enrollment.create','enrollment.update',
            'invoice.view','invoice.create','invoice.update',
            'payment.view','payment.create',
            'attendance.view','attendance.update',
            'timesheet.view','timesheet.submit',
            'report.view',
        ];
        $manager->syncPermissions($managerPerms);

        // Teacher
        $teacherPerms = [
            'attendance.view','attendance.update',
            'class.view',
            'report.view',
        ];
        $teacher->syncPermissions($teacherPerms);

        // Làm sạch cache permission lần nữa
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
