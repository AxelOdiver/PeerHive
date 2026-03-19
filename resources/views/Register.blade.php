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
        <p class="login-box-msg">Register to start your session</p>

        <div id="loginError" class="alert alert-danger py-2 d-none"></div>

        <form method="POST" action="{{ route('login.store') }}" id="form">
            @csrf

          <div class="input-group mb-3">
            <input
              type="text"
              name="first_name"
              class="form-control"
              placeholder="First Name"
              required
              autofocus
            >            
            <div class="invalid-feedback" data-error-for="first_name"></div>
          </div>

          <div class="input-group mb-3">
            <input
              type="text"
              name="middle_name"
              class="form-control"
              placeholder="Middle Name"
              required
              autofocus
            >            
            <div class="invalid-feedback" data-error-for="middle_name"></div>
          </div>

          <div class="input-group mb-3">
            <input
              type="text"
              name="last_name"
              class="form-control"
              placeholder="Last Name"
              required
              autofocus
            >
            <div class="invalid-feedback" data-error-for="last_name"></div>
          </div>

          <div class="input-group mb-3">
            <input
              type="text"
              name="email"
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

          <div class="input-group mb-3">
            <input
              type="password"
              name="password"
              class="form-control"
              placeholder="Password"
              required
            >
            <span class="input-group-text">
              <i class="bi bi-lock-fill"></i>
            </span>
            <div class="invalid-feedback" data-error-for="password"></div>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            Login
          </button>
          
        </form>
        <div class="social-auth-links text-center mt-3 mb-3">
          <p>- OR -</p>
          <button type="submit" form="form" class="btn btn-secondary w-100">Register</button>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const $form = $('#form');

  function clearErrors() {
    $form.find('.is-invalid').removeClass('is-invalid');
    $('[data-error]').text('');
  }

  $form.on('submit', function (e) {
    e.preventDefault();
    clearErrors();

    $.ajax({
      url: $form.attr('action'),
      method: 'POST',
      data: $form.serialize(),
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function (response) {
        window.location.assign(response.redirect);
      },
      error: function (xhr) {
        if (xhr.status === 422) {
          const errors = xhr.responseJSON?.errors || {};
          for (const field in errors) {
            const msg = errors[field]?.[0] ?? 'Invalid input';
            const $input = $form.find(`[name="${field}"]`);
            $input.addClass('is-invalid');
            $form.find(`[data-error-for="${field}"]`).text(msg);
          }
          return;
        }

        // 401 invalid credentials (or other errors)
        const msg = xhr.responseJSON?.message ?? 'Login failed';
        $('#loginError').removeClass('d-none').text(msg);
      }
    });
  });
});
</script>
@endpush