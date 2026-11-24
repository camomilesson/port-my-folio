<?php

namespace App\Models;

use Database\Factories\CvDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['storage_disk', 'portfolio_id', 'storage_path', 'original_filename', 'mime_type', 'size_bytes', 'status', 'extracted_content', 'error_code'])]
class CvDocument extends Model
{
    /** @use HasFactory<CvDocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'extracted_content' => 'array'];
    }

    public function storageDisk(): string
    {
        return $this->storage_disk ?? config('portmyfolio.cv_disk');
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
