@extends('layouts.dashboard')
@section('title', 'Admin - Manage Qualifications')

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-dark text-white rounded-top-4">
      <h5 class="card-title mb-0"><i class="bi bi-shield-lock me-2"></i> Qualification Requests</h5>
    </div>
    
    <div class="card-body p-0 table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table">
          <tr>
            <th class="ps-4">Student Name</th>
            <th>Subject</th>
            <th>Proof Document</th>
            <th>Status</th>
            <th>Admin Comment</th>
            <th class="text-end pe-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($qualifications as $q)
          <tr>
            <td class="ps-4">{{ $q->user->first_name . ' ' . $q->user->last_name ?? 'Unknown User' }}</td>
            <td>{{ $q->subject_name }}</td>
            <td>
              <a href="{{ asset('storage/' . $q->proof_file_path) }}" target="_blank" class="btn btn-sm btn-primary rounded-2 px-3">
                <i class="bi bi-file-earmark-text"></i> View File
              </a>
            </td>
            <td>
              @if($q->status === 'pending')
              <span class="badge bg-warning text-dark px-2 py-1 rounded-pill fw-medium">Pending</span>
              @elseif($q->status === 'approved')
              <span class="badge bg-success px-2 py-1 rounded-pill fw-medium">Approved</span>
              @else
              <span class="badge bg-danger px-2 py-1 rounded-pill fw-medium">Rejected</span>
              @endif
            </td>
            <td style="min-width: 220px;">
              @if($q->status === 'rejected' && $q->rejection_reason)
              <span class="small text-muted">{{ $q->rejection_reason }}</span>
              @else
              <span class="text-muted small">-</span>
              @endif
            </td>
            <td class="text-end pe-4">
              @if($q->status === 'pending')
              <div class="d-flex gap-2 justify-content-end">
                <form action="{{ route('admin.qualifications.respond', $q->id) }}" method="POST">
                  @csrf
                  <input type="hidden" name="status" value="approved">
                  <button type="submit" class="btn btn-success btn-sm rounded-2 px-3">Approve</button>
                </form>
                <button
                  type="button"
                  class="btn btn-danger btn-sm rounded-2 px-3"
                  data-bs-toggle="modal"
                  data-bs-target="#rejectQualificationModal{{ $q->id }}"
                >
                  Reject
                </button>
              </div>
              @else
              <span class="text-muted small">Reviewed</span>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-1 d-block mb-3"></i>
              No qualification requests found.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @foreach($qualifications->where('status', 'pending') as $q)
  <x-modal id="rejectQualificationModal{{ $q->id }}" title="Reject Qualification">
    <form id="rejectQualificationForm{{ $q->id }}" action="{{ route('admin.qualifications.respond', $q->id) }}" method="POST">
      @csrf
      <input type="hidden" name="status" value="rejected">

      <p class="text-muted small mb-3">
        Add a clear reason for rejecting {{ $q->user->first_name }} {{ $q->user->last_name }}'s request for {{ $q->subject_name }}.
      </p>

      <div class="mb-0">
        <label for="rejectionReason{{ $q->id }}" class="form-label">Rejection comment</label>
        <textarea
          id="rejectionReason{{ $q->id }}"
          name="rejection_reason"
          class="form-control @error('rejection_reason') is-invalid @enderror"
          rows="4"
          maxlength="1000"
          placeholder="Explain why this qualification was rejected"
          required
        >{{ old('rejection_reason') }}</textarea>
        @error('rejection_reason')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
      </div>
    </form>

    <x-slot:footer>
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-danger" form="rejectQualificationForm{{ $q->id }}">Reject Request</button>
    </x-slot:footer>
  </x-modal>
  @endforeach
</div>
@endsection
