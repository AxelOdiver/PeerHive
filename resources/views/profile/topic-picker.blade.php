<div data-topic-picker>
  <select id="{{ $field }}" name="{{ $field }}[]" multiple hidden aria-hidden="true">@include('profile.catalog-topic-options', ['selected' => $selected])</select>
  <button type="button" id="{{ $field }}_picker" class="btn btn-outline-secondary w-100 text-start" data-topic-open aria-haspopup="dialog" aria-controls="{{ $field }}_modal"><i class="bi bi-plus-circle me-2" aria-hidden="true"></i>Choose topics</button>
  <div data-topic-selections class="d-flex flex-wrap gap-2 mt-2" aria-live="polite"></div>
  <div data-topic-limit class="form-text"></div>
  <div class="modal fade" id="{{ $field }}_modal" tabindex="-1" aria-labelledby="{{ $field }}_title" aria-hidden="true" data-topic-modal>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="{{ $field }}_title">{{ $field === 'learning_interests' ? 'What would you like to learn?' : 'Choose your skills & interests' }}</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <p class="text-body-secondary">{{ $field === 'learning_interests' ? 'Choose topics to help us recommend peers who can teach them.' : 'Share your interests and self-described skills. These do not count as verified teaching qualifications.' }}</p>
          <label class="visually-hidden" for="{{ $field }}_search">Search topics or subjects</label>
          <input type="search" class="form-control mb-4" id="{{ $field }}_search" placeholder="Search topics or subjects…" data-topic-search>
          <div data-topic-cards></div>
          <p class="text-body-secondary mb-0" data-topic-empty hidden>No matching topics. Try another search.</p>
        </div>
        <div class="modal-footer justify-content-between">
          <span class="small text-body-secondary" data-topic-count aria-live="polite"></span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" data-topic-save>Save selection</button>
          </div>
          <p class="small text-body-secondary w-100 mb-0">Save your profile afterward to keep these changes.</p>
        </div>
      </div>
    </div>
  </div>
</div>
