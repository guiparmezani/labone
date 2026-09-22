<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_e_lider_recebem_403_em_usuarios(): void
    {
        $this->actingAsRole(Role::Operator);
        $this->get('/usuarios')->assertForbidden();

        $this->actingAsRole(Role::Leader);
        $this->get('/usuarios')->assertForbidden();
    }

    public function test_admin_cria_usuario(): void
    {
        $this->actingAsRole(Role::Admin);

        $this->post('/usuarios', [
            'name' => 'Carlos Operador',
            'email' => 'carlos@oficina.test',
            'password' => 'senha-segura',
            'role' => 'operator',
            'active' => '1',
        ])->assertRedirect('/usuarios');

        $this->assertDatabaseHas('users', [
            'email' => 'carlos@oficina.test',
            'role' => 'operator',
            'active' => true,
        ]);
    }

    public function test_senha_curta_e_recusada(): void
    {
        $this->actingAsRole(Role::Admin);

        $this->post('/usuarios', [
            'name' => 'Carlos Operador',
            'email' => 'carlos@oficina.test',
            'password' => 'curta',
            'role' => 'operator',
            'active' => '1',
        ])->assertInvalid(['password' => 'A senha precisa ter pelo menos 8 caracteres.']);
    }

    public function test_admin_atualiza_sem_trocar_a_senha(): void
    {
        $admin = $this->actingAsRole(Role::Admin);
        $operator = User::factory()->operator()->create(['password' => 'senha-antiga']);

        $this->put('/usuarios/'.$operator->id, [
            'name' => 'Nome novo',
            'email' => $operator->email,
            'password' => '',
            'role' => 'leader',
            'active' => '1',
        ])->assertRedirect('/usuarios');

        $operator->refresh();
        $this->assertSame('Nome novo', $operator->name);
        $this->assertSame(Role::Leader, $operator->role);
        $this->assertTrue(password_verify('senha-antiga', $operator->password));
        $this->assertTrue($admin->isAdmin());
    }

    public function test_nao_remove_o_unico_administrador_ativo(): void
    {
        $admin = $this->actingAsRole(Role::Admin);

        $this->put('/usuarios/'.$admin->id, [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'leader',
            'active' => '1',
        ])->assertInvalid(['role' => 'Precisa existir pelo menos um administrador ativo.']);
    }

    public function test_conta_desativada_no_meio_da_sessao_sai(): void
    {
        $user = $this->actingAsRole(Role::Operator);
        $user->update(['active' => false]);

        $this->get('/')->assertRedirect('/entrar');
        $this->assertGuest();
    }
}
