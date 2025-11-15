<!-- Logout Confirmation Modal -->
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

    
    <!-- Supplier Materials Modal -->
    <div class="modal fade" id="supplierMaterialsModal" tabindex="-1" aria-labelledby="supplierMaterialsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierMaterialsModalLabel">Supplier Materials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                <input type="hidden" id="supplierId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-primary" id="supplierNameDisplay"></h6>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="materialsTable">
                            <thead class="bg-info text-white">
                                <tr>
                                    <th>Material Name</th>
                                    <th>Brand</th>
                                    <th>Unit</th>
                                    <th>Price</th>
                                    <th>Specification</th>
                                    <th>Lead Time</th>
                                </tr>
                            </thead>
                            <tbody id="materialsTableBody">
                                <!-- Materials will be loaded here via AJAX -->
                            </tbody>
                        </table>
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted" id="materialsPaginationInfo">
                                Showing <span id="startItem">0</span> to <span id="endItem">0</span> of <span id="totalItems">0</span> items
                            </div>
                            <nav aria-label="Materials pagination">
                                <ul class="pagination pagination-sm mb-0" id="materialsPagination">
                                    <li class="page-item disabled" id="prevPage">
                                        <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                                    </li>
                                    <li class="page-item disabled" id="nextPage">
                                        <a class="page-link" href="#">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div class="text-muted" id="pageInfo">Page 1 of 1</div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

       <!-- Add Supplier Modal (same as before, but right-aligned buttons) -->
                <!-- Edit Supplier Modal (structure like Add, fields prefilled by JS) -->
                <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <form action="update_supplier.php" method="POST" novalidate>
                        <input type="hidden" name="edit_supplier_id" id="edit_supplier_id">
                        <div class="modal-body">
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-group mb-3">
                                <label>Supplier Name *</label>
                                <input type="text" class="form-control" name="supplier_name" id="edit_supplier_name" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.&]+" title="Supplier name can only contain letters, numbers, spaces, hyphens, dots, and ampersands">
                                <div class="invalid-feedback">Please enter a valid supplier name (2-100 characters).</div>
                              </div>
                              <div class="form-group mb-3">
                                <label>Contact Person First Name</label>
                                <input type="text" class="form-control" name="contact_firstname" id="edit_contact_firstname" maxlength="50" pattern="[A-Za-z\s\.]+" title="First name can only contain letters, spaces, and dots">
                                <div class="invalid-feedback">Please enter a valid first name.</div>
                              </div>
                              <div class="form-group mb-3">
                                <label>Contact Person Last Name</label>
                                <input type="text" class="form-control" name="contact_lastname" id="edit_contact_lastname" maxlength="50" pattern="[A-Za-z\s\.]+" title="Last name can only contain letters, spaces, and dots">
                                <div class="invalid-feedback">Please enter a valid last name.</div>
                              </div>
                              <div class="form-group mb-3">
                                <label>Contact Number</label>
                                <input type="tel" class="form-control" name="contact_number" id="edit_contact_number" maxlength="20" pattern="[\d\s\-\+\(\)]+" title="Contact number can only contain digits, spaces, hyphens, plus signs, and parentheses">
                                <div class="invalid-feedback">Please enter a valid contact number.</div>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group mb-3">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email" maxlength="100">
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                              </div>
                              <div class="form-group mb-3">
                                <label>Address</label>
                                <textarea class="form-control" name="address" id="edit_address" rows="2" maxlength="500" title="Address can contain up to 500 characters"></textarea>
                                <div class="invalid-feedback">Please enter a valid address (max 500 characters).</div>
                              </div>
                              <div class="form-group mb-3">
                                <label>Status *</label>
                                <select class="form-control" name="status" id="edit_supplier_status" required>
                                  <option value="Active">Active</option>
                                  <option value="Inactive">Inactive</option>
                                </select>
                                <div class="invalid-feedback">Please select a status.</div>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer justify-content-end">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" name="edit_supplier" class="btn btn-success">Update Supplier</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <!-- Feedback Modal (Unified for Success/Error) with higher z-index -->
                <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true" style="z-index: 9999;">
                    <div class="modal-dialog modal-dialog-centered" style="z-index: 10000;">
                        <div class="modal-content text-center">
                            <div class="modal-body">
                                <span id="feedbackIcon" style="font-size: 3rem;"></span>
                                <h4 id="feedbackTitle"></h4>
                                <p id="feedbackMessage"></p>
                            </div>
                            <div class="modal-footer justify-content-center">
                                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="addSupplierForm" novalidate>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label>Supplier Name *</label>
                    <input type="text" class="form-control" name="supplier_name" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.&]+" title="Supplier name can only contain letters, numbers, spaces, hyphens, dots, and ampersands">
                    <div class="invalid-feedback">Please enter a valid supplier name (2-100 characters).</div>
                  </div>
                  <div class="form-group mb-3">
                    <label>Contact Person First Name</label>
                    <input type="text" class="form-control" name="contact_firstname" maxlength="50" pattern="[A-Za-z\s\.]+" title="First name can only contain letters, spaces, and dots">
                    <div class="invalid-feedback">Please enter a valid first name.</div>
                  </div>
                  <div class="form-group mb-3">
                    <label>Contact Person Last Name</label>
                    <input type="text" class="form-control" name="contact_lastname" maxlength="50" pattern="[A-Za-z\s\.]+" title="Last name can only contain letters, spaces, and dots">
                    <div class="invalid-feedback">Please enter a valid last name.</div>
                  </div>
                  <div class="form-group mb-3">
                    <label>Contact Number</label>
                    <input type="tel" class="form-control" name="contact_number" maxlength="20" pattern="[\d\s\-\+\(\)]+" title="Contact number can only contain digits, spaces, hyphens, plus signs, and parentheses">
                    <div class="invalid-feedback">Please enter a valid contact number.</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label>Email</label>
                    <input type="email" class="form-control" name="email" maxlength="100">
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                  </div>
                  <div class="form-group mb-3">
                    <label>Address *</label>
                    <div class="row g-2">
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_region" required><option value="">Select Region</option></select>
                        <div class="invalid-feedback">Please select a region.</div>
                      </div>
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_province" required disabled><option value="">Select Province</option></select>
                        <div class="invalid-feedback">Please select a province.</div>
                      </div>
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_city" required disabled><option value="">Select City/Municipality</option></select>
                        <div class="invalid-feedback">Please select a city/municipality.</div>
                      </div>
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_barangay" required disabled><option value="">Select Barangay</option></select>
                        <div class="invalid-feedback">Please select a barangay.</div>
                      </div>
                    </div>
                    <input type="hidden" name="address" id="add_address_hidden" required>
                  </div>
                  <div class="form-group mb-3">
                    <label>Status *</label>
                    <select class="form-control" name="status" required disabled>
                      <option value="Active" selected>Active</option>
                    </select>
                    <input type="hidden" name="status" value="Active">
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <div id="addSupplierFeedback" class="w-100 mb-2"></div>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="add_supplier" class="btn btn-success">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                Add Supplier
              </button>
            </div>
          </form>
          <script>
          // Handle add supplier form submission
          document.getElementById('addSupplierForm').addEventListener('submit', function(e) {
              e.preventDefault();
              
              const form = this;
              const submitBtn = form.querySelector('button[type="submit"]');
              const spinner = submitBtn.querySelector('.spinner-border');
              const feedbackDiv = document.getElementById('addSupplierFeedback');
              
              // Show loading state
              submitBtn.disabled = true;
              spinner.classList.remove('d-none');
              feedbackDiv.innerHTML = '';
              
              // Get form data
              const formData = new FormData(form);
              formData.append('add_supplier', '1');
              
              // Submit via AJAX
              fetch('add_supplier.php', {
                  method: 'POST',
                  body: formData
              })
              .then(response => response.json())
              .then(data => {
                  if (data.success) {
                      // Show success message
                      feedbackDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                      // Reload the page after 1.5 seconds
                      setTimeout(() => {
                          window.location.reload();
                      }, 1500);
                  } else {
                      // Show error message
                      feedbackDiv.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error adding supplier. Please try again.') + '</div>';
                      submitBtn.disabled = false;
                      spinner.classList.add('d-none');
                  }
              })
              .catch(error => {
                  console.error('Error:', error);
                  feedbackDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
                  submitBtn.disabled = false;
                  spinner.classList.add('d-none');
              });
          });
          </script>
        </div>
      </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="supplierName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>

    <!-- Export PDF Confirmation Modal (only one per page) -->
<div class="modal fade" id="exportSuppliersPdfModal" tabindex="-1" aria-labelledby="exportSuppliersPdfModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exportSuppliersPdfModalLabel">Export as PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to export the suppliers list and their materials as PDF?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmExportSuppliersPdf" class="btn btn-danger">Export</a>
      </div>
    </div>
  </div>
</div>