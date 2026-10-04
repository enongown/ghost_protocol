<?php
header("Content-Type: application/json");

$roll = isset($_POST["rollNo"]) ? $_POST["rollNo"] : "";
$id = isset($_POST["id"]) ? $_POST["id"] : "";
$action = isset($_POST["action"]) ? $_POST["action"] : "";

if (!preg_match("/^[A-Za-z0-9]+$/", $roll) || !preg_match("/^[A-Za-z0-9-]+$/", $id)) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Invalid roll number or id"]);
  exit;
}
if ($action !== "pin" && $action !== "unpin") {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Action must be pin or unpin"]);
  exit;
}

$path = __DIR__ . "/users/" . $roll . ".json";
if (!is_file($path)) {
  http_response_code(404);
  echo json_encode(["ok" => false, "error" => "No account for that roll number"]);
  exit;
}

$data = json_decode(file_get_contents($path), true);
if (!is_array($data) || !isset($data["user"])) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "Bad user file"]);
  exit;
}

$user = $data["user"];
if (!isset($user["pinned"]) || !is_array($user["pinned"])) {
  $user["pinned"] = ["ids" => []];
}
$ids = isset($user["pinned"]["ids"]) && is_array($user["pinned"]["ids"]) ? $user["pinned"]["ids"] : [];

if ($action === "pin") {
  if (!in_array($id, $ids, true)) $ids[] = $id;
} else {
  $ids = array_values(array_filter($ids, function ($item) use ($id) {
    return $item !== $id;
  }));
}

$user["pinned"]["ids"] = $ids;
$data["user"] = $user;
file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

echo json_encode(["ok" => true, "ids" => $ids]);
