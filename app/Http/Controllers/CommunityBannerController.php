<?php

namespace App\Http\Controllers;

use App\Models\{Community, CommunityBanner};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class CommunityBannerController extends Controller
{
    private function authorizeManager(Request $request, Community $community): void
    {
        abort_unless($request->user()->id === $community->user_id || $request->user()->role === 'admin', 403);
    }

    public function store(Request $request, Community $community)
    {
        $this->authorizeManager($request, $community);
        $data = $request->validate([
            'banner' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'expected_banner_id' => 'present|nullable|integer|min:1',
            'position_x' => 'sometimes|required|numeric|between:0,100',
            'position_y' => 'sometimes|required|numeric|between:0,100',
            'zoom' => 'sometimes|required|numeric|between:1,3',
        ]);
        $file = $request->file('banner');
        $path = $file?->store('community-banners', 'local');
        abort_if($file && !$path, 500, 'The banner could not be saved. Please try again.');
        try {
            DB::transaction(function () use ($request, $community, $data, $path, $file) {
                $locked = Community::whereKey($community->id)->lockForUpdate()->firstOrFail();
                $this->authorizeManager($request, $locked);
                $current = $locked->banner;
                abort_unless(($current?->id ?? 0) === (int) ($data['expected_banner_id'] ?? 0), 409, 'The banner changed. Refresh before updating it.');
                abort_unless($file || $current, 422, 'Choose a banner image first.');
                $position = [
                    'position_x' => round((float) ($data['position_x'] ?? ($file ? 50 : $current->position_x)), 2),
                    'position_y' => round((float) ($data['position_y'] ?? ($file ? 50 : $current->position_y)), 2),
                    'zoom' => round((float) ($data['zoom'] ?? ($file ? 1 : $current->zoom)), 2),
                ];
                if (!$file) {
                    $current->update($position);
                    return;
                }
                $locked->banners()->whereNull('removed_at')->update(['removed_at' => now()]);
                $locked->banners()->create($position + ['uploaded_by' => $request->user()->id, 'path' => $path, 'mime_type' => $file->getMimeType()]);
            });
        } catch (\Throwable $error) {
            if ($path) Storage::disk('local')->delete($path);
            throw $error;
        }
        return response()->json(['message' => 'Community banner updated.']);
    }

    public function destroy(Request $request, Community $community)
    {
        $this->authorizeManager($request, $community);
        $data = $request->validate(['expected_banner_id' => 'required|integer|min:1']);
        DB::transaction(function () use ($request, $community, $data) {
            $locked = Community::whereKey($community->id)->lockForUpdate()->firstOrFail();
            $this->authorizeManager($request, $locked);
            $banner = $locked->banner;
            abort_unless($banner && $banner->id === (int) $data['expected_banner_id'], 409, 'The banner changed. Refresh before removing it.');
            $banner->update(['removed_at' => now()]);
        });
        return response()->json(['message' => 'Banner removed. The default cover is now shown.']);
    }

    public function show(Request $request, CommunityBanner $banner)
    {
        // Active banners are part of every signed-in member's community preview.
        // Archived versions remain available only to admins reviewing reports.
        abort_if($banner->removed_at && $request->user()->role !== 'admin', 404);
        abort_unless(Storage::disk('local')->exists($banner->path), 404);
        $path = Storage::disk('local')->path($banner->path);
        $mime = @getimagesize($path)['mime'] ?? null;
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        return response()->file($path, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }
}
