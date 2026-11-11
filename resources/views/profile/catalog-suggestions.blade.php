<!-- Shared suggestion dialog. One form serves Academic, Skills & Interests, and Teaching Verification. -->
<div class="modal fade" id="catalogSuggestionModal" tabindex="-1" aria-labelledby="catalogSuggestionTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h2 id="catalogSuggestionTitle" class="modal-title h5 mb-0">Suggest a topic</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body" id="catalog-suggestions">
        <p class="small text-body-secondary" id="catalogSuggestionDescription">Missing a program or topic? Suggest it for admin review. Adding a topic does not verify someone to teach it.</p>
        <form action="{{ route('catalog.suggest') }}" method="POST" id="catalogSuggestionForm" class="d-grid gap-3">@csrf
          <input type="hidden" name="kind" id="catalogSuggestionKind" value="topic">
          <div id="catalogSubjectWrap"><label class="form-label" for="catalogSubject">Subject</label><select name="subject_id" id="catalogSubject" class="form-select">@foreach($catalogSubjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach<option value="">Other / Not listed</option></select><div id="catalogOtherSubjectWrap" class="mt-2" hidden><label for="catalogSubjectName" class="form-label">Suggested subject</label><input name="subject_name" id="catalogSubjectName" class="form-control" maxlength="100"></div></div>
          <div><label class="form-label" for="catalogSuggestionName">Suggested name</label><input name="name" id="catalogSuggestionName" class="form-control" required maxlength="100" placeholder="For example: Japanese Conversation"></div>
        </form>
        @if($catalogSuggestions->isNotEmpty())
          <div class="mt-4"><h3 class="h6">Your suggestions</h3><ul class="list-unstyled small mb-0">@foreach($catalogSuggestions->take(8) as $suggestion)<li class="py-1">{{ ucfirst($suggestion->kind) }}: {{ $suggestion->name }} <span class="badge text-bg-secondary">{{ ucfirst($suggestion->status) }}</span> @if($suggestion->review_note)<span class="text-body-secondary">{{ $suggestion->review_note }}</span>@endif</li>@endforeach</ul></div>
        @endif
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="catalogSuggestionForm" class="btn btn-primary">Submit suggestion</button></div>
    </div>
  </div>
</div>
