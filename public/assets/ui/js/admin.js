/**
 * The Admin screen's one convenience: a temporary password to read out.
 *
 * Generated in the browser and written into the box, so whoever is setting it
 * can see it and say it. Nothing is generated on the server, because nothing
 * is stored on the server yet — the reset flow is the screen and nothing
 * behind it, and the dialog says so.
 *
 * An addition and nothing more: with this file blocked the box is an ordinary
 * text box somebody types into, and the dialog works the same.
 */
(function () {
    'use strict';

    // No I, l, O or 0: a temporary password gets read aloud down a corridor
    // or copied off a sticky note, and those four are where that goes wrong.
    var ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    var LENGTH = 12;

    function generate() {
        var out = '';
        var i;

        // crypto where there is one, which is every browser this runs in;
        // Math.random only so that the button still does something in an
        // ancient one rather than throwing.
        if (window.crypto && window.crypto.getRandomValues) {
            var bytes = new Uint32Array(LENGTH);
            window.crypto.getRandomValues(bytes);

            for (i = 0; i < LENGTH; i++) {
                out += ALPHABET.charAt(bytes[i] % ALPHABET.length);
            }

            return out;
        }

        for (i = 0; i < LENGTH; i++) {
            out += ALPHABET.charAt(Math.floor(Math.random() * ALPHABET.length));
        }

        return out;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-generate-password]');

        if (button === null) {
            return;
        }

        var field = document.getElementById(button.getAttribute('data-generate-password'));

        if (field === null) {
            return;
        }

        field.value = generate();
        // Selected, because the next thing anybody does with it is copy it.
        field.focus();
        field.select();
    });
}());
