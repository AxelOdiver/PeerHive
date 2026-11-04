const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(fetch) {
  const elements = new Map(), listeners = {}, revoked = [];
  let reloads = 0;
  const element = id => {
    if (!elements.has(id)) elements.set(id, {id, hidden: false, disabled: false, value: '', textContent: '', files: [], fields: {}, listeners: {},
      addEventListener(event, fn) { this.listeners[event] = fn; },
      removeAttribute(name) { delete this[name]; }, focus() {}, reset() { this.fields = {}; },
      style: {setProperty(name, value) { this[name] = value; }},
      classList: {add() {}, remove() {}},
      getBoundingClientRect() { return {width: 500, height: 100}; },
      setPointerCapture(id) { this.captured = id; }, hasPointerCapture(id) { return this.captured === id; }, releasePointerCapture() { this.captured = null; },
      querySelector() { return element(id + ':submit'); },
    });
    return elements.get(id);
  };
  const root = {getElementById: element, querySelector: selector => selector.startsWith('meta') ? {content: 'csrf-token'} : element('header'), addEventListener(event, fn) { listeners[event] = fn; }};
  const context = {document: root, fetch,
    FormData: class {
      constructor(form) {
        this.fields = new Map(Object.entries(form.fields));
        if (form.id === 'communityBannerUploadForm') {
          this.fields.set('expected_banner_id', element('communityBannerExpectedId').value);
          this.fields.set('position_x', element('communityBannerPositionX').value);
          this.fields.set('position_y', element('communityBannerPositionY').value);
          this.fields.set('zoom', element('communityBannerZoomValue').value);
        }
      }
      set(key, value) { this.fields.set(key, value); }
      get(key) { return this.fields.get(key); }
    },
    URL: {createObjectURL: () => 'blob:preview', revokeObjectURL: value => revoked.push(value)},
    window: {bootstrap: {Modal: {getOrCreateInstance: () => ({show() {}})}}, location: {reload() { reloads++; }}, confirmAction: async () => ({isConfirmed: true})},
  };
  vm.createContext(context);
  vm.runInContext(fs.readFileSync('resources/js/pages/community-banners.js', 'utf8').replace('export function', 'function'), context);
  context.initCommunityBanners(root);
  function click(selector, dataset) {
    let prevented = false, stopped = false;
    listeners.click({target: {closest: value => value === selector ? {dataset} : null},
      preventDefault() { prevented = true; }, stopPropagation() { stopped = true; }});
    return {prevented, stopped};
  }
  const submit = id => element(id).listeners.submit({preventDefault() {}});
  return {element, click, submit, revoked, reloads: () => reloads};
}

test('opening a banner avoids the card navigation and reporting keeps the exact image id', async () => {
  let finish, calls = 0, body;
  const page = setup(async (url, options) => {
    assert.equal(url, '/reports'); calls++; body = options.body;
    await new Promise(resolve => { finish = resolve; });
    return {ok: true, json: async () => ({message: 'Report submitted.'})};
  });
  assert.deepEqual(page.click('[data-community-banner-url]', {communityBannerId: '7', communityBannerUrl: '/community-banners/7', communityBannerName: 'Circle', communityBannerReportable: '1'}), {prevented: true, stopped: true});
  page.element('communityBannerReportToggle').listeners.click();
  page.element('communityBannerReportForm').fields = {reason: 'Sexual or explicit content', details: 'Please review.'};
  const first = page.submit('communityBannerReportForm');
  await page.submit('communityBannerReportForm');
  assert.equal(calls, 1);
  assert.equal(body.get('reportable_id'), '7');
  assert.equal(body.get('reportable_type'), 'App\\Models\\CommunityBanner');
  finish(); await first;
  assert.equal(page.element('communityBannerReportForm').hidden, true);
  assert.equal(page.element('communityBannerReportStatus').textContent, 'Report submitted.');
});

test('report errors preserve the reason and details for correction', async () => {
  const page = setup(async () => ({ok: false, json: async () => ({message: 'This banner changed.'})}));
  page.click('[data-community-banner-url]', {communityBannerId: '2', communityBannerUrl: '/community-banners/2', communityBannerName: 'Circle', communityBannerReportable: '1'});
  page.element('communityBannerReportToggle').listeners.click();
  page.element('communityBannerReportForm').fields = {reason: 'Other', details: 'Keep this explanation'};
  await page.submit('communityBannerReportForm');
  assert.equal(page.element('communityBannerReportForm').hidden, false);
  assert.equal(page.element('communityBannerReportForm').fields.details, 'Keep this explanation');
  assert.equal(page.element('communityBannerReportError').textContent, 'This banner changed.');
});

test('uploads send the expected version, prevent concurrent saves, and preserve errors', async () => {
  let finish, calls = 0, body;
  const page = setup(async (url, options) => {
    assert.equal(url, '/community/3/banner'); calls++; body = options.body;
    await new Promise(resolve => { finish = resolve; });
    return {ok: false, json: async () => ({errors: {banner: ['Choose a supported image.']}})};
  });
  page.click('[data-community-banner-upload]', {communityBannerUpload: '/community/3/banner', currentBannerId: '12', currentBannerUrl: '/community-banners/12'});
  page.element('communityBannerUploadForm').fields = {banner: 'selected-image'};
  const first = page.submit('communityBannerUploadForm');
  await page.submit('communityBannerUploadForm');
  assert.equal(calls, 1); assert.equal(body.get('expected_banner_id'), '12');
  assert.equal(page.element('communityBannerSave').disabled, true);
  finish(); await first;
  assert.equal(page.element('communityBannerUploadError').textContent, 'Choose a supported image.');
  assert.equal(page.element('communityBannerUploadForm').fields.banner, 'selected-image');
  assert.equal(page.element('communityBannerSave').disabled, false); assert.equal(page.reloads(), 0);
});

