<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class AcademicPdfService
{
    public function __construct(private AcademicService $academic) {}

    public function history(User $student): Response
    {
        $course = Course::with('subjects')->firstOrFail();
        $rows = $this->academic->transcript($student, $course);

        return $this->download('pdf.history', compact('student', 'course', 'rows'), 'historico-ibadeg-matricula-'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT).'.pdf');
    }

    public function certificate(Certificate $certificate, bool $inline = false): Response
    {
        return $this->download('pdf.certificate', compact('certificate'), 'certificado-ibadeg-'.$certificate->uuid.'.pdf', 'landscape', false, $inline);
    }

    public function completeHistory(): Response
    {
        $attempts = Attempt::with(['student', 'subject', 'grades.reviewer'])
            ->whereNotNull('submitted_at')->orderBy('user_id')->orderBy('subject_id')->orderBy('number')->get();

        return $this->download('pdf.complete-history', compact('attempts'), 'historico-completo-ibadeg-'.now()->format('Y-m-d').'.pdf');
    }

    /** @param array<string, mixed> $data */
    private function download(string $view, array $data, string $filename, string $orientation = 'portrait', bool $numberPages = true, bool $inline = false): Response
    {
        File::ensureDirectoryExists(storage_path('fonts'));
        File::ensureDirectoryExists(storage_path('framework/cache/pdf'));

        $pdf = Pdf::loadView($view, $data + [
            'logo' => 'data:image/png;base64,'.base64_encode(File::get(public_path('assets/logo-ibadeg.png'))),
            'generatedAt' => now(),
        ])->setPaper('a4', $orientation)->setOptions([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'chroot' => public_path('assets'),
            'tempDir' => storage_path('framework/cache/pdf'),
            'isFontSubsettingEnabled' => true,
        ], true);

        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        if ($numberPages) {
            $canvas = $dompdf->getCanvas();
            $canvas->page_text($canvas->get_width() - 112, $canvas->get_height() - 25, 'Página {PAGE_NUM} de {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [.45, .45, .45]);
        }

        return $inline ? $pdf->stream($filename) : $pdf->download($filename);
    }
}
