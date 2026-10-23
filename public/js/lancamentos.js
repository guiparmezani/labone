(function () {
    function instante(valor, original) {
        var texto = original && original.slice(0, 16) === valor ? original : valor;
        var partes = String(texto).match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/);

        if (!partes) {
            return null;
        }

        return Date.UTC(Number(partes[1]), Number(partes[2]) - 1, Number(partes[3]), Number(partes[4]), Number(partes[5]), Number(partes[6] || 0));
    }

    document.querySelectorAll('[data-time-interval]').forEach(function (caixa) {
        var inicio = caixa.querySelector('[name="started_at"]');
        var fim = caixa.querySelector('[name="ended_at"]');
        var total = caixa.querySelector('[data-duration]');

        function calcular() {
            var de = instante(inicio.value, caixa.getAttribute('data-original-start'));
            var ate = instante(fim.value, caixa.getAttribute('data-original-end'));

            if (de === null || ate === null) {
                total.value = fim.value === '' && caixa.getAttribute('data-original-start') && !caixa.getAttribute('data-original-end')
                    ? 'Em andamento'
                    : 'Informe o início e o fim';
                return;
            }

            if (ate <= de) {
                total.value = 'O fim precisa ser depois do início.';
                return;
            }

            var minutos = Math.floor((ate - de) / 60000);
            total.value = Math.floor(minutos / 60) + 'h ' + String(minutos % 60).padStart(2, '0') + 'min';
        }

        caixa.addEventListener('change', calcular);
        caixa.closest('form').addEventListener('reset', function () {
            setTimeout(calcular, 0);
        });
        calcular();
    });
})();
