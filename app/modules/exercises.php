<?php
declare(strict_types=1);

function exercisesPage(int $personal, int $id, bool $post): void
{
    $exercise = $id ? (query('SELECT * FROM exercises WHERE id = ? AND personal_id = ?', [$id, $personal])->fetch() ?: missing()) : null;
    if ($post) {
        if ($exercise && field('action', 20, false) === 'delete') {
            try {
                query('DELETE FROM exercises WHERE id = ? AND personal_id = ?', [$id, $personal]);
            } catch (PDOException $error) {
                if (($error->errorInfo[1] ?? null) === 1451) {
                    throw new InvalidArgumentException('Este exercício está vinculado a uma ficha de treino.');
                }
                throw $error;
            }
            notice('Exercício excluído.', '?page=exercises');
        }
        $name = field('name', 120);
        $group = field('muscle_group', 100);
        $url = field('video_url', 500, false);
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) ||
            !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            throw new InvalidArgumentException('Informe um link de vídeo HTTP ou HTTPS válido.');
        }
        if ($exercise) {
            query('UPDATE exercises SET name = ?, muscle_group = ?, video_url = ? WHERE id = ? AND personal_id = ?', [$name, $group, $url, $id, $personal]);
        } else {
            query('INSERT INTO exercises (name, muscle_group, video_url, personal_id) VALUES (?, ?, ?, ?)', [$name, $group, $url, $personal]);
        }
        notice('Exercício salvo.', '?page=exercises');
    }
    $rows = query('SELECT * FROM exercises WHERE personal_id = ? ORDER BY muscle_group, name', [$personal])->fetchAll();
    ?>
    <header><h1>Exercícios</h1><p>Organize seu banco de exercícios por grupo muscular.</p></header>
    <section class="panel"><h2><?= $exercise ? 'Editar exercício' : 'Cadastrar exercício' ?></h2>
    <form method="post" class="grid">
        <?php csrf(); ?>
        <label>Nome <input name="name" maxlength="120" required value="<?= e($exercise['name'] ?? '') ?>"></label>
        <label>Grupo muscular <input name="muscle_group" maxlength="100" required value="<?= e($exercise['muscle_group'] ?? '') ?>"></label>
        <label class="wide">Link do vídeo <input type="url" name="video_url" maxlength="500" value="<?= e($exercise['video_url'] ?? '') ?>"></label>
        <div class="actions"><button>Salvar exercício</button><?php if ($exercise): ?><a href="?page=exercises">Cancelar</a><?php endif; ?></div>
    </form>
    <?php if ($exercise): ?>
    <form method="post" data-confirm="Excluir este exercício?"><?php csrf(); ?><button class="danger" name="action" value="delete">Excluir exercício</button></form>
    <?php endif; ?>
    </section>
    <section class="panel"><h2>Banco de exercícios</h2>
    <?php if (!$rows): ?><p>Nenhum exercício cadastrado.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Grupo muscular</th><th>Exercício</th><th>Vídeo</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
    <tr><td><?= e($row['muscle_group']) ?></td><td><?= e($row['name']) ?></td>
    <td><?php videoLink($row['video_url']); ?></td><td><a href="?page=exercises&id=<?= $row['id'] ?>">Editar</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    </section>
    <?php
}
