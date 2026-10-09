@extends('layouts.dashboard')
@section('title', 'Admin - Manage Reports')

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0 rounded-4">
    
    <div class="card-header bg-dark text-white rounded-top-4">
      <h5 class="card-title mb-0"><i class="bi bi-flag-fill me-2"></i> Manage Reports</h5>
    </div>
    
    <div class="card-body p-0 table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table">
          <tr>
            <th class="ps-4">Status</th>
            <th>Type</th>
            <th>Content Preview</th>
            <th>Reported By</th>
            <th>Reason</th>
            <th class="text-end pe-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reports as $report)
          <tr>
            <td class="ps-4">
              @if($report->status === 'pending')
                <span class="badge bg-warning text-dark px-2 py-1 rounded-pill fw-medium">Pending</span>
              @elseif($report->status === 'resolved')
                <span class="badge bg-success px-2 py-1 rounded-pill fw-medium">Resolved</span>
              @else
                <span class="badge bg-secondary px-2 py-1 rounded-pill fw-medium">Dismissed</span>
              @endif
            </td>
            <td>
              <span class="badge border text-secondary px-2 py-1 rounded-pill fw-medium">
                {{ class_basename($report->reportable_type) }}
              </span>
            </td>
            <td>
              @if($report->reportable)
                <div class="small fw-medium text-truncate mb-1" style="max-width: 250px;" title="{{ $report->reportable->body }}">
                  "{{ $report->reportable->body }}"
                </div>
                
                <div class="text-muted d-flex flex-column gap-1" style="font-size: 0.75rem;">
                  <div>
                    <i class="bi bi-person-circle me-1"></i> Author: {{ $report->reportable->user->first_name ?? 'Unknown' }}
                  </div>
                  
                  <!-- Dynamically fetch the community based on report type -->
                  @php
                    $community = $report->reportable_type === 'App\Models\Post' 
                        ? $report->reportable->community 
                        : ($report->reportable->post->community ?? null);
                  @endphp
                  
                  @if($community)
                  <div>
                    <i class="bi bi-diagram-3 me-1"></i> Community: <span class="fw-medium">{{ $community->name }}</span>
                  </div>
                  @endif
                </div>
              @else
                <span class="text-muted fst-italic small">Content already deleted</span>
              @endif
            </td>
            <td>
              <div class="small">{{ $report->reporter->first_name }} {{ $report->reporter->last_name }}</div>
              <div class="text-muted" style="font-size: 0.75rem;">{{ $report->created_at->format('M d, Y g:i A') }}</div>
            </td>
            <td>
              <div class="small fw-bold">{{ $report->reason }}</div>
              @if($report->details)
                <div class="text-muted mt-1 p-2 bg-body-tertiary rounded border text-break" style="font-size: 0.75rem; max-width: 280px; max-height: 80px; overflow-y: auto;">
                  {{ $report->details }}
                </div>
              @endif
            </td>
            <td class="text-end pe-4">
              @if($report->status === 'pending' && $report->reportable)
                <div class="d-flex gap-2 justify-content-end">
                  
                  <form action="{{ route('admin.reports.dismiss', $report->id) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-secondary btn-sm rounded-2 px-3">
                      Dismiss
                    </button>
                  </form>
                  
                 <!-- Delete Content Button (Triggers SweetAlert) -->
                  <form action="{{ route('admin.reports.destroyContent', $report->id) }}" method="POST" class="delete-content-form">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-danger btn-sm rounded-2 px-3 delete-content-btn">
                      Delete Content
                    </button>
                  </form>
                </div>
              @else
                <span class="text-muted small">Reviewed</span>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-shield-check fs-1 d-block mb-3"></i>
              No reports found. All good!
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  
  @if($reports->hasPages())
  <div class="mt-4">
    {{ $reports->links() }}
  </div>
  @endif
</div>

<!-- SweetAlert Confirmation Script -->
  <script type="module">
    $(document).ready(function() {
        $('.delete-content-btn').on('click', async function(e) {
            e.preventDefault();
            
            const $form = $(this).closest('form');
            
            const result = await window.confirmAction(
                'Are you sure you want to delete this content? This action will permanently remove it from the community and automatically mark this report as resolved.',
                'Delete Content'
            );
            
            if (result.isConfirmed) {
                $form.submit();
            }
        });
    });
  </script>

</div> 
@endsection