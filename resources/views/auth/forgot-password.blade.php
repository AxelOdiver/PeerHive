@extends('layouts.auth')
@section('title', 'Forgot password')
@section('body-class', 'login-page bg-body-tertiary')
@section('content')
  <div class="login-box">
    <div class="login-logo"><a href="{{ url('/') }}"><b>Peer</b>Hive</a></div>
    <div class="card">
      <div class="card-body login-card-body">
        <h1 class="h4 text-center mb-2">Forgot your password?</h1>
        <p class="login-box-msg">Enter your email and we'll send you a link to reset it.</p>
        @if (session('status'))
          <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('password.email') }}">
          @csrf
          <div class="mb-3">
            <label for="reset-email" class="form-label">Email</label>
            <input type="email" id="reset-email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus aria-describedby="email-error">
            <div id="email-error" class="invalid-feedback">@error('email') {{ $message }} @enderror</div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Send reset link</button>
        </form>
        <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}">Back to log in</a></p>
      </div>
    </div>
  </div>
@endsection
