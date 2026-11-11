@extends('layouts.auth')
@section('title', 'Forgot password')
@section('body-class', 'login-page ph-auth-page bg-body-tertiary')
@section('content')
<main class="peerhive-login-shell ph-auth-shell" aria-label="PeerHive authentication">
  @include('auth.partials.brand-story')
  <section class="ph-auth-panel">
    <div class="ph-auth-panel-inner">
  <div class="login-box">
    <div class="ph-mobile-brand"><a href="{{ url('/') }}" aria-label="PeerHive home"><x-peerhive-logo /><span>PeerHive</span></a></div>
    <div class="card">
      <div class="card-body login-card-body">
        <h1 class="h4 text-center mb-2">Forgot your password?</h1>
        <p class="login-box-msg">Enter your email and we'll send you a link to reset it.</p>
        @if (session('status'))
          <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('password.email') }}" id="peerhivePasswordEmailForm" data-cooldown="{{ max(0, (int) session('password_reset_cooldown_until', 0) - now()->timestamp) }}">
          @csrf
          <div class="mb-3">
            <label for="reset-email" class="form-label">Email</label>
            <input type="email" id="reset-email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus aria-describedby="email-error">
            <div id="email-error" class="invalid-feedback">@error('email') {{ $message }} @enderror</div>
          </div>
          <button type="submit" class="btn btn-primary w-100" id="peerhivePasswordEmailButton"><span id="peerhivePasswordEmailLabel">Send reset link</span></button>
        </form>
        <p class="small text-body-secondary mt-3 mb-0" id="peerhivePasswordEmailHelp" role="status" aria-live="polite">You can request a new link 60 seconds after your last request.</p>
        <a href="{{ route('login') }}" class="btn auth-back-button w-100 mt-3">Back to log in</a>
        <script>
          document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('peerhivePasswordEmailForm');
            const button = document.getElementById('peerhivePasswordEmailButton');
            const label = document.getElementById('peerhivePasswordEmailLabel');
            if (!form || !button || !label) return;
            let remaining = Number(form.dataset.cooldown || 0);
            const update = () => {
              const waiting = remaining > 0;
              button.disabled = waiting;
              label.textContent = waiting ? `Resend reset link in ${remaining}s` : (form.dataset.cooldown !== '0' ? 'Resend reset link' : 'Send reset link');
              if (waiting) remaining--;
            };
            update();
            window.setInterval(update, 1000);
            form.addEventListener('submit', () => { button.disabled = true; label.textContent = 'Sending...'; });
          });
        </script>
      </div>
    </div>
  </div>
    </div>
  </section>
</main>
@endsection

