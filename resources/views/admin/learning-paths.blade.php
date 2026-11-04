@extends('layouts.dashboard')
@section('title', 'Admin - Skill Tree')
@section('content')
<h1 class="h4">Skill Tree learning paths</h1>
<p class="text-muted">Configure approved topics. Prerequisites are suggestions. Completion requires peer-reviewed activities covering every outcome listed below.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-4"><div><label for="pathTopic" class="form-label">Topic</label><select id="pathTopic" name="topic" class="form-select" onchange="this.form.requestSubmit()">@foreach($topics as $option)<option value="{{ $option->id }}" @selected($topic?->id === $option->id)>{{ $option->parent?->name }} → {{ $option->name }}</option>@endforeach</select></div><noscript><button class="btn btn-outline-primary" type="submit">Load topic</button></noscript></form>
@if($topic)
<form method="POST" action="{{ route('admin.learning-paths.update', $topic) }}" class="card card-body gap-3">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $plan?->revision ?? 0 }}">
  <h2 class="h5">{{ $topic->name }}</h2>
  <label>Description<textarea name="description" rows="3" maxlength="1500" class="form-control mt-1">{{ old('description', $plan?->description) }}</textarea></label>
  <label>Suggested prerequisite<select name="prerequisite_id" class="form-select mt-1"><option value="">None</option>@foreach($topics->where('parent_id', $topic->parent_id)->where('id', '!=', $topic->id) as $option)<option value="{{ $option->id }}" @selected(old('prerequisite_id', $plan?->prerequisite_id) == $option->id)>{{ $option->name }}</option>@endforeach</select></label>
  <label>Display order<input name="sort_order" class="form-control mt-1" type="number" min="0" max="1000" value="{{ old('sort_order', $plan?->sort_order ?? 0) }}" required></label>
  <label>Required outcomes (one per line, up to 10)<textarea name="outcomes_text" class="form-control mt-1" rows="6" maxlength="2500" placeholder="Leave blank until requirements have been prepared">{{ old('outcomes_text', collect($plan?->outcomes ?? [])->pluck('text')->implode("\n")) }}</textarea></label>
  <p class="small text-muted">Each outcome can use up to 240 characters. Adding or rewriting an outcome makes it pending until assessed in a new activity. Existing submissions, feedback, and passes are preserved. Changing only the description or display order does not affect completion.</p>
  <div><button class="btn btn-primary" type="submit">Save learning path</button></div>
</form>
@endif
@endsection
