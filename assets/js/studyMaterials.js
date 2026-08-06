document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.querySelector('.filter-bar');
    
    if (filterForm) {
        // Optional: Auto-submit form when dropdowns or date change
        const autoSubmitFields = filterForm.querySelectorAll('select, input[type="date"]');
        autoSubmitFields.forEach(field => {
            field.addEventListener('change', () => {
                filterForm.submit();
            });
        });

        // Ensure ID is present before submission (Safety Check)
        filterForm.addEventListener('submit', function(e) {
            const idInput = filterForm.querySelector('input[name="id"]');
            if (!idInput) {
                const urlParams = new URLSearchParams(window.location.search);
                const id = urlParams.get('id');
                if (id) {
                    const hiddenId = document.createElement('input');
                    hiddenId.type = 'hidden';
                    hiddenId.name = 'id';
                    hiddenId.value = id;
                    filterForm.appendChild(hiddenId);
                }
            }
        });
    }
});

function toggleMenu(event, id) {
    event.stopPropagation();
    document.querySelectorAll('.sm-dropdown-content').forEach(el => {
        if(el.id !== 'menu-'+id) el.classList.remove('show');
    });
    document.getElementById('menu-' + id).classList.toggle('show');
}

function openPostModal() {
    document.getElementById('uploadModal').style.display = 'flex';
    selectUploadMethod('file');
}

function closePostModal() {
    document.getElementById('uploadModal').style.display = 'none';
    // reset form
    document.getElementById('uploadForm').reset();
    document.getElementById('dropText').textContent = 'No file chosen';
    document.getElementById('materialType').value = '';
}

// Toggle between file and URL upload
/**
 * Global State Management for Upload Methods
 */
selectUploadMethod = function(method) {
    const fileArea = document.getElementById('fileUploadArea');
    const urlArea = document.getElementById('urlUploadArea');
    const fileInput = document.getElementById('materialFile');
    const urlInput = document.getElementById('materialUrl');
    const dropText = document.getElementById('dropText');

    if (method === 'file') {
        fileArea.style.display = 'block';
        urlArea.style.display = 'none';
        // Clear URL when switching to File mode to ensure data integrity
        urlInput.value = ''; 
    } else {
        fileArea.style.display = 'none';
        urlArea.style.display = 'block';
        // Clear File when switching to URL mode
        fileInput.value = ''; 
        if (dropText) dropText.textContent = 'No file chosen';
    }
    
    // Reset type inference based on the newly empty/cleared fields
    document.getElementById('materialType').value = '';
    
    // Refresh button states and preview
    updateButtonStates();
    // Dispatch a custom event or call updatePreview if defined in scope
    window.dispatchEvent(new CustomEvent('methodSwitched'));
};

/**
 * Disables buttons based on current visibility and input presence
 */
function updateButtonStates() {
    const fileInput = document.getElementById('materialFile');
    const urlInput = document.getElementById('materialUrl');
    const useFileBtn = document.getElementById('useFileBtn');
    const useUrlBtn = document.getElementById('useUrlBtn');
    
    const fileAreaVisible = document.getElementById('fileUploadArea').style.display !== 'none';
    const hasFile = fileInput.files && fileInput.files.length > 0;
    const hasUrl = urlInput.value.trim().length > 0;

    // 1. Disable "Upload File" button if:
    // - User is already viewing the file area
    // - OR user has already typed something in the URL field
    useFileBtn.disabled = fileAreaVisible || hasUrl;

    // 2. Disable "Use URL" button if:
    // - User is already viewing the URL area
    // - OR user has already selected a file
    useUrlBtn.disabled = !fileAreaVisible || hasFile;
}

/**
 * Main Initialization and Event Handling
 */
(function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('materialFile');
    const urlInput = document.getElementById('materialUrl');
    const dropText = document.getElementById('dropText');
    const categorySelect = document.getElementById('materialCategory');
    const materialTypeInput = document.getElementById('materialType');

    // Initialize: Default to File Upload view
    window.selectUploadMethod('file');

    // --- Helper Functions ---

    function inferType(name) {
        const ext = name.split('.').pop().toLowerCase();
        if (ext && ext.length <= 5) {
            materialTypeInput.value = ext;
        }
        updatePreview();
    }

    function updatePreview() {
        const type = materialTypeInput.value || 'unknown';
        const category = categorySelect.value || 'other';
        const preview = document.getElementById('filePreview');
        const text = document.getElementById('previewText');
        
        let name = '';
        if (fileInput.files && fileInput.files.length) {
            name = fileInput.files[0].name;
        } else if (urlInput.value.trim()) {
            name = urlInput.value.trim();
        }

        if (!name) {
            preview.style.display = 'none';
            return;
        }
        preview.style.display = 'block';
        text.textContent = `${name} — type: ${type}, category: ${category}`;
    }

    // --- Event Listeners ---

    // File Selection Logic
    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#3a86ff';
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = '#cbd5e1';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#cbd5e1';
        if (e.dataTransfer.files && e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            const fileName = fileInput.files[0].name;
            dropText.textContent = fileName;
            inferType(fileName);
            updateButtonStates();
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            const fileName = fileInput.files[0].name;
            dropText.textContent = fileName;
            inferType(fileName);
        } else {
            dropText.textContent = 'No file chosen';
        }
        updateButtonStates();
    });

    // URL Input Logic
    urlInput.addEventListener('input', () => {
        const val = urlInput.value.trim();
        if (val) {
            try {
                const path = new URL(val).pathname;
                inferType(path);
            } catch (e) { /* Invalid URL while typing */ }
        }
        updateButtonStates();
        updatePreview();
    });

    categorySelect.addEventListener('change', updatePreview);
    window.addEventListener('methodSwitched', updatePreview);

    // --- Form Submission ---

    window.submitForUpload = function(forApproval) {
        document.getElementById('forApproval').value = forApproval ? '1' : '0';
        
        const title = document.getElementById('materialTitle').value.trim();
        const hasFile = fileInput.files && fileInput.files.length > 0;
        const hasUrl = urlInput.value.trim().length > 0;
        
        // Check if we are editing an existing record
        const isEditing = document.getElementById('editMaterialId').value !== "";

        if (!title) {
            alert('Please enter a material name.');
            return;
        }

        // If it's a NEW upload, we need a file or URL.
        // If it's an EDIT, we only need a file/URL if the user wants to REPLACE the old one.
        if (!isEditing && !hasFile && !hasUrl) {
            alert('Please choose a file to upload or enter a URL.');
            return;
        }

        document.getElementById('uploadForm').submit();
    };
})();

