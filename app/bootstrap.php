<?php
declare(strict_types=1);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo');

function db(): PDO
{
    static $pdo;
    return $pdo ??= new PDO(
        getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=fitcontrol;charset=utf8mb4',
        getenv('DB_USER') ?: 'fitcontrol',
        (string) getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function query(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function transaction(callable $action): void
{
    db()->beginTransaction();
    try {
        $action();
        db()->commit();
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function field(string $name, int $max, bool $required = true): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('Campo inválido.');
    }
    $value = trim($value);
    if (($required && $value === '') || strlen($value) > $max) {
        throw new InvalidArgumentException('Confira os campos obrigatórios e seus limites de tamanho.');
    }
    return $value;
}

function dateField(string $name): ?string
{
    $value = field($name, 10, false);
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') {
        throw new InvalidArgumentException('Informe uma data válida.');
    }
    return $value;
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function missing(): never
{
    http_response_code(404);
    exit('Registro não encontrado.');
}

function ownedStudent(int $id, int $personal, bool $lock = false): array
{
    $row = query(
        'SELECT s.*, u.name, u.email FROM students s JOIN users u ON u.id = s.id
         WHERE s.id = ? AND s.personal_id = ?' . ($lock ? ' FOR UPDATE' : ''),
        [$id, $personal]
    )->fetch();
    return $row ?: missing();
}

function csrf(): void
{
    echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function notice(string $message, string $url): never
{
    $_SESSION['notice'] = $message;
    redirect($url);
}

function passwordHash(string $password): string
{
    if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new InvalidArgumentException('A senha deve ter entre 8 e 72 bytes.');
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

function dateLabel(?string $date): string
{
    return $date ? (new DateTimeImmutable($date))->format('d/m/Y') : '—';
}
