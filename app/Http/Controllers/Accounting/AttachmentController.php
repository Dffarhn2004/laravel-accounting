<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Services\Accounting\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function destroy(Request $request, Attachment $attachment, AttachmentService $service): RedirectResponse
    {
        abort_unless($attachment->company_id === $request->user()->company_id, 404);
        $service->delete($attachment);

        return back()->with('success', 'Bukti dihapus.');
    }

    /** Evidence is only visible to users of the owning company. */
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->company_id === $request->user()->company_id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
        ], 'inline');
    }
}
