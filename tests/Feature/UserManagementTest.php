<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_recebe_403_em_usuarios(): void
    {
        $this->actingAsRole(Role::Operator);
        $this->get('/usuarios')->assertForbidden();
        $this->post('/usuarios', [
            'name' => 'Carlos Operador',
            'email' => 'carlos@oficina.test',
            'password' => 'senha-segura',
            'role' => 'operator',
            'active' => '1',
        ])->assertForbidden();
    }

    public function test_lider_cria_e_desativa_usuario(): void
    {
        $this->actingAsRole(Role::Leader);

        $this->get('/usuarios')->assertOk()->assertSee('Novo usuário');

        $this->post('/usuarios', [
            'name' => 'Ana Operadora',
            'email' => 'ana@oficina.test',
            'password' => 'senha-segura',
            'role' => 'operator',
            'active' => '1',
        ])->assertRedirect('/usuarios');

        $ana = User::query()->where('email', 'ana@oficina.test')->first();
        $this->assertNotNull($ana);
        $this->assertTrue($ana->active);

        $this->put('/usuarios/'.$ana->id, [
            'name' => 'Ana Operadora',
            'email' => 'ana@oficina.test',
            'password' => '',
            'role' => 'operator',
            'active' => '0',
        ])->assertRedirect('/usuarios');

        $this->assertFalse($ana->refresh()->active);
    }

    public function test_lider_nao_cria_nem_edita_administrador(): void
    {
        $this->actingAsRole(Role::Leader);
        $admin = User::factory()->create(['role' => Role::Admin, 'name' => 'Admin Protegido']);
        $operator = User::factory()->operator()->create();

        $this->get('/usuarios')
            ->assertOk()
            ->assertSee('Admin Protegido')
            ->assertSee('data-abrir="editar-usuario-'.$operator->id.'"', false)
            ->assertDontSee(route('usuarios.edit', $operator), false)
            ->assertDontSee(route('usuarios.edit', $admin), false);

        $this->get('/usuarios/create')->assertOk()->assertDontSee('Administrador');

        $this->post('/usuarios', [
            'name' => 'Novo Admin',
            'email' => 'novo-admin@oficina.test',
            'password' => 'senha-segura',
            'role' => 'admin',
            'active' => '1',
        ])->assertInvalid(['role' => 'Você não pode definir o papel de administrador.']);

        $this->get('/usuarios/'.$admin->id.'/edit')->assertForbidden();

        $this->put('/usuarios/'.$admin->id, [
            'name' => 'Nome trocado',
            'email' => $admin->email,
            'role' => 'leader',
            'active' => '0',
        ])->assertForbidden();

        $admin->refresh();
        $this->assertSame('Admin Protegido', $admin->name);
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->active);

        $this->put('/usuarios/'.$operator->id, [
            'name' => $operator->name,
            'email' => $operator->email,
            'role' => 'admin',
            'active' => '1',
        ])->assertInvalid(['role' => 'Você não pode definir o papel de administrador.']);

        $this->assertSame(Role::Operator, $operator->refresh()->role);
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
            'password' => 'abc',
            'role' => 'operator',
            'active' => '1',
        ])->assertInvalid(['password' => 'A senha precisa ter pelo menos 4 caracteres.']);

        $this->post('/usuarios', [
            'name' => 'Carlos Operador',
            'email' => 'carlos@oficina.test',
            'password' => 'abcd',
            'role' => 'operator',
            'active' => '1',
        ])->assertRedirect('/usuarios');
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

    public function test_email_e_opcional_e_o_nome_e_unico(): void
    {
        $this->actingAsRole(Role::Admin);

        $this->post('/usuarios', [
            'name' => 'Sem E-mail',
            'email' => '',
            'password' => 'abcd',
            'role' => 'operator',
            'active' => '1',
        ])->assertRedirect('/usuarios');

        $this->assertDatabaseHas('users', [
            'name' => 'Sem E-mail',
            'email' => null,
        ]);

        $this->post('/usuarios', [
            'name' => 'sem e-mail',
            'email' => '',
            'password' => 'abcd',
            'role' => 'operator',
            'active' => '1',
        ])->assertInvalid(['name' => 'Já existe um usuário com este nome.']);
    }

    public function test_erro_de_edicao_reabre_o_popup_daquela_pessoa(): void
    {
        $this->actingAsRole(Role::Admin);
        $operator = User::factory()->operator()->create(['name' => 'Editavel']);
        $outro = User::factory()->operator()->create(['name' => 'Outra Pessoa']);

        $pagina = $this->followingRedirects()->from('/usuarios')->put('/usuarios/'.$operator->id, [
            'name' => '',
            'email' => '',
            'password' => '',
            'role' => 'operator',
            'active' => '1',
            'lightbox' => 'editar-usuario-'.$operator->id,
        ]);

        $pagina->assertSee('Informe o nome.');
        $this->assertSame(1, substr_count($pagina->getContent(), 'Informe o nome.'));
        $pagina->assertSee('value="'.$outro->name.'"', false);
        $pagina->assertSee('document.getElementById("editar-usuario-'.$operator->id.'")?.showModal();', false);
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
