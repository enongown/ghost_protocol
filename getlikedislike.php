<?php
header("Content-Type: application/json");

$path = __DIR__ . "/likes.json";
$data = ["posts" => []];
if (is_file($path)) {
  $saved = json_decode(file_get_contents($path), true);
  if (is_array($saved) && isset($saved["posts"])) $data = $saved;
}

function counts($posts) {
  $out = [];
  foreach ($posts as $id => $row) {
    $out[$id] = [
      "likes" => count(isset($row["likes"]) ? $row["likes"] : []),
      "dislikes" => count(isset($row["dislikes"]) ? $row["dislikes"] : [])
    ];
  }
  return $out;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  echo json_encode(["ok" => true, "votes" => counts($data["posts"])]);
  exit;
}

$roll = isset($_POST["rollNo"]) ? $_POST["rollNo"] : "";
$id = isset($_POST["id"]) ? $_POST["id"] : "";
$vote = isset($_POST["vote"]) ? $_POST["vote"] : "";
if (!preg_match("/^[A-Za-z0-9]+$/", $roll) || !preg_match("/^[A-Za-z0-9-]+$/", $id)) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Invalid roll number or post"]);
  exit;
}
if ($vote !== "like" && $vote !== "dislike") {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Vote must be like or dislike"]);
  exit;
}

if (!isset($data["posts"][$id])) $data["posts"][$id] = ["likes" => [], "dislikes" => []];
$row = $data["posts"][$id];
unset($row["likes"][$roll], $row["dislikes"][$roll]);
$row[$vote === "like" ? "likes" : "dislikes"][$roll] = true;
$data["posts"][$id] = $row;
file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT) . "\n");

echo json_encode(["ok" => true, "votes" => counts($data["posts"])]);
