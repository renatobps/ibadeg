<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $data = json_decode(file_get_contents(resource_path('course/questions.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($data) {
            $course = Course::firstOrCreate(['slug' => 'teologia'], ['name' => 'Curso Médio em Teologia', 'description' => 'Uma jornada de estudo bíblico, formação teológica e serviço cristão em 21 disciplinas.', 'max_attempts' => 3, 'passing_grade' => 6, 'workload_hours' => 1680]);
            foreach ($data as $exam) {
                $subject = Subject::firstOrCreate(['course_id' => $course->id, 'number' => $exam['number']], ['title' => $exam['title'], 'book_file' => sprintf('%02d', $exam['number']).'.pdf', 'exam_file' => sprintf('%02d', $exam['number']).'.pdf']);
                foreach ($exam['questions'] as $q) {
                    Question::firstOrCreate(['subject_id' => $subject->id, 'code' => $q['code']], $q);
                }
            }
        });
    }
}
