@extends('layouts.auth')

@section('title', 'Verify your email')
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
        <h1 class="h4 mb-2">{{ session('otp_purpose') === 'registration' ? 'Verify your account' : 'Verify your login' }}</h1>
        <p class="login-box-msg"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>We sent a 6-digit code to your email. Enter it below.</p>

        <div id="otpError" class="alert alert-danger py-2 d-none" role="alert" aria-live="assertive"></div>
        <div id="otpSuccess" class="alert alert-success py-2 d-none" role="status" aria-live="polite"></div>

        @if(session('resent'))
          <div class="alert alert-success py-2">A new code has been sent to your email.</div>
        @endif

        @if($errors->any())
          <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('otp.verify') }}" id="otpForm">
          @csrf

          <div class="input-group mb-2">
            <input
              type="text"
              name="code"
              id="otpCode"
              aria-describedby="otpError"
              class="form-control text-center fw-bold @error('code') is-invalid @enderror"
              style="font-size: 1.6rem; letter-spacing: 0.5rem;"
              placeholder="— — — — — —"
              maxlength="6"
              inputmode="numeric"
              pattern="\d{6}"
              autocomplete="one-time-code"
              autofocus
              required
            >
          </div>

          <p class="text-muted small text-center mb-3">
            Code expires in <span id="countdown" class="fw-bold text-danger" data-expires-at-ms="{{ $otpExpiresAtMs }}" data-server-now-ms="{{ $otpServerNowMs }}">--:--</span>
          </p>
          <p id="otpExpiredHint" class="small text-danger text-center mb-3 d-none" role="status">This code has expired. Request a new code below.</p>

          {{-- Trust device checkbox --}}
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="trust_device" id="trustDevice" value="1">
            <label class="form-check-label small" for="trustDevice">
              Remember this device for 30 days
            </label>
          </div>

          <button type="submit" class="btn btn-primary w-100 mb-2 d-inline-flex align-items-center justify-content-center gap-2" id="otpSubmitBtn">
            <span class="spinner-border spinner-border-sm d-none" id="otpSubmitSpinner" aria-hidden="true"></span>
            <span id="otpSubmitText">Verify</span>
          </button>
        </form>

        <div class="text-center mt-2">
          <form method="POST" action="{{ route('otp.resend') }}" id="resendForm">
            @csrf
            <button type="submit" class="btn ph-quiet-action ph-otp-resend w-100">
              Didn't receive a code? Resend
            </button>
          </form>
        </div>

        <div class="text-center mt-2">
          <a href="{{ route('login') }}" class="btn auth-back-button w-100 mt-2">
            Back to log in
          </a>
        </div>
      </div>
    </div>

  </div>

  {{-- Use the server-issued expiry, not a fresh countdown after refresh. --}}
  <script>
    (function () {
      const el = document.getElementById('countdown');
      const submit = document.getElementById('otpSubmitBtn');
      const expiredHint = document.getElementById('otpExpiredHint');
      const code = document.getElementById('otpCode');
      let expiresAt = Number(el.dataset.expiresAtMs) || 0;
      let serverOffset = (Number(el.dataset.serverNowMs) || Date.now()) - Date.now();
      let isSubmitting = false;
      const tick = () => {
        const seconds = Math.max(0, Math.ceil((expiresAt - Date.now() - serverOffset) / 1000));
        submit.disabled = isSubmitting || seconds === 0;
        if (seconds === 0) {
          el.textContent = 'Expired';
          expiredHint.classList.remove('d-none');
          return;
        }
        expiredHint.classList.add('d-none');
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        el.textContent = `${m}:${s}`;
      };
      window.peerhiveOtpTimer = {
        update(expiryMs, serverNowMs) {
          expiresAt = Number(expiryMs) || 0;
          serverOffset = (Number(serverNowMs) || Date.now()) - Date.now();
          tick();
        },
        setSubmitting(submitting) {
          isSubmitting = submitting;
          tick();
        },
        canSubmit() { return !isSubmitting && expiresAt > Date.now() + serverOffset; },
      };
      tick();
      window.setInterval(tick, 1000);
      document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
      code.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        this.classList.remove('is-invalid');
        this.removeAttribute('aria-invalid');
      });
    })();
  </script>

  {{-- AJAX submit --}}
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const $form    = $('#otpForm');
      const $error   = $('#otpError');
      const $success = $('#otpSuccess');
      const $submitSpinner = $('#otpSubmitSpinner');
      const $submitText = $('#otpSubmitText');

      function setSubmitting(isSubmitting) {
        window.peerhiveOtpTimer.setSubmitting(isSubmitting);
        $submitSpinner.toggleClass('d-none', !isSubmitting);
        $submitText.text(isSubmitting ? 'Verifying...' : 'Verify');
      }

      $form.on('submit', function (e) {
        e.preventDefault();
        if (!window.peerhiveOtpTimer.canSubmit()) return;
        $error.addClass('d-none').text('');
        $success.addClass('d-none').text('');
        setSubmitting(true);

        $.ajax({
          url: $form.attr('action'),
          method: 'POST',
          data: $form.serialize(),
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
          },
          success: function (response) {
            window.location.assign(response.redirect);
          },
          error: function (xhr) {
            setSubmitting(false);
            const errors = xhr.responseJSON?.errors?.code;
            const msg = errors ? errors[0] : (xhr.responseJSON?.message ?? 'Could not verify the code. Please try again.');
            $('#otpCode').addClass('is-invalid').attr('aria-invalid', 'true');
            $error.removeClass('d-none').text(msg);
          },
        });
      });

      $('#resendForm').on('submit', function (e) {
        e.preventDefault();
        $error.addClass('d-none');
        $success.addClass('d-none');

        $.ajax({
          url: $(this).attr('action'),
          method: 'POST',
          data: $(this).serialize(),
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
          },
          success: function (response) {
            window.peerhiveOtpTimer?.update(response.expires_at_ms, response.server_now_ms);
            $('#otpCode').val('').removeClass('is-invalid').removeAttr('aria-invalid');
            $success.removeClass('d-none').text(response.message);
          },
          error: function (xhr) {
            const msg = xhr.status === 429
              ? 'Please wait before requesting another code.'
              : (xhr.responseJSON?.message ?? 'Could not resend code.');
            $error.removeClass('d-none').text(msg);
          },
        });
      });
    });
  </script>
    </div>
  </section>
</main>
@endsection

