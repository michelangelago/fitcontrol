<?php
declare(strict_types=1);

function workoutItems(int $workout): array
{
    return query('SELECT i.*, e.name, e.muscle_group, e.video_url FROM workout_items i
        JOIN exercises e ON e.id = i.exercise_id WHERE i.workout_id = ? ORDER BY i.series, i.position', [$workout])->fetchAll();
}

function saveWorkout(int $student, int $personal, int $workout, array $exercises): never
{
    $action = field('action', 20, false);
    $items = [];
    $title = '';
    if (!in_array($action, ['delete', 'activate'], true)) {
        $title = field('title', 120);
        $submitted = $_POST['items'] ?? null;
        if (!is_array($submitted) || count($submitted) < 1 || count($submitted) > 100) {
            throw new InvalidArgumentException('Inclua de 1 a 100 exercícios na ficha.');
        }
        $allowed = array_column($exercises, 'id');
        foreach ($submitted as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Confira os exercícios da ficha.');
            }
            $series = $item['series'] ?? '';
            $exercise = filter_var($item['exercise_id'] ?? '', FILTER_VALIDATE_INT);
            $sets = filter_var($item['sets'] ?? '', FILTER_VALIDATE_INT);
            $reps = $item['reps'] ?? '';
            if (!in_array($series, ['A', 'B', 'C'], true) || !in_array($exercise, $allowed, true) ||
                $sets < 1 || $sets > 999 || !is_string($reps) || trim($reps) === '' || strlen($reps) > 40) {
                throw new InvalidArgumentException('Confira a série, o exercício, as séries e as repetições de cada linha.');
            }
            $items[] = [$series, $exercise, $sets, trim($reps)];
        }
    }
    transaction(function () use ($student, $personal, $workout, $action, $title, $items) {
        ownedStudent($student, $personal, true);
        if ($workout && !query('SELECT id FROM workouts WHERE id = ? AND student_id = ?', [$workout, $student])->fetch()) {
            missing();
        }
        if ($action === 'delete' && $workout) {
            query('DELETE FROM workouts WHERE id = ?', [$workout]);
            return;
        }
        if ($action === 'activate' && !$workout) {
            throw new InvalidArgumentException('Selecione uma ficha para ativar.');
        }
        if ($action === 'activate' || !$workout) {
            query('UPDATE workouts SET active_student_id = NULL WHERE active_student_id = ?', [$student]);
        }
        if ($action === 'activate') {
            query('UPDATE workouts SET active_student_id = ? WHERE id = ?', [$student, $workout]);
            return;
        }
        if ($action === 'delete') {
            throw new InvalidArgumentException('Selecione uma ficha para excluir.');
        }
        if (!$workout) {
            query('INSERT INTO workouts (student_id, title, active_student_id) VALUES (?, ?, ?)', [$student, $title, $student]);
            $workout = (int) db()->lastInsertId();
        } else {
            query('UPDATE workouts SET title = ? WHERE id = ?', [$title, $workout]);
            query('DELETE FROM workout_items WHERE workout_id = ?', [$workout]);
        }
        foreach ($items as $position => $item) {
            query('INSERT INTO workout_items (workout_id, position, series, exercise_id, sets, reps) VALUES (?, ?, ?, ?, ?, ?)',
                [$workout, $position, ...$item]);
        }
    });
    $message = match ($action) {
        'delete' => 'Ficha excluída.',
        'activate' => 'Ficha ativada.',
        default => 'Ficha salva.',
    };
    notice($message, '?page=workouts&id=' . $student);
}

function workoutFields(array $exercises, array $item, string $index): void
{
    ?>
    <div class="workout-row" data-item>
        <label>Treino <select name="items[<?= e($index) ?>][series]">
        <?php foreach (['A', 'B', 'C'] as $series): ?>
        <option <?= ($item['series'] ?? '') === $series ? 'selected' : '' ?>><?= $series ?></option>
        <?php endforeach; ?></select></label>
        <label>Exercício <select required name="items[<?= e($index) ?>][exercise_id]"><option value="">Selecione</option>
        <?php foreach ($exercises as $exercise): ?>
        <option value="<?= $exercise['id'] ?>" <?= ($item['exercise_id'] ?? null) === $exercise['id'] ? 'selected' : '' ?>><?= e($exercise['muscle_group'] . ' · ' . $exercise['name']) ?></option>
        <?php endforeach; ?></select></label>
        <label>Séries <input required type="number" min="1" max="999" name="items[<?= e($index) ?>][sets]" value="<?= e($item['sets'] ?? 3) ?>"></label>
        <label>Repetições <input required maxlength="40" name="items[<?= e($index) ?>][reps]" value="<?= e($item['reps'] ?? '') ?>"></label>
        <button type="button" class="secondary" data-remove>Remover</button>
    </div>
    <?php
}

