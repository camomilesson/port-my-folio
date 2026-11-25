<?php

namespace App\Actions;

class ProviderResult
{
    /** @param array<string, mixed> $content
     * @param  array<string, mixed>  $usage
     */
    public function __construct(public readonly array $content, public readonly string $provider, public readonly string $model, public readonly string $promptVersion, public readonly array $usage) {}
}