// Close modal when clicking outside content
document.getElementById('uploadModal').addEventListener('click', function(e){
    if (e.target === this) closePostModal();
});




// Close dropdowns when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.sm-dots-btn')) {
        document.querySelectorAll('.sm-dropdown-content').forEach(el => el.classList.remove('show'));
    }
}

function clearExistingSource(type) {
    const fileBtn = document.getElementById('useFileBtn');
    const urlBtn = document.getElementById('useUrlBtn');
    
    if (type === 'file') {
        document.getElementById('currentFileDisplay').style.display = 'none';
        document.getElementById('dropText').innerText = "No file chosen";
        // Re-enable the URL option since the file is being "removed"
        urlBtn.disabled = false;
        urlBtn.style.opacity = "1";
    } else {
        document.getElementById('materialUrl').value = '';
        // Re-enable the File option since the URL is cleared
        fileBtn.disabled = false;
        fileBtn.style.opacity = "1";
    }
    // Set a hidden flag if you want to tell the PHP to delete the old file
}

function openEditModal(data) {
    const modal = document.getElementById('uploadModal');
    const form = document.getElementById('uploadForm');
    
    // UI Setup
    document.getElementById('uploadTitle').innerText = "Edit Material";
    form.action = "../api/study_material_handler.php?action=update";
    document.getElementById('editMaterialId').value = data.id;
    document.getElementById('materialTitle').value = data.title;
    document.getElementById('materialCategory').value = data.category;

    const fileBtn = document.getElementById('useFileBtn');
    const urlBtn = document.getElementById('useUrlBtn');

    // --- ENFORCE STATUS ---
    const isUrl = data.file_path.startsWith('http') || data.type === 'url';

    if (isUrl) {
        selectUploadMethod('url');
        document.getElementById('materialUrl').value = data.file_path;
        document.getElementById('currentFileDisplay').style.display = 'none';
        
        // Disable File button because a URL exists
        fileBtn.disabled = true;
        fileBtn.style.opacity = "0.5";
        fileBtn.title = "Remove URL to upload a file instead";
        
        urlBtn.disabled = false;
        urlBtn.style.opacity = "1";
    } else {
        selectUploadMethod('file');
        document.getElementById('currentFileDisplay').style.display = 'block';
        document.getElementById('currentFileName').innerText = data.original_name;
        document.getElementById('dropText').innerText = "Current: " + data.original_name;
        
        // Disable URL button because a File exists
        urlBtn.disabled = true;
        urlBtn.style.opacity = "0.5";
        urlBtn.title = "Remove file to use a URL instead";
        
        fileBtn.disabled = false;
        fileBtn.style.opacity = "1";
    }

    modal.style.display = 'flex';
}

// Monitor URL input changes
document.getElementById('materialUrl').addEventListener('input', function(e) {
    const fileBtn = document.getElementById('useFileBtn');
    if (this.value.trim() !== "") {
        fileBtn.disabled = true;
        fileBtn.style.opacity = "0.5";
    } else {
        fileBtn.disabled = false;
        fileBtn.style.opacity = "1";
    }
});

// Monitor File input changes
document.getElementById('materialFile').addEventListener('change', function(e) {
    const urlBtn = document.getElementById('useUrlBtn');
    if (this.files.length > 0) {
        urlBtn.disabled = true;
        urlBtn.style.opacity = "0.5";
    } else {
        urlBtn.disabled = false;
        urlBtn.style.opacity = "1";
    }
});


/* for report */
// Add this to the bottom of studyMaterials.php
document.addEventListener("DOMContentLoaded", function() {
    // Check if the URL has a hash (e.g., #material_15)
    if(window.location.hash) {
        const targetId = window.location.hash.substring(1); // Removes the '#'
        const element = document.getElementById(targetId);
        
        if(element) {
            // 1. Add the highlight class
            element.classList.add('highlight-report');
            
            // 2. Smoothly scroll to the element
            element.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
            
            // 3. Remove the highlight after 3 seconds so it doesn't stay red forever
            setTimeout(() => {
                element.classList.remove('highlight-report');
                element.style.transform = "scale(1)";
            }, 3000);
        }
    }
});