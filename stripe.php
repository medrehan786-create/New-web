<?php
/**
 * Stripe Payment API Handler
 * URL: http://localhost:8080/stripe.php?lista=card&site=site.com
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Initialize response array
$response = array(
    'success' => false,
    'message' => '',
    'data' => array(),
    'nonces_captured' => array(),
    'request_data' => array()
);

// Function to capture all nonces from request
function captureAllNonces() {
    $nonces = array();
    
    // WooCommerce nonces
    $woocommerce_nonces = array(
        'woocommerce-register-nonce',
        'woocommerce-login-nonce',
        'woocommerce-reset-password-nonce',
        'woocommerce-edit-account-nonce',
        'woocommerce-cart-nonce',
        'woocommerce-checkout-nonce',
        'woocommerce-process-checkout-nonce',
        'woocommerce-apply-coupon-nonce',
        'woocommerce-remove-coupon-nonce',
        'woocommerce-update-shipping-method-nonce',
        'woocommerce-dismiss-notice-nonce',
        'woocommerce-clear-notices-nonce',
        'woocommerce-ajax-nonce',
        'wc-frontend-nonce',
        'wc-country-select-nonce',
        'woocommerce-add-payment-method-nonce',
        'woocommerce-set-payment-method-nonce',
        'woocommerce-payment-method-nonce',
        'add-payment-method-nonce'
    );
    
    // Stripe nonces
    $stripe_nonces = array(
        'stripe-add-payment-method-nonce',
        'wc-stripe-payment-nonce',
        'wc-stripe-setup-intent-nonce',
        'wc-stripe-payment-intent-nonce',
        'wc-stripe-confirm-payment-nonce',
        'wc-stripe-create-payment-method-nonce',
        'wc-stripe-update-payment-method-nonce',
        'stripe-confirm-card-payment-nonce',
        'stripe-create-payment-intent-nonce',
        'stripe-handle-card-payment-nonce',
        'stripe-handle-payment-method-nonce',
        'wc-stripe-create-setup-intent-nonce',
        'wc-stripe-confirm-setup-intent-nonce',
        'stripe-create-subscription-nonce',
        'stripe-update-subscription-nonce',
        '_stripe_nonce',
        'stripe-payment-nonce',
        'stripe-ajax-nonce',
        'stripe-secret-nonce'
    );
    
    // Combine all nonces
    $all_nonces = array_merge($woocommerce_nonces, $stripe_nonces);
    
    // Capture nonces from GET parameters
    foreach ($all_nonces as $nonce_name) {
        if (isset($_GET[$nonce_name])) {
            $nonces[$nonce_name] = $_GET[$nonce_name];
        }
    }
    
    // Capture nonces from POST parameters
    foreach ($all_nonces as $nonce_name) {
        if (isset($_POST[$nonce_name])) {
            $nonces[$nonce_name] = $_POST[$nonce_name];
        }
    }
    
    // Capture from headers
    $headers = getallheaders();
    foreach ($all_nonces as $nonce_name) {
        $header_name = str_replace('_', '-', $nonce_name);
        if (isset($headers[$header_name])) {
            $nonces[$nonce_name] = $headers[$header_name];
        }
    }
    
    return $nonces;
}

// Function to validate card data
function validateCardData($card_data) {
    $errors = array();
    
    if (empty($card_data['number']) || !preg_match('/^\d{13,19}$/', $card_data['number'])) {
        $errors[] = 'Invalid card number';
    }
    
    if (empty($card_data['exp_month']) || !preg_match('/^(0[1-9]|1[0-2])$/', $card_data['exp_month'])) {
        $errors[] = 'Invalid expiration month';
    }
    
    if (empty($card_data['exp_year']) || !preg_match('/^\d{4}$/', $card_data['exp_year'])) {
        $errors[] = 'Invalid expiration year';
    }
    
    if (empty($card_data['cvc']) || !preg_match('/^\d{3,4}$/', $card_data['cvc'])) {
        $errors[] = 'Invalid CVC';
    }
    
    return $errors;
}

// Function to process Stripe payment
function processStripePayment($card_data, $site) {
    // Simulate Stripe API call
    // In real implementation, you would use Stripe PHP SDK
    
    $stripe_response = array(
        'id' => 'pi_' . uniqid(),
        'object' => 'payment_intent',
        'amount' => 1000, // $10.00
        'currency' => 'usd',
        'status' => 'succeeded',
        'charges' => array(
            'data' => array(
                array(
                    'id' => 'ch_' . uniqid(),
                    'payment_method_details' => array(
                        'card' => array(
                            'brand' => getCardBrand($card_data['number']),
                            'last4' => substr($card_data['number'], -4),
                            'exp_month' => $card_data['exp_month'],
                            'exp_year' => $card_data['exp_year']
                        )
                    )
                )
            )
        )
    );
    
    return $stripe_response;
}

// Function to determine card brand
function getCardBrand($card_number) {
    $first_digit = substr($card_number, 0, 1);
    
    switch ($first_digit) {
        case '4': return 'Visa';
        case '5': return 'MasterCard';
        case '3': return 'American Express';
        case '6': return 'Discover';
        default: return 'Unknown';
    }
}

// Main execution
try {
    // Capture all request data
    $request_method = $_SERVER['REQUEST_METHOD'];
    $get_params = $_GET;
    $post_params = $_POST;
    
    $response['request_data'] = array(
        'method' => $request_method,
        'get_params' => $get_params,
        'post_params' => $post_params,
        'headers' => getallheaders()
    );
    
    // Capture all nonces
    $captured_nonces = captureAllNonces();
    $response['nonces_captured'] = $captured_nonces;
    
    // Check if lista parameter is present
    if (!isset($_GET['lista']) || $_GET['lista'] !== 'card') {
        throw new Exception('Missing or invalid lista parameter. Use ?lista=card');
    }
    
    // Get site parameter
    $site = isset($_GET['site']) ? $_GET['site'] : 'unknown-site.com';
    $response['site'] = $site;
    
    // Process based on request method
    if ($request_method === 'POST') {
        // Handle POST request for card processing
        
        // Get card data from POST
        $card_data = array(
            'number' => isset($_POST['card_number']) ? str_replace(' ', '', $_POST['card_number']) : '',
            'exp_month' => isset($_POST['exp_month']) ? $_POST['exp_month'] : '',
            'exp_year' => isset($_POST['exp_year']) ? $_POST['exp_year'] : '',
            'cvc' => isset($_POST['cvc']) ? $_POST['cvc'] : '',
            'name' => isset($_POST['card_holder']) ? $_POST['card_holder'] : ''
        );
        
        // Validate card data
        $validation_errors = validateCardData($card_data);
        
        if (!empty($validation_errors)) {
            throw new Exception('Card validation failed: ' . implode(', ', $validation_errors));
        }
        
        // Process Stripe payment
        $stripe_result = processStripePayment($card_data, $site);
        
        $response['success'] = true;
        $response['message'] = 'Card processed successfully';
        $response['payment_result'] = $stripe_result;
        $response['card_info'] = array(
            'brand' => getCardBrand($card_data['number']),
            'last4' => substr($card_data['number'], -4),
            'exp_month' => $card_data['exp_month'],
            'exp_year' => $card_data['exp_year']
        );
        
    } else {
        // Handle GET request - show available endpoints and captured data
        $response['success'] = true;
        $response['message'] = 'Stripe API is running. Use POST method to process cards.';
        $response['available_endpoints'] = array(
            'GET' => 'Show API information and captured nonces',
            'POST' => 'Process card payment',
            'required_parameters' => array(
                'lista=card',
                'site=your-site.com'
            ),
            'post_parameters' => array(
                'card_number',
                'exp_month', 
                'exp_year',
                'cvc',
                'card_holder (optional)'
            )
        );
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
    $response['error_details'] = $e->getTraceAsString();
}

// Log the request (optional)
file_put_contents('stripe_api_log.txt', 
    date('Y-m-d H:i:s') . " - " . 
    json_encode($response) . "\n", 
    FILE_APPEND | LOCK_EX
);

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Stripe API Tester</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { padding: 8px; width: 100%; max-width: 300px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer; }
        .result { margin-top: 20px; padding: 15px; background: #f5f5f5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Stripe API Tester</h1>
        
        <form id="cardForm" method="POST">
            <div class="form-group">
                <label>Card Number:</label>
                <input type="text" name="card_number" placeholder="4242 4242 4242 4242" required>
            </div>
            
            <div class="form-group">
                <label>Expiration Month:</label>
                <input type="text" name="exp_month" placeholder="12" required>
            </div>
            
            <div class="form-group">
                <label>Expiration Year:</label>
                <input type="text" name="exp_year" placeholder="2024" required>
            </div>
            
            <div class="form-group">
                <label>CVC:</label>
                <input type="text" name="cvc" placeholder="123" required>
            </div>
            
            <div class="form-group">
                <label>Card Holder Name (optional):</label>
                <input type="text" name="card_holder" placeholder="John Doe">
            </div>
            
            <button type="submit">Process Card</button>
        </form>
        
        <div class="result">
            <h3>API Response:</h3>
            <pre id="apiResponse"><?php 
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    echo json_encode($response, JSON_PRETTY_PRINT);
                }
            ?></pre>
        </div>
        
        <div class="result">
            <h3>Test URLs:</h3>
            <ul>
                <li><a href="/stripe.php?lista=card&site=test.com">Basic Test</a></li>
                <li><a href="/stripe.php?lista=card&site=test.com&woocommerce-register-nonce=test123">With Nonce Test</a></li>
            </ul>
        </div>
    </div>

    <script>
        // AJAX form submission
        document.getElementById('cardForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('lista', 'card');
            formData.append('site', 'test-site.com');
            
            fetch('/stripe.php?lista=card&site=test-site.com', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('apiResponse').textContent = JSON.stringify(data, null, 2);
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>