// Handle login form submission
if (document.getElementById('login-form')) {
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const gmail = document.getElementById('gmail').value;
        const password = document.getElementById('password').value;

        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('gmail', gmail);
        formData.append('password', password);

        const response = await fetch('../api/auth.php', {
            method: 'POST',
            body: formData,
        });

        const result = await response.json();

        if (result.status === 'success') {
            window.location.href = result.redirect; // Redirect to the appropriate page
        } else {
            // Show error message
            const errorMessage = document.getElementById('error-message');
            errorMessage.textContent = result.message;
            errorMessage.classList.remove('d-none');
        }
    });
}

// Handle register form submission
if (document.getElementById('register-form')) {
    document.getElementById('register-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const name = document.getElementById('name').value;
        const gmail = document.getElementById('gmail').value;
        const password = document.getElementById('password').value;

        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('name', name);
        formData.append('gmail', gmail);
        formData.append('password', password);

        const response = await fetch('../api/auth.php', {
            method: 'POST',
            body: formData,
        });

        const result = await response.json();

        if (result.status === 'success') {
            window.location.href = result.redirect; // Redirect to OTP verification page
        } else {
            // Show error message
            const errorMessage = document.getElementById('error-message');
            errorMessage.textContent = result.message;
            errorMessage.classList.remove('d-none');
        }
    });
}

// Handle OTP input fields and verification form submission
if (document.getElementById('verify-form')) {
    const otpInputs = document.querySelectorAll('.otp-input');
    const verifyForm = document.getElementById('verify-form');
    const errorMessage = document.getElementById('error-message');

    // Allow only numbers in OTP fields
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            // Allow only numbers
            e.target.value = e.target.value.replace(/\D/g, '');

            // Move focus to the next input
            if (e.target.value.length === 1 && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }

            // Automatically submit the form when the 6th digit is entered
            if (index === otpInputs.length - 1 && e.target.value.length === 1) {
                verifyForm.dispatchEvent(new Event('submit'));
            }
        });

        // Handle backspace to move focus to the previous input
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && index > 0 && e.target.value.length === 0) {
                otpInputs[index - 1].focus();
            }
        });
    });

    // Handle form submission
    verifyForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Combine OTP digits into a single string
        const otp = Array.from(otpInputs).map(input => input.value).join('');

        const formData = new FormData();
        formData.append('action', 'verify_otp');
        formData.append('otp', otp);

        const response = await fetch('../api/auth.php', {
            method: 'POST',
            body: formData,
        });

        const result = await response.json();

        if (result.status === 'success') {
            window.location.href = result.redirect; // Redirect to the dashboard
        } else {
            // Show error message
            errorMessage.textContent = result.message;
            errorMessage.classList.remove('d-none');
        }
    });
}