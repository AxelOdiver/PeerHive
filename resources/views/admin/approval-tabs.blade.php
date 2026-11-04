<h1 class="h4 fw-bold mb-3">Approvals</h1>
<nav class="nav nav-tabs flex-wrap mb-3" aria-label="Approval categories">
  <a class="nav-link {{ request()->routeIs('admin.qualifications') ? 'active' : '' }}" href="{{ route('admin.qualifications') }}" @if(request()->routeIs('admin.qualifications')) aria-current="page" @endif>Teaching qualifications</a>
  @foreach(['program' => 'Programs', 'subject' => 'Subjects', 'topic' => 'Topics'] as $catalogKind => $catalogLabel)
    @php($catalogActive = request()->routeIs('admin.catalog*') && request()->query('kind', 'program') === $catalogKind)
    <a class="nav-link {{ $catalogActive ? 'active' : '' }}" href="{{ route('admin.catalog', ['kind' => $catalogKind]) }}" @if($catalogActive) aria-current="page" @endif>{{ $catalogLabel }}</a>
  @endforeach
</nav>
<p class="text-muted mb-4">Catalog approval makes an entry selectable by everyone. Teaching qualification approval is separate.</p>
