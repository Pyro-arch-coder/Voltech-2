// Simple Budget Handler
class BudgetHandler {
    constructor(projectId) {
        this.projectId = projectId;
        this.budgetData = null;
        this.init();
    }

    async init() {
        await this.loadBudgetData();
        this.setupEventListeners();
    }

    async loadBudgetData() {
        try {
            const response = await fetch(`get_budget.php?project_id=${this.projectId}`);
            if (!response.ok) {
                throw new Error('Failed to load budget data');
            }
            
            this.budgetData = await response.json();
            this.updateBudgetUI();
            
        } catch (error) {
            console.error('Error loading budget data:', error);
            alert('Failed to load budget data. Please try again later.');
        }
    }

    // Update the next button state based on budget status
    updateNextButtonState(isEnabled) {
        // Find all next buttons in the current step
        const nextButtons = document.querySelectorAll('.next-step');
        nextButtons.forEach(button => {
            // Only update buttons in the current active step
            const currentStep = document.querySelector('.step-content:not(.d-none)');
            if (currentStep && currentStep.contains(button)) {
                button.disabled = !isEnabled;
                if (isEnabled) {
                    button.classList.remove('btn-secondary');
                    button.classList.add('btn-primary');
                } else {
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-secondary');
                }
            }
        });
    }

    updateBudgetUI() {
        if (!this.budgetData) {
            // If no budget data, disable next button
            this.updateNextButtonState(false);
            return;
        }

        // Update the budget amount
        const budgetElement = document.getElementById('projectTotal');
        // Handle when budget is an object (from PHP) or string/null
        let budgetValue = null;
        if (typeof this.budgetData.budget === 'object' && this.budgetData.budget !== null) {
            // If PHP returns {budget: {...}}
            budgetValue = this.budgetData.budget.budget;
        } else if (typeof this.budgetData.budget === 'string' || typeof this.budgetData.budget === 'number') {
            budgetValue = this.budgetData.budget;
        }

        if (budgetElement && budgetValue && !isNaN(parseFloat(budgetValue))) {
            budgetElement.textContent = `₱${parseFloat(budgetValue).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        } else if (budgetElement && this.budgetData.budget && this.budgetData.budget.status === 'Not Uploaded') {
            budgetElement.textContent = 'Not Uploaded';
        } else if (budgetElement && !budgetValue) {
            budgetElement.textContent = '₱0.00';
        }

        // Update status
        this.updateStatusUI();
    }

    updateStatusUI() {
        // Handle both structures
        let status = 'pending';
        if (typeof this.budgetData.budget === 'object' && this.budgetData.budget !== null && this.budgetData.budget.status) {
            status = this.budgetData.budget.status.toLowerCase();
        } else if (this.budgetData.status) {
            status = this.budgetData.status.toLowerCase();
        }
        
        // Create status badge
        const statusElement = document.createElement('span');
        statusElement.className = `badge bg-${status === 'approved' ? 'success' : status === 'rejected' ? 'danger' : status === 'not uploaded' ? 'secondary' : 'warning'}`;
        statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);

        // Clear previous status
        const statusContainer = document.querySelector('.budget-status');
        if (statusContainer) {
            statusContainer.innerHTML = '';
            statusContainer.appendChild(statusElement);
        }

        // Update button states based on status
        const approveBtn = document.getElementById('approveBudget');
        const rejectBtn = document.getElementById('rejectBudget');
        
        if (status === 'approved') {
            if (approveBtn) {
                approveBtn.textContent = 'Approved';
                approveBtn.disabled = true;
                approveBtn.classList.remove('btn-primary');
                approveBtn.classList.add('btn-success');
            }
            if (rejectBtn) {
                rejectBtn.disabled = true;
            }
            // Enable next button when budget is approved
            this.updateNextButtonState(true);
        } else if (status === 'rejected') {
            if (approveBtn) {
                approveBtn.disabled = true;
            }
            if (rejectBtn) {
                rejectBtn.disabled = true;
                rejectBtn.textContent = 'Rejected';
            }
            // Disable next button when budget is rejected
            this.updateNextButtonState(false);
        } else {
            // For any other status (pending, not uploaded, etc.)
            this.updateNextButtonState(false);
        }
    }

    setupEventListeners() {
        // Budget Approval/Rejection
        document.getElementById('approveBudget')?.addEventListener('click', () => this.handleBudgetAction('approve'));
        document.getElementById('rejectBudget')?.addEventListener('click', () => this.handleBudgetAction('reject'));
    }

    async handleBudgetAction(action) {
        if (!confirm(`Are you sure you want to ${action} this budget?`)) return;
        
        const approveBtn = document.getElementById('approveBudget');
        const rejectBtn = document.getElementById('rejectBudget');
        
        // Disable buttons during processing
        if (approveBtn) approveBtn.disabled = true;
        if (rejectBtn) rejectBtn.disabled = true;
        
        try {
            const response = await fetch('update_budget_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `project_id=${this.projectId}&status=${action === 'approve' ? 'Approved' : 'Rejected'}`
            });
            
            if (!response.ok) throw new Error('Failed to update budget status');
            
            // Reload budget data
            await this.loadBudgetData();
            
            // Show enhanced success modal
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            const successIcon = document.getElementById('successIcon');
            const successTitle = document.getElementById('successTitle');
            const successMessage = document.getElementById('successMessage');
            
            // Update modal content based on action
            if (action === 'approve') {
                successIcon.className = 'fas fa-check-circle text-success mb-3';
                successTitle.textContent = 'Budget Approved!';
                successMessage.innerHTML = 'The budget has been successfully approved.';
            } else {
                successIcon.className = 'fas fa-times-circle text-danger mb-3';
                successTitle.textContent = 'Budget Rejected!';
                successMessage.innerHTML = 'The budget has been rejected and removed.';
            }
            
            // Show the modal
            successModal.show();
            
            // Reload the page after a short delay
            setTimeout(() => {
                successModal.hide();
                window.location.reload();
            }, 2000);
            
        } catch (error) {
            console.error(`Error ${action}ing budget:`, error);
            alert(`Failed to ${action} budget. Please try again.`);
            
            // Re-enable buttons on error
            if (approveBtn) approveBtn.disabled = false;
            if (rejectBtn) rejectBtn.disabled = false;
        }
    }
}

// Initialize when the page loads
document.addEventListener('DOMContentLoaded', () => {
    const projectId = window.currentProjectId; // This should be set in your PHP file
    if (projectId) {
        window.budgetHandler = new BudgetHandler(projectId);
    }
});