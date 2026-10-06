# Guia do piloto

O sistema se chama **Controle de projetos**. Na hospedagem do cliente ele abre em [https://ponto.laboneindustrial.com.br](https://ponto.laboneindustrial.com.br). Nesta máquina, o endereço local é [http://labone.localhost](http://labone.localhost).

A produção recebeu uma cópia dos usuários e projetos do staging em 05/10/2026. Os nomes e senhas são os mesmos do staging; novas alterações ficam independentes em cada instalação. As contas Administrador e Joana Operadora ainda usam a senha de demonstração: o administrador deve trocar essas senhas em Usuários antes de liberar o acesso ao cliente.

A senha inicial de todos os usuários de demonstração é `senha-segura`. Troque essas senhas antes de entregar o endereço ao cliente. Quem redefine senha é o administrador, em Usuários.

| E-mail | Papel |
|---|---|
| admin@labone.test | Administrador |
| lider@labone.test | Líder |
| joana@labone.test | Operadora |
| carlos@labone.test | Operador |

## O que cada pessoa faz

O administrador vê tudo: projetos, lançamentos, relatórios e usuários.

O líder cria e encerra projetos, inclui tarefas internas e de equipe terceira, e corrige o ponto de qualquer pessoa. Não há tela de relatório para o líder.

O operador entra, escolhe um projeto aberto e uma tarefa interna, e aperta Iniciar. Para encerrar, aperta Parar. Pode incluir uma tarefa interna. Não vê horas gastas, orçamento, nem o ponto das outras pessoas. Sair do sistema não para o ponto.

Equipe terceira é só orçamento, em reais. Esse tipo de tarefa não recebe ponto.

Projeto ou tarefa que já tem lançamento não é apagado. O projeto é encerrado, e o histórico fica.

Horários na tela são de Brasília. Valores são em reais.

## Relatórios

Só o administrador. O período padrão é o mês atual. Ponto ainda aberto não entra na soma. Os dois arquivos CSV abrem no Excel em português: separador ponto e vírgula, decimal com vírgula.
