<?php
$overview = query('SELECT
    (SELECT COUNT(*) FROM students WHERE personal_id = ?) AS students,
    (SELECT COUNT(*) FROM workouts w JOIN students s ON s.id = w.student_id
        WHERE s.personal_id = ? AND w.active_student_id IS NOT NULL) AS workouts,
    (SELECT COUNT(*) FROM payments p JOIN students s ON s.id = p.student_id
        WHERE s.personal_id = ? AND p.paid_on IS NULL) AS payments',
    [$user['id'], $user['id'], $user['id']])->fetch();
?>
<header><h1>Visão geral</h1><p>Treinos e recebimentos em um só lugar.</p></header>
<div class="metrics">
    <a class="panel metric" href="?page=students"><span>Alunos cadastrados</span><strong><?= e($overview['students']) ?></strong></a>
    <a class="panel metric" href="?page=workouts"><span>Fichas ativas</span><strong><?= e($overview['workouts']) ?></strong></a>
    <a class="panel metric" href="?page=payments"><span>Mensalidades em aberto</span><strong><?= e($overview['payments']) ?></strong></a>
</div>
<section class="panel"><h2>Gestão da academia</h2>
<div class="shortcuts">
    <a href="?page=students"><strong>Cadastrar aluno</strong><span>Dados pessoais, anamnese e acesso</span></a>
    <a href="?page=exercises"><strong>Gerenciar exercícios</strong><span>Grupos musculares e vídeos</span></a>
    <a href="?page=workouts"><strong>Montar ficha</strong><span>Treinos A, B e C</span></a>
    <a href="?page=payments"><strong>Lançar mensalidade</strong><span>Vencimentos e pagamentos</span></a>
</div></section>
