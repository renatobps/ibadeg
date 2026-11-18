<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 20)->default('student')->index();
            $t->string('status', 20)->default('pending')->index();
            $t->string('document', 30)->nullable()->unique();
            $t->date('birth_date')->nullable();
            foreach (['address', 'city', 'birthplace', 'nationality', 'father_name', 'mother_name', 'education', 'education_other', 'church', 'pastor', 'church_role', 'nucleus'] as $f) {
                $t->string($f)->nullable();
            }
            $t->string('postal_code', 8)->nullable();
            $t->string('state', 2)->nullable();
            $t->string('phone', 15)->nullable();
            $t->string('sex', 20)->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
        });
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->text('description');
            $t->unsignedSmallInteger('max_attempts')->default(3);
            $t->decimal('passing_grade', 4, 2)->default(7);
            $t->unsignedSmallInteger('workload_hours')->nullable();
            $t->timestamps();
        });
        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('number');
            $t->string('title');
            $t->string('book_file');
            $t->string('exam_file');
            $t->unsignedSmallInteger('max_attempts')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['course_id', 'number']);
        });
        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subject_id')->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->unsignedSmallInteger('position');
            $t->string('group_code', 20);
            $t->text('instruction');
            $t->string('kind', 20);
            $t->string('label', 10)->nullable();
            $t->text('prompt')->nullable();
            $t->json('options')->nullable();
            $t->text('correct_answer');
            $t->json('excerpt')->nullable();
            $t->boolean('review_required')->default(false);
            $t->boolean('scorable')->default(true);
            $t->timestamps();
            $t->unique(['subject_id', 'code']);
        });
        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('attempt_limit')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'course_id']);
        });
        Schema::create('attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('subject_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('number');
            $t->string('status', 25)->default('in_progress')->index();
            $t->json('question_snapshot');
            $t->json('student_snapshot');
            $t->json('draft_answers')->nullable();
            $t->decimal('score', 4, 2)->nullable();
            $t->unsignedSmallInteger('max_points');
            $t->timestamp('started_at');
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'subject_id', 'number']);
        });
        Schema::create('attempt_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained()->restrictOnDelete();
            $t->string('question_code', 30);
            $t->text('answer');
            $t->decimal('points', 4, 2)->nullable();
            $t->boolean('needs_review');
            $t->text('feedback')->nullable();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['attempt_id', 'question_code']);
        });
        Schema::create('grade_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('revision');
            $t->decimal('grade', 4, 2);
            $t->decimal('points', 8, 2);
            $t->unsignedSmallInteger('max_points');
            $t->string('kind', 20);
            $t->text('reason');
            $t->json('answer_snapshot');
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at');
            $t->unique(['attempt_id', 'revision']);
        });
        Schema::create('certificates', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $t->json('snapshot');
            $t->timestamp('issued_at');
            $t->unique(['user_id', 'course_id']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->string('target');
            $t->json('details')->nullable();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'certificates', 'grade_records', 'attempt_answers', 'attempts', 'enrollments', 'questions', 'subjects', 'courses'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('approved_by');
            $t->dropColumn(['role', 'status', 'document', 'birth_date', 'address', 'city', 'birthplace', 'nationality', 'father_name', 'mother_name', 'education', 'education_other', 'church', 'pastor', 'church_role', 'nucleus', 'postal_code', 'state', 'phone', 'sex', 'approved_at']);
        });
    }
};
