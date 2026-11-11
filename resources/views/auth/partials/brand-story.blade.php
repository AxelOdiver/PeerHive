<aside class="ph-auth-story" aria-labelledby="ph-auth-story-heading">
  <a class="ph-story-brand" href="{{ url('/') }}" aria-label="PeerHive home">
    <x-peerhive-logo />
    <span>PeerHive</span>
  </a>
  <div class="ph-story-body">
    <span class="ph-story-eyebrow"><i class="bi bi-stars" aria-hidden="true"></i> LEARN · SHARE · GROW</span>
    <h2 id="ph-auth-story-heading">Better learning<br>starts <span>together.</span></h2>
    <p>Find your people, share what you know, and turn every connection into a new opportunity to learn.</p>
    <div class="ph-story-features" aria-label="PeerHive highlights">
      <div><i class="bi bi-people-fill" aria-hidden="true"></i><span>Connect with student peers</span></div>
      <div><i class="bi bi-arrow-left-right" aria-hidden="true"></i><span>Swap skills and knowledge</span></div>
      <div><i class="bi bi-mortarboard-fill" aria-hidden="true"></i><span>Grow with every session</span></div>
    </div>
  </div>
  <div class="ph-story-footer">
    <p>Your community for meaningful learning.</p>
    <nav class="ph-story-legal" aria-label="Legal information">
      <a href="{{ route('terms') }}" @if(request()->routeIs('login')) data-bs-toggle="modal" data-bs-target="#loginTermsModal" @endif>Terms and Conditions</a>
      <span aria-hidden="true">·</span>
      <a href="{{ route('privacy') }}" @if(request()->routeIs('login')) data-bs-toggle="modal" data-bs-target="#loginPrivacyModal" @endif>Privacy Policy</a>
    </nav>
  </div>
</aside>

