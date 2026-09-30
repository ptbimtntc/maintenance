function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

/**
 * Drag-and-drop for the Task Planner board (see tasks/plan.blade.php): drop a
 * card into any bucket, and its final position among that bucket's cards is
 * saved via tasks.move. The DOM is moved optimistically; a failed save
 * reloads the page so the board never shows an order the server doesn't have.
 */
function initTaskBoard() {
    const board = document.getElementById('task-board');

    if (!board) {
        return;
    }

    const moveUrl = (id) => board.dataset.moveUrlTemplate.replace('__ID__', id);
    let dragged = null;

    board.addEventListener('dragstart', (event) => {
        dragged = event.target.closest('[data-task-id]');
        dragged?.classList.add('opacity-50');
        event.dataTransfer.effectAllowed = 'move';
    });

    board.addEventListener('dragend', () => {
        dragged?.classList.remove('opacity-50');
        dragged = null;
    });

    board.addEventListener('dragover', (event) => {
        const zone = event.target.closest('[data-bucket-drop]');

        if (!zone || !dragged) {
            return;
        }

        event.preventDefault();

        const after = [...zone.querySelectorAll('[data-task-id]:not(.opacity-50)')]
            .find((card) => event.clientY < card.getBoundingClientRect().top + card.offsetHeight / 2);

        zone.insertBefore(dragged, after ?? null);
    });

    board.addEventListener('drop', async (event) => {
        const zone = event.target.closest('[data-bucket-drop]');

        if (!zone || !dragged) {
            return;
        }

        event.preventDefault();

        const position = [...zone.querySelectorAll('[data-task-id]')].indexOf(dragged);

        try {
            const response = await fetch(moveUrl(dragged.dataset.taskId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ task_bucket_id: zone.dataset.bucketDrop, position }),
            });

            if (!response.ok) {
                throw new Error('move failed');
            }
        } catch {
            window.location.reload();
        }
    });
}

initTaskBoard();
