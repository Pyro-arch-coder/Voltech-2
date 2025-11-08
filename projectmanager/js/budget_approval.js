document.addEventListener('DOMContentLoaded', function() {
    const requestBudgetBtn = document.getElementById('requestBudgetBtn');
    const budgetInput = document.getElementById('budgetAmount');
    const budgetError = document.getElementById('budgetError');

    const minFromAttr = budgetInput ? parseInt(budgetInput.getAttribute('data-min-budget') || '0', 10) : 0;
    const MIN_BUDGET = (Number.isFinite(minFromAttr) && minFromAttr > 0) ? minFromAttr : 100000;
    const MAX_BUDGET = 100000000;
    const MAX_LENGTH = 9; // 9 digits max

    if (requestBudgetBtn && budgetInput) {
        // Validate on input
        budgetInput.addEventListener('input', function() {
            let value = this.value.replace(/[^0-9]/g, ''); // numbers only

            // Limit input to 9 digits
            if (value.length > MAX_LENGTH) {
                value = value.substring(0, MAX_LENGTH);
            }

            this.value = value;

            if (!value) {
                requestBudgetBtn.disabled = true;
                budgetError.style.display = "none";
                return;
            }

            let budget = parseInt(value, 10);

            if (budget < MIN_BUDGET) {
                budgetError.textContent = `Minimum budget is ₱${MIN_BUDGET.toLocaleString()}`;
                budgetError.style.display = "block";
                requestBudgetBtn.disabled = true;
            } else if (budget > MAX_BUDGET) {
                budgetError.textContent = `Maximum budget is ₱${MAX_BUDGET.toLocaleString()}`;
                budgetError.style.display = "block";
                requestBudgetBtn.disabled = true;
            } else {
                budgetError.style.display = "none";
                requestBudgetBtn.disabled = false;
            }
        });

        // Handle budget submission
        requestBudgetBtn.addEventListener('click', function() {
            const budget = parseInt(budgetInput.value.trim(), 10);
            const projectId = document.querySelector('input[name="project_id"]')?.value;

            if (!projectId) {
                console.error('Project ID not found');
                return;
            }

            if (budget < MIN_BUDGET || budget > MAX_BUDGET) {
                alert(`Budget must be between ₱${MIN_BUDGET.toLocaleString()} and ₱${MAX_BUDGET.toLocaleString()}`);
                return;
            }

            const originalBtnText = requestBudgetBtn.innerHTML;
            requestBudgetBtn.disabled = true;
            requestBudgetBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';

            const formData = new FormData();
            formData.append('project_id', projectId);
            formData.append('budget', budget);

            fetch('save_budget_approval.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Budget approval request submitted successfully!');
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Failed to submit budget approval');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + (error.message || 'Failed to process request'));
                requestBudgetBtn.disabled = false;
                requestBudgetBtn.innerHTML = originalBtnText;
            });
        });
    }
});
