<nav class="ph-tabs-nav mb-4" data-ph-tabs data-ph-tabs-key="progress" aria-label="My Progress sections">
  <div class="ph-tabs-track">
    <a href="{{ route('progress.index') }}" class="ph-tabs-item {{ request()->routeIs('progress.index') ? 'is-active' : '' }}" @if(request()->routeIs('progress.index')) aria-current="page" @endif>Overview</a>
    <a href="{{ route('progress.skill-tree') }}" class="ph-tabs-item {{ request()->routeIs('progress.skill-tree') ? 'is-active' : '' }}" @if(request()->routeIs('progress.skill-tree')) aria-current="page" @endif>Skill Tree</a>
    <span class="ph-tabs-indicator" aria-hidden="true"></span>
  </div>
</nav>
