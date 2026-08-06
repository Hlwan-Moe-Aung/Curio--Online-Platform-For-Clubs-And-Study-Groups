document.addEventListener('DOMContentLoaded', () => {
    // Select all drop zones on the page
    const dropZones = document.querySelectorAll('.drop-zone');

    dropZones.forEach(zone => {
        const fileInput = zone.querySelector('input[type="file"]');
        const dropText = zone.querySelector('.drop-text');

        // Trigger file browser on click
        zone.addEventListener('click', () => fileInput.click());

        // Update text when file is selected via click
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                dropText.innerText = fileInput.files[0].name;
            }
        });

        // Handle drag events
        ['dragover', 'dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        // Handle dropped files
        zone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files; // Assign files to the input
                dropText.innerText = files[0].name;
            }
        });
    });
});
