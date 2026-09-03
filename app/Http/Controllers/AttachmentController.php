<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeAccess($request, $attachment);

        $disk = config('support.attachments.disk', 'local');

        if (! Storage::disk($disk)->exists($attachment->path)) {
            abort(404);
        }

        // Sanitize filename for Content-Disposition
        $filename = preg_replace('/[^\w\-. ]/', '_', $attachment->original_filename);
        $filename = $filename ?: 'attachment';

        return Storage::disk($disk)->download(
            $attachment->path,
            $filename,
            [
                // Force download, never render inline — prevents XSS via
                // hostile HTML/SVG attachments served on the app origin.
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function preview(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeAccess($request, $attachment);

        // Only allow safe image types for inline preview
        $safeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (! in_array($attachment->mime_type, $safeTypes)) {
            abort(403, 'Preview not available for this file type.');
        }

        $disk = config('support.attachments.disk', 'local');

        if (! Storage::disk($disk)->exists($attachment->path)) {
            abort(404);
        }

        return new StreamedResponse(function () use ($disk, $attachment) {
            $stream = Storage::disk($disk)->readStream($attachment->path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    protected function authorizeAccess(Request $request, Attachment $attachment): void
    {
        $attachable = $attachment->attachable;

        if (! $attachable) {
            abort(404);
        }

        // Resolve the ticket from the attachable (Ticket or Comment)
        if ($attachable instanceof Ticket) {
            $ticket = $attachable;
        } elseif ($attachable instanceof Comment) {
            $ticket = $attachable->ticket;
        } else {
            abort(404);
        }

        if (! $ticket) {
            abort(404);
        }

        // Agent auth
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if (! $user->canSeeTicket($ticket)) {
                abort(403);
            }

            return;
        }

        // Customer portal auth
        if (Auth::guard('customer')->check()) {
            $customer = Auth::guard('customer')->user();
            if (strtolower($ticket->requester_email) !== strtolower($customer->email)) {
                abort(403);
            }

            return;
        }

        // API auth (Sanctum)
        if ($request->user('sanctum')) {
            $user = $request->user('sanctum');
            if (! $user->canSeeTicket($ticket)) {
                abort(403);
            }

            return;
        }

        abort(401);
    }
}
