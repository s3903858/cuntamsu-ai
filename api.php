<?php
header('Content-Type: application/json');
include 'db.php';

// 1. SETTINGS
$apiKey = "AIzaSyDypVSjFX8w2606glZ2KoDOhLwZlv0OKzg"; 
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(["status" => "error", "message" => "No data received"]);
    exit;
}

$mood = $input['mood'] ?? 'Normal';
$context = $input['context'] ?? 'Quiet';
$note = $input['note'] ?? '';
$lang = $input['lang'] ?? 'vi';

// 2. THE PROMPT (Flexible for long responses)
$prompt = ($lang === 'vi') 
    ? "Bạn là chuyên gia hành vi chó. Trả lời bằng tiếng Việt thân thiện. Cún đang $mood và $context. Ghi chú: $note. 1. Cún đang nghĩ gì? 2. Lời khuyên chi tiết cho Sen."
    : "You are a dog behavior expert. Respond in friendly English. The pup is $mood and $context. Note: $note. 1. What is the pup thinking? 2. Detailed advice for owner.";

// 3. THE API CALL
$payload = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["temperature" => 0.8, "maxOutputTokens" => 1000]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$result = json_decode($response, true);
curl_close($ch);

// 4. THE ERROR CHECK
if ($httpCode !== 200) {
    $errorMsg = $result['error']['message'] ?? "Google Server Error";
    echo json_encode(["status" => "error", "message" => "Google Error: " . $errorMsg]);
    exit;
}

// 5. GLUE THE PIECES TOGETHER (Prevents truncation)
$aiText = "";
if (isset($result['candidates'][0]['content']['parts'])) {
    foreach ($result['candidates'][0]['content']['parts'] as $part) {
        $aiText .= $part['text'];
    }
}

// 6. SAVE TO DATABASE (Safe Mode)
if (!empty($aiText)) {
    try {
        $stmt = $conn->prepare("INSERT INTO dog_logs (mood, context, note, ai_response, language) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $mood, $context, $note, $aiText, $lang);
        $stmt->execute();
    } catch (Exception $e) { /* Keeps working even if DB fails */ }
}

echo json_encode(["status" => "success", "ai_response" => $aiText]);
?>
