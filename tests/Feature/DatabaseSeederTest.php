<?php

namespace Tests\Feature;

use App\Models\TimeLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_piloto_tem_os_tres_papeis_e_um_ponto_aberto(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, User::query()->count());
        $this->assertSame(1, TimeLog::query()->whereNull('ended_at')->count());

        $this->post('/entrar', [
            'email' => 'joana@labone.test',
            'password' => 'senha-segura',
        ])->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('R$ 25.000,00')
            ->assertSee('Molde da tampa');
    }
}
