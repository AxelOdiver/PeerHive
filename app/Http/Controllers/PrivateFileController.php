<?php

namespace App\Http\Controllers;

use App\Models\{Message, UserSubjectQualification};
use Illuminate\Support\Facades\Storage;

class PrivateFileController extends Controller
{
    public function message(Message $message)
    {
        abort_unless($message->conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_if($message->is_unsent || !$message->attachment_path, 404);
        if (request()->boolean('preview')) {
            $disk = Storage::disk('local')->exists($message->attachment_path) ? 'local' : 'public';
            abort_unless(preg_match('#^message_attachments/[a-zA-Z0-9_.-]+$#D', $message->attachment_path) && Storage::disk($disk)->exists($message->attachment_path), 404);
            $bytes = Storage::disk($disk)->get($message->attachment_path);
            $mime = @getimagesizefromstring($bytes)['mime'] ?? null;
            abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
            return response($bytes)->withHeaders([
                'Content-Type' => $mime, 'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
                'Content-Security-Policy' => "sandbox; default-src 'none'",
            ]);
        }
        return $this->download($message->attachment_path, $message->attachment_name ?: 'attachment');
    }

    public function qualification(UserSubjectQualification $qualification)
    {
        abort_unless(auth()->id() === $qualification->user_id || auth()->user()->role === 'admin', 403);
        return $this->download($qualification->proof_file_path, 'qualification.'.pathinfo($qualification->proof_file_path, PATHINFO_EXTENSION));
    }

    private function download(string $path, string $name)
    {
        abort_unless(preg_match('#^(message_attachments|qualifications)/[a-zA-Z0-9_.-]+$#D', $path), 404);
        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($path), 404);
        return Storage::disk($disk)->download($path, basename($name), [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }
}
