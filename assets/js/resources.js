// Resources Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Handle resource card clicks
    $('.resource-card').on('click', function(e) {
        // Don't trigger if clicking on buttons or links inside the card
        if ($(e.target).closest('.btn').length === 0) {
            const resourceId = $(this).find('.view-resource').data('resource-id');
            const resourceType = $(this).find('.view-resource').data('resource-type');
            const resourceUrl = $(this).find('.view-resource').data('resource-url');
            const resourceTitle = $(this).find('.card-title').text();
            
            showResourceModal(resourceId, resourceType, resourceUrl, resourceTitle);
        }
    });

    // Function to show resource in modal
    function showResourceModal(resourceId, resourceType, resourceUrl, resourceTitle) {
        $('#resourceModalTitle').text(resourceTitle);
        $('#resourceModalLink').attr('href', resourceUrl);
        
        // Show loading state
        $('#resourceModalBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2">Loading resource...</p>
            </div>
        `);
        
        // Show modal immediately
        $('#resourceModal').modal('show');

        // Determine content based on resource type
        let modalContent = '';
        const videoTypes = ['mp4', 'webm', 'ogg'];
        const imageTypes = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (videoTypes.includes(resourceType)) {
            modalContent = `
                <div class="embed-responsive embed-responsive-16by9">
                    <video controls autoplay class="embed-responsive-item">
                        <source src="${resourceUrl}" type="video/${resourceType}">
                        Your browser does not support the video tag.
                    </video>
                </div>
                <div class="mt-3">
                    <p class="text-muted"><small>Video resource: ${resourceTitle}</small></p>
                </div>
            `;
            $('#resourceModalLinkText').text('Open Video');
        } else if (resourceType === 'pdf') {
            modalContent = `
                <div class="pdf-viewer-container">
                    <iframe src="${resourceUrl}#view=fitH" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="mt-3">
                    <p class="text-muted"><small>PDF document: ${resourceTitle}</small></p>
                </div>
            `;
            $('#resourceModalLinkText').text('Open PDF');
        } else if (imageTypes.includes(resourceType)) {
            modalContent = `
                <div class="text-center">
                    <img src="${resourceUrl}" class="img-fluid img-viewer" alt="${resourceTitle}" style="max-height: 70vh;">
                </div>
                <div class="mt-3">
                    <p class="text-muted"><small>Image: ${resourceTitle}</small></p>
                </div>
            `;
            $('#resourceModalLinkText').text('Open Image');
        } else {
            // For other file types or when we want to show details
            $.ajax({
                url: BASE_URL + '/ajax/get_resource_details.php',
                method: 'POST',
                data: { resource_id: resourceId },
                success: function(response) {
                    if (response.success) {
                        modalContent = `
                            <div class="resource-details">
                                <div class="row">
                                    <div class="col-md-6">
                                        <img src="${response.thumbnail_path ? BASE_URL + '/uploads/resources/' + response.thumbnail_path : BASE_URL + '/assets/images/default-resource-thumbnail.jpg'}" 
                                             class="img-fluid rounded mb-3" alt="${response.title}">
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Description</h5>
                                        <p class="text-muted">${response.description || 'No description available.'}</p>
                                        <div class="resource-meta mt-4">
                                            <p><i class="material-icons">category</i> ${response.type}</p>
                                            <p><i class="material-icons">event</i> ${new Date(response.created_at).toLocaleDateString()}</p>
                                            ${response.author ? `<p><i class="material-icons">person</i> ${response.author}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        modalContent = `
                            <div class="alert alert-info">
                                <p>Click "Open Resource" to view this content.</p>
                            </div>
                        `;
                    }
                    $('#resourceModalBody').html(modalContent);
                },
                error: function() {
                    $('#resourceModalBody').html(`
                        <div class="alert alert-warning">
                            <p>Could not load resource details. Please try again.</p>
                        </div>
                    `);
                }
            });
            return; // Exit early for AJAX content
        }
        
        // Set content for non-AJAX resources
        $('#resourceModalBody').html(modalContent);
    }

    // Initialize image viewer when modal is shown
    $('#resourceModal').on('shown.bs.modal', function() {
        const viewerElements = document.querySelectorAll('.img-viewer');
        if (viewerElements.length > 0) {
            new Viewer(viewerElements[0], {
                navbar: false,
                title: false,
                toolbar: {
                    zoomIn: true,
                    zoomOut: true,
                    rotateLeft: true,
                    rotateRight: true,
                    reset: true,
                }
            });
        }
    });

    // Clear modal content when closed to prevent video/audio from playing in background
    $('#resourceModal').on('hidden.bs.modal', function() {
        $('#resourceModalBody').html('');
    });

    // Handle category tab switching
    $('.category-link[data-toggle="tab"]').on('click', function() {
        $('.category-item').removeClass('active');
        $(this).closest('.category-item').addClass('active');
    });

    // Smooth scroll for category navigation
    $('.categories-menu a').on('click', function(e) {
        if (this.hash !== "") {
            e.preventDefault();
            
            const hash = this.hash;
            $('html, body').animate({
                scrollTop: $(hash).offset().top - 100
            }, 800);
        }
    });

    // Highlight active category based on scroll position
    $(window).on('scroll', function() {
        const scrollPosition = $(window).scrollTop();
        
        $('.tab-pane').each(function() {
            const sectionTop = $(this).offset().top - 150;
            const sectionBottom = sectionTop + $(this).outerHeight();
            const sectionId = $(this).attr('id');
            
            if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                $('.category-item').removeClass('active');
                $(`.category-link[href="#${sectionId}"]`).closest('.category-item').addClass('active');
            }
        });
    });

    // Initialize scrollspy
    $('body').scrollspy({ 
        target: '.resource-categories-nav',
        offset: 150
    });

    // Handle search form submission with empty query
    $('.resource-search').on('submit', function(e) {
        const searchInput = $(this).find('input[name="search"]');
        if (searchInput.val().trim() === '') {
            e.preventDefault();
            window.location.href = 'resources.php';
        }
    });

    // Add animation class when elements come into view
    const animateOnScroll = function() {
        const elements = document.querySelectorAll('.resource-card, .section-title, .section-subtitle');
        
        elements.forEach(element => {
            const elementPosition = element.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (elementPosition < windowHeight - 100) {
                element.classList.add('animated');
            }
        });
    };

    window.addEventListener('scroll', animateOnScroll);
    animateOnScroll(); // Run once on page load
});