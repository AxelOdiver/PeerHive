@extends('layouts.auth')

@section('title', 'Login')
@section('body-class', 'login-page ph-auth-page bg-body-tertiary')

@section('content')
<main class="peerhive-login-shell ph-auth-shell" aria-label="PeerHive authentication">
  @include('auth.partials.brand-story')
  <section class="ph-auth-panel">
    <div class="ph-auth-panel-inner">
  <div class="login-box ph-login-box">

    <div class="ph-mobile-brand"><a href="{{ url('/') }}" aria-label="PeerHive home"><x-peerhive-logo /><span>PeerHive</span></a></div>

    <div class="card">
      <div class="card-body login-card-body">
        <h1 class="h4 mb-2">Welcome back</h1>
        <p class="login-box-msg">Log in to continue your learning journey.</p>
        @if (session('status'))
          <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <div id="loginError" class="alert alert-danger py-2 d-none"></div>

        <form method="POST" action="{{ route('login.store') }}" id="form">
            @csrf

          <label for="login-email" class="form-label">Email</label>
          <div class="input-group mb-3 has-validation">
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
          <div class="input-group mb-3 has-validation">
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
          
          <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-2" id="loginSubmitBtn">
            <span class="spinner-border spinner-border-sm d-none" id="loginSubmitSpinner" aria-hidden="true"></span>
            <span id="loginSubmitText">Log in</span>
          </button>
          <div class="peerhive-login-forgot"><a class="ph-quiet-action" href="{{ route('password.request') }}">Forgot password?</a></div>
        </form>

        <div class="peerhive-login-divider"><span>New to PeerHive?</span></div>
        <a class="btn peerhive-login-register w-100" href="{{ route('register') }}">Create an account</a>
      </div>
    </div>

  </div>
    </div>
  </section>
</main>
@endsection

