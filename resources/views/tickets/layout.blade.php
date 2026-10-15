<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo')</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body { width: 80mm; padding: 3mm 4mm 6mm; font-family: 'Courier New', Courier, monospace; font-size: 11.5px; line-height: 1.35; }
        .centro { text-align: center; }
        .der { text-align: right; white-space: nowrap; }
        .negrita { font-weight: 700; }
        .grande { font-size: 14px; }
        .pequeno { font-size: 10px; }
        .linea { border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .sangria { padding-left: 8px; }
        .total td { font-size: 15px; font-weight: 700; padding-top: 3px; }
        .letras { font-size: 10px; margin-top: 3px; }
        .anulada { font-size: 16px; font-weight: 700; margin: 4px 0; }
        .logo { max-width: 50mm; max-height: 22mm; margin-bottom: 3px; }
        .firma { margin-top: 28px; }
        @media screen { body { margin: 12px auto; box-shadow: 0 0 0 1px #ddd; } }
    </style>
</head>
<body>
    @yield('contenido')

    @if (request()->boolean('imprimir', true))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 150);
            });
        </script>
    @endif
</body>
</html>
