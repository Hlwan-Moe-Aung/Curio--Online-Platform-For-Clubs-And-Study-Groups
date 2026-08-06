<?php
function renderReportModal($current_user_email, $manual_club_id = 0) {
    $final_community_id = ($manual_club_id > 0) ? $manual_club_id : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
    ?>
    <div id="reportModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <button type="button" class="btn-back" onclick="closeReportModal()">← Cancel</button>
            <div class="modal-header">
                <h3>Report Content</h3>
            </div>
            
            <div class="form-container">
                <div class="report-notice" style="background: #fff4f4; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.9em; color: #a94442; border: 1px solid #ebccd1;">
                    <strong>Important Notes:</strong>
                    <ul style="margin: 5px 0; padding-left: 20px;">
                        <li>Reports are reviewed by administrators manually.</li>
                        <li>False reporting or platform abuse may lead to account suspension.</li>
                        <li>If you have a link as evidence, please paste it into the description box below.</li>
                    </ul>
                </div>

                <form id="reportForm" action="../api/submit_report_handler.php" method="POST" enctype="multipart/form-data" class="modern-form">
                    <input type="hidden" name="item_id" id="reportItemId">
                    <input type="hidden" name="item_type" id="reportItemType">
                    <input type="hidden" name="community_id" value="<?php echo isset($_GET['id']) ? (int)$_GET['id'] : 0; ?>">
                    <input type="hidden" name="reporter_email" value="<?php echo $current_user_email; ?>">

                    <div class="form-group">
                        <label>Select Reason(s) for Report</label>
                        <div class="report-grid">
    <?php
    $reasons = [
        "Hate Speech & Harassment" => "Attacking or bullying individuals/groups.",
        "Violence & Explicit Content" => "Graphic violence or adult material.",
        "Dangerous Activities" => "Content encouraging self-harm or illegal acts.",
        "Misinformation" => "False or misleading academic information.",
        "Spamming & Automation" => "Irrelevant repeated content or bot-like activity.",
        "Inauthentic Behavior" => "Fake accounts or impersonation.",
        "Security Violations" => "Phishing, hacking, or data privacy leaks.",
        "Intellectual Property" => "Copyright or trademark infringement.",
        "Local Laws" => "Violations of regional/legal regulations."
    ];
    foreach ($reasons as $title => $desc): ?>
        <div class="checkbox-item">
            <input type="checkbox" name="reasons[]" value="<?php echo $title; ?>" id="chk_<?php echo md5($title); ?>">
            <label for="chk_<?php echo md5($title); ?>">
                <span><?php echo $title; ?></span>
                <div class="tooltip-container">
                    <span class="info-icon">ⓘ</span>
                    <span class="tooltip-text"><?php echo $desc; ?></span>
                </div>
            </label>
        </div>
    <?php endforeach; ?>
</div>
                    </div>

                    <div class="form-group">
                        <label for="reportDescription">Description & Evidence Links</label>
                        <textarea id="reportDescription" name="description" rows="4" required placeholder="Explain why this is being reported. Paste any relevant URLs here..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="report_evidence">Attach Screenshot Evidence (Optional)</label>
                        <div class="file-input-wrapper">
                            <div class="drop-zone" onclick="document.getElementById('report_evidence').click()" style="border: 2px dashed #ccc; padding: 20px; text-align: center; cursor: pointer;">
                                <p id="reportDropText" class="drop-text">Click to upload image (.jpg, .png)</p>
                                <input type="file" id="report_evidence" name="report_evidence" accept="image/*" style="display:none;" onchange="updateReportPreview(this)">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" style="background: #d9534f; color: white;">
                        <span>Submit Official Report</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    window.openReportModal = function(id, type) {
        document.getElementById('reportItemId').value = id;
        document.getElementById('reportItemType').value = type;
        document.getElementById('reportModal').style.display = 'flex';
        document.body.classList.add('modal-open'); // Lock background scroll
    };

    window.closeReportModal = function() {
        document.getElementById('reportModal').style.display = 'none';
        document.body.classList.remove('modal-open'); // Unlock background scroll
    };

    function updateReportPreview(input) {
        if (input.files && input.files[0]) {
            document.getElementById('reportDropText').innerText = "Selected: " + input.files[0].name;
        }
    }
    </script>
    <?php
}
?>