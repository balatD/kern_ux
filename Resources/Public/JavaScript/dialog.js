/**
 * Dialog behaviour for the KERN dialog component.
 *
 * KERN documents <dialog class="kern-dialog"> and a data-attribute trigger, but ships
 * no JavaScript for it - this is the integrator's one real scripting obligation in
 * the whole design system.
 *
 * Native <dialog>.showModal() is used rather than a hand-rolled overlay, so the focus
 * trap, the inertness of the rest of the page, Escape-to-close and returning focus to
 * the trigger all come from the browser. The only things left to do are opening,
 * closing, and dismissing on a backdrop click.
 */
const TRIGGER = '[data-kernt3-dialog]';
const CLOSE = '[data-kernt3-dialog-close]';

/**
 * @param {Element} element
 * @returns {HTMLDialogElement|null}
 */
function dialogFor(element) {
    const id = element.getAttribute('data-kernt3-dialog');
    if (id === null) {
        return null;
    }
    const dialog = document.getElementById(id);

    return dialog instanceof HTMLDialogElement ? dialog : null;
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const trigger = event.target.closest(TRIGGER);
    if (trigger !== null) {
        const dialog = dialogFor(trigger);
        if (dialog !== null) {
            event.preventDefault();
            dialog.showModal();
        }
        return;
    }

    const closer = event.target.closest(CLOSE);
    if (closer !== null) {
        closer.closest('dialog')?.close();
        return;
    }

    // A click that lands on the dialog element itself - rather than on any of its
    // children - hit the backdrop, because the children cover the whole panel.
    if (event.target instanceof HTMLDialogElement && event.target.open) {
        event.target.close();
    }
});
