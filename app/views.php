<?php
declare(strict_types=1);

function videoLink(string $url): void
{
    if ($url !== '') {
        echo '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">Ver vídeo</a>';
    } else {
        echo '—';
    }
}

function studentPicker(string $page, int $personal): void
{
    $students = query('SELECT s.id, u.name FROM students s JOIN users u ON u.id = s.id WHERE s.personal_id = ? ORDER BY u.name', [$personal])->fetchAll();
    ?>
    <section class="panel"><h2>Selecione um aluno</h2>
    <?php if (!$students): ?><p><a href="?page=students">Cadastre um aluno</a> para continuar.</p><?php else: ?>
    <ul class="student-list"><?php foreach ($students as $student): ?>
        <li><a href="?page=<?= e($page) ?>&amp;id=<?= $student['id'] ?>"><?= e($student['name']) ?> <span aria-hidden="true">→</span></a></li>
    <?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php
}
