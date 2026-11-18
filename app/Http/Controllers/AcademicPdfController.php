<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\User;
use App\Services\AcademicPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AcademicPdfController extends Controller
{
    public function history(Request $request, AcademicPdfService $pdf): Response
    {
        return $pdf->history($request->user());
    }

    public function studentHistory(User $student, AcademicPdfService $pdf): Response
    {
        abort_unless($student->role === 'student', 404);

        return $pdf->history($student);
    }

    public function certificate(Request $request, Certificate $certificate, AcademicPdfService $pdf): Response
    {
        abort_unless($certificate->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return $pdf->certificate($certificate, $request->boolean('inline'));
    }

    public function completeHistory(AcademicPdfService $pdf): Response
    {
        return $pdf->completeHistory();
    }
}
