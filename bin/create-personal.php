<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
[$script, $name, $email] = array_pad($argv, 3, '');
$email = strtolower(trim($email));
if (trim($name) === '' || strlen($name) > 120 || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php bin/create-personal.php <nome> <email>\n");
    exit(1);
}
fwrite(STDERR, "Informe a senha pela entrada padrão: ");
$password = rtrim((string) fgets(STDIN), "\r\n");
try {
    query("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'personal')",
        [trim($name), $email, passwordHash($password)]);
    echo "Personal cadastrado.\n";
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} catch (PDOException $error) {
    fwrite(STDERR, "Falha ao cadastrar. Confira a conexão e se o e-mail já existe.\n");
    exit(1);
}
