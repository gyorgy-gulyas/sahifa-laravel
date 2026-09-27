<?php

namespace Sahifa\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Sahifa\Laravel\Document pdf(string $source, array $options = [])
 * @method static \Sahifa\Laravel\Document pdfFromView(string $view, array $data = [], array $options = [])
 * @method static \Sahifa\Laravel\Document screenshot(array $options)
 *
 * @see \Sahifa\Laravel\Sahifa
 */
class Sahifa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sahifa\Laravel\Sahifa::class;
    }
}