test('local previews reject unsupported files and release their object URLs', () => {
  const page = setup();
  const file = page.element('communityBannerFile');
  file.files = [{type: 'image/svg+xml', size: 20}]; file.listeners.change();
  assert.equal(page.element('communityBannerPositionEditor').hidden, true);
  assert.match(page.element('communityBannerUploadError').textContent, /JPG/);
  file.files = [{type: 'image/png', size: 100}]; file.listeners.change();
  assert.equal(page.element('communityBannerUploadPreview').src, 'blob:preview');
  page.element('communityBannerUploadModal').listeners['hidden.bs.modal']();
  assert.deepEqual(page.revoked, ['blob:preview']);
});

test('banner positioning restores saved coordinates, supports dragging, and sends the adjusted position', async () => {
  let body;
  const page = setup(async (url, options) => { body = options.body; return {ok: true, json: async () => ({message: 'Saved'})}; });
  page.click('[data-community-banner-upload]', {communityBannerUpload: '/community/3/banner', currentBannerId: '12', currentBannerUrl: '/community-banners/12', currentBannerX: '35', currentBannerY: '70'});
  assert.equal(page.element('communityBannerFile').required, false);
  assert.equal(page.element('communityBannerPositionX').value, '35.00');
  assert.equal(page.element('communityBannerPositionY').value, '70.00');
  const crop = page.element('communityBannerCrop'), preview = page.element('communityBannerUploadPreview');
  preview.naturalWidth = 1000; preview.naturalHeight = 1000; // Cover is 500 × 500, overflowing vertically by 400.
  crop.listeners.pointerdown({button: 0, pointerId: 1, clientX: 100, clientY: 100, preventDefault() {}});
  crop.listeners.pointerdown({button: 0, pointerId: 2, clientX: 300, clientY: 500, preventDefault() {}}); // Keep the first finger in control.
  crop.listeners.pointermove({pointerId: 2, clientX: 100, clientY: 900}); // Ignore a second finger.
  assert.equal(page.element('communityBannerPositionY').value, '70.00');
  crop.listeners.pointermove({pointerId: 1, clientX: 150, clientY: 180});
  assert.equal(page.element('communityBannerPositionX').value, '35.00'); // No horizontal overflow.
  assert.equal(page.element('communityBannerPositionY').value, '50.00');
  crop.listeners.pointermove({pointerId: 1, clientX: 100, clientY: 900});
  assert.equal(page.element('communityBannerPositionY').value, '0.00');
  crop.listeners.pointerup({pointerId: 1});
  crop.listeners.keydown({key: 'ArrowUp', shiftKey: true, preventDefault() {}});
  assert.equal(page.element('communityBannerPositionY').value, '10.00');
  await page.submit('communityBannerUploadForm');
  assert.equal(body.get('position_x'), '35.00'); assert.equal(body.get('position_y'), '10.00');
  assert.equal(page.reloads(), 1);
});

test('zoom is restored and saved, drag bounds account for zoom, and a new image starts at 100 percent', async () => {
  let body;
  const page = setup(async (url, options) => { body = options.body; return {ok: true, json: async () => ({message: 'Saved'})}; });
  page.click('[data-community-banner-upload]', {communityBannerUpload: '/community/3/banner', currentBannerId: '12', currentBannerUrl: '/community-banners/12', currentBannerX: '35', currentBannerY: '70', currentBannerZoom: '1.75'});
  const zoom = page.element('communityBannerZoom'), crop = page.element('communityBannerCrop'), preview = page.element('communityBannerUploadPreview');
  assert.equal(page.element('communityBannerZoomValue').value, '1.75');
  assert.equal(page.element('communityBannerZoomLabel').textContent, '175%');
  zoom.value = '200'; zoom.listeners.input();
  assert.equal(crop.style['--banner-zoom'], '2.00');
  preview.naturalWidth = preview.naturalHeight = 1000;
  crop.listeners.pointerdown({button: 0, pointerId: 1, clientX: 100, clientY: 100, preventDefault() {}});
  crop.listeners.pointermove({pointerId: 1, clientX: 150, clientY: 190});
  crop.listeners.pointerup({pointerId: 1});
  assert.equal(page.element('communityBannerPositionX').value, '25.00');
  assert.equal(page.element('communityBannerPositionY').value, '60.00');
  await page.submit('communityBannerUploadForm');
  assert.equal(body.get('zoom'), '2.00');
  assert.equal(body.get('position_x'), '25.00'); assert.equal(body.get('position_y'), '60.00');
  const file = page.element('communityBannerFile');
  file.files = [{type: 'image/png', size: 100}]; file.listeners.change();
  assert.equal(page.element('communityBannerZoomValue').value, '1.00');
  assert.equal(page.element('communityBannerZoomLabel').textContent, '100%');
});
