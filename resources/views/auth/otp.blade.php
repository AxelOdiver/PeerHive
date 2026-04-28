@extends('layouts.auth')

@section('title', 'Verify Login')
@section('body-class', 'login-page bg-body-tertiary')

@section('content')
  <div class="login-box">

    <div class="login-logo">
      <a href="{{ url('/') }}"><b>Peer</b>Hive</a>
    </div>

    <div class="card">
      <div class="card-body login-card-body">
        <p class="login-box-msg">
          <i class="bi bi-envelope-check me-1"></i>
          We sent a 6-digit code to your email. Enter it below.
        </p>

        <div id="otpError" class="alert alert-danger py-2 d-none"></div>
        <div id="otpSuccess" class="alert alert-success py-2 d-none"></div>

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
            Code expires in <span id="countdown" class="fw-bold text-danger">10:00</span>
          </p>

          {{-- Trust device checkbox --}}
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="trust_device" id="trustDevice" value="1">
            <label class="form-check-label small" for="trustDevice">
              Remember this device for 30 days
            </label>
          </div>

          <button type="submit" class="btn btn-primary w-100 mb-2">Verify</button>
        </form>

        <div class="text-center mt-2">
          <form method="POST" action="{{ route('otp.resend') }}" id="resendForm">
            @csrf
            <button type="submit" class="btn btn-link text-muted p-0 small">
              Didn't receive a code? Resend
            </button>
          </form>
        </div>

        <div class="text-center mt-2">
          <a href="{{ route('login') }}" class="btn btn-secondary w-100">
            Back to Login
          </a>
        </div>
      </div>
    </div>

  </div>

  {{-- Countdown timer --}}
  <script>
    (function () {
      let seconds = 10 * 60;
      const el = document.getElementById('countdown');
      const tick = () => {
        if (seconds <= 0) { el.textContent = 'Expired'; return; }
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        el.textContent = `${m}:${s}`;
        seconds--;
        setTimeout(tick, 1000);
      };
      tick();
    })();

    document.getElementById('otpCode').addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });
  </script>

  {{-- AJAX submit --}}
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const $form    = $('#otpForm');
      const $error   = $('#otpError');
      const $success = $('#otpSuccess');

      $form.on('submit', function (e) {
        e.preventDefault();
        $error.addClass('d-none').text('');

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
            const errors = xhr.responseJSON?.errors?.code;
            const msg = errors ? errors[0] : (xhr.responseJSON?.message ?? 'Something went wrong.');
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
            $success.removeClass('d-none').text(response.message);
          },
          error: function (xhr) {
            $error.removeClass('d-none').text(xhr.responseJSON?.message ?? 'Could not resend code.');
          },
        });
      });
    });
  </script>
@endsection
