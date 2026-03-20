<?php
header('Content-Type: application/json');
include 'db.php';

// 1. SETTINGS
// IMPORTANT: Keep your API Key secret. Do not share this file publicly.
$apiKey = "AIzaSyDypVSjFX8w2606glZ2KoDOhLwZlv0OKzg"; 

// UPDATED: Using the stable v1 endpoint and the newer gemini-2.5-flash model
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(["status" => "error", "message" => "No data received"]);
    exit;
}

$mood = $input['mood'] ?? 'Normal';
$context = $input['context'] ?? 'Quiet';
$note = $input['note'] ?? '';
$lang = $input['lang'] ?? 'vi';

// 2. THE PROMPT
if ($lang === 'vi') {
    $prompt = "Bạn là chuyên gia hành vi chó. Trả lời bằng tiếng Việt thân thiện (Sen/Cún). 
    Cún đang $mood và $context. Ghi chú: $note. 
    1. Cún đang nghĩ gì? 2. Lời khuyên cho Sen (1-2 câu).";
} else {
    $prompt = "You are a dog behavior expert. Respond in friendly English (Owner/Pup). 
    The pup is $mood and $context. Note: $note. 
    1. What is the pup thinking? 2. Advice for owner (1-2 sentences).";
}

// 3. API CALL (Updated Payload Structure)
$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 300
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$result = json_decode($response, true);

// 4. HANDLING THE RESPONSE
if ($httpCode !== 200) {
    $errMsg = $result['error']['message'] ?? "Error code: " . $httpCode . " " . $curlError;
    echo json_encode(["status" => "error", "message" => "Google API Error: " . $errMsg]);
    exit;
}

// Extract the text safely
if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $aiText = $result['candidates'][0]['content']['parts'][0]['text'];
} else {
    $aiText = "Cún đang suy nghĩ... (No response from AI)";
}

// 5. SAVE TO DB
try {
    $stmt = $conn->prepare("INSERT INTO dog_logs (mood, context, note, ai_response, language) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $mood, $context, $note, $aiText, $lang);
    $stmt->execute();
} catch (Exception $e) { 
    // Silently fail DB if needed, but we could log it for debugging
}

echo json_encode(["status" => "success", "ai_response" => $aiText]);
?>