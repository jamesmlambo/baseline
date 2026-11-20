<?php

declare(strict_types=1);

it('shows the public homepage with the Baseline Lodge name', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Baseline Lodge')
        ->assertDontSee('Nyala')
        ->assertSee('We will never ask for your card details by WhatsApp, SMS or chat.');
});

it('shows the demo-content banner outside production', function (): void {
    $this->get('/')->assertSee('DEMO');
});
