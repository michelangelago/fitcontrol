<?php
declare(strict_types=1);

function studentsPage(int $personal, int $id, bool $post): void
{
    $student = $id ? ownedStudent($id, $personal) : null;
    if ($post) {
        if ($student && field('action', 20, false) === 'delete') {
            query('DELETE u FROM users u JOIN students s ON s.id = u.id WHERE s.id = ? AND s.personal_id = ?', [$id, $personal]);
            notice('Aluno excluído.', '?page=students');
        }
        $name = field('name', 120);
        $email = strtolower(field('email', 190));
        $phone = field('phone', 40, false);
        $birth = dateField('birth_date');
        $anamnesis = field('anamnesis', 12000, false);
        $day = filter_var(field('due_day', 2), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$day || ($birth && $birth > date('Y-m-d'))) {
            throw new InvalidArgumentException('Confira o e-mail, a data de nascimento e o dia de vencimento.');
        }
        $password = $_POST['password'] ?? '';
        if (!is_string($password)) {
            throw new InvalidArgumentException('Senha inválida.');
        }
        $hash = (!$student || $password !== '') ? passwordHash($password) : null;

        transaction(function () use ($id, $personal, $student, $name, $email, $hash, $phone, $birth, $anamnesis, $day) {
            if ($student) {
                ownedStudent($id, $personal, true);
                query('UPDATE users SET name = ?, email = ?, password_hash = COALESCE(?, password_hash) WHERE id = ?', [$name, $email, $hash, $id]);
                query('UPDATE students SET phone = ?, birth_date = ?, anamnesis = ?, due_day = ? WHERE id = ?', [$phone, $birth, $anamnesis, $day, $id]);
            } else {
                query("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'aluno')", [$name, $email, $hash]);
                query('INSERT INTO students (id, personal_id, phone, birth_date, anamnesis, due_day) VALUES (?, ?, ?, ?, ?, ?)',
                    [(int) db()->lastInsertId(), $personal, $phone, $birth, $anamnesis, $day]);
            }
        });
        notice('Cadastro salvo.', '?page=students');
    }
    $students = query('SELECT s.id, u.name, u.email, s.due_day FROM students s JOIN users u ON u.id = s.id WHERE s.personal_id = ? ORDER BY u.name', [$personal])->fetchAll();
    ?>
    <header><h1>Alunos</h1><p>Gerencie cadastros, dados de saúde e vencimentos.</p></header>
    <section class="panel">
    <h2><?= $student ? 'Editar aluno' : 'Cadastrar aluno' ?></h2>
    <form method="post" class="grid">
        <?php csrf(); ?>
        <label>Nome <input name="name" required maxlength="120" value="<?= e($student['name'] ?? '') ?>"></label>
        <label>E-mail <input type="email" name="email" required maxlength="190" value="<?= e($student['email'] ?? '') ?>"></label>
        <label>Telefone <input type="tel" name="phone" maxlength="40" value="<?= e($student['phone'] ?? '') ?>"></label>
        <label>Nascimento <input type="date" name="birth_date" max="<?= date('Y-m-d') ?>" value="<?= e($student['birth_date'] ?? '') ?>"></label>
        <label>Dia do vencimento <input type="number" name="due_day" min="1" max="31" required value="<?= e($student['due_day'] ?? '') ?>"></label>
        <label><?= $student ? 'Nova senha (opcional)' : 'Senha de acesso' ?> <input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" <?= $student ? '' : 'required' ?>></label>
        <label class="wide">Anamnese <textarea name="anamnesis" rows="4" maxlength="12000"><?= e($student['anamnesis'] ?? '') ?></textarea></label>
        <div class="actions"><button>Salvar aluno</button><?php if ($student): ?><a href="?page=students">Cancelar</a><?php endif; ?></div>
    </form>
    <?php if ($student): ?>
    <form method="post" data-confirm="Excluir este aluno e todo seu histórico de treinos e mensalidades?">
        <?php csrf(); ?><button class="danger" name="action" value="delete">Excluir aluno</button>
    </form>
    <?php endif; ?>
    </section>
    <section class="panel"><h2>Lista de alunos</h2>
    <?php if (!$students): ?><p>Nenhum aluno cadastrado.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Vencimento</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($students as $row): ?>
        <tr><td><?= e($row['name']) ?></td><td><?= e($row['email']) ?></td><td>Dia <?= e($row['due_day']) ?></td>
        <td class="links"><a href="?page=students&id=<?= $row['id'] ?>">Editar</a>
        <a href="?page=workouts&id=<?= $row['id'] ?>">Treinos</a>
        <a href="?page=payments&id=<?= $row['id'] ?>">Mensalidades</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    </section>
    <?php
}
