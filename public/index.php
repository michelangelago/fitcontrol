<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/views.php';
require __DIR__ . '/../app/modules/students.php';
require __DIR__ . '/../app/modules/exercises.php';
require __DIR__ . '/../app/modules/workouts.php';
require __DIR__ . '/../app/modules/payments.php';

function routeId(string $name): int
{
    if (!array_key_exists($name, $_GET)) {
        return 0;
    }
    $id = filter_var($_GET[$name], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
    return $id ?: missing();
}

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax',
    'secure' => getenv('APP_SECURE_COOKIE') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");

$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf']))) {
    http_response_code(403);
    exit('Sessão inválida. Recarregue a página.');
}

$user = null;
$page = '';
$error = null;
ob_start();
try {
    if (isset($_SESSION['user_id'])) {
        $user = query('SELECT id, name, role FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch() ?: null;
        if (!$user) {
            unset($_SESSION['user_id']);
        }
    }
    $page = $_GET['page'] ?? ($user ? ($user['role'] === 'personal' ? 'home' : 'my-workout') : 'login');
    if (!is_string($page)) {
        missing();
    }
    if ($page === 'logout' && $post && $user) {
        $_SESSION = [];
        session_destroy();
        redirect('./');
    }
    if (!$user && $page !== 'login') {
        redirect('./');
    }
    if (!$user) {
        if ($post) {
            $email = strtolower(field('email', 190));
            $password = $_POST['password'] ?? '';
            $account = query('SELECT id, password_hash FROM users WHERE email = ?', [$email])->fetch();
            if (!is_string($password) || strlen($password) > 72 || !$account || !password_verify($password, $account['password_hash'])) {
                throw new InvalidArgumentException('E-mail ou senha inválidos.');
            }
            session_regenerate_id(true);
            $_SESSION = ['user_id' => $account['id'], 'csrf' => bin2hex(random_bytes(32))];
            redirect('./');
        }
    } elseif ($user['role'] === 'aluno') {
        if ($post || !in_array($page, ['my-workout', 'my-payments'], true)) {
            missing();
        }
        if ($page === 'my-workout') {
            echo '<header><h1>Minha ficha</h1><p>Consulte seu treino ativo.</p></header>';
            activeWorkout($user['id']);
        } else {
            echo '<header><h1>Minhas mensalidades</h1><p>Consulte sua situação financeira.</p></header>';
            paymentHistory($user['id']);
        }
    } else {
        switch ($page) {
            case 'home':
                require __DIR__ . '/../app/dashboard.php';
                break;
            case 'students':
                studentsPage($user['id'], routeId('id'), $post);
                break;
            case 'exercises':
                exercisesPage($user['id'], routeId('id'), $post);
                break;
            case 'workouts':
                workoutsPage($user['id'], routeId('id'), $post);
                break;
            case 'payments':
                paymentsPage($user['id'], routeId('id'), $post);
                break;
            default:
                missing();
        }
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    $error = $exception->getMessage();
} catch (PDOException $exception) {
    if (($exception->errorInfo[1] ?? null) === 1062) {
        http_response_code(409);
        $error = match ($page) {
            'students' => 'Já existe uma conta com esse e-mail.',
            'payments' => 'Já existe uma mensalidade para esse mês.',
            default => 'Já existe um registro com esses dados.',
        };
    } else {
        error_log((string) $exception);
        http_response_code(500);
        $error = 'Não foi possível concluir a operação. Tente novamente.';
    }
}
$content = ob_get_clean();
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);
$navigation = ($user['role'] ?? '') === 'personal'
    ? ['home' => 'Visão geral', 'students' => 'Alunos', 'exercises' => 'Exercícios', 'workouts' => 'Treinos', 'payments' => 'Mensalidades']
    : ['my-workout' => 'Minha ficha', 'my-payments' => 'Mensalidades'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($navigation[$page] ?? 'Acesso') ?> | Fitcontrol</title>
<link rel="stylesheet" href="style.css">
<script src="app.js" defer></script>
</head>
<body class="<?= $user ? 'signed-in' : 'signed-out' ?>">
<aside>
    <a class="brand" href="./"><span aria-hidden="true">F</span> Fitcontrol</a>
    <?php if ($user): ?>
    <nav aria-label="Menu principal">
        <?php foreach ($navigation as $key => $label): ?>
        <a href="?page=<?= $key ?>" <?= $key === $page ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="account"><strong><?= e($user['name']) ?></strong><small><?= $user['role'] === 'personal' ? 'Personal' : 'Aluno' ?></small>
    <form method="post" action="?page=logout"><?php csrf(); ?><button class="secondary">Sair</button></form></div>
    <?php endif; ?>
</aside>
<main>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if (!$user): ?>
<section class="panel login"><h1>Acesse sua conta</h1>
<form method="post" action="./">
    <?php csrf(); ?>
    <label>E-mail <input required name="email" type="email" autocomplete="username" maxlength="190"></label>
    <label>Senha <input required name="password" type="password" autocomplete="current-password" maxlength="72"></label>
    <button>Entrar</button>
</form></section>
<?php elseif ($error): ?>
<a class="button secondary" href="<?= e('./?' . http_build_query($_GET)) ?>">Voltar ao formulário</a>
<?php else: ?>
<?= $content ?>
<?php endif; ?>
</main>
</body>
</html>
