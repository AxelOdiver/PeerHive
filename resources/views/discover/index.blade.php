@extends('layouts.dashboard')
@section('title', 'Search | PeerHive')
@section('content')
<div class="ph-discover-page" id="phDiscoveryPage">
  <header class="mb-4">
    <h1 class="h2 fw-bold mb-1">Search PeerHive</h1>
    <p class="text-body-secondary mb-0">Find people, communities, skills, and study sessions.</p>
  </header>
  <div class="card shadow-sm ph-discover-card">
    <div class="card-body p-3 p-md-4">
      <label for="phDiscoveryInput" class="form-label fw-semibold">Search</label>
      <input id="phDiscoveryInput" type="search" class="form-control ph-discover-input" maxlength="100" autocomplete="off" placeholder="What are you looking for?" aria-describedby="phDiscoveryHelp">
      <p id="phDiscoveryHelp" class="form-text">Only discoverable content is included. Private conversations are never searched.</p>
      <div class="ph-discover-filters mt-3 mb-3" id="phDiscoveryFilters" role="group" aria-label="Filter search results"></div>
      <div id="phDiscoveryResults" aria-live="polite" aria-busy="false"></div>
    </div>
  </div>
</div>
@endsection
