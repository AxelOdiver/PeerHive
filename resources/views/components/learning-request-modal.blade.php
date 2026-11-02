<x-modal id="swapModal" title="Connect with a learning partner">
  <form id="swapRequestForm">
    <input type="hidden" id="swapUserId" name="user_id">
    <p id="learningPeerName" class="fw-semibold"></p>
    <label class="form-label" for="learningRequestType">What would you like to do?</label>
    <select id="learningRequestType" name="request_type" class="form-select mb-2">
      <option value="learning">Request learning help</option>
      <option value="skill_swap">Propose a skill swap</option>
    </select>
    <p class="small text-muted" id="learningRequestHint">You do not need a teaching qualification to learn. Your tutor must be verified for the selected subject.</p>
    <div class="mb-3">
      <label class="form-label" for="requestedSubject">Topic I want to learn</label>
      <select id="requestedSubject" name="requested_subject" class="form-select" required disabled></select>
    </div>
    <div id="offeredSubjectWrap" class="mb-3" hidden>
      <label class="form-label" for="offeredSubject">In return, I can teach this topic</label>
      <select id="offeredSubject" name="offered_subject" class="form-select" disabled></select>
      <a class="small" href="{{ route('profile') }}#teaching-verification">Apply for Teaching Verification</a>
    </div>
    <label class="form-label" for="swapRequestMessage">Message (optional)</label>
    <textarea class="form-control mb-3" id="swapRequestMessage" name="message" rows="3" maxlength="2000"></textarea>
    <p id="learningRequestStatus" class="small" role="status" aria-live="polite"></p>
    <button type="button" id="retryLearningOptions" class="btn btn-sm btn-outline-secondary" hidden>Retry loading subjects</button>
  </form>
  <x-slot:footer>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-primary" id="sendSwapRequestBtn" form="swapRequestForm" disabled>Send Request</button>
  </x-slot:footer>
</x-modal>
