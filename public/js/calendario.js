// Calendário em português. O controle nativo do navegador ignora o idioma da página.
(function () {
    var MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    document.addEventListener('invalid', function (event) {
        var campo = event.target;

        if (!campo || !campo.setCustomValidity) {
            return;
        }

        if (campo.validity.valueMissing) {
            campo.setCustomValidity('Preencha este campo.');
        } else if (campo.validity.typeMismatch && campo.type === 'email') {
            campo.setCustomValidity('Informe um e-mail válido.');
        }
    }, true);

    document.addEventListener('input', function (event) {
        if (event.target && event.target.setCustomValidity && !event.target.classList.contains('calendario-texto')) {
            event.target.setCustomValidity('');
        }
    }, true);

    function dois(numero) {
        return String(numero).padStart(2, '0');
    }

    function diasNoMes(ano, mes) {
        return new Date(ano, mes, 0).getDate();
    }

    function agoraSaoPaulo() {
        var mapa = {};
        new Intl.DateTimeFormat('en-US', {
            timeZone: 'America/Sao_Paulo',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date()).forEach(function (parte) {
            mapa[parte.type] = parte.value;
        });

        var hora = Number(mapa.hour);

        return {
            ano: Number(mapa.year),
            mes: Number(mapa.month),
            dia: Number(mapa.day),
            hora: hora === 24 ? 0 : hora,
            minuto: Number(mapa.minute),
        };
    }

    function ler(texto, modo) {
        var encontrado = String(texto).trim().match(/^(\d{2})\/(\d{2})\/(\d{4})(?: (\d{2}):(\d{2}))?$/);

        if (!encontrado) {
            return null;
        }

        var dia = Number(encontrado[1]);
        var mes = Number(encontrado[2]);
        var ano = Number(encontrado[3]);
        var hora = encontrado[4] === undefined ? 0 : Number(encontrado[4]);
        var minuto = encontrado[5] === undefined ? 0 : Number(encontrado[5]);

        if (mes < 1 || mes > 12 || dia < 1 || dia > diasNoMes(ano, mes) || hora > 23 || minuto > 59) {
            return null;
        }

        if (modo === 'datetime' && encontrado[4] === undefined) {
            return null;
        }

        if (modo === 'date' && encontrado[4] !== undefined) {
            return null;
        }

        return { ano: ano, mes: mes, dia: dia, hora: hora, minuto: minuto };
    }

    function visivel(data, modo) {
        var texto = dois(data.dia) + '/' + dois(data.mes) + '/' + data.ano;

        if (modo === 'datetime') {
            texto += ' ' + dois(data.hora) + ':' + dois(data.minuto);
        }

        return texto;
    }

    function maquina(data, modo) {
        var texto = data.ano + '-' + dois(data.mes) + '-' + dois(data.dia);

        if (modo === 'datetime') {
            texto += 'T' + dois(data.hora) + ':' + dois(data.minuto);
        }

        return texto;
    }

    function mensagem(modo) {
        return modo === 'datetime'
            ? 'Use o formato dd/mm/aaaa hh:mm.'
            : 'Use o formato dd/mm/aaaa.';
    }

    document.querySelectorAll('.calendario').forEach(function (caixa) {
        var modo = caixa.getAttribute('data-modo') === 'datetime' ? 'datetime' : 'date';
        var texto = caixa.querySelector('.calendario-texto');
        var oculto = caixa.querySelector('input[type="hidden"]');
        var pop = caixa.querySelector('.calendario-pop');
        var rotulo = caixa.querySelector('[data-rotulo]');
        var grade = caixa.querySelector('.calendario-grade');
        var hora = caixa.querySelector('[data-hora]');
        var minuto = caixa.querySelector('[data-minuto]');
        var vista = ler(texto.value, modo) || agoraSaoPaulo();

        function atual(data) {
            texto.value = visivel(data, modo);
            oculto.value = maquina(data, modo);
            texto.setCustomValidity('');
            vista.ano = data.ano;
            vista.mes = data.mes;
            vista.dia = data.dia;
            vista.hora = data.hora;
            vista.minuto = data.minuto;

            if (hora) {
                hora.value = dois(data.hora);
                minuto.value = dois(data.minuto);
            }

            desenhar();
        }

        function desenhar() {
            rotulo.textContent = MESES[vista.mes - 1] + ' ' + vista.ano;
            grade.replaceChildren();

            var primeiro = new Date(vista.ano, vista.mes - 1, 1);
            var inicio = (primeiro.getDay() + 6) % 7;
            var total = diasNoMes(vista.ano, vista.mes);
            var hoje = agoraSaoPaulo();
            var escolhido = ler(texto.value, modo);
            var i;

            for (i = 0; i < inicio; i += 1) {
                var vazio = document.createElement('span');
                grade.appendChild(vazio);
            }

            for (i = 1; i <= total; i += 1) {
                var botao = document.createElement('button');
                botao.type = 'button';
                botao.textContent = String(i);

                if (escolhido && escolhido.ano === vista.ano && escolhido.mes === vista.mes && escolhido.dia === i) {
                    botao.className = 'is-selected';
                } else if (hoje.ano === vista.ano && hoje.mes === vista.mes && hoje.dia === i) {
                    botao.className = 'is-today';
                }

                botao.addEventListener('click', function (evento) {
                    var dia = Number(evento.currentTarget.textContent);
                    var base = ler(texto.value, modo) || vista;
                    atual({
                        ano: vista.ano,
                        mes: vista.mes,
                        dia: dia,
                        hora: hora ? Number(hora.value) : base.hora,
                        minuto: minuto ? Number(minuto.value) : base.minuto,
                    });

                    if (modo === 'date') {
                        pop.hidden = true;
                    }
                });

                grade.appendChild(botao);
            }
        }

        function abrir() {
            var lido = ler(texto.value, modo);

            if (lido) {
                vista = lido;

                if (hora) {
                    hora.value = dois(lido.hora);
                    minuto.value = dois(lido.minuto);
                }
            }

            desenhar();
            pop.hidden = false;
            pop.classList.remove('is-acima');

            if (pop.getBoundingClientRect().bottom > window.innerHeight - 8) {
                pop.classList.add('is-acima');
            }
        }

        texto.addEventListener('focus', abrir);
        texto.addEventListener('click', abrir);

        texto.addEventListener('input', function () {
            texto.setCustomValidity('');
            var lido = ler(texto.value, modo);
            oculto.value = lido ? maquina(lido, modo) : '';

            if (lido) {
                vista = lido;
                desenhar();
            }
        });

        texto.addEventListener('blur', function () {
            if (texto.value.trim() === '') {
                oculto.value = '';
                texto.setCustomValidity('');
                return;
            }

            if (!ler(texto.value, modo)) {
                texto.setCustomValidity(mensagem(modo));
            }
        });

        caixa.querySelector('[data-acao="anterior"]').addEventListener('click', function () {
            vista.mes -= 1;

            if (vista.mes < 1) {
                vista.mes = 12;
                vista.ano -= 1;
            }

            desenhar();
        });

        caixa.querySelector('[data-acao="proximo"]').addEventListener('click', function () {
            vista.mes += 1;

            if (vista.mes > 12) {
                vista.mes = 1;
                vista.ano += 1;
            }

            desenhar();
        });

        caixa.querySelector('[data-acao="hoje"]').addEventListener('click', function () {
            atual(agoraSaoPaulo());

            if (modo === 'date') {
                pop.hidden = true;
            }
        });

        caixa.querySelector('[data-acao="limpar"]').addEventListener('click', function () {
            texto.value = '';
            oculto.value = '';
            texto.setCustomValidity('');
            pop.hidden = true;
        });

        if (hora) {
            function mudarHora() {
                var base = ler(texto.value, modo) || vista;
                atual({
                    ano: base.ano,
                    mes: base.mes,
                    dia: base.dia,
                    hora: Number(hora.value),
                    minuto: Number(minuto.value),
                });
            }

            hora.addEventListener('change', mudarHora);
            minuto.addEventListener('change', mudarHora);
        }

        document.addEventListener('mousedown', function (evento) {
            if (!caixa.contains(evento.target)) {
                pop.hidden = true;
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                pop.hidden = true;
            }
        });
    });

    document.addEventListener('submit', function (evento) {
        var invalido = false;

        document.querySelectorAll('.calendario').forEach(function (caixa) {
            var modo = caixa.getAttribute('data-modo') === 'datetime' ? 'datetime' : 'date';
            var texto = caixa.querySelector('.calendario-texto');
            var oculto = caixa.querySelector('input[type="hidden"]');
            var lido = ler(texto.value, modo);

            if (texto.value.trim() === '') {
                oculto.value = '';
                return;
            }

            if (!lido) {
                texto.setCustomValidity(mensagem(modo));
                invalido = texto;
                return;
            }

            texto.setCustomValidity('');
            oculto.value = maquina(lido, modo);
        });

        if (invalido) {
            evento.preventDefault();
            invalido.reportValidity();
        }
    }, true);
})();
