function updateCategories() {
    const typeSelect = document.getElementById('type');
    const categorySelect = document.getElementById('category');
    
    const selectedType = typeSelect.value;
    
    // Clear current options
    categorySelect.innerHTML = '<option value="">Select Category</option>';

    if (selectedType === "") return;

    let options = [];

    if (selectedType === 'club') {
        options = ['Physical', 'Mental', 'Creative', 'Social', 'Business'];
    } else if (selectedType === 'study_group') {
        options = ['Math', 'Language', 'Science', 'CS', 'History'];
    }

    options.forEach(function(opt) {
        let el = document.createElement("option");
        el.textContent = opt;
        el.value = opt.toLowerCase();
        categorySelect.appendChild(el);
    });
}

function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
        tabcontent[i].classList.remove("active");
    }
    tablinks = document.getElementsByClassName("tab-btn");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}


// managa_group.php
function showBanModal(email) {
    document.getElementById('ban_email').value = email;
    document.getElementById('banModal').style.display = 'block';
}


// club_dashboard.php
function goToSlide(index) {
    const track = document.getElementById('sliderTrack');
    track.style.transform = `translateX(-${index * 100}%)`;
                
    // Update active dot
    document.querySelectorAll('.dot').forEach((dot, i) => {
        dot.classList.toggle('active', i === index);
    });
}

function openPostModal() {
    document.getElementById('postModal').style.display = 'flex';
}

function closePostModal() {
    document.getElementById('postModal').style.display = 'none';
}

function openDisbandModal() {
    document.getElementById('disbandModal').style.display = 'flex';
}
function closeDisbandModal() {
    document.getElementById('disbandModal').style.display = 'none';
}

function openCommunityEditModal(id) { 
    document.getElementById(id).style.display = 'flex'; 
}
    
function closeCommunityEditModal(id) { 
    document.getElementById(id).style.display = 'none'; 
}

function openLeaveModal() {
    document.getElementById('leaveClubModal').style.display = 'flex';
    document.body.classList.add('modal-open');
}
function closeLeaveModal() {
    document.getElementById('leaveClubModal').style.display = 'none';
    document.body.classList.remove('modal-open');
}

function openEditModal(postData) {
    // 1. Show the modal
    document.getElementById('postModal').style.display = 'flex';
    
    // 2. Change text to "Edit" mode
    document.getElementById('modalTitle').innerText = "Edit Post";
    document.getElementById('submitBtn').innerText = "Save Changes";
    document.getElementById('imageNote').style.display = "block";
    
    // 3. Fill the values
    document.getElementById('edit_post_id').value = postData.id;
    document.getElementById('post_action').value = "edit";
    document.getElementById('edit_title').value = postData.title;
    document.getElementById('edit_content').value = postData.content;
}

// Reset modal when closing so "Create" works next time
function closeEditModal() {
    document.getElementById('postModal').style.display = 'none';
    document.getElementById('modalTitle').innerText = "Create Private Discussion";
    document.getElementById('submitBtn').innerText = "Submit for Approval";
    document.getElementById('post_action').value = "create";
    document.getElementById('edit_post_id').value = "";
}

// Close modal if user clicks on the gray background
window.onclick = function(event) {
    let modal = document.getElementById('postModal');
    if (event.target == modal) {
        closePostModal();
    }
}

function viewFullImage(imageSrc) {
    // Create a temporary overlay or use a dedicated image modal
    const overlay = document.createElement('div');
    overlay.style = "position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:10000; display:flex; align-items:center; justify-content:center; cursor:pointer;";
    overlay.onclick = () => overlay.remove(); // Click anywhere to close

    const img = document.createElement('img');
    img.src = imageSrc;
    img.style = "max-width:90%; max-height:90%; border-radius:8px; box-shadow: 0 0 20px rgba(255,255,255,0.2);";
    
    overlay.appendChild(img);
    document.body.appendChild(overlay);
}

function toggleText(postId) {
    const textDiv = document.getElementById('text-' + postId);
    const btn = textDiv.nextElementSibling;

    if (textDiv.classList.contains('expanded')) {
        textDiv.classList.remove('expanded');
        btn.innerText = 'See more...';
    } else {
        textDiv.classList.add('expanded');
        btn.innerText = 'See less';
    }
}

function toggleLike(btn, postId) {
    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('action', 'like'); // Tells the handler this is a like request

    fetch('../api/interaction_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const countSpan = btn.querySelector('.count');
        let currentCount = parseInt(countSpan.innerText);
        
        if(data.status === 'liked') {
            btn.classList.add('liked');
            countSpan.innerText = currentCount + 1;
        } else if(data.status === 'unliked') {
            btn.classList.remove('liked');
            countSpan.innerText = currentCount - 1;
        }
    });
}

function copyPostLink(postId) {
    const link = window.location.origin + '/SemV-PHP-Project/views/view_post.php?id=' + postId;
    navigator.clipboard.writeText(link).then(() => {
        alert("Link copied to clipboard!");
    });
}

function toggleComments() {
    const section = document.getElementById('commentSection');
    section.style.display = (section.style.display === 'none' || section.style.display === '') ? 'block' : 'none';
}


// Function for Online Status Feature (Admin View)
function updateStatus() {
    // Send a request to the server in the background
    fetch('../api/activity_handler.php')
        .then(response => response.json()) // Tell JS to expect JSON back
        .then(data => {
            console.log("Activity updated:", data.status);
        })
        .catch(error => console.error('Error updating status:', error));
}

//  Run it immediately when the page loads
updateStatus();

//  Run it every 2 minutes (120000 milliseconds) 
// This keeps the user "Online" even if they are just reading a long post
setInterval(updateStatus, 120000);

var statusTimerInterval;

function confirmUpdate() {
    const modal = document.getElementById('confirmModal');
    const timerSpan = document.getElementById('timer');
    const yesBtn = document.getElementById('yesBtn');
    
    modal.style.display = 'block';
    let timeLeft = 5;
    
    // Reset button state
    yesBtn.disabled = true;
    yesBtn.style.background = '#ccc';
    yesBtn.style.cursor = 'not-allowed';
    timerSpan.innerText = timeLeft;

    statusTimerInterval = setInterval(() => {
        timeLeft--;
        timerSpan.innerText = timeLeft;
        if (timeLeft <= 0) {
            clearInterval(statusTimerInterval);
            yesBtn.disabled = false;
            yesBtn.style.background = '#3a86ff'; // Accent blue from your global.css
            yesBtn.style.color = 'white';
            yesBtn.style.cursor = 'pointer';
            yesBtn.innerText = "Yes, Save Changes";
        }
    }, 1000);

    yesBtn.onclick = function() {
        document.getElementById('adminUpdateForm').submit();
    };
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
    clearInterval(statusTimerInterval);
}

function confirmRemoval(communityName) {
    return confirm("Are you sure you want to remove this user from '" + communityName + "'? This action cannot be undone.");
}



// view_community_detail.php

// Right click logic
function showCustomMenu(e) {
    e.preventDefault();
    let menu = document.getElementById('contextMenu');
    menu.style.display = 'block';
    menu.style.left = e.pageX + 'px';
    menu.style.top = e.pageY + 'px';
}
function deleteProfilePic() {
    if(confirm("Permanently remove community profile picture?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../api/admin_update_community.php';
        const idInput = document.createElement('input');
        idInput.name = 'community_id';
        idInput.value = '<?= $community_id ?>';
        const actionInput = document.createElement('input');
        actionInput.name = 'delete_pic';
        actionInput.value = '1';
        form.appendChild(idInput);
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}





