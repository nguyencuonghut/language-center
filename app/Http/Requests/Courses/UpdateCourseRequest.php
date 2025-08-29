<?php

namespace App\Http\Requests\Courses;

use Illuminate\Validation\Rule;

class UpdateCourseRequest extends StoreCourseRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // Get the course ID from the route
        $course = $this->route('course');

        // Update the unique rule to ignore the current course
        $rules['code'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('courses', 'code')->ignore($course->id)
        ];

        return $rules;
    }
}
