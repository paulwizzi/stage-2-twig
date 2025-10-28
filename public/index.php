<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Store;
use App\Validators;

session_start();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false,
]);

$store = new Store(__DIR__ . '/../storage');

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$twig->addGlobal('current_path', $path);

$requireAuth = function(): void {
    if (!isset($_SESSION['user'])) {
        $from = $_SERVER['REQUEST_URI'] ?? '/dashboard';
        redirect('/auth/login?from=' . urlencode($from));
    }
};

switch (true) {
    // Landing
    case $path === '/':
        echo $twig->render('landing.twig', ['user' => $_SESSION['user'] ?? null]);
        break;

    // Auth: Login
    case $path === '/auth/login' && $method === 'GET':
        echo $twig->render('login.twig', [
            'error' => $_GET['error'] ?? null,
            'from' => $_GET['from'] ?? '/dashboard',
            'user' => $_SESSION['user'] ?? null,
        ]);
        break;
    case $path === '/auth/login' && $method === 'POST':
        $email = trim($_POST['email'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        $from = $_POST['from'] ?? '/dashboard';
        if ($email === '' || $pass === '') {
            redirect('/auth/login?error=' . urlencode('Email and password required.') . '&from=' . urlencode($from));
        }
        $user = $store->validateUser($email, $pass);
        if ($user) {
            $_SESSION['user'] = ['email' => $user['email']];
            redirect($from);
        } else {
            redirect('/auth/login?error=' . urlencode('Invalid email or password. Please check your credentials.') . '&from=' . urlencode($from));
        }
        break;

    // Auth: Signup
    case $path === '/auth/signup' && $method === 'GET':
        echo $twig->render('signup.twig', [
            'error' => $_GET['error'] ?? null,
            'user' => $_SESSION['user'] ?? null,
        ]);
        break;
    case $path === '/auth/signup' && $method === 'POST':
        $email = trim($_POST['email'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        if ($email === '' || $pass === '' || strlen($pass) < 8) {
            redirect('/auth/signup?error=' . urlencode('Provide a valid email and password (min 8 chars).'));
        }
        $result = $store->registerUser($email, $pass);
        if (!$result['success']) {
            redirect('/auth/signup?error=' . urlencode($result['error']));
        }
        $_SESSION['user'] = ['email' => $email];
        redirect('/dashboard');
        break;

    // Logout
    case $path === '/auth/logout':
        unset($_SESSION['user']);
        redirect('/auth/login');
        break;

    // Dashboard
    case $path === '/dashboard':
        $requireAuth();
        $tickets = $store->getTickets($_SESSION['user']['email']);
        $total = count($tickets);
        $open = count(array_filter($tickets, fn($t) => $t['status'] === 'open'));
        $inProgress = count(array_filter($tickets, fn($t) => $t['status'] === 'in_progress'));
        $closed = count(array_filter($tickets, fn($t) => $t['status'] === 'closed'));
        echo $twig->render('dashboard.twig', [
            'user' => $_SESSION['user'],
            'stats' => compact('total','open','inProgress','closed'),
        ]);
        break;

    // Tickets index + actions
    case $path === '/tickets' && $method === 'GET':
        $requireAuth();
        $tickets = $store->getTickets($_SESSION['user']['email']);
        echo $twig->render('tickets/index.twig', [
            'user' => $_SESSION['user'],
            'tickets' => array_values($tickets),
        ]);
        break;
    case $path === '/tickets' && $method === 'POST':
        $requireAuth();
        $id = $_POST['id'] !== '' ? intval($_POST['id']) : null;
        $title = trim($_POST['title'] ?? '');
        $status = trim($_POST['status'] ?? 'open');
        $description = trim($_POST['description'] ?? '');
        $errors = Validators::validateTicket([ 'title' => $title, 'status' => $status, 'description' => $description ]);
        if (!empty($errors)) {
            $tickets = $store->getTickets($_SESSION['user']['email']);
            echo $twig->render('tickets/index.twig', [
                'user' => $_SESSION['user'],
                'tickets' => array_values($tickets),
                'form' => [ 'id' => $id, 'title' => $title, 'status' => $status, 'description' => $description ],
                'errors' => $errors,
            ]);
            break;
        }
        if ($id) {
            $store->updateTicket($_SESSION['user']['email'], $id, $title, $status, $description);
        } else {
            $store->createTicket($_SESSION['user']['email'], $title, $status, $description);
        }
        redirect('/tickets');
        break;

    case $path === '/tickets/delete' && $method === 'POST':
        $requireAuth();
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $store->deleteTicket($_SESSION['user']['email'], $id);
        }
        redirect('/tickets');
        break;

    // Import from localStorage
    case $path === '/import' && $method === 'GET':
        echo $twig->render('import.twig', [ 'user' => $_SESSION['user'] ?? null ]);
        break;
    case $path === '/import' && $method === 'POST':
        $payload = file_get_contents('php://input') ?: '';
        $json = json_decode($payload, true);
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            break;
        }
        $users = $json['users'] ?? [];
        $tickets = $json['tickets'] ?? [];
        $session = $json['session'] ?? null;
        if (is_array($users)) $store->replaceUsers($users);
        if (is_array($tickets)) $store->replaceTickets($tickets);
        if (is_array($session) && isset($session['user']['email'])) {
            $_SESSION['user'] = ['email' => (string)$session['user']['email']];
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(302);
        header('Location: /');
}


