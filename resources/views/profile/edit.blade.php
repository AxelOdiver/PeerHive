@extends('layouts.dashboard')

@section('title', 'Profile')
@section('page-title', 'Profile')

@section('content')
<div class="card">
  <div class="d-flex justify-content-center position-relative p-4">
    <img src="profile.jpg" class="rounded-circle" style="width:150px;height:150px;">
    <label for="file-upload" class="position-absolute bottom-0 end-0 bg-white p-2 rounded-circle border">
        <i class="bi bi-camera"></i> <!-- Requires Bootstrap Icons -->
        <input id="file-upload" type="file" style="display:none;" accept="image/*">
    </label>
  </div>
     
  <div class="row">
    <div class="col-lg-6">
        <div class="card-header">
        </div>
        <div class="card-body">
          <form id="form" method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
              <label for="first_name" class="form-label">First Name</label>
              <input
                id="first_name"
                type="text"
                name="first_name"
                class="form-control @error('first_name') is-invalid @enderror"
                value="{{ old('first_name', auth()->user()->first_name) }}"
                required
              >
              @error('first_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="mb-3">
              <label for="middle_name" class="form-label">Middle Name</label>
              <input
                id="middle_name"
                type="text"
                name="middle_name"
                class="form-control @error('middle_name') is-invalid @enderror"
                value="{{ old('middle_name', auth()->user()->middle_name) }}"
              >
              @error('middle_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="mb-3">
              <label for="last_name" class="form-label">Last Name</label>
              <input
                id="last_name"
                type="text"
                name="last_name"
                class="form-control @error('last_name') is-invalid @enderror"
                value="{{ old('last_name', auth()->user()->last_name) }}"
                required
              >
              @error('last_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input
                id="email"
                type="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', auth()->user()->email) }}"
                required
              >
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">New Password</label>
              <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
              >
              <div class="form-text">Leave blank to keep your current password.</div>
              @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password_confirmation" class="form-label">Confirm New Password</label>
              <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                class="form-control"
              >
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
