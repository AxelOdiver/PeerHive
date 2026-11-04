<section class="card border-0 shadow-sm dashboard-quick-start mb-4" aria-labelledby="quickStartHeading">
  <h2 id="quickStartHeading" class="card-header bg-transparent p-0 h6 mb-0">
    <button type="button" class="dashboard-guide-toggle d-flex align-items-center gap-2 w-100 border-0 bg-transparent text-body text-start p-3 fw-semibold" data-bs-toggle="collapse" data-bs-target="#quickStartContent" aria-expanded="true" aria-controls="quickStartContent">
      <i class="bi bi-chevron-down" aria-hidden="true"></i>How PeerHive works
    </button>
  </h2>
  <div id="quickStartContent" class="collapse show">
  @php
    $walkthrough = [
      ['icon' => 'bi-search', 'title' => 'Find your learning partner', 'text' => 'Browse recommended peers or use search to find a learning partner. Open their profile to explore their skills and interests.', 'tip' => 'Start with a skill you want to learn.'],
      ['icon' => 'bi-arrow-left-right', 'title' => 'Send a learning request', 'text' => 'Choose Learn / Swap on a student card. Introduce yourself and explain what you would like to learn and what you can share.', 'tip' => 'Check which topics the peer is verified to teach.'],
      ['icon' => 'bi-chat-dots', 'title' => 'Start a conversation', 'text' => 'Open Messages to connect with your peer. Discuss your learning goals and decide how you will work together.', 'tip' => 'On your phone, tap a chat to open it.'],
      ['icon' => 'bi-calendar-check', 'title' => 'Make time to learn', 'text' => 'Set your weekly availability in Schedule. Use Messages to agree with your peer on a time that suits you both.', 'tip' => 'Choose a day and set your available hours.'],
      ['icon' => 'bi-people', 'title' => 'Learn with a community', 'text' => 'Explore Community to find groups around your interests. Join a group to connect, ask questions, and share what you know.', 'tip' => 'Private communities require approval or an invitation.'],
    ];
  @endphp
  <section id="peerHiveWalkthrough" class="p-3 carousel slide tutorial-card" aria-label="How PeerHive works" aria-roledescription="carousel" tabindex="0">
    <p class="small fw-semibold text-primary mb-3">Your quick start guide</p>
    <div class="carousel-inner" aria-live="polite">
      @foreach($walkthrough as $step)
        <article class="carousel-item {{ $loop->first ? 'active' : '' }}" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ count($walkthrough) }}" @if(!$loop->first) aria-hidden="true" @endif>
          <div class="tutorial-icon mb-3"><i class="bi {{ $step['icon'] }}" aria-hidden="true"></i></div>
          <h3 class="h5 fw-bold">{{ $step['title'] }}</h3>
          <p class="text-muted mb-3">{{ $step['text'] }}</p>
          <p class="small mb-0"><i class="bi bi-lightbulb text-warning me-1" aria-hidden="true"></i>{{ $step['tip'] }}</p>
        </article>
      @endforeach
    </div>
    <div class="tutorial-controls d-grid align-items-center gap-2 mt-4">
      <button id="tutorialPrevious" type="button" class="btn btn-outline-secondary btn-sm" aria-label="Previous tutorial step" disabled><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="ms-1">Previous</span></button>
      <div class="d-flex align-items-center tutorial-dots" aria-label="Choose a tutorial step">
        @foreach($walkthrough as $step)
          <button type="button" class="tutorial-dot {{ $loop->first ? 'is-active' : '' }}" data-tutorial-slide="{{ $loop->index }}" aria-label="Step {{ $loop->iteration }}: {{ $step['title'] }}" aria-current="{{ $loop->first ? 'step' : 'false' }}"><span></span></button>
        @endforeach
      </div>
      <button id="tutorialNext" type="button" class="btn btn-primary btn-sm" aria-label="Next tutorial step"><span class="me-1">Next</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
    </div>
  </section>
  </div>
</section>
