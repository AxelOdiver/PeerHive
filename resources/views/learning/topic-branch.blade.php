@foreach($branch as $node)
<li>
  <a class="skill-tree-node text-decoration-none text-body {{ $selected && $selected['topic']->id === $node['topic']->id ? 'is-selected' : '' }}" href="{{ route('progress.skill-tree', ['subject'=>$subject->id, 'topic'=>$node['topic']->id]) }}" @if($selected && $selected['topic']->id === $node['topic']->id) aria-current="true" @endif>
    <span class="fw-semibold">{{ $node['topic']->name }}</span><span class="small {{ $node['status'] === 'Completed' ? 'text-success' : 'text-muted' }}">{{ $node['status'] }}</span>
  </a>
  @php($children = $nodes->filter(fn ($child) => $child['plan']?->prerequisite_id === $node['topic']->id))
  @if($children->isNotEmpty())<ul>@include('learning.topic-branch', ['branch'=>$children])</ul>@endif
</li>
@endforeach
