<?php
// Add Category Modal
?>
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white justify-content-center">
                <h5 class="modal-title text-center" id="addCategoryModalLabel">Add New Category</h5>
                <button type="button" class="btn-close btn-close-white position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addCategoryForm" action="" method="POST">
                <div class="modal-body text-center">
                    <div class="mb-3 text-start mx-auto" style="max-width: 400px;">
                        <label for="category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-center" id="category_name" name="category_name" required>
                    </div>
                    <div class="mb-3 text-start mx-auto" style="max-width: 400px;">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control text-center" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-success">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white justify-content-center">
                <h5 class="modal-title text-center" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close btn-close-white position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm" action="" method="POST">
                <input type="hidden" id="edit_category_id" name="category_id">
                <div class="modal-body text-center">
                    <div class="mb-3 text-start mx-auto" style="max-width: 400px;">
                        <label for="edit_category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-center" id="edit_category_name" name="category_name" required>
                    </div>
                    <div class="mb-3 text-start mx-auto" style="max-width: 400px;">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control text-center" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_category" class="btn btn-warning">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteCategoryForm" action="" method="POST">
                <input type="hidden" id="delete_category_id" name="category_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the category: <strong id="delete_category_name"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedbackModalLabel">Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div id="feedbackIcon"></div>
                <h4 id="feedbackTitle" class="mt-3"></h4>
                <p id="feedbackMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
</script>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to log out?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="changePasswordForm">
          <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required>
          </div>
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required>
          </div>
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
          </div>
          <div id="changePasswordFeedback" class="mb-2"></div>
          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-success">Change Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
<script>


// Handle edit button click to populate the edit modal
document.addEventListener('DOMContentLoaded', function() {
    // Edit Category Modal Handler
    document.querySelectorAll('.edit-category').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_description').value = description || '';
            
            const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            editModal.show();
        });
    });

    // Delete Category Modal Handler
    document.querySelectorAll('.delete-category').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            document.getElementById('delete_category_id').value = id;
            document.getElementById('delete_category_name').textContent = name;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
            deleteModal.show();
        });
    });

    // Show feedback modal if there are messages
    <?php if (isset($_SESSION['success'])): ?>
        showFeedbackModal(true, '<?php echo addslashes($_SESSION['success']); ?>');
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        showFeedbackModal(false, '<?php echo addslashes($_SESSION['error']); ?>');
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});

function showFeedbackModal(isSuccess, message) {
    const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    const feedbackIcon = document.getElementById('feedbackIcon');
    const feedbackTitle = document.getElementById('feedbackTitle');
    const feedbackMessage = document.getElementById('feedbackMessage');
    
    if (isSuccess) {
        feedbackIcon.innerHTML = '<i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>';
        feedbackTitle.textContent = 'Success!';
        feedbackTitle.className = 'text-success';
    } else {
        feedbackIcon.innerHTML = '<i class="fas fa-times-circle text-danger" style="font-size: 3rem;"></i>';
        feedbackTitle.textContent = 'Error!';
        feedbackTitle.className = 'text-danger';
    }
    
    feedbackMessage.textContent = message;
    feedbackModal.show();
}
</script>