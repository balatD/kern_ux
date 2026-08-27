/**
 * Disclosure behaviour for the KERN header pattern.
 *
 * KERN documents the header as a pattern: it defines the markup and the
 * aria-expanded / aria-controls wiring but ships no JavaScript, so the behaviour is
 * the integrator's job. This keeps the two in sync and nothing else.
 *
 * Scoped to [data-kernt3-toggle] rather than to every element with aria-controls,
 * so it can never hijack an unrelated disclosure elsewhere on the page.
 */
const TOGGLE = '[data-kernt3-toggle]';

/**
 * aria-controls may reference several ids - the header's menu button controls both
 * navigations at once.
 */
function controlledElements(button) {
    return (button.getAttribute('aria-controls') || '')
        .split(/\s+/)
        .filter(Boolean)
        .map((id) => document.getElementById(id))
        .filter((element) => element !== null);
}

function setExpanded(button, expanded) {
    button.setAttribute('aria-expanded', String(expanded));
    // kern-hidden only applies below the lg breakpoint, where kern-block-lg stops
    // overriding it - so toggling it is a no-op on desktop, which is what we want.
    controlledElements(button).forEach((element) => {
        element.classList.toggle('kern-hidden', !expanded);
    });
}

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest(TOGGLE) : null;
    if (button === null) {
        return;
    }
    setExpanded(button, button.getAttribute('aria-expanded') !== 'true');
});

/**
 * The navigation flyouts open on hover and on :focus-within, both of which are CSS.
 * WCAG 1.4.13 also asks such content to be dismissible without moving the pointer or
 * the focus, and that is the one part CSS cannot express - so Escape marks the open item
 * as dismissed, and the marker is cleared again as soon as hover or focus moves on.
 */
const FLYOUT_ITEM = '.kernt3-nav__item';
const DISMISSED = 'data-kernt3-flyout-dismissed';

function clearDismissed(root = document) {
    root.querySelectorAll(`[${DISMISSED}]`).forEach((item) => item.removeAttribute(DISMISSED));
}

/**
 * The item a flyout is currently open on: whatever holds the focus, or failing that
 * whatever the pointer is over.
 */
function openFlyoutItem() {
    const focused = document.activeElement instanceof Element
        ? document.activeElement.closest(FLYOUT_ITEM)
        : null;
    if (focused !== null) {
        return focused;
    }
    const hovered = document.querySelectorAll(`${FLYOUT_ITEM}:hover`);

    // :hover matches every ancestor, so the last match is the innermost one.
    return hovered.length > 0 ? hovered[hovered.length - 1] : null;
}

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    const item = openFlyoutItem();
    if (item !== null && item.querySelector('.kernt3-nav__sub') !== null) {
        item.setAttribute(DISMISSED, '');
        // Focus moves out of the panel it just closed, onto the item's own link.
        const trigger = item.querySelector(':scope > a');
        if (trigger instanceof HTMLElement) {
            trigger.focus();
        }

        return;
    }

    // Nothing was open, so Escape means the mobile panel.
    // Focus goes back to the trigger, otherwise it is left on a hidden element.
    document.querySelectorAll(`${TOGGLE}[aria-expanded="true"]`).forEach((button) => {
        setExpanded(button, false);
        button.focus();
    });
});

// Once the pointer or the keyboard leaves the item, a dismissal has served its purpose:
// the next hover or tab has to open the panel again.
document.addEventListener('pointerover', (event) => {
    const item = event.target instanceof Element ? event.target.closest(FLYOUT_ITEM) : null;
    document.querySelectorAll(`[${DISMISSED}]`).forEach((dismissed) => {
        if (dismissed !== item) {
            dismissed.removeAttribute(DISMISSED);
        }
    });
});

document.addEventListener('focusout', () => {
    // Deferred: at focusout time activeElement is still the old element.
    window.setTimeout(() => {
        const item = document.activeElement instanceof Element
            ? document.activeElement.closest(FLYOUT_ITEM)
            : null;
        if (item === null) {
            clearDismissed();
        }
    }, 0);
});
