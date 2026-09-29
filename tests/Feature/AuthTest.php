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
            'name' => 'Ana Oficina',
            'password' => 'senha-segura',
        ]);

        $this->get('/entrar')
            ->assertOk()
            ->assertSee('Ana Oficina')
            ->assertDontSee('type="email"', false);

        $this->post('/entrar', [
            'user_id' => $user->id,
            'password' => 'senha-segura',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_senha_errada_nao_revela_o_motivo(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana Oficina',
            'password' => 'senha-segura',
        ]);

        $this->post('/entrar', [
            'user_id' => $user->id,
            'password' => 'outra-senha',
        ])->assertInvalid(['user_id' => 'Não foi possível entrar.']);
    }

    public function test_conta_inativa_nao_entra(): void
    {
        $user = User::factory()->inactive()->create([
            'name' => 'Conta Inativa',
            'password' => 'senha-segura',
        ]);

        $this->get('/entrar')->assertOk()->assertDontSee('Conta Inativa');

        $this->post('/entrar', [
            'user_id' => $user->id,
            'password' => 'senha-segura',
        ])->assertInvalid(['user_id' => 'Não foi possível entrar.']);

        $this->assertGuest();
    }

    public function test_sair_encerra_a_sessao_e_nao_depende_de_ponto(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/sair')->assertRedirect('/entrar');
        $this->assertGuest();
    }

    public function test_sexta_tentativa_na_mesma_pessoa_e_recusada(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana Oficina',
            'password' => 'senha-segura',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/entrar', [
                'user_id' => $user->id,
                'password' => 'errada-'.$i,
            ])->assertInvalid(['user_id' => 'Não foi possível entrar.']);
        }

        $this->post('/entrar', [
            'user_id' => $user->id,
            'password' => 'senha-segura',
        ])->assertInvalid([
            'user_id' => 'Muitas tentativas. Espere um minuto e tente de novo.',
        ]);
    }
}
