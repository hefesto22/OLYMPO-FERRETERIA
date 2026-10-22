<?php

declare(strict_types=1);

namespace App\Filament\Schemas\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

/**
 * Campo de código de barras con botón de cámara.
 *
 * En el teléfono o la tablet, el botón abre el escáner del panel
 * (resources/views/filament/escaner-codigo.blade.php) y el código leído
 * queda escrito en el campo. Con lector USB se escanea como siempre, sobre
 * el campo.
 *
 * Uso:
 *   CodigoBarrasField::make()                     // 'codigo_barras'
 *   CodigoBarrasField::make()->unique()
 */
final class CodigoBarrasField
{
    public static function make(string $name = 'codigo_barras'): TextInput
    {
        return TextInput::make($name)
            ->maxLength(60)
            ->autocomplete(false)
            ->suffixAction(
                Action::make('escanear')
                    ->label('Escanear con la cámara')
                    ->tooltip('Escanear con la cámara')
                    ->icon(Heroicon::OutlinedCamera)
                    ->iconButton()
                    // Solo en el navegador: el escáner llena el campo por su id.
                    ->alpineClickHandler(fn (TextInput $component): string => '$dispatch(\'abrir-escaner\', '.Js::from([
                        'campo'  => $component->getId(),
                        'titulo' => 'Escanear código de barras',
                    ]).')'),
            );
    }
}
