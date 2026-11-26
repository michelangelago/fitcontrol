<?php
declare(strict_types=1);

function paymentsPage(int $personal, int $id, bool $post): void
{
    if (!$id) {
        ?><header><h1>Mensalidades</h1><p>Acompanhe os recebimentos dos seus alunos.</p></header><?php
        studentPicker('payments', $personal);
        return;
    }
    $student = ownedStudent($id, $personal);
    $paymentId = routeId('payment');
    $payment = $paymentId ? (query('SELECT * FROM payments WHERE id = ? AND student_id = ?', [$paymentId, $id])->fetch() ?: missing()) : null;
    $url = '?page=payments&id=' . $id;
    if ($post) {
        if ($payment && field('action', 20, false) === 'delete') {
            query('DELETE FROM payments WHERE id = ? AND student_id = ?', [$paymentId, $id]);
            notice('Mensalidade excluída.', $url);
        }
        $month = field('month', 7);
        if (!preg_match('/\A[1-9][0-9]{3}-(0[1-9]|1[0-2])\z/', $month)) {
            throw new InvalidArgumentException('Informe um mês de referência válido.');
        }
        $amount = str_replace(',', '.', field('amount', 11));
        if (!preg_match('/\A[0-9]{1,8}(\.[0-9]{1,2})?\z/', $amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException('Informe um valor positivo com até duas casas decimais.');
        }
        $paidOn = dateField('paid_on');
        if ($paidOn && $paidOn > date('Y-m-d')) {
            throw new InvalidArgumentException('A data de pagamento não pode ser futura.');
        }
        $reference = $month . '-01';
        $lastDay = (int) (new DateTimeImmutable($reference))->format('t');
        $dueDate = $payment && $payment['reference_month'] === $reference
            ? $payment['due_date']
            : sprintf('%s-%02d', $month, min($lastDay, $student['due_day']));
        if ($payment) {
            query('UPDATE payments SET reference_month = ?, due_date = ?, amount = ?, paid_on = ? WHERE id = ? AND student_id = ?',
                [$reference, $dueDate, $amount, $paidOn, $paymentId, $id]);
        } else {
            query('INSERT INTO payments (reference_month, due_date, amount, paid_on, student_id) VALUES (?, ?, ?, ?, ?)',
                [$reference, $dueDate, $amount, $paidOn, $id]);
        }
        notice('Mensalidade salva.', $url);
    }
    ?>
    <header><h1>Mensalidades</h1><p><?= e($student['name']) ?> · Dia <?= e($student['due_day']) ?> · <a href="?page=payments">Trocar aluno</a></p></header>
    <section class="panel"><h2><?= $payment ? 'Editar mensalidade' : 'Lançar mensalidade' ?></h2>
    <form method="post" class="grid">
        <?php csrf(); ?>
        <label>Mês de referência <input type="month" name="month" required value="<?= e(substr($payment['reference_month'] ?? date('Y-m'), 0, 7)) ?>"></label>
        <label>Valor (R$) <input name="amount" inputmode="decimal" required maxlength="11" value="<?= e($payment['amount'] ?? '') ?>"></label>
        <label>Data do pagamento (opcional) <input type="date" name="paid_on" max="<?= date('Y-m-d') ?>" value="<?= e($payment['paid_on'] ?? '') ?>"></label>
        <div class="actions"><button>Salvar mensalidade</button><?php if ($payment): ?><a href="<?= e($url) ?>">Cancelar</a><?php endif; ?></div>
    </form>
    <?php if ($payment): ?>
    <form method="post" data-confirm="Excluir esta mensalidade?"><?php csrf(); ?><button class="danger" name="action" value="delete">Excluir mensalidade</button></form>
    <?php endif; ?>
    </section>
    <?php paymentHistory($id, true);
}

function paymentHistory(int $student, bool $editable = false): void
{
    $rows = query('SELECT * FROM payments WHERE student_id = ? ORDER BY reference_month DESC', [$student])->fetchAll();
    $today = date('Y-m-d');
    ?>
    <section class="panel"><h2>Histórico financeiro</h2>
    <?php if (!$rows): ?><p>Nenhuma mensalidade registrada.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Referência</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Pagamento</th><?php if ($editable): ?><th>Ações</th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($rows as $row):
        $status = $row['paid_on'] ? 'Paga' : ($row['due_date'] < $today ? 'Vencida' : 'Pendente');
        $style = $row['paid_on'] ? 'positive' : ($row['due_date'] < $today ? 'overdue' : '');
    ?>
    <tr><td><?= (new DateTimeImmutable($row['reference_month']))->format('m/Y') ?></td>
    <td><?= dateLabel($row['due_date']) ?></td><td>R$ <?= number_format((float) $row['amount'], 2, ',', '.') ?></td>
    <td><span class="pill <?= $style ?>"><?= $status ?></span></td><td><?= dateLabel($row['paid_on']) ?></td>
    <?php if ($editable): ?><td><a href="?page=payments&amp;id=<?= $student ?>&amp;payment=<?= $row['id'] ?>">Editar</a></td><?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    </section>
    <?php
}
