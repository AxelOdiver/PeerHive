<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePictureController extends Controller
{
    public function show(Request $request, string $filename)
    {
        // Profile photos already use public storage. Never allow this endpoint to serve other uploads.
        abort_unless(preg_match('/\A[a-zA-Z0-9_.-]+\z/D', $filename), 404);
        $path = $filename === 'default'
            ? public_path('images/profile-placeholder.jpeg')
            : Storage::disk('public')->path('profile_pictures/'.$filename);
        abort_unless(is_file($path), 404);
        $image = @getimagesize($path);
        $mime = $image['mime'] ?? null;
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/avif'], true), 404);

        // Uploads get a new filename whenever replaced, so new photos do not reuse an old cache entry.
        $response = response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setAutoEtag();
        $response->isNotModified($request);
        return $response;
    }
}
