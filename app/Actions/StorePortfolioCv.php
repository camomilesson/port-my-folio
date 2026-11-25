<?php

namespace App\Actions;

use App\Models\CvDocument;
use App\Models\Portfolio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorePortfolioCv
{
    public function __construct(private EditPortfolioDraft $editor) {}

    public function handle(Portfolio $portfolio, UploadedFile $file): void
    {
        $bytes = file_get_contents($file->getRealPath());
        if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'application/pdf' || ! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -1024), '%%EOF')) {
            throw ValidationException::withMessages(['pdf' => 'Upload a valid, complete PDF document.']);
        }
        app(ValidateCvPdf::class)->handle($bytes);
        $disk = config('portmyfolio.cv_disk');
        $path = 'cvs/'.Str::uuid().'.pdf';
        $written = false;
        try {
            $this->editor->handle($portfolio, function (Portfolio $locked) use ($file, $disk, $path, &$written): void {
                if ($locked->cvDocument()->exists() || $locked->generations()->where('status', 'succeeded')->exists()) {
                    throw ValidationException::withMessages(['pdf' => 'This portfolio already has its source CV. The original PDF cannot be replaced.']);
                }
                if (config('filesystems.disks.'.$disk.'.visibility') === 'public') {
                    throw ValidationException::withMessages(['pdf' => 'Source PDFs must use private storage.']);
                }
                $written = Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));
                if (! $written) {
                    throw new \RuntimeException('Unable to store the source CV.');
                }
                $locked->cvDocument()->create(['storage_disk' => $disk, 'storage_path' => $path, 'original_filename' => $file->getClientOriginalName(), 'mime_type' => 'application/pdf', 'size_bytes' => $file->getSize(), 'status' => 'uploaded']);
            });
        } catch (\Throwable $exception) {
            if ($written && ! CvDocument::where('storage_path', $path)->exists()) {
                Storage::disk($disk)->delete($path);
            }
            throw $exception;
        }
    }
}
