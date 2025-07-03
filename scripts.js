// Client-side validation and interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Date validation for application form
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            if (this.value) {
                endDateInput.min = this.value;
                if (endDateInput.value && endDateInput.value < this.value) {
                    endDateInput.value = '';
                }
            }
        });
    }
    
    // File upload preview and validation
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            
            const fileInfo = this.nextElementSibling;
            if (fileInfo && fileInfo.classList.contains('file-info')) {
                fileInfo.textContent = `Selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            }
            
            // Validate file size client-side
            if (file.size > <?php echo MAX_FILE_SIZE; ?>) {
                alert('File exceeds maximum size of 5MB');
                this.value = '';
                if (fileInfo) fileInfo.textContent = 'PDF or Word document, max 5MB';
            }
            
            // Validate file type client-side
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'doc', 'docx'].includes(ext)) {
                alert('Only PDF and Word documents are allowed');
                this.value = '';
                if (fileInfo) fileInfo.textContent = 'PDF or Word document, max 5MB';
            }
        });
    });
    
    // Toggle feedback form visibility
    const rejectButtons = document.querySelectorAll('.reject-btn');
    rejectButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.getAttribute('href').includes('action=reject')) {
                e.preventDefault();
                const feedbackForm = this.nextElementSibling;
                if (feedbackForm && feedbackForm.classList.contains('feedback-form')) {
                    feedbackForm.style.display = feedbackForm.style.display === 'none' ? 'block' : 'none';
                }
            }
        });
    });
});