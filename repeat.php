<?php
header("Content-Type: application/json");

function clean_id($v) {
  return preg_match("/^[A-Za-z0-9-]+$/", $v) ? $v : "";
}

$roll = isset($_REQUEST["rollNo"]) ? $_REQUEST["rollNo"] : "";
if (!preg_match("/^[A-Za-z0-9]+$/", $roll)) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Invalid roll number"]);
  exit;
}

$dir = __DIR__ . "/analytics";
if (!is_dir($dir)) mkdir($dir, 0755, true);
$path = $dir . "/" . $roll . ".json";

$data = ["rollNo" => $roll, "posts" => []];
if (is_file($path)) {
  $saved = json_decode(file_get_contents($path), true);
  if (is_array($saved)) $data = $saved + $data;
  if (!isset($data["posts"]) || !is_array($data["posts"])) $data["posts"] = [];
}

$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : "due";

if ($action === "record") {
  $id = clean_id(isset($_POST["id"]) ? $_POST["id"] : "");
  $seconds = (int)(isset($_POST["seconds"]) ? $_POST["seconds"] : 0);
  if ($id === "" || $seconds < 60) {
    echo json_encode(["ok" => true, "saved" => false]);
    exit;
  }
  $now = time();
  $row = isset($data["posts"][$id]) ? $data["posts"][$id] : [
    "viewsOverMinute" => 0,
    "totalSeconds" => 0
  ];
  $row["viewsOverMinute"] = (int)$row["viewsOverMinute"] + 1;
  $row["totalSeconds"] = (int)$row["totalSeconds"] + $seconds;
  $row["lastViewed"] = date("c", $now);
  $row["repeatAfter"] = date("c", $now + 6 * 3600);
  $data["posts"][$id] = $row;
  file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
  echo json_encode(["ok" => true, "saved" => true, "repeatAfter" => $row["repeatAfter"]]);
  exit;
}

$due = [];
$now = time();
foreach ($data["posts"] as $id => $row) {
  if (empty($row["repeatAfter"])) continue;
  if (strtotime($row["repeatAfter"]) <= $now) $due[] = $id;
}
echo json_encode(["ok" => true, "due" => $due, "posts" => $data["posts"]]);
