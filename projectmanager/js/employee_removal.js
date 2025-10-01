// Handle employee removal functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Employee removal script loaded');
    
    // Handle employee removal
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-employee')) {
            e.preventDefault();
            const button = e.target.closest('.remove-employee');
            const employeeId = button.dataset.id;
            const isEstimation = button.dataset.isEstimation === '1';
            const projectId = button.closest('form')?.querySelector('input[name="project_id"]')?.value || 
                           new URLSearchParams(window.location.search).get('project_id') ||
                           new URLSearchParams(window.location.search).get('id');
            
            console.log('Remove employee clicked:', { employeeId, isEstimation, projectId });
            
            if (!employeeId || !projectId) {
                console.error('Missing employeeId or projectId:', { employeeId, projectId });
                alert('Error: Missing required information. Please refresh the page and try again.');
                return;
            }
            
            if (confirm('Are you sure you want to remove this employee from the project?')) {
                // Create form data
                const formData = new FormData();
                formData.append('remove_estimation_employee', '1');
                formData.append('employee_id', employeeId);
                formData.append('project_id', projectId);
                
                console.log('Sending request to server...');
                
                // Send AJAX request
                fetch('project_add_functions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Server response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Server response data:', data);
                    if (data.success) {
                        // Reload the page to show updated employee list
                        window.location.reload();
                    } else {
                        console.error('Server error:', data.error);
                        alert('Error removing employee: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error removing employee:', error);
                    alert('Error removing employee. Please try again.');
                });
            }
        }
    });
});
