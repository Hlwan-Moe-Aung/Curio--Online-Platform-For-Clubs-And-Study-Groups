function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById("main");
    const btn = document.querySelector('.collapse-btn span');
    
    sidebar.classList.toggle('collapsed');
    
    if (sidebar.classList.contains('collapsed')) {
        sidebar.style.width = "60px"; 
        if (btn) btn.textContent = 'Maximize';
        if (main) main.classList.add("expanded");
        localStorage.setItem('sidebar-collapsed', 'true'); // Save state
    } else {
        sidebar.style.width = ""; 
        if (btn) btn.textContent = 'Minimize';
        if (main) main.classList.remove("expanded");
        localStorage.setItem('sidebar-collapsed', 'false'); // Save state
    }
}

function toggleSeeMore(btn) {
    const parent = btn.parentElement;
    const hiddenItems = parent.querySelectorAll('.hidden-item');
    const isShowingMore = btn.textContent === "See less";

    if (!isShowingMore) {
        hiddenItems.forEach(item => {
            item.style.display = 'flex';
            item.classList.add('revealed');
        });
        btn.textContent = "See less";
    } else {
        parent.querySelectorAll('.revealed').forEach(item => {
            item.style.display = 'none';
            item.classList.remove('revealed');
        });
        btn.textContent = "See more...";
    }
}

function toggleSection(sectionId, btn) {
    const section = document.getElementById(sectionId);
    if (section.style.display === "none") {
        section.style.display = "block";
        btn.classList.remove("rotated");
        btn.textContent = "▲";
        localStorage.setItem(sectionId + '-collapsed', 'false'); // Save section state
    } else {
        section.style.display = "none";
        btn.classList.add("rotated");
        btn.textContent = "▼";
        localStorage.setItem(sectionId + '-collapsed', 'true'); // Save section state
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const btn = document.querySelector('.collapse-btn span');
    const main = document.getElementById("main");

    // 1. Restore Sidebar State
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        sidebar.classList.add('collapsed');
        sidebar.style.width = "60px";
        if (btn) btn.textContent = 'Maximize';
        if (main) main.classList.add("expanded");
    } else {
        sidebar.classList.remove('collapsed');
        sidebar.style.width = "";
        if (btn) btn.textContent = 'Minimize';
        if (main) main.classList.remove("expanded");
    }

    // 2. Restore Section States (Clubs/Study Groups)
    const sections = ['club-list', 'study-group-list'];
    sections.forEach(id => {
        const section = document.getElementById(id);
        const toggleBtn = document.querySelector(`button[onclick*="${id}"]`);
        
        if (section && localStorage.getItem(id + '-collapsed') === 'true') {
            section.style.display = "none";
            if (toggleBtn) {
                toggleBtn.classList.add("rotated");
                toggleBtn.textContent = "▼";
            }
        }
    });

    // 3. Re-attach your original click listener to call your toggle function
    document.querySelector('.collapse-btn').addEventListener('click', toggleSidebar);
});

