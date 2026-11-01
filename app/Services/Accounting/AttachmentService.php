<?php

namespace App\Services\Accounting;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Evidence files for any transaction (PRD: max 5 image/PDF per transaction).
 * Stored on the private disk under random names; served only through an
 * authorised, company-scoped route. Content type is sniffed, never trusted
 * from the file name.
 */
class AttachmentService
{
    public const MAX_FILES = 5;

    public const MAX_KB = 5120;

    public const MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    private const EXTENSION = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Validation rules to merge into a FormRequest for field `attachments`. */
    public static function rules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => ['file', 'max:'.self::MAX_KB, 'mimetypes:'.implode(',', self::MIMES)],
        ];
    }

    /** @param  array<int, UploadedFile>  $files */
    public function attach(Model $owner, int $companyId, array $files, ?int $userId = null): void
    {
        $existing = Attachment::where(['attachable_type' => $owner->getMorphClass(), 'attachable_id' => $owner->getKey()])->count();

        if ($existing + count($files) > self::MAX_FILES) {
            throw ValidationException::withMessages(['attachments' => 'Maksimal '.self::MAX_FILES.' lampiran per transaksi.']);
        }

        foreach ($files as $file) {
            $mime = $file->getMimeType();
            if (! in_array($mime, self::MIMES, true)) {
                throw ValidationException::withMessages(['attachments' => 'Hanya gambar (JPG, PNG, WebP) atau PDF yang diizinkan.']);
            }

            $path = $file->storeAs("attachments/{$companyId}", bin2hex(random_bytes(16)).'.'.self::EXTENSION[$mime], 'local');

            Attachment::create([
                'company_id' => $companyId,
                'attachable_type' => $owner->getMorphClass(),
                'attachable_id' => $owner->getKey(),
                'disk' => 'local',
                'path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
                'mime' => $mime,
                'size' => $file->getSize(),
                'uploaded_by' => $userId,
            ]);
        }
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }
}
