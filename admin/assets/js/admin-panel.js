$(document).ready(function() {
    // Toggle the side navigation
    $("#sidebarToggle, #sidebarToggleTop").on('click', function(e) {
        $("body").toggleClass("sidebar-toggled");
        $(".sidebar").toggleClass("toggled");
        if ($(".sidebar").hasClass("toggled")) {
            $('.sidebar .collapse').collapse('hide');
        };
    });

    // Close any open menu accordions when window is resized below 768px
    $(window).resize(function() {
        if ($(window).width() < 768) {
            $('.sidebar .collapse').collapse('hide');
        };
    });

    // Prevent the content wrapper from scrolling when the fixed side navigation hovered over
    $('body.fixed-nav .sidebar').on('mousewheel DOMMouseScroll wheel', function(e) {
        if ($(window).width() > 768) {
            var e0 = e.originalEvent,
                delta = e0.wheelDelta || -e0.detail;
            this.scrollTop += (delta < 0 ? 1 : -1) * 30;
            e.preventDefault();
        }
    });

    // Scroll to top button appear
    $(document).on('scroll', function() {
        var scrollDistance = $(this).scrollTop();
        if (scrollDistance > 100) {
            $('.scroll-to-top').fadeIn();
        } else {
            $('.scroll-to-top').fadeOut();
        }
    });

    // Smooth scrolling using jQuery easing
    $(document).on('click', 'a.scroll-to-top', function(e) {
        var $anchor = $(this);
        $('html, body').stop().animate({
            scrollTop: ($($anchor.attr('href')).offset().top)
        }, 1000, 'easeInOutExpo');
        e.preventDefault();
    });

    // Initialize DataTables for all tables with IDs ending in 'Table'
    $('table[id$="Table"]').each(function() {
        $(this).DataTable({
            "ordering": true,
            "searching": true,
            "paging": true,
            "info": true,
            "lengthChange": true
        });
    });

    // Handle logout modal
    $('#logoutModal').on('show.bs.modal', function(e) {
        // Additional logout handling can be added here
    });
    
    // Handle logout button click
    $('#logoutButton').on('click', function(e) {
        e.preventDefault();
        
        // Send AJAX request to logout endpoint
        $.ajax({
            url: '../../api.php/users/logout',
            method: 'POST',
            contentType: 'application/json',
            success: function(response) {
                // Redirect to login page
                window.location.href = '../views/pages/login.php';
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Logout failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert(errorMessage);
                // Still redirect to login page
                window.location.href = '../views/pages/login.php';
            }
        });
    });

    // Form submission handlers for various entities
    // These are placeholders that would be connected to backend APIs
    
    // Services form submission
    $('#serviceForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        var serviceId = $('#serviceId').val();
        var serviceName = $('#serviceName').val();
        var serviceDescription = $('#serviceDescription').val();
        var serviceShortDescription = $('#serviceShortDescription').val();
        var serviceIcon = $('#serviceIcon').val();
        var servicePrice = $('#servicePrice').val();
        var serviceDuration = $('#serviceDuration').val();
        var serviceFeatures = $('#serviceFeatures').val();
        var serviceFeatured = $('#serviceFeatured').is(':checked');
        
        // Validate required fields
        if (!serviceName || !serviceDescription || !serviceShortDescription) {
            alert('Please fill in all required fields');
            return;
        }
        
        // Parse features as JSON array
        var featuresArray = [];
        if (serviceFeatures) {
            try {
                featuresArray = JSON.parse(serviceFeatures);
            } catch (e) {
                alert('Please enter features as a valid JSON array');
                return;
            }
        }
        
        // Prepare data for API
        var serviceData = {
            name: serviceName,
            description: serviceDescription,
            short_description: serviceShortDescription,
            icon: serviceIcon,
            price: servicePrice ? parseFloat(servicePrice) : null,
            duration: serviceDuration,
            features: featuresArray,
            is_featured: serviceFeatured
        };
        
        // Determine if this is an update or create operation
        var isUpdate = serviceId && serviceId !== '';
        
        // Send AJAX request
        $.ajax({
            url: '/company_profile_syntaxtrust/backend/services' + (isUpdate ? '/' + serviceId : ''),
            method: isUpdate ? 'PUT' : 'POST',
            contentType: 'application/json',
            data: JSON.stringify(serviceData),
            success: function(response) {
                // Show success message
                alert(response.message || (isUpdate ? 'Service updated successfully!' : 'Service created successfully!'));
                
                // Close modal
                $('#serviceModal').modal('hide');
                
                // Reset form
                $('#serviceForm')[0].reset();
                $('#serviceId').val('');
                
                // Reload the page to show updated data
                location.reload();
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Error saving service';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert(errorMessage);
            }
        });
    });

    // Portfolio form submission
    $('#portfolioForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        var portfolioId = $('#portfolioId').val();
        var portfolioTitle = $('#portfolioTitle').val();
        var portfolioDescription = $('#portfolioDescription').val();
        var portfolioShortDescription = $('#portfolioShortDescription').val();
        var portfolioClient = $('#portfolioClient').val();
        var portfolioCategory = $('#portfolioCategory').val();
        var portfolioUrl = $('#portfolioUrl').val();
        var portfolioTechnologies = $('#portfolioTechnologies').val();
        var portfolioDate = $('#portfolioDate').val();
        var portfolioFeatured = $('#portfolioFeatured').is(':checked');
        
        // Validate required fields
        if (!portfolioTitle || !portfolioDescription || !portfolioShortDescription) {
            alert('Please fill in all required fields');
            return;
        }
        
        // Parse technologies as JSON array
        var technologiesArray = [];
        if (portfolioTechnologies) {
            try {
                technologiesArray = JSON.parse(portfolioTechnologies);
            } catch (e) {
                alert('Please enter technologies as a valid JSON array');
                return;
            }
        }
        
        // Prepare data for API
        var portfolioData = {
            title: portfolioTitle,
            description: portfolioDescription,
            short_description: portfolioShortDescription,
            client_name: portfolioClient,
            category: portfolioCategory,
            project_url: portfolioUrl,
            technologies: technologiesArray,
            project_date: portfolioDate,
            is_featured: portfolioFeatured
        };
        
        // Determine if this is an update or create operation
        var isUpdate = portfolioId && portfolioId !== '';
        
        // Send AJAX request
        $.ajax({
            url: '/company_profile_syntaxtrust/backend/portfolio' + (isUpdate ? '/' + portfolioId : ''),
            method: isUpdate ? 'PUT' : 'POST',
            contentType: 'application/json',
            data: JSON.stringify(portfolioData),
            success: function(response) {
                // Show success message
                alert(response.message || (isUpdate ? 'Portfolio item updated successfully!' : 'Portfolio item created successfully!'));
                
                // Close modal
                $('#portfolioModal').modal('hide');
                
                // Reset form
                $('#portfolioForm')[0].reset();
                $('#portfolioId').val('');
                
                // Reload the page to show updated data
                location.reload();
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Error saving portfolio item';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert(errorMessage);
            }
        });
    });

    // Team member form submission
    $('#teamForm').on('submit', function(e) {
        e.preventDefault();
        // This would be replaced with actual AJAX call to backend API
        console.log('Team member form submitted');
        // Show success message
        alert('Team member saved successfully!');
        // Reset form
        this.reset();
    });

    // Client form submission
    $('#clientForm').on('submit', function(e) {
        e.preventDefault();
        // This would be replaced with actual AJAX call to backend API
        console.log('Client form submitted');
        // Show success message
        alert('Client saved successfully!');
        // Reset form
        this.reset();
    });

    // Testimonial form submission
    $('#testimonialForm').on('submit', function(e) {
        e.preventDefault();
        // This would be replaced with actual AJAX call to backend API
        console.log('Testimonial form submitted');
        // Show success message
        alert('Testimonial saved successfully!');
        // Reset form
        this.reset();
    });

    // Blog post form submission
    $('#blogPostForm').on('submit', function(e) {
        e.preventDefault();
        // This would be replaced with actual AJAX call to backend API
        console.log('Blog post form submitted');
        // Show success message
        alert('Blog post saved successfully!');
        // Reset form
        this.reset();
    });

    // User form submission
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        // This would be replaced with actual AJAX call to backend API
        console.log('User form submitted');
        // Show success message
        alert('User saved successfully!');
        // Reset form
        this.reset();
    });

    // Edit service button click handler
    $(document).on('click', '.edit-service-btn', function() {
        var serviceId = $(this).data('id');
        
        // Fetch service data from API
        $.ajax({
            url: '/company_profile_syntaxtrust/backend/services/' + serviceId,
            method: 'GET',
            success: function(response) {
                if (response.status === 'success' && response.data) {
                    var service = response.data;
                    
                    // Populate form with service data
                    $('#serviceId').val(service.id);
                    $('#serviceName').val(service.name);
                    $('#serviceDescription').val(service.description);
                    $('#serviceShortDescription').val(service.short_description);
                    $('#serviceIcon').val(service.icon);
                    $('#servicePrice').val(service.price);
                    $('#serviceDuration').val(service.duration);
                    
                    // Parse features if they exist
                    if (service.features) {
                        try {
                            var featuresArray = JSON.parse(service.features);
                            $('#serviceFeatures').val(JSON.stringify(featuresArray, null, 2));
                        } catch (e) {
                            $('#serviceFeatures').val(service.features);
                        }
                    } else {
                        $('#serviceFeatures').val('');
                    }
                    
                    // Set featured checkbox
                    $('#serviceFeatured').prop('checked', service.is_featured === '1' || service.is_featured === true);
                    
                    // Update modal title
                    $('#serviceModalLabel').text('Edit Service');
                    
                    // Show modal
                    $('#serviceModal').modal('show');
                } else {
                    alert('Error loading service data');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Error loading service data';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert(errorMessage);
            }
        });
    });
    
    // Delete service button click handler
    $(document).on('click', '.delete-service-btn', function() {
        var serviceId = $(this).data('id');
        
        if (confirm('Are you sure you want to delete this service?')) {
            // Send DELETE request to API
            $.ajax({
                url: '/company_profile_syntaxtrust/backend/services/' + serviceId,
                method: 'DELETE',
                success: function(response) {
                    // Show success message
                    alert(response.message || 'Service deleted successfully!');
                    
                    // Reload the page to show updated data
                    location.reload();
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'Error deleting service';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    alert(errorMessage);
                }
            });
        }
    });
    
    // Generic delete button click handler (for other entities)
    $('.delete-btn').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this item?')) {
            // This would be replaced with actual AJAX call to backend API
            console.log('Item deleted');
            alert('Item deleted successfully!');
        }
    });
});
