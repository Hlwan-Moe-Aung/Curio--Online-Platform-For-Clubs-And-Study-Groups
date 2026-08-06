<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-color: #2563eb;
            --bg-color: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --card-bg: #ffffff;
            --accent: #dbeafe;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }

        .rules-container {
            max-width: 800px;
            width: 100%;
            background: var(--card-bg);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
            border-bottom: 3px solid var(--accent);
            display: inline-block;
        }

        .intro-text {
            color: var(--text-muted);
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .rule-card {
            border-left: 4px solid var(--primary-color);
            background: var(--bg-color);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }

        .rule-card h3 {
            margin: 0 0 5px 0;
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .rule-card p {
            margin: 0;
            font-size: 0.95rem;
        }

        .rule-card .why {
            margin-top: 8px;
            font-style: italic;
            color: var(--text-muted);
            font-size: 0.85rem;
            display: block;
        }

        .process-section {
            margin-top: 40px;
            padding: 20px;
            background: var(--accent);
            border-radius: 8px;
        }

        .process-section h2 {
            margin-top: 0;
            font-size: 1.4rem;
        }

        .step-list {
            padding-left: 20px;
        }

        .step-list li {
            margin-bottom: 10px;
            font-weight: 500;
        }

        .btn-container {
            margin-top: 30px;
            text-align: center;
        }

        .back-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background-color: #1d4ed8;
        }
    </style>
    <title>Community Rules</title>
</head>
<body>

<div class="rules-container">
    <h1 class="page-title">Community Standards</h1>
    <p class="intro-text">To maintain a vibrant platform environment, all clubs and study groups must adhere to these six core rules.</p>

    <div class="rule-card">
        <h3>1. Unique Identity</h3>
        <p>The community name must be distinct and not mimic existing groups.</p>
        <span class="why">Why? Prevents confusion and helps users find exactly what they need.</span>
    </div>

    <div class="rule-card">
        <h3>2. Defined Purpose</h3>
        <p>Provide a mission statement of at least 3 sentences during creation.</p>
        <span class="why">Why? Helps the Admin verify the group's value to the platform.</span>
    </div>

    <div class="rule-card">
        <h3>3. Non-Commercial Use</h3>
        <p>No selling products, services, or running private businesses.</p>
        <span class="why">Why? Keeps the platform focused on community and education.</span>
    </div>

    <div class="rule-card">
        <h3>4. Academic Integrity</h3>
        <p>No sharing exam answers or facilitating cheating in study groups.</p>
        <span class="why">Why? Upholds the University’s Code of Conduct.</span>
    </div>

    <div class="rule-card">
        <h3>5. Active Leadership</h3>
        <p>Leaders must review pending requests (UC-10 & UC-11) within 48 hours.</p>
        <span class="why">Why? Ensures groups remain active and responsive to members.</span>
    </div>

    <div class="rule-card">
        <h3>6. Safety & Respect</h3>
        <p>No hate speech, harassment, or illegal content.</p>
        <span class="why">Why? Safety violations lead to immediate group disbandment.</span>
    </div>

    <div class="process-section">
        <h2>The Approval Process</h2>
        <ol class="step-list">
            <li>Submit creation request form.</li>
            <li>Admin reviews request against the rules above.</li>
            <li>Receive notification of Approval/Rejection.</li>
            <li>If approved, the group becomes active immediately.</li>
        </ol>
    </div>

    <div class="btn-container">
        <a href="#" class="back-btn" onclick="history.back()">Back</a>
    </div>
</div>

</body>
</html>