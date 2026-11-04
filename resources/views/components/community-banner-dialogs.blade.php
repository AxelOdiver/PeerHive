<x-modal id="communityBannerViewer" title="Community banner" size="xl" dialogClass="modal-dialog-centered modal-dialog-scrollable">
  <img id="communityBannerFullImage" class="community-banner-viewer-image" alt="Community banner">
  <button type="button" id="communityBannerReportToggle" class="btn btn-outline-danger btn-sm mt-3">Report image</button>
  <form id="communityBannerReportForm" class="border-top pt-3 mt-3" hidden>
    @csrf
    <label for="communityBannerReportReason" class="form-label">Reason for reporting</label>
    <select id="communityBannerReportReason" name="reason" class="form-select mb-3" required>
      <option value="" selected disabled>Choose a reason</option>
      <option>Sexual or explicit content</option><option>Violence or graphic content</option><option>Harassment or hateful content</option><option>Other</option>
    </select>
    <label for="communityBannerReportDetails" class="form-label">Details (optional)</label>
    <textarea id="communityBannerReportDetails" name="details" class="form-control mb-3" rows="3" maxlength="1000"></textarea>
    <p class="small text-muted">An administrator will review this image.</p>
    <p id="communityBannerReportError" class="small text-danger" role="alert"></p>
    <button type="submit" class="btn btn-danger btn-sm">Submit report</button>
  </form>
  <p id="communityBannerReportStatus" class="small text-success mt-3 mb-0" role="status"></p>
  <x-slot:footer><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></x-slot:footer>
</x-modal>
<x-modal id="communityBannerUploadModal" title="Community banner" size="lg" dialogClass="modal-dialog-centered modal-dialog-scrollable">
  <form id="communityBannerUploadForm" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="expected_banner_id" id="communityBannerExpectedId">
    <input type="hidden" name="position_x" id="communityBannerPositionX" value="50">
    <input type="hidden" name="position_y" id="communityBannerPositionY" value="50">
    <input type="hidden" name="zoom" id="communityBannerZoomValue" value="1">
    <label for="communityBannerFile" class="form-label">Choose a banner</label>
    <input type="file" id="communityBannerFile" name="banner" class="form-control" accept="image/jpeg,image/png,image/webp" required aria-describedby="communityBannerUploadHelp">
    <p id="communityBannerUploadHelp" class="form-text">JPG, PNG, or WebP, up to 5 MB and 6000 × 6000 pixels. A wide image works best. Banners are visible in community previews to signed-in members.</p>
    <div id="communityBannerPositionEditor" class="mt-3" hidden>
      <p id="communityBannerPositionHelp" class="small text-muted mb-2">Drag the picture to position your banner, then adjust the zoom. You can also use the arrow keys to move it. Different screen sizes may crop the edges.</p>
      <div id="communityBannerCrop" class="community-banner-crop" tabindex="0" role="group" aria-label="Banner position" aria-describedby="communityBannerPositionHelp">
        <img id="communityBannerUploadPreview" class="community-banner-upload-preview" alt="Banner preview" draggable="false">
      </div>
      <div class="mt-3">
        <div class="d-flex justify-content-between align-items-center"><label for="communityBannerZoom" class="form-label small mb-1">Zoom</label><output id="communityBannerZoomLabel" for="communityBannerZoom" class="small text-muted">100%</output></div>
        <input type="range" id="communityBannerZoom" class="form-range" min="100" max="300" step="1" value="100">
      </div>
    </div>
    <p id="communityBannerUploadError" class="small text-danger mt-3 mb-0" role="alert"></p>
  </form>
  <x-slot:footer>
    <button type="button" id="communityBannerRemove" class="btn btn-outline-danger me-auto" hidden>Remove banner</button>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-primary" form="communityBannerUploadForm" id="communityBannerSave">Save banner</button>
  </x-slot:footer>
</x-modal>
