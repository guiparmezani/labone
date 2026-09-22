<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_vai_para_a_tela_de_entrar(): void
    {
        $this->get('/')->assertRedirect('/entrar');
    }

    public function test_login_com_senha_certa_entra(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'ana@oficina.test',
            'password' => 'senha-segura',
        ]);

        $this->post('/entrar', [
            'email' => 'ana@oficina.test',
            'password' => 'senha-segura',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_senha_errada_nao_revela_o_motivo(): void
    {
        User::factory()->create([
            'email' => 'ana@oficina.test',
            'password' => 'senha-segura',
        ]);

        $this->post('/entrar', [
            'email' => 'ana@oficina.test',
            'password' => 'outra-senha',
        ])->assertInvalid(['email' => 'E-mail ou senha inválidos.']);
    }

    public function test_conta_inativa_nao_entra(): void
    {
        User::factory()->inactive()->create([
            'email' => 'ana@oficina.test',
            'password' => 'senha-segura',
        ]);

        $this->post('/entrar', [
            'email' => 'ana@oficina.test',
            'password' => 'senha-segura',
        ])->assertInvalid(['email' => 'E-mail ou senha inválidos.']);

        $this->assertGuest();
    }

    public function test_sair_encerra_a_sessao_e_nao_depende_de_ponto(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/sair')->assertRedirect('/entrar');
        $this->assertGuest();
    }

    public function test_sexta_tentativa_no_mesmo_email_e_recusada(): void
    {
        User::factory()->create([
            'email' => 'ana@oficina.test',
            'password' => 'senha-segura',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/entrar', [
                'email' => 'ana@oficina.test',
                'password' => 'errada-'.$i,
            ])->assertInvalid(['email' => 'E-mail ou senha inválidos.']);
        }

        $this->post('/entrar', [
            'email' => 'ana@oficina.test',
            'password' => 'senha-segura',
        ])->assertInvalid([
            'email' => 'Muitas tentativas. Espere um minuto e tente de novo.',
        ]);
    }
}
