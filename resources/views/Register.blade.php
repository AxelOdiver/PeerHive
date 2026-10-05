@extends('layouts.auth')

@section('title', 'Create account')
@section('body-class', 'login-page bg-body-tertiary')

@section('content')
  <div class="login-box">

    <div class="login-logo">
      <a href="{{ url('/') }}"><b>Peer</b>Hive</a>
    </div>

    <div class="card">
      <div class="card-body login-card-body">
        <h1 class="h4 text-center mb-2">Join PeerHive</h1>
        <p class="login-box-msg">Create an account to share skills and learn together.</p>

        <div id="registerError" class="alert alert-danger py-2 d-none"></div>

        <form method="POST" action="{{ route('register.store') }}" id="form">
            @csrf

          <label for="register-first_name" class="form-label">First name</label>
          <div class="input-group mb-3">
            <input
              type="text"
              autocomplete="given-name" name="first_name" id="register-first_name"
              class="form-control"
              placeholder="First Name"
              required
              autofocus
            >            
            <div class="invalid-feedback" data-error-for="first_name"></div>
          </div>

          <label for="register-last_name" class="form-label">Last name</label>
          <div class="input-group mb-3">
            <input
              type="text"
              autocomplete="family-name" name="last_name" id="register-last_name"
              class="form-control"
              placeholder="Last Name"
              required
            >
            <div class="invalid-feedback" data-error-for="last_name"></div>
          </div>

          <label for="register-email" class="form-label">Email</label>
          <div class="input-group mb-3">
            <input
              type="email"
              autocomplete="email" name="email" id="register-email"
              class="form-control"
              placeholder="Email"
              required
            >
            <span class="input-group-text">
              <i class="bi bi-envelope"></i>
            </span>
            <div class="invalid-feedback" data-error-for="email"></div>
          </div>

          <label for="register-password" class="form-label">Password</label>
          <div class="input-group mb-3">
            <input
              type="password"
              autocomplete="new-password" name="password" id="register-password" aria-describedby="password-requirements"
              class="form-control"
              placeholder="Password"
              required
            >
            <button type="button" class="input-group-text toggle-password" aria-label="Show password" aria-controls="register-password" aria-pressed="false"><i class="bi bi-eye-slash-fill" aria-hidden="true"></i></button>
            <div class="invalid-feedback" data-error-for="password"></div>
          </div>
          
          @include('auth.partials.password-requirements')

          <label for="register-password_confirmation" class="form-label">Confirm password</label>
          <div class="input-group mb-3">
            <input
              type="password"
              autocomplete="new-password" name="password_confirmation" id="register-password_confirmation"
              class="form-control"
              placeholder="Confirm Password"
              required
            >
            <button type="button" class="input-group-text toggle-password" aria-label="Show confirm password" aria-controls="register-password_confirmation" aria-pressed="false"><i class="bi bi-eye-slash-fill" aria-hidden="true"></i></button>
            <div class="invalid-feedback" data-error-for="password_confirmation"></div>
          </div>
          
        </form>

        <div class="social-auth-links text-center mt-3 mb-3">
          <button type="submit" form="form" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-2" id="registerSubmitBtn">
            <span class="spinner-border spinner-border-sm d-none" id="registerSubmitSpinner" aria-hidden="true"></span>
            <span id="registerSubmitText">Create account</span>
          </button>
        </div>
        <p class="text-center small mt-3 mb-0">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
      </div>
    </div>
  </div>
@endsection
