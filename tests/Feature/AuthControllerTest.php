<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('renders the sign-in and account creation forms', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeText('Sign in to site monitoring');

    $this->get(route('register'))
        ->assertOk()
        ->assertSeeText('Create your account');
});

it('registers an authenticated account with a hashed password', function () {
    $this->post(route('register.store'), [
        'name' => 'Site Owner',
        'email' => 'owner@example.test',
        'password' => 'StrongPassword123',
        'password_confirmation' => 'StrongPassword123',
    ])->assertRedirect(route('health-monitor.index'));

    $user = User::query()->where('email', 'owner@example.test')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseMissing('users', [
        'email' => 'owner@example.test',
        'password' => 'StrongPassword123',
    ]);
});

it('rejects invalid credentials without authenticating', function () {
    User::factory()->create([
        'email' => 'owner@example.test',
        'password' => 'StrongPassword123',
    ]);

    $this->post(route('login.store'), [
        'email' => 'owner@example.test',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out and invalidates the signed-in session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('health-monitor.logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
