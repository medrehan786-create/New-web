<?php
// killer.php
require_once __DIR__ . '/config.php';
require_login();

// Get user ID from session (config.php uses 'uid' not 'user_id')
$user_id = $_SESSION['uid'];
$user = load_user_by_id($user_id);

// Handle form submission
$result = null;
$error = null;
$card_data = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['card_data'])) {
    $card_data = trim($_POST['card_data']);
    
    // Validate card format (number|month|year|CVV)
    if (!preg_match('/^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/', $card_data)) {
        $error = "Invalid card format. Please use: number|month|year|CVV";
    } else {
        // Check if user has enough credits
        if ($user['credits'] < 100) {
            $error = "Insufficient credits. You need 100 credits to check a card.";
        } else {
            // Check the card
            $api_result = check_card($card_data);
            
            if (isset($api_result['error'])) {
                $error = $api_result['error'];
            } else {
                $result = $api_result;
                
                // Deduct 100 credits regardless of result
                $new_credits = change_credits($user_id, -100);
                
                // Update user data with new credit balance
                $user['credits'] = $new_credits;
            }
        }
    }
}

// Check if user can check a card (has enough credits)
$can_check = $user['credits'] >= 100;

// HTML escape function
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Killer System</title>
    <style>
        :root {
            --primary: #6c5ce7;
            --secondary: #a29bfe;
            --dark: #2d3436;
            --light: #f5f6fa;
            --success: #00b894;
            --danger: #d63031;
            --warning: #fdcb6e;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        
        .header {
            background: var(--primary);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            background: var(--secondary);
            color: white;
            padding: 15px 20px;
        }
        
        .content {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5649c5;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
            background: #f8f9fa;
        }
        
        .success {
            background: #e8f5e9;
            border-left: 4px solid var(--success);
        }
        
        .error {
            background: #ffebee;
            border-left: 4px solid var(--danger);
        }
        
        .info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
        
        .result-title {
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .response-list {
            list-style: none;
            margin-top: 10px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .response-list li {
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-family: monospace;
            font-size: 14px;
        }
        
        .response-list li:nth-child(odd) {
            background: #f9f9f9;
        }
        
        .footer {
            text-align: center;
            padding: 15px;
            color: #777;
            font-size: 14px;
        }
        
        .cooldown {
            background: #fff3cd;
            border-left: 4px solid var(--warning);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Card Killer System</h1>
        </div>
        
        <div class="user-info">
            <div>User: <?php echo e($user['username'] ?? $user['id']); ?></div>
            <div>Credits: <?php echo e($user['credits']); ?></div>
        </div>
        
        <div class="content">
            <?php if (!$can_check): ?>
                <div class="cooldown">Insufficient credits. You need 100 credits to check a card.</div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="card_data">Card Data (format: number|month|year|CVV)</label>
                    <input type="text" id="card_data" name="card_data" value="<?php echo e($card_data); ?>" 
                           placeholder="1234567812345678|12|2025|123" required>
                </div>
                
                <button type="submit" class="btn" <?php echo (!$can_check) ? 'disabled' : ''; ?>>Check Card (100 credits)</button>
            </form>
            
            <?php if ($error): ?>
                <div class="result error">
                    <div class="result-title">Error</div>
                    <p><?php echo e($error); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($result): ?>
                <div class="result <?php echo $result['killed'] ? 'success' : 'info'; ?>">
                    <div class="result-title">
                        <?php echo $result['killed'] ? 'Card Killed Successfully!' : 'Card Not Killed'; ?>
                    </div>
                    <p>Declined transactions: <?php echo e($result['declined_count']); ?> out of <?php echo e($result['total_processes']); ?></p>
                    
                    <p>100 credits deducted from your account</p>
                    
                    <?php if (!$result['killed']): ?>
                        <p>Card was not killed (need at least 10 declined transactions)</p>
                    <?php endif; ?>
                    
                    <div class="result-title" style="margin-top: 15px;">API Response:</div>
                    <ul class="response-list">
                        <?php foreach ($result['raw_response'] as $response): ?>
                            <li><?php echo e($response); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Remaining Credits: <?php echo e($user['credits']); ?> | <a href="index.php">Back to Dashboard</a></p>
        </div>
    </div>
</body>
</html>