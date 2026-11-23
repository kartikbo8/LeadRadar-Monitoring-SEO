<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(LazilyRefreshDatabase::class);

it('links to the forgot password form from the sign-in page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('password.request'));

    $this->get(route('password.request'))
        ->assertOk()
        ->assertSeeText('Forgot your password?');
});

it('emails a reset link to an existing account', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'owner@example.test']);

    $this->post(route('password.email'), ['email' => 'owner@example.test'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether an email has an account', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'nobody@example.test'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create(['email' => 'owner@example.test']);
    $token = Password::createToken($user);

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertSeeText('Choose a new password');

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'owner@example.test',
        'password' => 'NewStrongPassword123',
        'password_confirmation' => 'NewStrongPassword123',
    ])->assertRedirect(route('login'))->assertSessionHas('status');

    expect(Hash::check('NewStrongPassword123', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create(['email' => 'owner@example.test']);

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => 'owner@example.test',
        'password' => 'NewStrongPassword123',
        'password_confirmation' => 'NewStrongPassword123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('NewStrongPassword123', $user->fresh()->password))->toBeFalse();
});
