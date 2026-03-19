@extends('layouts.dashboard')

@section('title', 'Schedule')
@section('page-title', 'Schedule')

@section('content')

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="bg-light p-4 rounded-4">
  <div class="row gap-md-4 w-100"> 
    <div class="col-md-5">
      
      <div class="d-flex gap-3 mb-3">
        <button class="btn btn-outline-dark w-100 py-2 d-flex justify-content-between align-items-center shadow-none">
          Days <i class="bi bi-chevron-down small"></i>
        </button>
        <button class="btn btn-outline-dark w-100 py-2 d-flex justify-content-between align-items-center shadow-none">
          Timings <i class="bi bi-chevron-down small"></i>
        </button>
      </div>

      <div class="card shadow-sm border-0 bg-white p-3 rounded-4" style="max-width: 350px;">
        <input type="text" id="inline-calendar" class="d-none">
      </div>

      <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          flatpickr("#inline-calendar", {
            inline: true,     
            minDate: "today", 
            mode: "multiple"  
          });
        });
      </script>

    </div>
  </div>
</div>
@endsection