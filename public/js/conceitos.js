'use strict';

document.querySelectorAll('[data-menu-toggle]').forEach(function (button) {
    var menu = document.getElementById(button.getAttribute('aria-controls'));

    button.addEventListener('click', function () {
        var open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        button.textContent = open ? 'Fechar −' : 'Menu +';
        menu.classList.toggle('is-open', open);
    });

    menu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            button.setAttribute('aria-expanded', 'false');
            button.textContent = 'Menu +';
            menu.classList.remove('is-open');
        });
    });
});

document.querySelectorAll('[data-demo-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var result = form.querySelector('[data-form-result]');
        result.hidden = false;
        result.focus();
    });

    form.querySelector('[data-submit]').disabled = false;
});
