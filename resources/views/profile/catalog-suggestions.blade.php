<section class="mt-4" id="catalog-suggestions">
  <h3 class="h5">Missing a program or topic?</h3>
  @if(session('catalog_status'))<div class="alert alert-success">{{ session('catalog_status') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form action="{{ route('catalog.suggest') }}" method="POST" class="card card-body gap-3">
    @csrf
    <label class="form-label">Suggestion type<select name="kind" id="catalogSuggestionKind" class="form-select"><option value="topic">Learning / teaching topic</option><option value="program">Degree program</option></select></label>
    <div id="catalogSubjectWrap"><label class="form-label" for="catalogSubject">Subject</label><select name="subject_id" id="catalogSubject" class="form-select">@foreach($catalogSubjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach<option value="">Other / Not listed</option></select>
    <div id="catalogOtherSubjectWrap" class="mt-2" hidden><label for="catalogSubjectName" class="form-label">Suggested subject</label><input name="subject_name" id="catalogSubjectName" class="form-control" maxlength="100"></div></div>
    <label class="form-label">Suggested name<input name="name" id="catalogSuggestionName" class="form-control" required maxlength="100" placeholder="For example: Japanese Conversation"></label>
    <div><button class="btn btn-outline-primary">Submit suggestion</button></div>
    <p class="small text-muted mb-0">Approved entries become available to everyone. Adding a topic does not verify anyone to teach it.</p>
  </form>
  @if($catalogSuggestions->isNotEmpty())<h4 class="h6 mt-3">Your suggestions</h4><ul>@foreach($catalogSuggestions as $suggestion)<li>{{ ucfirst($suggestion->kind) }}: {{ $suggestion->name }} — {{ ucfirst($suggestion->status) }} @if($suggestion->review_note)<span class="text-muted">{{ $suggestion->review_note }}</span>@endif</li>@endforeach</ul>@endif
</section>
