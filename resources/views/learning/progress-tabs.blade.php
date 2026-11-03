<nav class="nav nav-pills gap-2 mb-4" aria-label="My Progress sections">
  <a href="{{ route('progress.index') }}" class="nav-link {{ request()->routeIs('progress.index') ? 'active' : '' }}" @if(request()->routeIs('progress.index')) aria-current="page" @endif>Overview</a>
  <a href="{{ route('progress.skill-tree') }}" class="nav-link {{ request()->routeIs('progress.skill-tree') ? 'active' : '' }}" @if(request()->routeIs('progress.skill-tree')) aria-current="page" @endif>Skill Tree</a>
</nav>
