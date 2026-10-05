@extends('layouts.auth')
@section('title', 'Reset password')
@section('body-class', 'login-page bg-body-tertiary')
@section('content')
  <div class="login-box">
    <div class="login-logo"><a href="{{ url('/') }}"><b>Peer</b>Hive</a></div>
    <div class="card">
      <div class="card-body login-card-body">
        <h1 class="h4 text-center mb-2">Choose a new password</h1>
        <p class="login-box-msg">Set a new password for your PeerHive account.</p>
        <form method="POST" action="{{ route('password.update') }}">
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">
          @error('token') <div class="alert alert-danger" role="alert">{{ $message }}</div> @enderror
          <div class="mb-3">
            <label for="reset-email" class="form-label">Email</label>
            <input type="email" id="reset-email" name="email" value="{{ old('email', $email) }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required aria-describedby="email-error">
            <div id="email-error" class="invalid-feedback">@error('email') {{ $message }} @enderror</div>
          </div>
          <div class="mb-3">
            <label for="reset-password" class="form-label">New password</label>
            <div class="input-group has-validation">
            <input type="password" id="reset-password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required aria-describedby="password-requirements">
            <button type="button" class="input-group-text toggle-password" aria-label="Show new password" aria-controls="reset-password" aria-pressed="false"><i class="bi bi-eye-slash-fill" aria-hidden="true"></i></button>
            @error('password') <div class="invalid-feedback" role="alert">{{ $message }}</div> @enderror
            </div>
          </div>
          @include('auth.partials.password-requirements')
          <div class="mb-3">
            <label for="reset-password-confirmation" class="form-label">Confirm new password</label>
            <div class="input-group">
            <input type="password" id="reset-password-confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
            <button type="button" class="input-group-text toggle-password" aria-label="Show confirm password" aria-controls="reset-password-confirmation" aria-pressed="false"><i class="bi bi-eye-slash-fill" aria-hidden="true"></i></button>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Reset password</button>
        </form>
        <p class="text-center mt-3 mb-1"><a href="{{ route('password.request') }}">Request a new reset link</a></p>
        <p class="text-center mb-0"><a href="{{ route('login') }}">Back to log in</a></p>
      </div>
    </div>
  </div>
@endsection
