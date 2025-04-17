document.addEventListener('DOMContentLoaded', function() {
    // File input label updates
    document.querySelectorAll('.custom-file-input').forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'Choose file...';
            const label = this.nextElementSibling;
            label.textContent = fileName;
        });
    });

    // Confirmation for delete actions
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
});

// assets/js/admin/home-content.js

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Handle file input labels
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
    
    // Confirm before deleting items
    $('form[data-confirm]').on('submit', function(e) {
        if (!confirm($(this).data('confirm'))) {
            e.preventDefault();
        }
    });
    
    // Tab persistence
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        localStorage.setItem('lastAboutTab', $(e.target).attr('href'));
    });
    
    // Restore last active tab
    const lastTab = localStorage.getItem('lastAboutTab');
    if (lastTab) {
        $('[href="' + lastTab + '"]').tab('show');
    }
    
    // Auto-resize textareas
    $('textarea').each(function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    }).on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});

// Update the category handling code
document.getElementById('addCategoryBtn').addEventListener('click', function() {
    const categorySelect = document.getElementById('productCategory');
    const newCategoryGroup = document.getElementById('newCategoryGroup');
    
    // Show the new category input
    newCategoryGroup.style.display = 'flex';
    newCategoryGroup.querySelector('input').focus();
});

document.getElementById('cancelCategoryBtn').addEventListener('click', function() {
    const newCategoryGroup = document.getElementById('newCategoryGroup');
    
    // Hide the input and clear it
    newCategoryGroup.style.display = 'none';
    newCategoryGroup.querySelector('input').value = '';
});

// Handle form submission to update the dropdown
document.querySelector('form').addEventListener('submit', function(e) {
    const categorySelect = document.getElementById('productCategory');
    const newCategoryInput = document.getElementById('newCategoryGroup').querySelector('input');
    const newCategory = newCategoryInput.value.trim();
    
    // If adding a new category
    if (newCategory && newCategoryInput.style.display !== 'none') {
        // Add the new category to the dropdown
        const newOption = document.createElement('option');
        newOption.value = newCategory;
        newOption.textContent = newCategory;
        newOption.selected = true;
        categorySelect.appendChild(newOption);
    }
});