{{-- Shared five-step guide: on the new-user dashboard and in Help for every signed-in user. --}}
@php
  $helpModal = $helpModal ?? false;
  $walkthroughId = $helpModal ? 'peerHiveHelpWalkthrough' : 'peerHiveWalkthrough';
  $previousId = $helpModal ? 'peerHiveHelpPrevious' : 'tutorialPrevious';
  $nextId = $helpModal ? 'peerHiveHelpNext' : 'tutorialNext';
    $walkthrough = [
      ['icon' => 'bi-search', 'title' => 'Find your learning partner', 'text' => 'Browse recommended peers or use search to find a learning partner. Open their profile to explore their skills and interests.', 'tip' => 'Start with a skill you want to learn.'],
      ['icon' => 'bi-arrow-left-right', 'title' => 'Send a learning request', 'text' => 'Choose Learn / Swap on a student card. Introduce yourself and explain what you would like to learn and what you can share.', 'tip' => 'Check which topics the peer is verified to teach.'],
      ['icon' => 'bi-chat-dots', 'title' => 'Start a conversation', 'text' => 'Open Messages to connect with your peer. Discuss your learning goals and decide how you will work together.', 'tip' => 'On your phone, tap a chat to open it.'],
      ['icon' => 'bi-calendar-check', 'title' => 'Make time to learn', 'text' => 'Set your weekly availability in Schedule. Use Messages to agree with your peer on a time that suits you both.', 'tip' => 'Choose a day and set your available hours.'],
      ['icon' => 'bi-people', 'title' => 'Learn with a community', 'text' => 'Explore Community to find groups around your interests. Join a group to connect, ask questions, and share what you know.', 'tip' => 'Private communities require approval or an invitation.'],
    ];
@endphp
@if(!$helpModal)
<section class="card border-0 shadow-sm dashboard-quick-start ph-v44-guide ph-v45-guide mb-4" aria-labelledby="quickStartHeading">
  <h2 id="quickStartHeading" class="card-header bg-transparent p-0 h6 mb-0">
    <button type="button" class="dashboard-guide-toggle d-flex align-items-center gap-2 w-100 border-0 bg-transparent text-body text-start fw-semibold" data-bs-toggle="collapse" data-bs-target="#quickStartContent" aria-expanded="true" aria-controls="quickStartContent">
      <i class="bi bi-chevron-down" aria-hidden="true"></i>How PeerHive works
    </button>
  </h2>
  <div id="quickStartContent" class="collapse show">
@endif
<section id="{{ $walkthroughId }}" class="ph-v45-guide-carousel carousel slide tutorial-card" aria-label="How PeerHive works" aria-roledescription="carousel" tabindex="0">
  <p class="ph-v45-guide-eyebrow small fw-semibold mb-3">Your quick start guide</p>
  <div class="carousel-inner" aria-live="polite">
    @foreach($walkthrough as $step)
      <article class="carousel-item {{ $loop->first ? 'active' : '' }}" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ count($walkthrough) }}" @if(!$loop->first) aria-hidden="true" @endif>
        <div class="ph-v45-guide-step">
          <div class="tutorial-icon"><i class="bi {{ $step['icon'] }}" aria-hidden="true"></i></div>
          <div class="ph-v45-guide-copy">
            <h3 class="h5 fw-bold">{{ $step['title'] }}</h3>
            <p class="text-body-secondary">{{ $step['text'] }}</p>
            <p class="small mb-0 ph-v45-guide-tip"><i class="bi bi-lightbulb text-warning me-2" aria-hidden="true"></i>{{ $step['tip'] }}</p>
          </div>
        </div>
      </article>
    @endforeach
  </div>
  <div class="tutorial-controls ph-v45-guide-controls d-grid align-items-center gap-2">
    <button id="{{ $previousId }}" type="button" class="btn btn-sm ph-v45-action ph-v45-action--outline" aria-label="Previous tutorial step" disabled><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="ms-1">Previous</span></button>
    <div class="d-flex align-items-center tutorial-dots" aria-label="Choose a tutorial step">
      @foreach($walkthrough as $step)
        <button type="button" class="tutorial-dot {{ $loop->first ? 'is-active' : '' }}" data-tutorial-slide="{{ $loop->index }}" aria-label="Step {{ $loop->iteration }}: {{ $step['title'] }}" aria-current="{{ $loop->first ? 'step' : 'false' }}"><span></span></button>
      @endforeach
    </div>
    <button id="{{ $nextId }}" type="button" class="btn btn-sm ph-v45-action ph-v45-action--primary" aria-label="Next tutorial step"><span class="me-1">Next</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
  </div>
</section>
@if(!$helpModal)
  </div>
</section>
@endif
