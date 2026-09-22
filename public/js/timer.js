// Atualiza cada ponto aberto a cada segundo. O valor inicial já vem do servidor.
function paintElapsed() {
    var now = Date.now();

    document.querySelectorAll('[data-started-at]').forEach(function (row) {
        var start = Date.parse(row.getAttribute('data-started-at'));
        var total = Math.max(0, Math.floor((now - start) / 1000));
        var target = row.querySelector('.elapsed');

        if (!target || Number.isNaN(start)) {
            return;
        }

        var hours = Math.floor(total / 3600);
        var minutes = Math.floor((total % 3600) / 60);
        var seconds = total % 60;

        target.textContent = String(hours).padStart(2, '0') + ':'
            + String(minutes).padStart(2, '0') + ':'
            + String(seconds).padStart(2, '0');
    });
}

paintElapsed();
setInterval(paintElapsed, 1000);
