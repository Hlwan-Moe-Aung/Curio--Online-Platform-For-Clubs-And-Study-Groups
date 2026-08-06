function closeToast() {
    const toast = document.getElementById('yt-toast');
    if (toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        toast.style.transition = 'all 0.5s ease';
        setTimeout(() => {
            toast.remove();

            // 1. Get current URL details
            const url = new URL(window.location.href);
            
            // 2. Specifically remove ONLY the 'msg' parameter
            url.searchParams.delete('msg');

            // 3. Update the browser address bar without refreshing
            // This preserves the ?id= and any other parameters
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }, 500);
    }
}

// Auto-hide after 5 seconds
{
    document.addEventListener('DOMContentLoaded', function() {
        const toast = document.getElementById('yt-toast');
        if (toast) {
            // Start the ring "draining" animation
            setTimeout(() => {
                toast.classList.add('active');
            }, 50);

            // Hide the toast after 5 seconds
            setTimeout(closeToast, 5000);
        }
    });
}


// 1. Save current data BEFORE form sends so we can "Undo" later
function saveOldData(oldName, oldEmail, oldDesc = '', oldPurpose = '') {
    localStorage.setItem('prev_name', oldName);
    localStorage.setItem('prev_email', oldEmail); // Acts as 'Category' for clubs
    localStorage.setItem('prev_desc', oldDesc);
    localStorage.setItem('prev_purpose', oldPurpose);
    console.log("Data cached:", {oldName, oldEmail, oldDesc, oldPurpose});
}
// 2. The Undo Function
function undoProfileUpdate() {
    // 1. Correctly select the elements
    const undoBtn = document.querySelector('.toast-action-btn');
    const undoText = document.getElementById('undo-text');
    const undoSpinner = document.getElementById('undo-spinner');
    
    const oldName = localStorage.getItem('prev_name');
    const oldEmail = localStorage.getItem('prev_email');
    
    // Find the current email input from the form on the page
    const emailInput = document.querySelector('input[name="email"]');
    
    if (!emailInput || !oldName || !oldEmail) return;

    // 2. TRIGGER UI CHANGES
    if (undoText) undoText.innerText = "Reverting";
    if (undoSpinner) {
        undoSpinner.style.display = "inline-block"; // Make sure this matches the ID in HTML
    }
    if (undoBtn) {
        undoBtn.style.pointerEvents = "none"; 
        undoBtn.style.opacity = "0.7";
    }

    // 3. Prepare the form
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../api/user_profile_update_handler.php';

    const fields = {
        'name': oldName,
        'email': oldEmail,
        'current_session_email': emailInput.value,
        'update_profile': '1'
    };

    for (const [key, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }

    document.body.appendChild(form);

    // 4. GIVE IT TIME TO RENDER
    // If you submit too fast, the browser won't show the spinner
    setTimeout(() => {
        form.submit();
    }, 800); 
}

function undoClubProfileUpdate() {
    const undoBtn = document.querySelector('.toast-action-btn');
    const undoText = document.getElementById('undo-text');
    const undoSpinner = document.getElementById('undo-spinner');
    
    // Retrieve cached club data
    const oldName = localStorage.getItem('prev_name');
    const oldCategory = localStorage.getItem('prev_email'); // We reused this slot for category
    const oldDesc = localStorage.getItem('prev_desc');
    const oldPurpose = localStorage.getItem('prev_purpose');
    
    // Find the ID currently on the page
    const idInput = document.querySelector('input[name="community_id"]');
    if (!idInput || !oldName) return;

    // Show Loading State
    if(undoText) undoText.innerText = "Reverting";
    if(undoSpinner) undoSpinner.style.display = "inline-block";
    undoBtn.style.pointerEvents = "none"; 

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../api/update_club_handler.php';

    // Map fields to what update_club_handler.php expects
    const fields = {
        'community_id': idInput.value,
        'community_name': oldName,
        'category': oldCategory,
        'description': oldDesc,
        'purpose': oldPurpose,
        'update_community': '1' // Triggers the correct logic in handler
    };

    for (const [key, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    setTimeout(() => { form.submit(); }, 600);
}


/* Example format.
   This should be placed in <body> </body> 
   toast.css and toast.js must be present as well.

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';
$show_undo = false;

if ($msg == 'success') {
    $display_text = "Club Info updated successfully";
    $show_undo = true; 
} elseif ($msg == 'disband_sent') {
    $display_text = "Disband request sent successfully";
    $show_undo = true; 
}
?>

<?php if ($display_text): ?>
    <div id="yt-toast" class="toast">
        <div class="toast-content">
            <?= htmlspecialchars($display_text) ?>
        </div>
        <div class="toast-actions">
            <?php if ($show_undo): ?>
                <button class="toast-action-btn" onclick="undoClubProfileUpdate()">
                    <span id="undo-text">Undo</span>
                    <span id="undo-spinner" style="display:none;" class="spinner"></span>
                </button>
            <?php endif; ?>
            <button class="toast-close" onclick="closeToast()" aria-label="Close">
                <svg class="progress-ring" width="24" height="24">
                    <circle class="progress-ring__circle" stroke="#3ea6ff" stroke-width="2" fill="transparent" r="10" cx="12" cy="12"/>
                </svg>
                <span class="close-icon">✕</span>
            </button>
        </div>
    </div>
<?php endif; ?>



*/