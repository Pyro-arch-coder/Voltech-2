<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <span id="feedbackIcon" style="font-size: 3rem;"></span>
        <h4 id="feedbackTitle"></h4>
        <p id="feedbackMessage"></p>
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Warehouse Modal -->
<div class="modal fade" id="addWarehouseModal" tabindex="-1" aria-labelledby="addWarehouseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addWarehouseModalLabel">Add Warehouse</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" novalidate>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group mb-3">
                <label>Warehouse *</label>
                <input type="text" class="form-control" name="warehouse" placeholder="Warehouse" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.&]+" title="Warehouse name can only contain letters, numbers, spaces, hyphens, dots, and ampersands">
                <div class="invalid-feedback">Please enter a valid warehouse name (2-100 characters).</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer justify-content-end">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_warehouse" class="btn btn-success">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Warehouse Modal -->
<div class="modal fade" id="editWarehouseModal" tabindex="-1" aria-labelledby="editWarehouseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editWarehouseModalLabel">Edit Warehouse</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="update_warehouse_material.php" novalidate>
        <input type="hidden" name="id" id="editWarehouseId">
        <div class="modal-body">
          <div class="mb-3">
            <label>Warehouse Name</label>
            <input type="text" class="form-control" name="warehouse" id="editWarehouseName" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteWarehouseModal" tabindex="-1" aria-labelledby="deleteWarehouseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteWarehouseModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="delete_warehouse.php" novalidate>
        <input type="hidden" name="id" id="deleteWarehouseId">
        <div class="modal-body">
          <p>Are you sure you want to delete <strong id="deleteWarehouseName"></strong>?</p>
          <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Handle edit button clicks
  document.querySelectorAll('.edit-warehouse-btn').forEach(button => {
    button.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const name = this.getAttribute('data-name');
      
      // Set values in the edit modal
      document.getElementById('editWarehouseId').value = id;
      document.getElementById('editWarehouseName').value = name;
      document.getElementById('editWarehouseModalLabel').textContent = 'Edit Warehouse: ' + name;
      
      // Show the edit modal
      const editModal = new bootstrap.Modal(document.getElementById('editWarehouseModal'));
      editModal.show();
    });
  });

  // Handle delete button clicks
  document.querySelectorAll('.delete-warehouse-btn').forEach(button => {
    button.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const name = this.getAttribute('data-name');
      
      // Set values in the delete modal
      document.getElementById('deleteWarehouseId').value = id;
      document.getElementById('deleteWarehouseName').textContent = name;
      
      // Show the delete confirmation modal
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteWarehouseModal'));
      deleteModal.show();
    });
  });
});
</script>