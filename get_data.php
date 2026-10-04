<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$path = __DIR__ . "/datas/hackathon.json";
if (!is_file($path)) {
  http_response_code(404);
  echo json_encode(["ok" => false, "error" => "datas/hackathon.json not found"]);
  exit;
}

$raw = file_get_contents($path);
$data = json_decode($raw, true);
if (!is_array($data)) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "Could not read opportunity data"]);
  exit;
}

echo json_encode(["ok" => true, "opportunities" => $data["opportunities"] ?? []]);