<?php if (isset($_GET['add_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> Material added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<div class="step-content d-none" id="step2">
    <h4 class="mb-4">Step 2: Cost Estimation</h4>
    <!-- Project Materials Section -->
    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-success text-white d-flex align-items-center">
            <span class="flex-grow-1">Project Materials</span>
            <button type="button" class="btn btn-light btn-sm ml-auto" id="addMaterialsBtn" data-bs-toggle="modal" data-bs-target="#addMaterialsModal">
                <i class="fas fa-plus-square me-1"></i> Add Materials
            </button>
            <button type="button" class="btn btn-light btn-sm ms-2" id="exportCostEstimationBtn">
                <i class="fas fa-file-export"></i> Export PDF
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="table-secondary">
                        <tr>
                            <th>No.</th>
                            <th>Name</th>
                            <th>Unit</th>
                            <th>Material Price</th>
                            <th>Labor/Other</th>
                            <th>Quantity</th>
                            <th>Supplier</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        require_once __DIR__ . '/../config.php';
                        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
                        $materials = [];
                        $total = 0;
                        $total_records = 0;
                        $records_per_page = 5;
                        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $offset = ($page - 1) * $records_per_page;
                        
                        if ($project_id) {
                            // Get total number of records and calculate grand total from ALL materials
                            $count_sql = "SELECT COUNT(*) as total, 
                                         COALESCE(SUM((pem.material_price + COALESCE(m.labor_other, 0)) * pem.quantity), 0) as grand_total 
                                         FROM project_estimating_materials pem
                                         LEFT JOIN materials m ON pem.material_id = m.id
                                         WHERE pem.project_id = $project_id";
                            $count_result = $con->query($count_sql);
                            $count_data = $count_result->fetch_assoc();
                            $total_records = $count_data['total'];
                            $grand_total = $count_data['grand_total'];
                            $total_pages = ceil($total_records / $records_per_page);
                            
                            // Get records for the current page
                            $sql = "SELECT pem.*, m.supplier_name, m.labor_other 
                                    FROM project_estimating_materials pem
                                    LEFT JOIN materials m ON pem.material_id = m.id
                                    WHERE pem.project_id = $project_id
                                    ORDER BY pem.id DESC
                                    LIMIT $offset, $records_per_page";
                                    
                            $result = $con->query($sql);
                            
                            if ($result && $result->num_rows > 0) {
                                $i = $offset + 1; // This will make the numbering continue from where the previous page left off
                                while ($row = $result->fetch_assoc()) {
                                    $materials[] = $row;
                                    echo '<tr>';
                                    echo '<td>' . $i++ . '</td>';
                                    echo '<td style="font-weight:bold;color:#222;">' . htmlspecialchars($row['material_name']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['unit']) . '</td>';
                                    echo '<td>' . number_format($row['material_price'], 2) . '</td>';
                                    echo '<td>' . (isset($row['labor_other']) ? number_format($row['labor_other'], 2) : '0.00') . '</td>';
                                    echo '<td>
                                            <div class="quantity-controls">
                                                <button type="button" class="btn btn-sm btn-outline-secondary quantity-decrease quantity-btn">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="form-control form-control-sm text-center mx-1 quantity-input" 
                                                       value="' . $row['quantity'] . '" min="1" step="1" 
                                                       data-pem-id="' . $row['id'] . '" 
                                                       data-price="' . $row['material_price'] . '" 
                                                       data-labor="' . (isset($row['labor_other']) ? $row['labor_other'] : '0') . '"
                                                       onchange="updateMaterialQuantity(' . $row['id'] . ', this.value)"
                                                       onkeydown="return event.key !== \'e\' && event.key !== \'E\' && event.key !== \'-\' && event.key !== \'+\';">
                                                <button type="button" class="btn btn-sm btn-outline-secondary quantity-increase quantity-btn">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                          </td>';
                                    echo '<td>' . (isset($row['supplier_name']) ? htmlspecialchars($row['supplier_name']) : 'N/A') . '</td>';
                                    echo '<td style="font-weight:bold;color:#222;">₱' . number_format((($row['material_price'] + (isset($row['labor_other']) ? $row['labor_other'] : 0)) * $row['quantity']), 2) . '</td>';
                                    echo '<td><button class="btn btn-danger btn-sm remove-material" onclick="removeMaterial(' . $row['id'] . ')"><i class="fas fa-trash"></i> Remove</button></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="9" class="text-center">No materials added</td></tr>';
                            }
                        } else {
                            echo '<tr><td colspan="9" class="text-center">No project selected</td></tr>';
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="7" class="text-end">Grand Total (All Materials)</th>
                            <th colspan="2" style="font-weight:bold; color:#222;" id="materialsTotal">₱<?= number_format($grand_total, 2) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($project_id && $total_records > $records_per_page): ?>
            <div class="card-footer bg-white">
                <nav aria-label="Materials pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?project_id=<?= $project_id ?>&page=<?= $page - 1 ?>#step2" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?project_id=<?= $project_id ?>&page=<?= $i ?>#step2"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?project_id=<?= $project_id ?>&page=<?= $page + 1 ?>#step2" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Navigation Buttons -->
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-secondary prev-step" data-prev="1">Previous</button>
        <button type="button" class="btn btn-primary next-step" data-next="3">Next <i class="fas fa-arrow-right"></i></button>
    </div>
</div>

<!-- Include the step2_estimation.js script -->
<script src="js/step2_estimation.js"></script>

<style>
/* Quantity controls styling */
.quantity-controls {
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantity-input {
    width: 80px !important;
    text-align: center;
    -moz-appearance: textfield;
}

.quantity-btn {
    min-width: 32px !important;
    padding: 0.25rem 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Hide number input arrows for all browsers */
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Firefox */
input[type=number] {
    -moz-appearance: textfield;
}
</style>