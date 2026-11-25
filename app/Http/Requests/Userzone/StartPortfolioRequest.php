<?php

namespace App\Http\Requests\Userzone;

use Illuminate\Support\Str;

class StartPortfolioRequest extends StorePortfolioRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['title' => Str::limit($this->user()->name, 95, '').' portfolio', 'slug' => 'portfolio-'.Str::lower(Str::random(12))]);
        parent::prepareForValidation();
    }

    public function rules(): array
    {
        return parent::rules() + ['confirm' => ['accepted']];
    }
}
