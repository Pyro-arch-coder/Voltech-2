 <!-- Add Material Modal -->
 <div class="modal fade" id="addMaterialModal" tabindex="-1" aria-labelledby="addMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMaterialModalLabel">Add Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="process_add_material.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Material Name *</label>
                                    <input type="text" class="form-control" name="material_name" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.]+" title="Material name can only contain letters, numbers, spaces, hyphens, and dots">
                                    <div class="invalid-feedback">Please enter a valid material name (2-100 characters).</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Brand *</label>
                                    <input type="text" class="form-control" name="brand" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.]+" placeholder="Enter brand name">
                                    <div class="invalid-feedback">Please enter a valid brand name (2-100 characters).</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Specification *</label>
                                    <textarea class="form-control" name="specification" required minlength="5" maxlength="500" rows="2" placeholder="Enter material specifications"></textarea>
                                    <div class="invalid-feedback">Please enter specifications (5-500 characters).</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Category *</label>
                                    <select class="form-control" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php 
                                        $userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';
                                        $stmt = $con->prepare("SELECT DISTINCT category FROM supplier_category WHERE email = ? AND category IS NOT NULL AND category != '' ORDER BY category");
                                        $stmt->bind_param("s", $userEmail);
                                        $stmt->execute();
                                        $categories = $stmt->get_result();
                                        while($cat = $categories->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                                        <?php endwhile; 
                                        $stmt->close();
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a category.</div>
                                </div>
                                <input type="hidden" name="quantity" value="99999999">
                                <div class="form-group mb-3">
                                    <label>Unit *</label>
                                    <select class="form-control" name="unit" required>
                                        <option value="">Select Unit</option>
                                        <option value="kg">Kilogram (kg)</option>
                                        <option value="g">Gram (g)</option>
                                        <option value="t">Ton (t)</option>
                                        <option value="m³">Cubic Meter (m³)</option>
                                        <option value="ft³">Cubic Feet (ft³)</option>
                                        <option value="L">Liter (L)</option>
                                        <option value="mL">Milliliter (mL)</option>
                                        <option value="m">Meter (m)</option>
                                        <option value="mm">Millimeter (mm)</option>
                                        <option value="cm">Centimeter (cm)</option>
                                        <option value="ft">Feet (ft)</option>
                                        <option value="in">Inch (in)</option>
                                        <option value="pcs">Pieces (pcs)</option>
                                        <option value="bndl">Bundle (bndl)</option>
                                        <option value="rl">Roll (rl)</option>
                                        <option value="set">Set</option>
                                        <option value="sack/bag">Sack/Bag</option>
                                        <option value="m²">Square Meter (m²)</option>
                                        <option value="ft²">Square Feet (ft²)</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a unit.</div>
                                </div>

                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Material Price *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0.01" max="999999.99" class="form-control" name="material_price" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid price (greater than 0).</div>
                                </div>
                                <input type="hidden" min="0" max="999999" class="form-control" name="low_stock_threshold" value="10">
                                <div class="form-group mb-3">
                                    <label>Lead Time (Days)</label>
                                    <input type="number" min="0" max="365" class="form-control" name="lead_time" value="0">
                                    <div class="invalid-feedback">Lead time must be between 0 and 365 days.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Labor/Other Cost</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0" max="999999.99" class="form-control" name="labor_other" value="0">
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid cost (0-999,999.99).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Material Modal -->
    <div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMaterialModalLabel">Edit Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="process_edit_material.php" method="POST">
                    <input type="hidden" id="edit_material_id" name="material_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Material Name *</label>
                                    <input type="text" class="form-control" name="material_name" id="edit_material_name" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.]+" title="Material name can only contain letters, numbers, spaces, hyphens, and dots">
                                    <div class="invalid-feedback">Please enter a valid material name (2-100 characters).</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Brand</label>
                                    <input type="text" class="form-control" name="brand" id="edit_brand" maxlength="100" placeholder="Enter brand name">
                                </div>
                                <div class="form-group mb-3">
                                    <label>Specification</label>
                                    <textarea class="form-control" name="specification" id="edit_specification" rows="2" maxlength="500" placeholder="Enter material specifications"></textarea>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Quantity</label>
                                    <input type="number" min="0" max="999999" class="form-control" name="quantity" id="edit_quantity" value="0">
                                    <div class="invalid-feedback">Quantity must be between 0 and 999,999.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Unit *</label>
                                    <select class="form-control" name="unit" id="edit_unit" required>
                                        <option value="">Select Unit</option>
                                        <option value="kg">Kilogram (kg)</option>
                                        <option value="g">Gram (g)</option>
                                        <option value="t">Ton (t)</option>
                                        <option value="m³">Cubic Meter (m³)</option>
                                        <option value="ft³">Cubic Feet (ft³)</option>
                                        <option value="L">Liter (L)</option>
                                        <option value="mL">Milliliter (mL)</option>
                                        <option value="m">Meter (m)</option>
                                        <option value="mm">Millimeter (mm)</option>
                                        <option value="cm">Centimeter (cm)</option>
                                        <option value="ft">Feet (ft)</option>
                                        <option value="in">Inch (in)</option>
                                        <option value="pcs">Pieces (pcs)</option>
                                        <option value="bndl">Bundle (bndl)</option>
                                        <option value="rl">Roll (rl)</option>
                                        <option value="set">Set</option>
                                        <option value="sack/bag">Sack/Bag</option>
                                        <option value="m²">Square Meter (m²)</option>
                                        <option value="ft²">Square Feet (ft²)</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a unit.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Category *</label>
                                    <?php
                                        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
                                            die("Unauthorized access");
                                        }

                                        $userEmail = $_SESSION['email'];

                                        $sql = "SELECT * FROM supplier_category WHERE email = ?";
                                        $stmt = $con->prepare($sql);
                                        $stmt->bind_param("s", $userEmail);
                                        $stmt->execute();
                                        $result = $stmt->get_result();

                                        $categories = [];
                                        while ($row = $result->fetch_assoc()) {
                                            $categories[] = $row;
                                        }
                                    ?>
                                    <select name="category" class="form-control" required>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                                <?php echo htmlspecialchars($cat['category']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a category.</div>
                                </div>

                                <div class="form-group mb-3">
                                    <label>Status</label>
                                    <select class="form-control" name="status" id="edit_material_status">
                                        <option value="Available">Available</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Material Price *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0.01" max="999999.99" class="form-control" name="material_price" id="edit_material_price" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid price (greater than 0).</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Low Stock Threshold</label>
                                    <input type="number" min="0" max="999999" class="form-control" name="low_stock_threshold" id="edit_low_stock_threshold" value="10">
                                    <div class="invalid-feedback">Low stock threshold must be between 0 and 999,999.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Lead Time (Days)</label>
                                    <input type="number" min="0" max="365" class="form-control" name="lead_time" id="edit_lead_time" value="0">
                                    <div class="invalid-feedback">Lead time must be between 0 and 365 days.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Labor/Other Cost</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0" max="999999.99" class="form-control" name="labor_other" id="edit_labor_other" value="0">
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid cost (0-999,999.99).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Material Modal -->
    <div class="modal fade" id="viewMaterialModal" tabindex="-1" aria-labelledby="viewMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewMaterialModalLabel">Material Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5 class="fw-bold">Basic Information</h5>
                            <hr>
                            <div class="mb-2">
                                <span class="fw-bold">Material Name:</span>
                                <span id="view_material_name" class="ms-2"></span>
                            </div>
                            <div class="mb-2">
                                <span class="fw-bold">Brand:</span>
                                <span id="view_brand" class="ms-2"></span>
                            </div>
                            <div class="mb-2">
                                <span class="fw-bold">Category:</span>
                                <span id="view_category" class="ms-2"></span>
                            </div>
                            <div class="mb-2">
                                <span class="fw-bold">Status:</span>
                                <span id="view_status" class="ms-2"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 class="fw-bold">Pricing & Inventory</h5>
                            <hr>
                            <div class="mb-2">
                                <span class="fw-bold">Quantity:</span>
                                <span id="view_quantity" class="ms-2"></span>
                                <span id="view_unit"></span>
                            </div>
                            <div class="mb-2">
                                <span class="fw-bold">Material Price:</span>
                                <span id="view_material_price" class="ms-2"></span>
                            </div>
                            <div class="mb-2">
                                <span class="fw-bold">Labor/Other Cost:</span>
                                <span id="view_labor_other" class="ms-2"></span>
                            </div>
                            <div class="mb-2">
                                <span class="fw-bold">Low Stock Threshold:</span>
                                <span id="view_low_stock_threshold" class="ms-2"></span>
                            </div>
                            <div class="mb-2">
                                <span class="fw-bold">Lead Time:</span>
                                <span id="view_lead_time" class="ms-2"></span> days
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h5 class="fw-bold">Specifications</h5>
                            <hr>
                            <div id="view_specification" class="bg-light p-3 rounded">
                                <!-- Specifications will be displayed here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

        <!-- Feedback Modal (Unified for Success/Error) -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-body p-4">
                    <div class="mb-3" id="feedbackIcon">
                        <!-- Icon will be inserted here by JavaScript -->
                    </div>
                    <h5 class="mb-3" id="feedbackTitle"><!-- Title will be inserted here --></h5>
                    <p class="mb-0" id="feedbackMessage"><!-- Message will be inserted here --></p>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="feedbackMessage" class="text-center"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteMaterialModal" tabindex="-1" aria-labelledby="deleteMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteMaterialModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this material? This action cannot be undone.</p>
                    <p class="fw-bold mb-0">Material: <span id="delete_material_name"></span></p>
                </div>
                <div class="modal-footer">
                    <form action="process_delete_material.php" method="POST">
                        <input type="hidden" name="material_id" id="delete_material_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Material</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
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
