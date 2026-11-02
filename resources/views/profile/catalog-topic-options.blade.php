@foreach($catalogTopics->groupBy(fn ($topic) => $topic->parent?->name ?? 'Topics') as $subject => $topics)
<optgroup label="{{ $subject }}">@foreach($topics as $topic)<option value="{{ $topic->name }}" @selected(in_array($topic->name, $selected, true))>{{ $topic->name }}</option>@endforeach</optgroup>
@endforeach
@foreach(array_diff($selected, $catalogTopics->pluck('name')->all()) as $legacy)<option value="{{ $legacy }}" selected>{{ $legacy }} (previous entry)</option>@endforeach
