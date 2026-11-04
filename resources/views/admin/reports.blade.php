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
                {{ $report->reportable_type === \App\Models\CommunityBanner::class ? 'Community banner' : ($report->reportable_type === \App\Models\SessionRating::class ? 'Learner review' : class_basename($report->reportable_type)) }}
              </span>
            </td>
            <td>
              @if($report->reportable)
                @php
                  $item = $report->reportable;
                  $isAccount = $report->reportable_type === \App\Models\User::class;
                  $isBanner = $item instanceof \App\Models\CommunityBanner;
                  $isReview = $item instanceof \App\Models\SessionRating;
                  $author = $isAccount ? $item : ($isReview ? $item->learner : ($isBanner ? $item->uploader : ($item->sender ?? $item->user ?? null)));
                  $preview = $isAccount ? 'Reported account' : ($isReview ? $item->stars.'/5 — '.($item->comment ?? 'No written review') : ($isBanner ? 'Reported community banner' : ($item->body ?? $item->title ?? '[Message attachment or empty message]')));
                  $community = $isBanner || $report->reportable_type === \App\Models\Post::class
                    ? $item->community
                    : ($report->reportable_type === \App\Models\Comment::class ? ($item->post->community ?? null) : null);
                @endphp
                <div class="small fw-medium text-truncate mb-1" style="max-width: 250px;" title="{{ $preview }}">
                  “{{ $preview }}”
                </div>
                @if($isBanner)
                  <a href="{{ route('community.banner.show', $item) }}" target="_blank" rel="noopener noreferrer" class="d-inline-block mb-2">
                    <img src="{{ route('community.banner.show', $item) }}" alt="Reported community banner" class="rounded" style="width:140px;height:70px;object-fit:cover"><span class="d-block small">View reported image</span>
                  </a>
                  @if($item->removed_at)<p class="small text-muted mb-2">This banner was already replaced or removed.</p>@endif
                @endif
                
                <div class="text-muted d-flex flex-column gap-1" style="font-size: 0.75rem;">
                  <div>
                    <i class="bi bi-person-circle me-1"></i> {{ $isAccount ? 'Account' : 'Author' }}: {{ $author->first_name ?? 'Unknown' }} {{ $author->last_name ?? '' }}
                  </div>
                  
                  <!-- Dynamically fetch the community based on report type -->
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
                  
                  @if(!($report->reportable instanceof \App\Models\User))
                  <form action="{{ route('admin.reports.destroyContent', $report->id) }}" method="POST" class="delete-content-form" @if($report->reportable instanceof \App\Models\CommunityBanner) data-confirm-title="Remove reported banner" data-confirm-text="Remove this reported banner and resolve the report? The community and any replacement banner are kept." @endif>
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-danger btn-sm rounded-2 px-3 delete-content-btn">
                      {{ $report->reportable instanceof \App\Models\SessionRating ? 'Remove review' : ($report->reportable instanceof \App\Models\CommunityBanner ? ($report->reportable->removed_at ? 'Resolve banner report' : 'Remove banner') : 'Delete Content') }}
                    </button>
                  </form>
                  @endif
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
                $form.data('confirm-text') || 'Are you sure you want to delete this content? This action will permanently remove it from the community and automatically mark this report as resolved.',
                $form.data('confirm-title') || 'Delete Content'
            );
            
            if (result.isConfirmed) {
                $form.submit();
            }
        });
    });
  </script>

</div> 
@endsection
