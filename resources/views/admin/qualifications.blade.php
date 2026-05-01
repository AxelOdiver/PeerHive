@extends('layouts.dashboard')
@section('title', 'Admin - Manage Qualifications')
@section('page-title', 'Manage Qualifications')

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
            <th class="text-end pe-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($qualifications as $q)
          <tr>
            <td class="ps-4">{{ $q->user->first_name . ' ' . $q->user->last_name ?? 'Unknown User' }}</td>
            <td>{{ $q->subject_name }}</td>
            <td>
              <a href="{{ asset('storage/' . $q->proof_file_path) }}" target="_blank" class="btn btn-sm btn-primary rounded-pill">
                <i class="bi bi-file-earmark-text"></i> View File
              </a>
            </td>
            <td>
              @if($q->status === 'pending')
              <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Pending</span>
              @elseif($q->status === 'approved')
              <span class="badge bg-success px-3 py-2 rounded-pill">Approved</span>
              @else
              <span class="badge bg-danger px-3 py-2 rounded-pill">Rejected</span>
              @endif
            </td>
            <td class="text-end pe-4">
              @if($q->status === 'pending')
              <div class="d-flex gap-2 justify-content-end">
                <form action="{{ route('admin.qualifications.respond', $q->id) }}" method="POST">
                  @csrf
                  <input type="hidden" name="status" value="approved">
                  <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">Approve</button>
                </form>
                
                <form action="{{ route('admin.qualifications.respond', $q->id) }}" method="POST">
                  @csrf
                  <input type="hidden" name="status" value="rejected">
                  <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3">Reject</button>
                </form>
              </div>
              @else
              <span class="text-muted small">Reviewed</span>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-1 d-block mb-3"></i>
              No qualification requests found.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection