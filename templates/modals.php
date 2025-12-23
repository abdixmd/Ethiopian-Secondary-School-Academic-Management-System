<!-- Reusable Modals -->

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to proceed with this action? This cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmModalButton" class="btn btn-danger">Confirm</a>
      </div>
    </div>
  </div>
</div>

<script>
// JavaScript to handle the confirmation modal dynamically
document.addEventListener('DOMContentLoaded', function() {
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        confirmModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Button that triggered the modal
            const confirmUrl = button.getAttribute('data-href'); // Get URL from data-href attribute
            const confirmButton = document.getElementById('confirmModalButton');
            confirmButton.setAttribute('href', confirmUrl);
        });
    }
});
</script>
