// Fetch billing request for Step 6
function fetchBillingRequest(projectId, userId) {
    return fetch(`../get_billing_request.php?project_id=${projectId}&user_id=${userId}`)
        .then(response => response.json());
}

document.addEventListener('DOMContentLoaded', function() {
    // Example: get userId from session or hidden input
    const projectId = window.currentProjectId;
    const userId = window.currentUserId || document.getElementById('userId')?.value;
    if (projectId && userId) {
        fetchBillingRequest(projectId, userId).then(data => {
            if (data) {
                document.getElementById('requestedAmount').textContent = `₱${parseFloat(data.amount).toLocaleString()}`;
                document.getElementById('requestDate').textContent = data.request_date || 'Not yet requested';
                document.getElementById('requestStatus').textContent = data.status || 'Pending';
            }
        });
    }
});
