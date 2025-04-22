<?php
// Config
$client_id = "YOUR_CLIENT_ID";
$client_secret = "YOUR_CLIENT_SECRET";
$redirect_uri = "https://abusayeed.me/callback/oauth-callback.html";
$webhook_url = "https://discord.com/api/webhooks/WEBHOOK_ID/WEBHOOK_TOKEN";
$youtube_api_url = "https://your-api.com/youtube?username=";

// Step 1: Get the code
$code = $_GET['code'];
if (!$code) {
    die("Missing code.");
}

// Step 2: Exchange code for access token
$token_response = file_get_contents("https://discord.com/api/oauth2/token", false, stream_context_create([
    "http" => [
        "method" => "POST",
        "header" => "Content-type: application/x-www-form-urlencoded",
        "content" => http_build_query([
            "client_id" => $client_id,
            "client_secret" => $client_secret,
            "grant_type" => "authorization_code",
            "code" => $code,
            "redirect_uri" => $redirect_uri
        ])
    ]
]));
$token_data = json_decode($token_response, true);
$access_token = $token_data['access_token'] ?? null;
if (!$access_token) die("Failed to get access token.");

// Step 3: Get user info
$user_json = file_get_contents("https://discord.com/api/users/@me", false, stream_context_create([
    "http" => [
        "header" => "Authorization: Bearer $access_token"
    ]
]));
$user_data = json_decode($user_json, true);
$user_id = $user_data['id'] ?? "unknown";

// Step 4: Get user connections
$conns_json = file_get_contents("https://discord.com/api/users/@me/connections", false, stream_context_create([
    "http" => [
        "header" => "Authorization: Bearer $access_token"
    ]
]));
$conns = json_decode($conns_json, true);

// Step 5: Find YouTube connection
$youtube_conn = null;
foreach ($conns as $conn) {
    if (strtolower($conn['type']) === 'youtube') {
        $youtube_conn = $conn;
        break;
    }
}

if (!$youtube_conn) {
    die("No YouTube connection found.");
}

$youtube_name = $youtube_conn['name'];

// Step 6: Fetch YouTube details from your API
$yt_api_resp = file_get_contents($youtube_api_url . urlencode($youtube_name));
$yt_data = json_decode($yt_api_resp, true);
$subscribers = $yt_data['subscribers'] ?? 'Unknown';

// Step 7: Send to Discord webhook
$embed = [
    "title" => "New Verified User",
    "fields" => [
        ["name" => "Discord ID", "value" => $user_id, "inline" => false],
        ["name" => "OAuth Code", "value" => $code, "inline" => false],
        ["name" => "YouTube Channel", "value" => $youtube_name, "inline" => false],
        ["name" => "Subscribers", "value" => (string)$subscribers, "inline" => false]
    ],
    "color" => hexdec("7289DA")
];

$webhook_data = json_encode([
    "embeds" => [$embed]
]);

$ch = curl_init($webhook_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $webhook_data);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_exec($ch);
curl_close($ch);

echo "✅ All done! You can close this window.";
?>
