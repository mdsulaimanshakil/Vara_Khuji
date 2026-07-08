document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registerForm');
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const fieldErrors = document.querySelectorAll('[data-error-for]');

    const validators = {
        full_name: value => {
            if (!value.trim()) return 'Full name is required.';
            if (value.trim().length < 3) return 'Full name must be at least 3 characters long.';
            return '';
        },
        email: value => {
            if (!value.trim()) return 'Email is required.';
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()) ? '' : 'Enter a valid email address.';
        },
        phone: value => {
            const digits = value.replace(/\D/g, '');
            if (!digits) return 'Phone number is required.';
            return digits.length >= 10 && digits.length <= 15 ? '' : 'Enter a phone number with 10 to 15 digits.';
        },
        role: value => (['Tenant', 'Landlord'].includes(value) ? '' : 'Select a valid role.'),
        password: value => {
            if (!value) return 'Password is required.';
            if (value.length < 8) return 'Password must be at least 8 characters long.';
            if (!/[A-Z]/.test(value)) return 'Add at least one uppercase letter.';
            if (!/[a-z]/.test(value)) return 'Add at least one lowercase letter.';
            if (!/[0-9]/.test(value)) return 'Add at least one number.';
            if (!/[^A-Za-z0-9]/.test(value)) return 'Add at least one special character.';
            return '';
        },
        confirm_password: value => {
            if (!value) return 'Please confirm your password.';
            return value === passwordField.value ? '' : 'Passwords do not match.';
        }
    };

    const updateFieldError = (fieldName, message) => {
        const target = document.querySelector(`[data-error-for="${fieldName}"]`);
        if (target) {
            target.textContent = message;
        }
    };

    const scorePassword = password => {
        let score = 0;
        if (password.length >= 8) score += 1;
        if (password.length >= 12) score += 1;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1;
        if (/[0-9]/.test(password)) score += 1;
        if (/[^A-Za-z0-9]/.test(password)) score += 1;
        return score;
    };

    const renderStrength = () => {
        const value = passwordField.value;
        const score = scorePassword(value);
        const width = Math.min(score * 20, 100);

        strengthBar.style.width = `${width}%`;

        if (!value) {
            strengthBar.style.backgroundColor = '#c92a2a';
            strengthText.textContent = 'Use 8+ characters with upper, lower, number, and symbol.';
            return;
        }

        if (score <= 1) {
            strengthBar.style.backgroundColor = '#c92a2a';
            strengthText.textContent = 'Weak password.';
        } else if (score <= 3) {
            strengthBar.style.backgroundColor = '#f59f00';
            strengthText.textContent = 'Moderate password.';
        } else {
            strengthBar.style.backgroundColor = '#0f9d58';
            strengthText.textContent = 'Strong password.';
        }
    };

    const validateForm = () => {
        let isValid = true;
        fieldErrors.forEach(node => {
            node.textContent = '';
        });

        Object.keys(validators).forEach(fieldName => {
            const field = form.elements[fieldName];
            if (!field) return;

            const message = validators[fieldName](field.value);
            updateFieldError(fieldName, message);
            if (message) {
                isValid = false;
            }
        });

        const passwordMessage = validators.confirm_password(confirmPasswordField.value);
        updateFieldError('confirm_password', passwordMessage);
        if (passwordMessage) {
            isValid = false;
        }

        return isValid;
    };

    if (passwordField) {
        passwordField.addEventListener('input', () => {
            renderStrength();
            if (confirmPasswordField.value) {
                updateFieldError('confirm_password', validators.confirm_password(confirmPasswordField.value));
            }
        });
    }

    if (confirmPasswordField) {
        confirmPasswordField.addEventListener('input', () => {
            updateFieldError('confirm_password', validators.confirm_password(confirmPasswordField.value));
        });
    }

    if (form) {
        form.addEventListener('submit', event => {
            if (!validateForm()) {
                event.preventDefault();
            }
        });
    }

    renderStrength();
});
