<dialog class="lightbox lightbox-form" id="confirmar-apagar">
    @include('partials.lightbox-fechar')
    <form class="stack" method="dialog">
        <div class="lightbox-intro">
            <h2>Apagar</h2>
            <p data-confirmar-texto></p>
        </div>
        <label class="check confirmar-caixa" data-confirmar-caixa hidden>
            <input type="checkbox" data-confirmar-check>
            <span>Apagar o projeto e todos os lançamentos.</span>
        </label>
        <hr>
        <div class="actions">
            <button class="btn btn-danger" type="button" data-confirmar-ok disabled>Apagar</button>
        </div>
    </form>
</dialog>
<script>
    (function () {
        var dialog = document.getElementById('confirmar-apagar');
        var texto = dialog?.querySelector('[data-confirmar-texto]');
        var caixa = dialog?.querySelector('[data-confirmar-caixa]');
        var check = dialog?.querySelector('[data-confirmar-check]');
        var botao = dialog?.querySelector('[data-confirmar-ok]');
        var form = null;

        if (!dialog || !texto || !caixa || !check || !botao) {
            return;
        }

        document.addEventListener('submit', function (event) {
            var alvo = event.target;

            if (!(alvo instanceof HTMLFormElement) || alvo.dataset.confirmado === '1') {
                return;
            }

            var projeto = alvo.hasAttribute('data-confirmar-projeto');
            var mensagem = alvo.getAttribute('data-confirmar');

            if (!projeto && !mensagem) {
                return;
            }

            event.preventDefault();
            form = alvo;
            document.querySelectorAll('[data-menu-painel]').forEach(function (painel) {
                painel.hidden = true;
            });
            document.querySelectorAll('[data-menu]').forEach(function (botao) {
                botao.setAttribute('aria-expanded', 'false');
            });
            texto.textContent = projeto
                ? 'O projeto, as tarefas e os lançamentos deixam de existir.'
                : mensagem;
            caixa.hidden = !projeto;
            check.checked = false;
            botao.disabled = projeto;
            dialog.showModal();
        });

        check.addEventListener('change', function () {
            botao.disabled = !check.checked;
        });

        botao.addEventListener('click', function () {
            if (!form || botao.disabled) {
                return;
            }

            var campo = form.querySelector('[name="apagar_lancamentos"]');

            if (campo) {
                campo.value = '1';
            }

            form.dataset.confirmado = '1';
            dialog.close();
            form.requestSubmit();
        });

        dialog.querySelectorAll('[data-fechar]').forEach(function (fechar) {
            fechar.addEventListener('click', function () {
                dialog.close();
            });
        });
    })();
</script>
