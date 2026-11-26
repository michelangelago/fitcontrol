document.addEventListener('submit', event => {
    const message = event.submitter?.dataset.confirm || event.target.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

const workout = document.querySelector('[data-workout]');
if (workout) {
    const items = workout.querySelector('[data-items]');
    const template = workout.querySelector('[data-template]');
    const add = workout.querySelector('[data-add]');
    let nextIndex = items.children.length;

    const updateButtons = () => {
        add.disabled = items.children.length >= 100;
        for (const button of items.querySelectorAll('[data-remove]')) {
            button.disabled = items.children.length <= 1;
        }
    };

    add.addEventListener('click', () => {
        const row = template.content.cloneNode(true);
        for (const field of row.querySelectorAll('[name]')) {
            field.name = field.name.replace('__INDEX__', nextIndex);
        }
        nextIndex++;
        items.append(row);
        items.lastElementChild.querySelector('select').focus();
        updateButtons();
    });

    items.addEventListener('click', event => {
        if (event.target.matches('[data-remove]')) {
            event.target.closest('[data-item]').remove();
            add.focus();
            updateButtons();
        }
    });
    updateButtons();
}
