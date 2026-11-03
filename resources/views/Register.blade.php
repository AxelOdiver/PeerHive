@extends('layouts.auth')

@section('title', 'Create account')
@section('body-class', 'login-page registration-page bg-body-tertiary')

@section('content')
  <div class="login-box">

    <div class="login-logo">
      <a href="{{ url('/') }}" class="peerhive-auth-brand" aria-label="PeerHive home"><x-peerhive-logo /><span>PeerHive</span></a>
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

          <div class="mb-3">
            <label for="register-username" class="form-label">Username <span class="text-muted small">(optional)</span></label>
            <input id="register-username" name="username" class="form-control" autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" aria-describedby="register-username-help">
            <div class="invalid-feedback" data-error-for="username"></div>
            <div class="form-text" id="register-username-help">3–30 letters, numbers or underscores. Others can use this to find you.</div>
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
          
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="accept_policies" id="accept_policies" value="1" required>
            <label class="form-check-label small" for="accept_policies">I agree to the <a href="{{ route('terms') }}" id="reviewTermsLink" class="text-primary text-decoration-underline">Terms and Conditions</a> and acknowledge the <a href="{{ route('privacy') }}" id="reviewPrivacyLink" class="text-primary text-decoration-underline">Privacy Policy</a>.</label>
            <div class="invalid-feedback" data-error-for="accept_policies"></div>
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
  <div class="modal fade" id="policyAcceptanceModal" tabindex="-1" aria-labelledby="policyAcceptanceTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="policyAcceptanceTitle">Review Terms and Privacy Policy</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close without accepting"></button>
        </div>
        <div class="modal-body">
          <p id="policyScrollHint" class="small text-muted" role="status" aria-live="polite">Scroll to the end to enable Accept.</p>
          <div id="policyScrollContent" class="border rounded p-3" style="max-height:50vh;overflow-y:auto;overscroll-behavior:contain" tabindex="0" role="region" aria-label="Terms and Privacy Policy text" aria-describedby="policyScrollHint">
            @include('legal.partials.terms')
            <hr class="my-4">
            @include('legal.partials.privacy')
            <p class="small fw-semibold mb-0">End of Terms and Privacy Policy.</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" id="acceptPoliciesBtn" disabled>Accept</button>
        </div>
      </div>
    </div>
  </div>
@endsection
