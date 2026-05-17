<?php
header('Content-Type: application/json; charset=UTF-8');

function loadEnv() {
    $path = __DIR__ . '/../api/.env';
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
    return $env;
}

$env = loadEnv();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') http_response_code(405) || die(json_encode(['ok'=>false]));
if (empty($env['MIIBO_API_KEY'])) http_response_code(500) || die(json_encode(['ok'=>false, 'error'=>'Config missing']));

$input = json_decode(file_get_contents('php://input'), true);
$message = mb_substr(strip_tags($input['message'] ?? ''), 0, 10000);
$uid = $input['uid'] ?? '';
$settings = $input['settings'] ?? null;

function sanitize($text) { return preg_replace('/[^\P{C}\n\r\t]/u', '', $text); }

if (empty($uid)) {
    $uid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    $utterance = "あなたはステーキ専門のソムリエです。ユーザーの設定情報:\n" . 
                 "肉: {$settings['meat']}\n味: {$settings['taste']}\n辛さ: {$settings['spice']}\n\n" .
                 "ユーザー: " . $message;
} else {
    $utterance = $message;
}

$ch = curl_init('https://api-mebo.dev/api');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'api_key' => $env['MIIBO_API_KEY'],
    'agent_id' => $env['MIIBO_AGENT_ID'],
    'utterance' => sanitize($utterance),
    'uid' => $uid
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo json_encode(['ok' => true, 'answer' => $data['bestResponse']['utterance'], 'uid' => $uid]);
} else {
    echo json_encode(['ok' => false, 'error' => 'API Error']);
}
?>