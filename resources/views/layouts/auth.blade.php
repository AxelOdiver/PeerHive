<!doctype html>
<html lang="en" data-bs-theme="light">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Auth')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/peerhive-logo.png') }}?v=2">
    @include('layouts.partials.theme-init')

    {{-- Your Vite bundle (should import bootstrap + adminlte) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>

  <body class="auth-page @yield('body-class')" data-page="{{ Route::currentRouteName() }}">
    <x-auth-theme-selector />
    @yield('content')
    @unless(request()->routeIs('register'))
      <div class="text-center small py-3">
        <a href="{{ route('terms') }}" @if(request()->routeIs('login')) data-bs-toggle="modal" data-bs-target="#loginTermsModal" @endif>Terms and Conditions</a>
        <span class="mx-2">·</span>
        <a href="{{ route('privacy') }}" @if(request()->routeIs('login')) data-bs-toggle="modal" data-bs-target="#loginPrivacyModal" @endif>Privacy Policy</a>
      </div>
    @endunless
    @if(request()->routeIs('login'))
      @foreach(['terms' => ['loginTermsModal', 'Terms and Conditions'], 'privacy' => ['loginPrivacyModal', 'Privacy Policy']] as $policy => [$modalId, $modalTitle])
        <x-modal :id="$modalId" :title="$modalTitle" dialogClass="modal-dialog-centered modal-dialog-scrollable" size="lg">
          @include('legal.partials.'.$policy)
        </x-modal>
      @endforeach
    @endif
  </body>
</html>
