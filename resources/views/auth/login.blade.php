@extends('layouts.auth')

@section('title', 'Login')
@section('body-class', 'login-page bg-body-tertiary')

@section('content')
  <div class="login-box">

    <div class="login-logo">
      <a href="{{ url('/') }}"><b>Peer</b>Hive</a>
    </div>

    <div class="card">
      <div class="card-body login-card-body">
        <h1 class="h4 text-center mb-2">Welcome back</h1>
        <p class="login-box-msg">Log in to continue learning with PeerHive.</p>
        @if (session('status'))
          <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <div id="loginError" class="alert alert-danger py-2 d-none"></div>

        <form method="POST" action="{{ route('login.store') }}" id="form">
            @csrf

          <label for="login-email" class="form-label">Email</label>
          <div class="input-group mb-3">
            <input
              type="email"
              autocomplete="username" name="email" id="login-email"
              class="form-control"
              placeholder="Email"
              required
              autofocus
            >
            <span class="input-group-text">
              <i class="bi bi-envelope"></i>
            </span>
            <div class="invalid-feedback" data-error-for="email"></div>
          </div>

          <label for="login-password" class="form-label">Password</label>
          <div class="input-group mb-3">
            <input
              type="password"
              autocomplete="current-password" name="password" id="login-password"
              class="form-control"
              placeholder="Password"
              required
            >
            <button type="button" class="input-group-text toggle-password" aria-label="Show password" aria-controls="login-password" aria-pressed="false"><i class="bi bi-eye-slash-fill" aria-hidden="true"></i></button>
            <div class="invalid-feedback" data-error-for="password"></div>
          </div>
          <p class="small text-body-secondary mb-3">On a new device, we’ll email you a verification code after you log in.</p>
          <div class="text-end mb-3"><a href="{{ route('password.request') }}">Forgot password?</a></div>
          <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-2" id="loginSubmitBtn">
            <span class="spinner-border spinner-border-sm d-none" id="loginSubmitSpinner" aria-hidden="true"></span>
            <span id="loginSubmitText">Log in</span>
          </button>
          
        </form>

        <p class="text-center small mt-3 mb-0">New to PeerHive? <a href="{{ route('register') }}">Create an account</a></p>
      </div>
    </div>

  </div>
@endsection
