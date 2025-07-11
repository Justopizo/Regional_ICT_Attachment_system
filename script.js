// Document ready function
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // File input preview (for application forms)
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'No file selected';
            const label = this.nextElementSibling;
            
            if (label && label.classList.contains('file-label')) {
                label.textContent = fileName;
            }
        });
    });
    
    // Password visibility toggle
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            if (input && input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else if (input) {
                input.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
    
    // Form validation feedback
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error-field');
                } else {
                    field.classList.remove('error-field');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                const errorElement = this.querySelector('.alert-danger') || document.createElement('div');
                if (!errorElement.classList.contains('alert-danger')) {
                    errorElement.className = 'alert alert-danger';
                    errorElement.textContent = 'Please fill in all required fields.';
                    this.insertBefore(errorElement, this.firstChild);
                }
            }
        });
    });
    
    // Status badge animations
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Document download tracking
    const documentLinks = document.querySelectorAll('.document-item');
    documentLinks.forEach(link => {
        link.addEventListener('click', function() {
            // You could add analytics tracking here
            console.log('Document downloaded: ', this.textContent.trim());
        });
    });
    
    // Auto-hide success messages after 5 seconds
    const successMessages = document.querySelectorAll('.alert-success');
    successMessages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });
});