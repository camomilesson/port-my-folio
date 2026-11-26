<?php

it('uses the configured styles and stock accent palette in the welcome preview', function () {
    $presets = config('portmyfolio.presets');
    $presets['material']['label'] = 'Soft surfaces';
    config(['portmyfolio.presets' => $presets, 'portmyfolio.accent_swatches' => ['Custom' => '#123456']]);

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Soft surfaces')
        ->assertSee('data-preview-preset="minimal"', false)
        ->assertSee('data-preview-preset="material"', false)
        ->assertSee('data-preview-preset="editorial"', false)
        ->assertSee('data-accents=\'["#123456"]\'', false)
        ->assertSee('--portfolio-accent: #123456', false);
});
