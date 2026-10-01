<script>
    (function () {
        if (window.jornadaPronta) {
            return;
        }

        window.jornadaPronta = true;

        function horaMascarada(bruto) {
            var digitos = String(bruto).replace(/\D/g, '');
            var saida = '';

            for (var i = 0; i < digitos.length && saida.replace(/\D/g, '').length < 4; i++) {
                var preenchidos = saida.replace(/\D/g, '').length;
                var digito = digitos[i];

                if (preenchidos === 0 && digito > '2') {
                    continue;
                }

                if (preenchidos === 1 && saida[0] === '2' && digito > '3') {
                    continue;
                }

                if (preenchidos === 2 && digito > '5') {
                    continue;
                }

                saida += digito;

                if (saida.length === 2) {
                    saida += ':';
                }
            }

            return saida;
        }

        document.addEventListener('input', function (event) {
            var campo = event.target;

            if (!campo.matches || !campo.matches('[data-mascara-hora]')) {
                return;
            }

            var digitosAntes = campo.value.slice(0, campo.selectionStart).replace(/\D/g, '').length;
            var formatado = horaMascarada(campo.value);
            campo.value = formatado;

            var novaPos = 0;
            var contados = 0;

            while (novaPos < formatado.length && contados < digitosAntes) {
                if (/\d/.test(formatado[novaPos])) {
                    contados++;
                }

                novaPos++;
            }

            if (digitosAntes === 2 && formatado[2] === ':') {
                novaPos = 3;
            }

            campo.setSelectionRange(novaPos, novaPos);
        });

        document.addEventListener('click', function (event) {
            var adicionar = event.target.closest('[data-adicionar-jornada]');

            if (adicionar) {
                var lista = adicionar.closest('[data-jornadas]');
                var proxima = lista.querySelector('[data-jornada][hidden]');

                if (proxima) {
                    proxima.hidden = false;
                    proxima.querySelector('input')?.focus();
                }

                if (!lista.querySelector('[data-jornada][hidden]')) {
                    adicionar.hidden = true;
                }

                atualizarRotulo(lista);

                return;
            }

            var remover = event.target.closest('[data-remover-jornada]');

            if (!remover) {
                return;
            }

            var bloco = remover.closest('[data-jornada]');
            var grupo = bloco.closest('[data-jornadas]');

            bloco.querySelectorAll('input').forEach(function (input) {
                input.value = '';
            });
            bloco.hidden = true;

            var blocos = grupo.querySelectorAll('[data-jornada]');

            if (blocos[0].hidden && !blocos[1].hidden) {
                var primeira = blocos[0].querySelectorAll('input');
                var segunda = blocos[1].querySelectorAll('input');

                primeira.forEach(function (input, indice) {
                    input.value = segunda[indice].value;
                    segunda[indice].value = '';
                });

                blocos[0].hidden = false;
                blocos[1].hidden = true;
            }

            grupo.querySelector('[data-adicionar-jornada]').hidden = false;
            atualizarRotulo(grupo);
        });

        function atualizarRotulo(grupo) {
            var vazia = grupo.querySelector('[data-jornada-vazia]');

            if (!vazia) {
                return;
            }

            var alguma = Array.from(grupo.querySelectorAll('[data-jornada]')).some(function (bloco) {
                return !bloco.hidden;
            });

            vazia.hidden = alguma;
        }

        document.querySelectorAll('dialog.lightbox').forEach(function (dialog) {
            dialog.addEventListener('close', function () {
                dialog.querySelectorAll('[data-jornadas]').forEach(function (grupo) {
                    grupo.querySelectorAll('[data-jornada]').forEach(function (bloco) {
                        var preenchido = Array.from(bloco.querySelectorAll('input')).some(function (input) {
                            return input.value.trim() !== '';
                        });
                        bloco.hidden = !preenchido;
                    });

                    grupo.querySelector('[data-adicionar-jornada]').hidden = !grupo.querySelector('[data-jornada][hidden]');
                    atualizarRotulo(grupo);
                });
            });
        });
    })();
</script>
