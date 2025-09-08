<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendanceController;

class AttendanceController extends TeacherAttendanceController
{
    // Inherit all methods from Teacher\AttendanceController
    // The logic will automatically route to Admin pages based on user role detection
}
