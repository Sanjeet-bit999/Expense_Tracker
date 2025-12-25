<?php
session_start();

// ----------------- DATABASE CONNECTION -----------------
$host = 'localhost';
$dbname = 'expense_db';
$user = 'root';
$pass = '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die(json_encode(['error' => 'DB connection failed']));
}

// ----------------- CREATE TABLES IF NOT EXIST -----------------
$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255)
)");

$db->exec("
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    item VARCHAR(100),
    amount DECIMAL(10,2),
    category VARCHAR(50),
    created_at DATETIME,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// ----------------- HELPER FUNCTIONS -----------------
function json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// ----------------- GET ACTION -----------------
$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

switch ($action) {

    // ---------------- REGISTER ----------------
    case 'register':
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (!$username || !$password) {
            json_response(['ok' => false, 'error' => 'Missing username or password']);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hash]);

            $_SESSION['user_id'] = $db->lastInsertId();

            json_response([
                'ok' => true,
                'username' => $username,
                'profilePic' => null
            ]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => 'Username already exists']);
        }
        break;

    // ---------------- LOGIN ----------------
    case 'login':
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (!$username || !$password) {
            json_response(['ok' => false, 'error' => 'Missing username or password']);
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE username=?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            json_response([
                'ok' => true,
                'username' => $user['username'],
                'profilePic' => null
            ]);
        } else {
            json_response(['ok' => false, 'error' => 'Invalid username or password']);
        }
        break;

    // ---------------- LOGOUT ----------------
    case 'logout':
        session_destroy();
        json_response(['ok' => true]);
        break;

    // ---------------- ADD EXPENSE ----------------
    case 'add':
        $uid = get_user_id();
        if (!$uid) json_response(['ok' => false, 'error' => 'Not logged in']);

        $item = trim($data['item'] ?? '');
        $amount = floatval($data['amount'] ?? 0);
        $category = trim($data['category'] ?? '');

        if (!$item || !$amount || !$category) {
            json_response(['ok' => false, 'error' => 'All fields are required']);
        }

        $stmt = $db->prepare("INSERT INTO expenses (user_id, item, amount, category, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$uid, $item, $amount, $category]);

        json_response(['ok' => true]);
        break;

    // ---------------- LIST EXPENSES ----------------
    case 'list':
        $uid = get_user_id();
        if (!$uid) json_response(['error' => 'Not logged in']);

        $stmt = $db->prepare("SELECT * FROM expenses WHERE user_id=? ORDER BY id DESC");
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response($rows);
        break;

    // ---------------- GET SINGLE EXPENSE ----------------
    case 'get':
        $uid = get_user_id();
        if (!$uid) json_response(['error' => 'Not logged in']);

        $id = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM expenses WHERE id=? AND user_id=?");
        $stmt->execute([$id, $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        json_response($row);
        break;

    // ---------------- UPDATE EXPENSE ----------------
    case 'update':
        $uid = get_user_id();
        if (!$uid) json_response(['ok' => false, 'error' => 'Not logged in']);

        parse_str(file_get_contents('php://input'), $data);

        $stmt = $db->prepare("UPDATE expenses SET item=?, amount=?, category=? WHERE id=? AND user_id=?");
        $stmt->execute([$data['item'], $data['amount'], $data['category'], $data['id'], $uid]);

        json_response(['ok' => true]);
        break;

    // ---------------- DELETE EXPENSE ----------------
    case 'delete':
        $uid = get_user_id();
        if (!$uid) json_response(['ok' => false, 'error' => 'Not logged in']);

        parse_str(file_get_contents('php://input'), $data);

        $stmt = $db->prepare("DELETE FROM expenses WHERE id=? AND user_id=?");
        $stmt->execute([$data['id'], $uid]);

        json_response(['ok' => true]);
        break;

    // ---------------- EXPORT CSV ----------------
    case 'export':
        $uid = get_user_id();
        if (!$uid) {
            header("HTTP/1.1 403 Forbidden");
            exit;
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="expenses.csv"');

        $stmt = $db->prepare("SELECT item, amount, category, created_at FROM expenses WHERE user_id=?");
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = fopen('php://output', 'w');

        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
        }

        foreach ($rows as $row) {
            fputcsv($out, $row);
        }

        fclose($out);
        exit;

    default:
        json_response(['error' => 'Invalid action']);
}
?>