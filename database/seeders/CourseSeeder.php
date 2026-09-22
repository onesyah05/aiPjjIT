<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = [
            ['code' => 'IF101', 'name' => 'Algoritma dan Pemrograman', 'semester' => 1],
            ['code' => 'IF201', 'name' => 'Struktur Data', 'semester' => 2],
            ['code' => 'IF301', 'name' => 'Basis Data', 'semester' => 3],
            ['code' => 'IF302', 'name' => 'Jaringan Komputer', 'semester' => 3],
        ];

        foreach ($courses as $course) {
            Course::query()->updateOrCreate(['code' => $course['code']], [...$course, 'status' => 'active']);
        }
    }
}
