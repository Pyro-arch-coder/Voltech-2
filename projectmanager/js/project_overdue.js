document.addEventListener('DOMContentLoaded', function() {
    // This will run when any element with class 'view-details' is clicked
    document.addEventListener('click', function(e) {
        if (e.target && (e.target.closest('.view-details') || e.target.classList.contains('view-details'))) {
            e.preventDefault();
            const button = e.target.closest('.view-details') || e.target;
            const projectId = button.getAttribute('data-project-id');
            
            if (projectId) {
                checkAndUpdateOverdueStatus(projectId, button);
            }
        }
    });

    // Function to check and update overdue status
    function checkAndUpdateOverdueStatus(projectId, button) {
        // Show loading state
        const originalText = button.innerHTML;
        const originalClass = button.className;
        
        // Add loading spinner and disable button
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Loading...';
        button.className = originalClass + ' disabled';
        button.disabled = true;

        // Make AJAX call to check overdue status
        fetch('check_overdue.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `project_id=${encodeURIComponent(projectId)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // If status was updated to overdue, show a message
                if (data.is_overdue) {
                    showAlert('Project marked as overdue', 'warning');
                }
                // Redirect to project details page
                window.location.href = `project_process_v2.php?project_id=${projectId}`;
            } else {
                throw new Error(data.message || 'Failed to check project status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Still allow viewing details even if there was an error
            window.location.href = `project_process_v2.php?project_id=${projectId}`;
        });
        // Note: We don't need finally here since we're redirecting
    }

    // Helper function to show alerts
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alertDiv.style.zIndex = '1100';
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alertDiv.classList.remove('show');
            setTimeout(() => alertDiv.remove(), 150);
        }, 5000);
    }
});
