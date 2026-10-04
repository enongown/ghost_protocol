<?php
// api.php — persists to hackathon.json + uploads/
header('Content-Type: application/json; charset=utf-8');

const DATA_FILE  = __DIR__ . '/datas/hackathon.json';   // fixed: was "hakathon.json"
const UPLOAD_DIR = __DIR__ . '/uploads/';
const ADMIN_KEY  = 'change-me';                   // change this before use
const MAX_SIZE   = 5 * 1024 * 1024;
const ALLOWED    = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];

// The 8 opportunity types we now support
const TYPES = ['Hackathon','Internship','Scholarship','Research',
               'Coding Contest','Workshop','Fellowship','Competition'];

function out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function clean($v, $max = 300) {
    return mb_substr(trim(strip_tags((string)$v)), 0, $max);
}

// keep the wrapper shape your hackathon.json already uses
function empty_db() {
    return ['opportunities' => [], 'subs' => [], 'posts' => [], 'mod' => '', 'applications' => []];
}

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

if (!file_exists(DATA_FILE) || json_decode(file_get_contents(DATA_FILE), true) === null) {
    file_put_contents(DATA_FILE, json_encode(empty_db(), JSON_PRETTY_PRINT));
}

function update(callable $fn) {
    $fp = fopen(DATA_FILE, 'c+');
    flock($fp, LOCK_EX);
    $db = json_decode(stream_get_contents($fp), true);
    if (!is_array($db)) $db = empty_db();
    $db += empty_db(); // backfill missing top-level keys
    $result = $fn($db);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

function save_upload($field = 'file') {
    if (empty($_FILES[$field]['name'])) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) out(['error' => 'The file could not be uploaded.'], 400);
    if ($f['size'] > MAX_SIZE) out(['error' => 'The file is larger than 5 MB.'], 400);
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED, true)) {
        out(['error' => 'Allowed file types: ' . implode(', ', ALLOWED) . '.'], 400);
    }
    $stored = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $stored)) {
        out(['error' => 'The file could not be saved.'], 500);
    }
    return ['name' => clean($f['name'], 100), 'path' => 'uploads/' . $stored];
}

function new_id() { return (int)(microtime(true) * 1000); }

function csv_list($v, $max = 20) {
    $parts = array_filter(array_map('clean', explode(',', (string)$v)));
    return array_values(array_slice($parts, 0, $max));
}

// Accept only one of the 8 known types; fall back to Hackathon
function safe_type($t) {
    $t = clean($t, 40);
    return in_array($t, TYPES, true) ? $t : 'Hackathon';
}

