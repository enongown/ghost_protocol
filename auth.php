<?php
header("Content-Type: application/json");

$roll = isset($_POST["rollNo"]) ? $_POST["rollNo"] : "";
$password = isset($_POST["password"]) ? $_POST["password"] : "";

if (!preg_match("/^[A-Za-z0-9]+$/", $roll)) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Invalid roll number"]);
  exit;
}

$path = __DIR__ . "/users/" . $roll . ".json";
if (!is_file($path)) {
  http_response_code(404);
  echo json_encode(["ok" => false, "error" => "No account for that roll number"]);
  exit;
}

$data = json_decode(file_get_contents($path), true);
$user = isset($data["user"]) ? $data["user"] : $data;

if (!isset($user["password"]) || $user["password"] !== $password || $user["rollNo"] !== $roll) {
  http_response_code(401);
  echo json_encode(["ok" => false, "error" => "Wrong roll number or password"]);
  exit;
}

unset($user["password"]);
echo json_encode(["ok" => true, "user" => $user]);