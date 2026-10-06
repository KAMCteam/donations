/**
 * The login screen's two conveniences.
 *
 * Both are additions and neither is load-bearing: with this file blocked the
 * password field is an ordinary password field and the button an ordinary
 * submit, and signing in works exactly as it does with it. Nothing here
 * validates anything — what is accepted is decided by App\Controllers\Auth,
 * on the server, where it cannot be switched off from a console.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-login-form]');

    if (form === null) {
        return;
    }

    // ---- Reading the password back -------------------------------------
    //
    // Typing a password blind and getting it wrong is most of what a refused
    // sign-in actually is. The button is built here rather than written into
    // the markup so that a browser without scripting is not shown a control
    // that would do nothing.
    var password = form.querySelector('#user-password');
    var field    = password === null ? null : password.closest('.login-field');

    if (field !== null) {
        var reveal = document.createElement('button');

        // Inside a form, so it has to say it is not the submit button.
        reveal.type        = 'button';
        reveal.className   = 'login-reveal';
        reveal.textContent = 'Show';
        reveal.setAttribute('aria-controls', 'user-password');
        reveal.setAttribute('aria-pressed', 'false');
        reveal.setAttribute('aria-label', 'Show password');

        reveal.addEventListener('click', function () {
            var shown = password.type === 'text';

            password.type           = shown ? 'password' : 'text';
            reveal.textContent      = shown ? 'Show' : 'Hide';
            reveal.setAttribute('aria-pressed', shown ? 'false' : 'true');
            reveal.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');

            // Back where they were typing, at the end of what they typed.
            password.focus();
            password.setSelectionRange(password.value.length, password.value.length);
        });

        field.appendChild(reveal);
        // Only now does the input need room for it.
        field.classList.add('login-field--revealable');
    }

    // ---- Saying the form is working -------------------------------------
    //
    // On `submit` and not on the button's `click`, so pressing Enter in a
    // field says it too.
    form.addEventListener('submit', function () {
        var submit = form.querySelector('[data-login-submit]');

        if (submit === null || submit.hasAttribute('data-busy')) {
            return;
        }

        submit.setAttribute('data-busy', '');
        submit.textContent = submit.getAttribute('data-busy-label') || 'Signing in…';

        // A disabled button is not posted, which does not matter here — the
        // button carries no value — and it is what stops a second press
        // posting the form twice. Deferred by a tick because a button
        // disabled during its own submit event cancels the submit in some
        // browsers.
        window.setTimeout(function () {
            submit.disabled = true;
        }, 0);
    });
}());