function safe_url($u) {
    $u = clean($u, 300);
    return preg_match('#^https?://#i', $u) ? $u : '';
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$adminActions = ['add_post','set_status','delete_post','set_mod',
                 'add_hackathon','add_opportunity','delete_hackathon','admin_list'];
if (in_array($action, $adminActions, true)) {
    if (!hash_equals(ADMIN_KEY, (string)($_POST['key'] ?? ''))) {
        out(['error' => 'Wrong admin key.'], 403);
    }
}

switch ($action) {

    case 'list':
        out(json_decode(file_get_contents(DATA_FILE), true) ?: empty_db());

    // -------- user post --------
    case 'add_sub':
        $branch   = clean($_POST['branch'] ?? '', 40);
        $year     = clean($_POST['year'] ?? '', 2);
        $skills   = array_slice(array_filter(array_map('clean', explode(',', $_POST['skill'] ?? ''))), 0, 15);
        $interest = clean($_POST['interest'] ?? '');
        $elig     = clean($_POST['elig'] ?? '', 40);
        $type     = safe_type($_POST['type'] ?? 'Hackathon');
        $link     = safe_url($_POST['link'] ?? '');

        if (!$branch || !$year || !$skills || !$interest) {
            out(['error' => 'Fill in branch, year, skills and interest.'], 400);
        }
        $sub = [
            'id' => new_id(), 'branch' => $branch, 'year' => $year,
            'skills' => array_values($skills), 'interest' => $interest,
            'elig' => $elig, 'type' => $type, 'link' => $link,
            'status' => 'pending', 'file' => save_upload('file'),
        ];
        update(function (&$db) use ($sub) { array_unshift($db['subs'], $sub); });
        out(['ok' => true, 'id' => $sub['id']]);

    // -------- user applies to a specific opportunity --------
    case 'apply':
        $oppId = clean($_POST['opportunityId'] ?? '', 60);
        $subId = (int)($_POST['subId'] ?? 0);
        $name  = clean($_POST['name'] ?? '', 80);
        $note  = clean($_POST['note'] ?? '', 500);
        if (!$oppId || !$subId || !$name) out(['error' => 'Fill in every field.'], 400);
        $app = [
            'id' => new_id(), 'opportunityId' => $oppId, 'subId' => $subId,
            'name' => $name, 'note' => $note, 'submittedAt' => new_id(),
            'file' => save_upload('file'),
        ];
        update(function (&$db) use ($app) { array_unshift($db['applications'], $app); });
        out(['ok' => true, 'id' => $app['id']]);

    // -------- admin: create a plain post --------
    case 'add_post':
        $title    = clean($_POST['title'] ?? '', 120);
        $body     = clean($_POST['body'] ?? '', 2000);
        $deadline = (string)($_POST['deadline'] ?? '');
        if (!$title || !$body || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            out(['error' => 'Add a title, description and deadline.'], 400);
        }
        $post = [
            'id' => new_id(), 'title' => $title, 'body' => $body,
            'branch' => clean($_POST['branch'] ?? 'All', 40),
            'year' => clean($_POST['year'] ?? 'All', 3),
            'skills' => csv_list($_POST['skills'] ?? '', 15),
            'deadline' => $deadline, 'file' => save_upload('file'),
        ];
        update(function (&$db) use ($post) { array_unshift($db['posts'], $post); });
        out(['ok' => true]);

    // -------- admin: add an opportunity (any of the 8 types) --------
    case 'add_opportunity':
    case 'add_hackathon': // legacy alias
        $title   = clean($_POST['title'] ?? '', 140);
        $deadline= (string)($_POST['deadlineToRegister'] ?? '');
        if (!$title || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            out(['error' => 'Add a title and a valid registration deadline.'], 400);
        }
        $type = safe_type($_POST['type'] ?? 'Hackathon');
        $hack = [
            'id'                 => strtolower(substr($type, 0, 4)) . '-' . substr(bin2hex(random_bytes(4)), 0, 6),
            'title'              => $title,
            'organizer'          => clean($_POST['organizer'] ?? ''),
            'type'               => $type,
            'matchHint'          => 0,
            'deadlineToRegister' => $deadline,
            'eventDates'         => clean($_POST['eventDates'] ?? '', 80),
            'location'           => clean($_POST['location'] ?? '', 80),
            'prize'              => clean($_POST['prize'] ?? '', 120),
            'registrationLink'   => safe_url($_POST['registrationLink'] ?? ''),
            'branch'             => csv_list($_POST['branch'] ?? 'Any'),
            'year'               => csv_list($_POST['year'] ?? ''),
            'skills'             => csv_list($_POST['skills'] ?? ''),
            'interests'          => csv_list($_POST['interests'] ?? ''),
            'eligibility'        => clean($_POST['eligibility'] ?? '', 600),
        ];
        update(function (&$db) use ($hack) { array_unshift($db['opportunities'], $hack); });
        out(['ok' => true, 'id' => $hack['id']]);

    // -------- admin: delete an opportunity --------
    case 'delete_hackathon':
        $id = (string)($_POST['id'] ?? '');
        if ($id === '') out(['error' => 'Missing id.'], 400);
        update(function (&$db) use ($id) {
            $db['opportunities'] = array_values(array_filter(
                $db['opportunities'], fn($o) => ($o['id'] ?? '') !== $id
            ));
        });
        out(['ok' => true]);

    // -------- moderator --------
    case 'set_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['approved','rejected'], true)) out(['error' => 'Invalid status.'], 400);
        update(function (&$db) use ($id, $status) {
            foreach ($db['subs'] as &$s) if ($s['id'] === $id) $s['status'] = $status;
        });
        out(['ok' => true]);

    case 'delete_post':
        $id = (int)($_POST['id'] ?? 0);
        update(function (&$db) use ($id) {
            foreach ($db['posts'] as $p) {
                if ($p['id'] === $id && !empty($p['file']['path'])) @unlink(__DIR__ . '/' . $p['file']['path']);
            }
            $db['posts'] = array_values(array_filter($db['posts'], fn($p) => $p['id'] !== $id));
        });
        out(['ok' => true]);

    case 'set_mod':
        $name = clean($_POST['name'] ?? '', 80);
        update(function (&$db) use ($name) { $db['mod'] = $name; });
        out(['ok' => true]);

    case 'admin_list':
        $db = json_decode(file_get_contents(DATA_FILE), true) ?: empty_db();
        out(['applications' => $db['applications'] ?? []]);

    default:
        out(['error' => 'Unknown action.'], 400);
}