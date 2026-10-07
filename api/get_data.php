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

function image_path($item) {
  $raw = "";
  if (isset($item["file"]["path"])) $raw = $item["file"]["path"];
  elseif (isset($item["imagePath"])) $raw = $item["imagePath"];
  $raw = str_replace("\\", "/", trim((string)$raw));
  if ($raw === "") return "";
  return ltrim($raw, "/");
}

function with_image($items) {
  $out = [];
  foreach ($items as $item) {
    if (!is_array($item)) continue;
    $item["imagePath"] = image_path($item);
    $out[] = $item;
  }
  return $out;
}

echo json_encode([
  "ok" => true,
  "opportunities" => with_image($data["opportunities"] ?? []),
  "posts" => with_image($data["posts"] ?? []),
  "subs" => with_image($data["subs"] ?? [])
], JSON_UNESCAPED_SLASHES);