function workoutsPage(int $personal, int $id, bool $post): void
{
    if (!$id) {
        ?><header><h1>Fichas de treino</h1><p>Monte e renove as fichas dos seus alunos.</p></header><?php
        studentPicker('workouts', $personal);
        return;
    }
    $student = ownedStudent($id, $personal);
    $edit = routeId('workout');
    $renew = $edit ? 0 : routeId('renew');
    $source = $edit ?: $renew;
    $workout = $source ? (query('SELECT * FROM workouts WHERE id = ? AND student_id = ?', [$source, $id])->fetch() ?: missing()) : null;
    $exercises = query('SELECT * FROM exercises WHERE personal_id = ? ORDER BY muscle_group, name', [$personal])->fetchAll();
    if ($post) {
        saveWorkout($id, $personal, $edit, $exercises);
    }
    $history = query('SELECT * FROM workouts WHERE student_id = ? ORDER BY id DESC', [$id])->fetchAll();
    $items = $source ? workoutItems($source) : [[]];
    $base = '?page=workouts&amp;id=' . $id;
    ?>
    <header><h1>Fichas de treino</h1><p><?= e($student['name']) ?> · <a href="?page=workouts">Trocar aluno</a></p></header>
    <section class="panel"><h2><?= $edit ? 'Editar ficha' : ($renew ? 'Renovar ficha' : 'Nova ficha') ?></h2>
    <?php if (!$exercises): ?><p><a href="?page=exercises">Cadastre um exercício</a> para montar uma ficha.</p><?php else: ?>
    <form method="post" data-workout>
        <?php csrf(); ?>
        <label>Título da ficha <input required maxlength="120" name="title" value="<?= e($workout['title'] ?? '') ?>"></label>
        <div data-items><?php foreach ($items as $i => $item) {
            workoutFields($exercises, $item, (string) $i);
        } ?></div>
        <template data-template><?php workoutFields($exercises, [], '__INDEX__'); ?></template>
        <div class="actions"><button type="button" class="secondary" data-add>Adicionar exercício</button>
        <button><?= $edit ? 'Salvar alterações' : ($renew ? 'Salvar renovação e ativar' : 'Salvar e ativar') ?></button>
        <?php if ($source): ?><a href="<?= $base ?>">Cancelar</a><?php endif; ?></div>
    </form>
    <?php endif; ?>
    </section>
    <section class="panel"><h2>Histórico de fichas</h2>
    <?php if (!$history): ?><p>Nenhuma ficha cadastrada.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Ficha</th><th>Criação</th><th>Status</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($history as $row): ?>
    <tr><td><?= e($row['title']) ?></td><td><?= dateLabel($row['created_on']) ?></td>
    <td><span class="pill <?= $row['active_student_id'] ? 'positive' : '' ?>"><?= $row['active_student_id'] ? 'Ativa' : 'Arquivada' ?></span></td>
    <td class="links"><a href="<?= $base ?>&amp;workout=<?= $row['id'] ?>">Editar</a>
    <a href="<?= $base ?>&amp;renew=<?= $row['id'] ?>">Renovar</a>
    <form method="post" action="<?= $base ?>&amp;workout=<?= $row['id'] ?>" class="inline">
        <?php csrf(); ?>
        <?php if (!$row['active_student_id']): ?><button class="text-button" name="action" value="activate">Ativar</button><?php endif; ?>
        <button class="text-button danger" name="action" value="delete" data-confirm="Excluir esta ficha?">Excluir</button>
    </form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    </section>
    <?php
}

function activeWorkout(int $student): void
{
    $workout = query('SELECT * FROM workouts WHERE student_id = ? AND active_student_id = ?', [$student, $student])->fetch();
    if (!$workout) {
        ?><section class="panel"><p>Você ainda não tem uma ficha ativa.</p></section><?php
        return;
    }
    $items = workoutItems($workout['id']);
    ?>
    <section class="panel"><h2><?= e($workout['title']) ?></h2>
    <div class="table-wrap"><table><thead><tr><th>Treino</th><th>Exercício</th><th>Séries</th><th>Repetições</th><th>Vídeo</th></tr></thead><tbody>
    <?php foreach ($items as $item): ?>
    <tr><td><?= e($item['series']) ?></td><td><?= e($item['name']) ?></td><td><?= e($item['sets']) ?></td>
    <td><?= e($item['reps']) ?></td><td><?php videoLink($item['video_url']); ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></section>
    <?php
}
