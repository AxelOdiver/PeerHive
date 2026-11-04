export function initCommunityBanners(root = document) {
  const viewer = root.getElementById('communityBannerViewer');
  const uploadModal = root.getElementById('communityBannerUploadModal');
  if (!viewer || !uploadModal) return;
  const image = root.getElementById('communityBannerFullImage');
  const reportToggle = root.getElementById('communityBannerReportToggle');
  const reportForm = root.getElementById('communityBannerReportForm');
  const reportError = root.getElementById('communityBannerReportError');
  const reportStatus = root.getElementById('communityBannerReportStatus');
  const uploadForm = root.getElementById('communityBannerUploadForm');
  const file = root.getElementById('communityBannerFile');
  const expectedId = root.getElementById('communityBannerExpectedId');
  const preview = root.getElementById('communityBannerUploadPreview');
  const editor = root.getElementById('communityBannerPositionEditor');
  const crop = root.getElementById('communityBannerCrop');
  const positionX = root.getElementById('communityBannerPositionX');
  const positionY = root.getElementById('communityBannerPositionY');
  const zoom = root.getElementById('communityBannerZoom');
  const zoomValue = root.getElementById('communityBannerZoomValue');
  const zoomLabel = root.getElementById('communityBannerZoomLabel');
  const uploadError = root.getElementById('communityBannerUploadError');
  const remove = root.getElementById('communityBannerRemove');
  const save = root.getElementById('communityBannerSave');
  let bannerId = null, objectUrl = null, uploadBusy = false, reportBusy = false;
  let viewerOpener = null, uploadOpener = null;
  let drag = null;

  function setPosition(x, y) {
    const clamp = value => Math.max(0, Math.min(100, Number.isFinite(Number(value)) ? Number(value) : 50));
    positionX.value = clamp(x).toFixed(2);
    positionY.value = clamp(y).toFixed(2);
    crop.style.setProperty('--banner-x', `${positionX.value}%`);
    crop.style.setProperty('--banner-y', `${positionY.value}%`);
  }
  function setZoom(value) {
    const scale = Math.max(1, Math.min(3, Number.isFinite(Number(value)) ? Number(value) : 1));
    zoomValue.value = scale.toFixed(2);
    zoom.value = Math.round(scale * 100);
    zoomLabel.textContent = `${zoom.value}%`;
    crop.style.setProperty('--banner-zoom', zoomValue.value);
  }
  function endDrag(event) {
    if (drag && event?.pointerId !== undefined && event.pointerId !== drag.id) return;
    const previous = drag;
    drag = null;
    if (previous && crop.hasPointerCapture(previous.id)) crop.releasePointerCapture(previous.id);
    crop.classList.remove('is-dragging');
  }
  zoom.addEventListener('input', () => { if (!uploadBusy) { endDrag(); setZoom(Number(zoom.value) / 100); } });
  crop.addEventListener('pointerdown', event => {
    if (drag || uploadBusy || event.button !== 0 || !preview.naturalWidth || !preview.naturalHeight) return;
    const box = crop.getBoundingClientRect();
    const scale = Math.max(box.width / preview.naturalWidth, box.height / preview.naturalHeight) * Number(zoomValue.value);
    drag = {id: event.pointerId, x: event.clientX, y: event.clientY, startX: Number(positionX.value), startY: Number(positionY.value),
      overflowX: Math.max(0, preview.naturalWidth * scale - box.width), overflowY: Math.max(0, preview.naturalHeight * scale - box.height)};
    crop.setPointerCapture(event.pointerId);
    crop.classList.add('is-dragging');
    crop.focus({preventScroll: true});
    event.preventDefault();
  });
  crop.addEventListener('pointermove', event => {
    if (!drag || event.pointerId !== drag.id || uploadBusy) return;
    setPosition(drag.overflowX > 1 ? drag.startX - (event.clientX - drag.x) / drag.overflowX * 100 : drag.startX,
      drag.overflowY > 1 ? drag.startY - (event.clientY - drag.y) / drag.overflowY * 100 : drag.startY);
  });
  for (const event of ['pointerup', 'pointercancel', 'lostpointercapture']) crop.addEventListener(event, endDrag);
  crop.addEventListener('keydown', event => {
    if (uploadBusy || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
    event.preventDefault();
    const step = event.shiftKey ? 10 : 1;
    setPosition(Number(positionX.value) + (event.key === 'ArrowLeft' ? step : event.key === 'ArrowRight' ? -step : 0),
      Number(positionY.value) + (event.key === 'ArrowUp' ? step : event.key === 'ArrowDown' ? -step : 0));
  });

  async function request(url, options) {
    const response = await fetch(url, {...options, headers: {Accept: 'application/json', 'X-CSRF-TOKEN': root.querySelector('meta[name="csrf-token"]').content, ...options.headers}});
    const result = await response.json();
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Please try again.');
    return result;
  }
  function clearObjectUrl() {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
  }
  function setUploadBusy(value) {
    uploadBusy = value;
    save.disabled = value;
    remove.disabled = value;
    file.disabled = zoom.disabled = value;
    save.textContent = value ? 'Saving…' : 'Save banner';
  }
  root.addEventListener('click', event => {
    const trigger = event.target.closest('[data-community-banner-url]');
    const manager = event.target.closest('[data-community-banner-upload]');
    if (trigger) {
      event.preventDefault(); event.stopPropagation();
      if (reportBusy) return;
      viewerOpener = trigger;
      bannerId = trigger.dataset.communityBannerId;
      image.src = trigger.dataset.communityBannerUrl;
      image.alt = `Banner for ${trigger.dataset.communityBannerName}`;
      reportForm.reset(); reportForm.hidden = true;
      reportError.textContent = ''; reportStatus.textContent = '';
      reportToggle.hidden = trigger.dataset.communityBannerReportable !== '1';
      window.bootstrap.Modal.getOrCreateInstance(viewer).show();
    }
    if (manager) {
      event.preventDefault(); event.stopPropagation();
      if (uploadBusy) return;
      uploadOpener = manager;
      endDrag(); clearObjectUrl(); uploadForm.reset();
      uploadForm.action = manager.dataset.communityBannerUpload;
      expectedId.value = manager.dataset.currentBannerId || '';
      file.required = !expectedId.value;
      remove.hidden = !expectedId.value;
      uploadError.textContent = '';
      editor.hidden = !manager.dataset.currentBannerUrl;
      const header = root.querySelector('.community-banner-image--header')?.getBoundingClientRect();
      if (header?.width && header?.height) crop.style.aspectRatio = `${header.width} / ${header.height}`;
      setPosition(manager.dataset.currentBannerX ?? 50, manager.dataset.currentBannerY ?? 50);
      setZoom(manager.dataset.currentBannerZoom ?? 1);
      if (!editor.hidden) preview.src = manager.dataset.currentBannerUrl;
      else preview.removeAttribute('src');
      window.bootstrap.Modal.getOrCreateInstance(uploadModal).show();
    }
  });
  reportToggle.addEventListener('click', () => {
    reportForm.hidden = !reportForm.hidden;
    if (!reportForm.hidden) root.getElementById('communityBannerReportReason').focus();
  });
  reportForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (reportBusy) return;
    reportBusy = true;
    const submit = reportForm.querySelector('button[type="submit"]');
    submit.disabled = true; reportError.textContent = '';
    const data = new FormData(reportForm);
    data.set('reportable_id', bannerId);
    data.set('reportable_type', 'App\\Models\\CommunityBanner');
    try {
      const result = await request('/reports', {method: 'POST', body: data});
      reportForm.hidden = true; reportToggle.hidden = true;
      reportStatus.textContent = result.message;
    } catch (error) { reportError.textContent = error.message; }
    finally { reportBusy = false; submit.disabled = false; }
  });
  file.addEventListener('change', () => {
    endDrag(); clearObjectUrl(); uploadError.textContent = '';
    // Keep an existing image adjustable when the file picker is cleared or rejected.
    const existing = uploadOpener?.dataset.currentBannerUrl;
    editor.hidden = !existing;
    if (existing) preview.src = existing;
    else preview.removeAttribute('src');
    setPosition(uploadOpener?.dataset.currentBannerX ?? 50, uploadOpener?.dataset.currentBannerY ?? 50);
    setZoom(uploadOpener?.dataset.currentBannerZoom ?? 1);
    const selected = file.files[0];
    if (!selected) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(selected.type) || selected.size > 5 * 1024 * 1024) {
      file.value = '';
      uploadError.textContent = 'Choose a JPG, PNG, or WebP image up to 5 MB.';
      return;
    }
    objectUrl = URL.createObjectURL(selected);
    preview.src = objectUrl; editor.hidden = false;
    setPosition(50, 50);
    setZoom(1);
  });
  uploadForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (uploadBusy) return;
    const data = new FormData(uploadForm);
    setUploadBusy(true); uploadError.textContent = '';
    try {
      await request(uploadForm.action, {method: 'POST', body: data});
      window.location.reload();
    } catch (error) { uploadError.textContent = error.message; }
    finally { setUploadBusy(false); }
  });
  remove.addEventListener('click', async () => {
    if (uploadBusy) return;
    const result = await window.confirmAction('Remove this banner and show the default cover?', 'Remove banner');
    if (!result.isConfirmed || uploadBusy) return;
    setUploadBusy(true); uploadError.textContent = '';
    try {
      await request(uploadForm.action, {method: 'DELETE', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({expected_banner_id: expectedId.value})});
      window.location.reload();
    } catch (error) { uploadError.textContent = error.message; }
    finally { setUploadBusy(false); }
  });
  uploadModal.addEventListener('hide.bs.modal', event => { if (uploadBusy) event.preventDefault(); });
  uploadModal.addEventListener('hidden.bs.modal', () => { endDrag(); clearObjectUrl(); preview.removeAttribute('src'); uploadOpener?.focus?.(); });
  viewer.addEventListener('hide.bs.modal', event => { if (reportBusy) event.preventDefault(); });
  viewer.addEventListener('hidden.bs.modal', () => { image.removeAttribute('src'); viewerOpener?.focus?.(); });
}
