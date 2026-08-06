<?php
session_start();
require_once('../includes/db.php');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: ../public/login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Statistics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #3ea6ff; --bg: #f0f4f8; --success: #00d27a; --danger: #ff4757; }
        body { display: flex; background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; }
        .main-content { flex: 1; margin-left: 260px; padding: 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .filter-group { background: white; padding: 10px; border-radius: 12px; display: flex; gap: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .select-input { border: 1px solid #ddd; padding: 8px; border-radius: 6px; font-weight: 600; }
        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .card p { color: #888; font-size: 11px; font-weight: 700; text-transform: uppercase; margin: 0; }
        .card h3 { font-size: 28px; margin: 10px 0 0; }
        .chart-box { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); margin-bottom: 25px; }
        .chart-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 25px; }
        .btn-apply { background: var(--primary); color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 700; }
        .cat-tab { border: none; background: #eee; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .cat-tab.active { background: var(--primary); color: white; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <div><h1>Platform Analytics</h1><p>Real-time data visualization</p></div>
            <div class="filter-group">
                <select id="sel-year" class="select-input"><?php for($y=date('Y'); $y>=2024; $y--) echo "<option value='$y'>$y</option>"; ?></select>
                <select id="sel-month" class="select-input">
                    <option value="all">Full Year</option>
                    <?php for($m=1; $m<=12; $m++) echo "<option value='$m'>".date('F', mktime(0,0,0,$m,1))."</option>"; ?>
                </select>
                <button class="btn-apply" onclick="updateDashboard()">Update</button>
            </div>
        </header>

        <div class="kpi-row">
            <div class="card"><p>🧑‍🎓 New Members</p><h3 id="val-users">0</h3></div>
            <div class="card"><p>🏘️ Active Communities</p><h3 id="val-coms">0</h3></div>
            <div class="card"><p>✅ Resolved Reports</p><h3 id="val-reps" style="color:var(--success)">0</h3></div>
            <div class="card"><p>📈 Total Activities</p><h3 id="val-act">0</h3></div>
        </div>

        <div class="chart-grid">
            <div class="chart-box"><h4>User Growth</h4><canvas id="growthChart" height="110"></canvas></div>
            <div class="chart-box"><h4>Community Split</h4><canvas id="donutChart"></canvas></div>
        </div>

        <div class="chart-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="chart-box">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h4 style="margin:0;">Category Trends</h4>
                    <div style="display:flex; gap:5px;">
                        <button class="cat-tab active" onclick="changeTab('all', this)">All</button>
                        <button class="cat-tab" onclick="changeTab('club', this)">Clubs</button>
                        <button class="cat-tab" onclick="changeTab('study_group', this)">Study</button>
                    </div>
                </div>
                <canvas id="categoryChart" height="150"></canvas>
            </div>
            <div class="chart-box"><h4>Activities Flow (Posts vs Comments)</h4><canvas id="activityChart" height="150"></canvas></div>
        </div>

        <div class="chart-box">
            <h4>Report Status Analysis (Total vs Resolved)</h4>
            <canvas id="reportCompChart" height="100"></canvas>
        </div>
    </div>

    <script>
        let charts = {};
        let currentType = 'all';

        function initCharts() {
            charts.growth = new Chart(document.getElementById('growthChart'), { type: 'line', data: { labels: [], datasets: [{ label: 'Signups', data: [], borderColor: '#3ea6ff', fill: true, tension: 0.4 }] } });
            charts.donut = new Chart(document.getElementById('donutChart'), { type: 'doughnut', data: { labels: [], datasets: [{ data: [], backgroundColor: ['#3ea6ff', '#00d27a'] }] }, options: { cutout: '75%' } });
            charts.category = new Chart(document.getElementById('categoryChart'), { type: 'bar', data: { labels: [], datasets: [{ data: [], backgroundColor: '#3ea6ff' }] }, options: { indexAxis: 'y', plugins: { legend: { display: false } } } });
            
            // Updated Dataset Labels: Posts vs Comments
            charts.activity = new Chart(document.getElementById('activityChart'), { 
                type: 'line', 
                data: { 
                    labels: [], 
                    datasets: [
                        { label: 'Posts', data: [], borderColor: '#ffa502', tension: 0.3 }, 
                        { label: 'Comments', data: [], borderColor: '#3ea6ff', tension: 0.3 }
                    ] 
                } 
            });
            
            charts.reportComp = new Chart(document.getElementById('reportCompChart'), { 
                type: 'bar', 
                data: { 
                    labels: [], 
                    datasets: [
                        { label: 'Total Reports', data: [], backgroundColor: '#ff4757' },
                        { label: 'Resolved Reports', data: [], backgroundColor: '#00d27a' }
                    ] 
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } } }
            });
        }

        function changeTab(type, btn) {
            document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentType = type;
            updateDashboard();
        }

        async function updateDashboard() {
            const y = document.getElementById('sel-year').value;
            const m = document.getElementById('sel-month').value;
            const res = await fetch(`get_analytics_data.php?year=${y}&month=${m}&filter_type=${currentType}`);
            const data = await res.json();

            document.getElementById('val-users').innerText = data.users;
            document.getElementById('val-coms').innerText = data.communities;
            document.getElementById('val-reps').innerText = data.reports;
            document.getElementById('val-act').innerText = data.activity;

            charts.growth.data.labels = data.labels; charts.growth.data.datasets[0].data = data.growth; charts.growth.update();
            charts.donut.data.labels = data.types; charts.donut.data.datasets[0].data = data.type_counts; charts.donut.update();
            charts.category.data.labels = data.cat_labels; charts.category.data.datasets[0].data = data.cat_values; charts.category.update();
            
            // Updated mapping: posts and comments
            charts.activity.data.labels = data.labels; 
            charts.activity.data.datasets[0].data = data.posts;
            charts.activity.data.datasets[1].data = data.comments; // Changed from investigations to comments
            charts.activity.update();

            charts.reportComp.data.labels = data.labels;
            charts.reportComp.data.datasets[0].data = data.total_reports_trend;
            charts.reportComp.data.datasets[1].data = data.investigations;
            charts.reportComp.update();
        }

        window.onload = () => { initCharts(); updateDashboard(); };
    </script>
</body>
</html>