$(document).ready(function() {
    // Handle login form submission
    $('.user').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        var email = $('#email').val().trim();
        var password = $('#password').val();
        
        // Validate required fields
        if (!email || !password) {
            alert('Please enter both email and password');
            return;
        }
        
        // Prepare data for API
        var loginData = {
            email: email,
            password: password
        };
        
        // Send AJAX request with JSON data
        $.ajax({
            url: '/company_profile_syntaxtrust/backend_new/api/auth.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(loginData),
            xhrFields: { withCredentials: true },
            success: function(response) {
                console.log('Login successful:', response);
                // Redirect to dashboard
                window.location.href = '/company_profile_syntaxtrust/backend_new/index.php';
            },
            error: function(xhr, status, error) {
                console.error('Login error:', xhr.responseText);
                var errorMessage = 'Login failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        errorMessage = response.message || errorMessage;
                    } catch (e) {
                        errorMessage = xhr.responseText;
                    }
                }
                alert(errorMessage);
            }
        });
    });
});
