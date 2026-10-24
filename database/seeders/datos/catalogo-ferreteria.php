<?php

declare(strict_types=1);

/*
| Catálogo de demostración de ferretería. Lo lee CatalogoDemoFerreteria,
| que explica cada campo y le da su tipo (PHPStan no puede seguir la
| forma de un arreglo tan grande).
*/

return [
    // ── Materiales de construcción ──────────────────────────────────
    'Cemento gris uso general 42.5 kg' => ['cat' => 'Materiales de construcción', 'marca' => null, 'und' => 'BLS', 'precio' => 245.00, 'mayoreo' => 235.00, 'costo' => 185.00, 'stock' => 120, 'min' => 30],
    'Bloque de concreto 15 cm (6")'    => ['cat' => 'Materiales de construcción', 'marca' => null, 'und' => 'UND', 'precio' => 19.00, 'mayoreo' => 18.00, 'costo' => 13.50, 'stock' => 800, 'min' => 200],
    'Bloque de concreto 10 cm (4")'    => ['cat' => 'Materiales de construcción', 'marca' => null, 'und' => 'UND', 'costo' => 11.00, 'stock' => 600, 'min' => 150, 'margen' => [35, 25], 'pres' => [['CTO', 100, 'Ciento', null, 'contiene', 28]]],
    'Bloque de concreto 20 cm (8")'    => ['cat' => 'Materiales de construcción', 'marca' => null, 'und' => 'UND', 'costo' => 16.50, 'stock' => 500, 'min' => 100, 'margen' => [35, 25], 'pres' => [['CTO', 100, 'Ciento', null, 'contiene', 28]]],
    'Ladrillo rafón'                   => ['cat' => 'Materiales de construcción', 'marca' => null, 'und' => 'UND', 'costo' => 4.20, 'stock' => 3000, 'min' => 500, 'margen' => [40, 25], 'pres' => [['CTO', 100, 'Ciento', null, 'contiene', 30], ['MIL', 1000, 'Millar', null, 'contiene', 22]]],
    'Cal hidratada 25 lb'              => ['cat' => 'Materiales de construcción', 'marca' => null, 'und' => 'BLS', 'costo' => 58.00, 'stock' => 60, 'min' => 15, 'margen' => [30, 20]],

    // ── Agregados (por metro cúbico) ────────────────────────────────
    'Arena de río'        => ['cat' => 'Agregados', 'marca' => null, 'und' => 'M3', 'costo' => 420.00, 'stock' => 30, 'min' => 6, 'margen' => [30, 20], 'pres' => [['VJE', 6, 'Volqueta 6 m³', null, 'contiene', 22]]],
    'Arena blanca (fina)' => ['cat' => 'Agregados', 'marca' => null, 'und' => 'M3', 'costo' => 520.00, 'stock' => 18, 'min' => 6, 'margen' => [30, 20], 'pres' => [['VJE', 6, 'Volqueta 6 m³', null, 'contiene', 22]]],
    'Piedrín 3/4"'        => ['cat' => 'Agregados', 'marca' => null, 'und' => 'M3', 'costo' => 650.00, 'stock' => 24, 'min' => 6, 'margen' => [30, 20], 'pres' => [['VJE', 6, 'Volqueta 6 m³', null, 'contiene', 22]]],
    'Piedra bola'         => ['cat' => 'Agregados', 'marca' => null, 'und' => 'M3', 'costo' => 450.00, 'stock' => 12, 'min' => 6, 'margen' => [30, 20], 'pres' => [['VJE', 6, 'Volqueta 6 m³', null, 'contiene', 22]]],
    'Material selecto'    => ['cat' => 'Agregados', 'marca' => null, 'und' => 'M3', 'costo' => 250.00, 'stock' => 36, 'min' => 12, 'margen' => [30, 20], 'pres' => [['VJE', 6, 'Volqueta 6 m³', null, 'contiene', 22]]],

    // ── Hierro y acero ──────────────────────────────────────────────
    'Varilla corrugada 3/8" x 6 m'           => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'VAR', 'precio' => 165.00, 'mayoreo' => 158.00, 'costo' => 122.00, 'stock' => 150, 'min' => 40],
    'Varilla corrugada 1/2" x 6 m'           => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'VAR', 'precio' => 290.00, 'mayoreo' => 279.00, 'costo' => 215.00, 'stock' => 80, 'min' => 20],
    'Hierro liso 1/4" x 6 m'                 => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'VAR', 'costo' => 48.00, 'stock' => 200, 'min' => 50, 'margen' => [25, 18]],
    'Alambre de amarre #16'                  => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'LB', 'precio' => 32.00, 'mayoreo' => 29.00, 'costo' => 22.50, 'stock' => 220, 'min' => 50, 'pres' => [['RLL', 50, 'Rollo 50 lb', 1450.00]]],
    'Alambre galvanizado #12'                => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'LB', 'costo' => 26.00, 'stock' => 150, 'min' => 30, 'margen' => [30, 20], 'pres' => [['RLL', 50, 'Rollo 50 lb', null, 'contiene', 18]]],
    'Tubo industrial 1" x 1" chapa 16 x 6 m' => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'UND', 'costo' => 290.00, 'stock' => 40, 'min' => 10, 'margen' => [25, 18]],
    'Angular 1 1/2" x 1/8" x 6 m'            => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'UND', 'costo' => 420.00, 'stock' => 25, 'min' => 6, 'margen' => [25, 18]],
    'Malla electrosoldada 6 x 6 cal. 10'     => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'M2', 'costo' => 46.00, 'stock' => 288, 'min' => 72, 'margen' => [25, 18], 'pres' => [['LAM', 14.4, 'Hoja 2.40 x 6 m', null]]],

    // ── Maderas y tableros (pino por pie tablar) ────────────────────
    'Madera de pino rústica'                         => ['cat' => 'Maderas y tableros', 'marca' => null, 'und' => 'PT', 'costo' => 22.00, 'stock' => 1200, 'min' => 200, 'margen' => [30, 20], 'pres' => [['UND', 8, 'Cuartón 2" x 4" x 12 pies'], ['UND', 12, 'Tabla 1" x 12" x 12 pies'], ['UND', 2.5, 'Regla 1" x 3" x 10 pies']]],
    'Plywood 4 x 8 pies 1/2"'                        => ['cat' => 'Maderas y tableros', 'marca' => null, 'und' => 'LAM', 'costo' => 560.00, 'stock' => 30, 'min' => 8, 'margen' => [30, 20]],
    'Plywood 4 x 8 pies 3/4"'                        => ['cat' => 'Maderas y tableros', 'marca' => null, 'und' => 'LAM', 'costo' => 780.00, 'stock' => 20, 'min' => 5, 'margen' => [30, 20]],
    'Tablayeso 4 x 8 pies 1/2"'                      => ['cat' => 'Maderas y tableros', 'marca' => null, 'und' => 'LAM', 'costo' => 210.00, 'stock' => 60, 'min' => 15, 'margen' => [30, 20]],
    'Fibrocemento (Durock) 4 x 8 pies 1/2"'          => ['cat' => 'Maderas y tableros', 'marca' => null, 'und' => 'LAM', 'costo' => 390.00, 'stock' => 25, 'min' => 6, 'margen' => [30, 20]],
    'Perfil (poste) para tablayeso 3 5/8" x 10 pies' => ['cat' => 'Maderas y tableros', 'marca' => null, 'und' => 'UND', 'costo' => 62.00, 'stock' => 120, 'min' => 30, 'margen' => [35, 25]],
    'Pasta para tablayeso 28 kg'                     => ['cat' => 'Maderas y tableros', 'marca' => null, 'und' => 'CUB', 'costo' => 420.00, 'stock' => 15, 'min' => 4, 'margen' => [30, 20]],

    // ── Techos y láminas ────────────────────────────────────────────
    'Lámina de zinc acanalada cal. 28 x 12 pies' => ['cat' => 'Techos y láminas', 'marca' => null, 'und' => 'LAM', 'costo' => 260.00, 'stock' => 100, 'min' => 20, 'margen' => [25, 18]],
    'Lámina de aluzinc cal. 26 x 12 pies'        => ['cat' => 'Techos y láminas', 'marca' => null, 'und' => 'LAM', 'costo' => 380.00, 'stock' => 60, 'min' => 15, 'margen' => [25, 18]],
    'Lámina traslúcida 12 pies'                  => ['cat' => 'Techos y láminas', 'marca' => null, 'und' => 'LAM', 'costo' => 340.00, 'stock' => 20, 'min' => 5, 'margen' => [30, 20]],
    'Cumbrera de zinc'                           => ['cat' => 'Techos y láminas', 'marca' => null, 'und' => 'UND', 'costo' => 95.00, 'stock' => 40, 'min' => 10, 'margen' => [30, 20]],

    // ── Mallas y cercas (por metro lineal o metro cuadrado) ─────────
    'Malla ciclón 6 pies (1.80 m) cal. 12.5'    => ['cat' => 'Mallas y cercas', 'marca' => null, 'und' => 'M', 'costo' => 75.00, 'stock' => 150, 'min' => 30, 'margen' => [30, 20], 'pres' => [['RLL', 15, 'Rollo 15 m', null, 'contiene', 22]]],
    'Malla de gallinero 1/2" (1 m de alto)'     => ['cat' => 'Mallas y cercas', 'marca' => null, 'und' => 'M', 'costo' => 28.00, 'stock' => 120, 'min' => 30, 'margen' => [35, 25], 'pres' => [['RLL', 30, 'Rollo 30 m', null, 'contiene', 25]]],
    'Cedazo de aluminio (1 m de ancho)'         => ['cat' => 'Mallas y cercas', 'marca' => null, 'und' => 'M', 'costo' => 45.00, 'stock' => 90, 'min' => 30, 'margen' => [40, 30], 'pres' => [['RLL', 30, 'Rollo 30 m', null, 'contiene', 28]]],
    'Malla sombra (sarán) 80 %'                 => ['cat' => 'Mallas y cercas', 'marca' => null, 'und' => 'M2', 'costo' => 18.00, 'stock' => 400, 'min' => 80, 'margen' => [40, 30], 'pres' => [['M', 4, 'Metro lineal (rollo de 4 m de ancho)'], ['RLL', 400, 'Rollo 4 x 100 m', null, 'contiene', 25]]],
    'Plástico negro calibre 600 (3 m de ancho)' => ['cat' => 'Mallas y cercas', 'marca' => null, 'und' => 'M', 'costo' => 24.00, 'stock' => 200, 'min' => 50, 'margen' => [40, 30], 'pres' => [['RLL', 50, 'Rollo 50 m', null, 'contiene', 28]]],
    'Alambre de púas cal. 15.5 (rollo 400 m)'   => ['cat' => 'Mallas y cercas', 'marca' => null, 'und' => 'RLL', 'costo' => 1150.00, 'stock' => 20, 'min' => 5, 'margen' => [25, 18]],

    // ── Pisos y revestimientos (por metro cuadrado) ─────────────────
    'Cerámica para piso 33 x 33 cm beige'  => ['cat' => 'Pisos y revestimientos', 'marca' => null, 'und' => 'M2', 'costo' => 145.00, 'stock' => 196, 'min' => 40, 'margen' => [35, 25], 'pres' => [['CJ', 1.96, 'Caja 18 piezas (1.96 m²)']]],
    'Porcelanato 60 x 60 cm gris'          => ['cat' => 'Pisos y revestimientos', 'marca' => null, 'und' => 'M2', 'costo' => 310.00, 'stock' => 144, 'min' => 29, 'margen' => [35, 25], 'pres' => [['CJ', 1.44, 'Caja 4 piezas (1.44 m²)']]],
    'Azulejo para pared 25 x 40 cm blanco' => ['cat' => 'Pisos y revestimientos', 'marca' => null, 'und' => 'M2', 'costo' => 165.00, 'stock' => 120, 'min' => 30, 'margen' => [35, 25], 'pres' => [['CJ', 1.5, 'Caja 15 piezas (1.50 m²)']]],
    'Pasto sintético 20 mm'                => ['cat' => 'Pisos y revestimientos', 'marca' => null, 'und' => 'M2', 'costo' => 160.00, 'stock' => 100, 'min' => 20, 'margen' => [40, 30], 'pres' => [['M', 2, 'Metro lineal (rollo de 2 m de ancho)'], ['RLL', 50, 'Rollo 2 x 25 m', null, 'contiene', 28]]],
    'Pegamento para cerámica 20 kg'        => ['cat' => 'Pisos y revestimientos', 'marca' => null, 'und' => 'BLS', 'costo' => 145.00, 'stock' => 50, 'min' => 10, 'margen' => [30, 20]],
    'Boquilla sin arena 5 lb'              => ['cat' => 'Pisos y revestimientos', 'marca' => null, 'und' => 'BLS', 'costo' => 55.00, 'stock' => 40, 'min' => 10, 'margen' => [40, 30]],

    // ── Tornillería y fijaciones ────────────────────────────────────
    'Clavo con cabeza 2 1/2"'                  => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'LB', 'precio' => 0, 'mayoreo' => null, 'costo' => 18.80, 'stock' => 150, 'min' => 40, 'margen' => [30, 20], 'pres' => [['CJ', 50, 'Caja 50 lb', null, 'contiene', 15], ['BLS', 1, 'Bolsa 1 lb', null], ['UND', 100, 'Clavo suelto', null, 'por_base']]],
    'Clavo con cabeza 3"'                      => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'LB', 'precio' => 0, 'mayoreo' => null, 'costo' => 18.50, 'stock' => 140, 'min' => 40, 'margen' => [30, 20], 'pres' => [['CJ', 50, 'Caja 50 lb', null, 'contiene', 15], ['BLS', 1, 'Bolsa 1 lb', null], ['UND', 80, 'Clavo suelto', null, 'por_base']]],
    'Clavo para lámina 2 1/2"'                 => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'LB', 'costo' => 26.00, 'stock' => 80, 'min' => 20, 'margen' => [35, 25], 'pres' => [['BLS', 1, 'Bolsa 1 lb']]],
    'Clavo de acero para concreto 2"'          => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'UND', 'costo' => 1.10, 'stock' => 2000, 'min' => 300, 'margen' => [60, 40], 'pres' => [['CJ', 100, 'Caja x100', null, 'contiene', 40]]],
    'Grapa para cerca 1"'                      => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'LB', 'costo' => 24.00, 'stock' => 60, 'min' => 15, 'margen' => [35, 25]],
    'Tornillo para tablayeso 6 x 1"'           => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'UND', 'precio' => 0, 'mayoreo' => null, 'costo' => 0.42, 'stock' => 5000, 'min' => 1000, 'margen' => [60, null], 'pres' => [['BLS', 100, 'Bolsa x100', null, 'contiene', 40], ['CJ', 1000, 'Caja x1000', null, 'contiene', 30]]],
    'Tornillo goloso 8 x 1 1/2"'               => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'UND', 'precio' => 0, 'mayoreo' => null, 'costo' => 0.70, 'stock' => 3000, 'min' => 500, 'margen' => [50, null], 'pres' => [['CJ', 100, 'Caja x100', null, 'contiene', 35]]],
    'Tornillo punta broca para lámina 1"'      => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'UND', 'costo' => 1.10, 'stock' => 3000, 'min' => 500, 'margen' => [60, 40], 'pres' => [['BLS', 100, 'Bolsa x100', null, 'contiene', 40]]],
    'Taco plástico (fisher) 1/4" con tornillo' => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'UND', 'costo' => 1.20, 'stock' => 2000, 'min' => 300, 'margen' => [60, 40], 'pres' => [['BLS', 100, 'Bolsa x100', null, 'contiene', 40]]],
    'Perno hexagonal 3/8" x 2" con tuerca'     => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'UND', 'costo' => 7.00, 'stock' => 300, 'min' => 50, 'margen' => [60, 40]],
    'Arandela plana 3/8"'                      => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'UND', 'costo' => 0.60, 'stock' => 1000, 'min' => 200, 'margen' => [80, 50], 'pres' => [['BLS', 100, 'Bolsa x100', null, 'contiene', 50]]],

    // ── Herramientas manuales ───────────────────────────────────────
    'Martillo de uña 16 oz'          => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'precio' => 210.00, 'mayoreo' => null, 'costo' => 135.00, 'stock' => 15, 'min' => 4],
    'Cinta métrica 5 m'              => ['cat' => 'Herramientas manuales', 'marca' => 'Stanley', 'und' => 'UND', 'precio' => 165.00, 'mayoreo' => null, 'costo' => 102.00, 'stock' => 20, 'min' => 5],
    'Pala cuadrada con mango'        => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 165.00, 'stock' => 20, 'min' => 5, 'margen' => [35, 25]],
    'Piocha con mango'               => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 210.00, 'stock' => 12, 'min' => 4, 'margen' => [35, 25]],
    'Carretilla 5 pies³'             => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 1350.00, 'stock' => 6, 'min' => 2, 'margen' => [30, null]],
    'Cuchara de albañil 8"'          => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 85.00, 'stock' => 20, 'min' => 5, 'margen' => [40, null]],
    'Llana metálica lisa'            => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 95.00, 'stock' => 15, 'min' => 4, 'margen' => [40, null]],
    'Nivel de aluminio 24"'          => ['cat' => 'Herramientas manuales', 'marca' => 'Stanley', 'und' => 'UND', 'costo' => 160.00, 'stock' => 10, 'min' => 3, 'margen' => [40, null]],
    'Serrucho 20"'                   => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 175.00, 'stock' => 10, 'min' => 3, 'margen' => [40, null]],
    'Alicate universal 8"'           => ['cat' => 'Herramientas manuales', 'marca' => 'Pretul', 'und' => 'UND', 'costo' => 120.00, 'stock' => 15, 'min' => 4, 'margen' => [40, null]],
    'Llave ajustable 10"'            => ['cat' => 'Herramientas manuales', 'marca' => 'Pretul', 'und' => 'UND', 'costo' => 160.00, 'stock' => 12, 'min' => 4, 'margen' => [40, null]],
    'Juego de desarmadores 6 piezas' => ['cat' => 'Herramientas manuales', 'marca' => 'Stanley', 'und' => 'JGO', 'costo' => 140.00, 'stock' => 10, 'min' => 3, 'margen' => [45, null]],
    'Arco para sierra 12"'           => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 110.00, 'stock' => 10, 'min' => 3, 'margen' => [40, null]],
    'Hoja de sierra bimetálica 12"'  => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 22.00, 'stock' => 60, 'min' => 15, 'margen' => [55, null]],
    'Mazo de 4 lb'                   => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 190.00, 'stock' => 8, 'min' => 2, 'margen' => [40, null]],

    // ── Herramientas eléctricas y brocas ────────────────────────────
    'Taladro percutor 1/2" 600 W'          => ['cat' => 'Herramientas eléctricas', 'marca' => 'Truper', 'und' => 'UND', 'precio' => 1450.00, 'mayoreo' => null, 'costo' => 980.00, 'stock' => 5, 'min' => 2],
    'Esmeriladora angular 4 1/2" 850 W'    => ['cat' => 'Herramientas eléctricas', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 1150.00, 'stock' => 6, 'min' => 2, 'margen' => [35, null]],
    'Sierra circular 7 1/4" 1400 W'        => ['cat' => 'Herramientas eléctricas', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 2300.00, 'stock' => 4, 'min' => 1, 'margen' => [35, null]],
    'Rotomartillo SDS plus 800 W'          => ['cat' => 'Herramientas eléctricas', 'marca' => 'Bosch', 'und' => 'UND', 'costo' => 2900.00, 'stock' => 3, 'min' => 1, 'margen' => [35, null]],
    'Motosierra a gasolina 18" 45 cc'      => ['cat' => 'Herramientas eléctricas', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 4700.00, 'stock' => 3, 'min' => 1, 'margen' => [30, null]],
    'Hidrolavadora 1600 PSI'               => ['cat' => 'Herramientas eléctricas', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 3200.00, 'stock' => 2, 'min' => 1, 'margen' => [30, null]],
    'Broca para concreto 3/8"'             => ['cat' => 'Herramientas eléctricas', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 38.00, 'stock' => 40, 'min' => 10, 'margen' => [55, null]],
    'Broca para metal HSS 1/4"'            => ['cat' => 'Herramientas eléctricas', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 26.00, 'stock' => 50, 'min' => 10, 'margen' => [55, null]],
    'Juego de brocas para metal 13 piezas' => ['cat' => 'Herramientas eléctricas', 'marca' => 'Truper', 'und' => 'JGO', 'costo' => 260.00, 'stock' => 8, 'min' => 2, 'margen' => [45, null]],

    // ── Soldadura ───────────────────────────────────────────────────
    'Electrodo 6013 1/8"'             => ['cat' => 'Soldadura', 'marca' => 'Lincoln', 'und' => 'LB', 'costo' => 48.00, 'stock' => 60, 'min' => 15, 'margen' => [35, 25], 'pres' => [['CJ', 10, 'Caja 10 lb', null, 'contiene', 25]]],
    'Electrodo 7018 1/8"'             => ['cat' => 'Soldadura', 'marca' => 'Lincoln', 'und' => 'LB', 'costo' => 62.00, 'stock' => 40, 'min' => 10, 'margen' => [35, 25], 'pres' => [['CJ', 10, 'Caja 10 lb', null, 'contiene', 25]]],
    'Soldadora inverter 200 A'        => ['cat' => 'Soldadura', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 3400.00, 'stock' => 3, 'min' => 1, 'margen' => [35, null]],
    'Careta para soldar fotosensible' => ['cat' => 'Soldadura', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 520.00, 'stock' => 6, 'min' => 2, 'margen' => [40, null]],
    'Guantes para soldador'           => ['cat' => 'Soldadura', 'marca' => 'Truper', 'und' => 'PAR', 'costo' => 110.00, 'stock' => 15, 'min' => 4, 'margen' => [40, null]],

    // ── Electricidad (cables por metro lineal) ──────────────────────
    'Cable THHN #14'                        => ['cat' => 'Electricidad', 'marca' => null, 'und' => 'M', 'costo' => 9.20, 'stock' => 500, 'min' => 100, 'margen' => [45, 30], 'pres' => [['RLL', 100, 'Rollo 100 m', null, 'contiene', 30]]],
    'Cable THHN #12'                        => ['cat' => 'Electricidad', 'marca' => null, 'und' => 'M', 'precio' => 19.00, 'mayoreo' => 17.00, 'costo' => 12.80, 'stock' => 600, 'min' => 100, 'pres' => [['RLL', 100, 'Rollo 100 m', 1650.00]]],
    'Cable THHN #10'                        => ['cat' => 'Electricidad', 'marca' => null, 'und' => 'M', 'precio' => 27.00, 'mayoreo' => 25.00, 'costo' => 18.90, 'stock' => 400, 'min' => 100, 'pres' => [['RLL', 100, 'Rollo 100 m', 2450.00]]],
    'Cordón dúplex 2 x 16 (cable paralelo)' => ['cat' => 'Electricidad', 'marca' => null, 'und' => 'M', 'costo' => 7.50, 'stock' => 300, 'min' => 100, 'margen' => [45, 30], 'pres' => [['RLL', 100, 'Rollo 100 m', null, 'contiene', 30]]],
    'Cable TSJ 3 x 12'                      => ['cat' => 'Electricidad', 'marca' => null, 'und' => 'M', 'costo' => 38.00, 'stock' => 200, 'min' => 50, 'margen' => [40, 28], 'pres' => [['RLL', 100, 'Rollo 100 m', null, 'contiene', 25]]],
    'Poliducto naranja 1/2"'                => ['cat' => 'Electricidad', 'marca' => null, 'und' => 'M', 'costo' => 4.50, 'stock' => 400, 'min' => 100, 'margen' => [50, 35], 'pres' => [['RLL', 100, 'Rollo 100 m', null, 'contiene', 30]]],
    'Tomacorriente doble polarizado'        => ['cat' => 'Electricidad', 'marca' => 'Bticino', 'und' => 'UND', 'precio' => 48.00, 'mayoreo' => null, 'costo' => 29.00, 'stock' => 60, 'min' => 15],
    'Apagador sencillo'                     => ['cat' => 'Electricidad', 'marca' => 'Bticino', 'und' => 'UND', 'precio' => 42.00, 'mayoreo' => null, 'costo' => 25.00, 'stock' => 60, 'min' => 15],
    'Breaker 1 polo 20 A'                   => ['cat' => 'Electricidad', 'marca' => 'Square D', 'und' => 'UND', 'costo' => 115.00, 'stock' => 30, 'min' => 8, 'margen' => [40, null]],
    'Centro de carga 4 espacios'            => ['cat' => 'Electricidad', 'marca' => 'Square D', 'und' => 'UND', 'costo' => 420.00, 'stock' => 8, 'min' => 2, 'margen' => [35, null]],
    'Caja rectangular plástica'             => ['cat' => 'Electricidad', 'marca' => null, 'und' => 'UND', 'costo' => 6.50, 'stock' => 200, 'min' => 50, 'margen' => [60, null]],
    'Cinta aislante negra'                  => ['cat' => 'Electricidad', 'marca' => '3M', 'und' => 'UND', 'costo' => 22.00, 'stock' => 80, 'min' => 20, 'margen' => [50, null]],
    'Extensión eléctrica 5 m'               => ['cat' => 'Electricidad', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 95.00, 'stock' => 20, 'min' => 5, 'margen' => [45, null]],

    // ── Iluminación ─────────────────────────────────────────────────
    'Foco LED 9 W luz blanca' => ['cat' => 'Iluminación', 'marca' => 'Sylvania', 'und' => 'UND', 'precio' => 55.00, 'mayoreo' => 50.00, 'costo' => 32.00, 'stock' => 100, 'min' => 24],
    'Tubo LED T8 18 W'        => ['cat' => 'Iluminación', 'marca' => 'Sylvania', 'und' => 'UND', 'costo' => 75.00, 'stock' => 40, 'min' => 10, 'margen' => [45, null]],
    'Reflector LED 50 W'      => ['cat' => 'Iluminación', 'marca' => 'Sylvania', 'und' => 'UND', 'costo' => 210.00, 'stock' => 15, 'min' => 4, 'margen' => [45, null]],

    // ── Plomería y PVC ──────────────────────────────────────────────
    'Tubo PVC 1/2" SDR 13.5 x 6 m'        => ['cat' => 'Plomería y PVC', 'marca' => 'Amanco', 'und' => 'UND', 'precio' => 95.00, 'mayoreo' => 89.00, 'costo' => 64.00, 'stock' => 120, 'min' => 30],
    'Tubo PVC 3/4" SDR 17 x 6 m'          => ['cat' => 'Plomería y PVC', 'marca' => 'Amanco', 'und' => 'UND', 'costo' => 82.00, 'stock' => 80, 'min' => 20, 'margen' => [35, 25]],
    'Tubo PVC 4" drenaje x 6 m'           => ['cat' => 'Plomería y PVC', 'marca' => 'Amanco', 'und' => 'UND', 'precio' => 520.00, 'mayoreo' => 495.00, 'costo' => 360.00, 'stock' => 25, 'min' => 6],
    'Codo PVC 1/2" x 90°'                 => ['cat' => 'Plomería y PVC', 'marca' => 'Amanco', 'und' => 'UND', 'precio' => 6.00, 'mayoreo' => null, 'costo' => 3.40, 'stock' => 400, 'min' => 100],
    'Tee PVC 1/2"'                        => ['cat' => 'Plomería y PVC', 'marca' => 'Amanco', 'und' => 'UND', 'costo' => 4.20, 'stock' => 300, 'min' => 80, 'margen' => [60, 40]],
    'Llave de paso 1/2"'                  => ['cat' => 'Plomería y PVC', 'marca' => null, 'und' => 'UND', 'costo' => 95.00, 'stock' => 25, 'min' => 6, 'margen' => [45, null]],
    'Llave de chorro 1/2"'                => ['cat' => 'Plomería y PVC', 'marca' => null, 'und' => 'UND', 'costo' => 110.00, 'stock' => 25, 'min' => 6, 'margen' => [45, null]],
    'Manguera de nivel transparente 3/8"' => ['cat' => 'Plomería y PVC', 'marca' => null, 'und' => 'M', 'costo' => 8.00, 'stock' => 100, 'min' => 25, 'margen' => [60, null]],
    'Cinta teflón 1/2"'                   => ['cat' => 'Plomería y PVC', 'marca' => null, 'und' => 'UND', 'precio' => 12.00, 'mayoreo' => null, 'costo' => 6.50, 'stock' => 200, 'min' => 50],
    'Tanque para agua 1100 litros'        => ['cat' => 'Plomería y PVC', 'marca' => 'Rotoplas', 'und' => 'UND', 'costo' => 3100.00, 'stock' => 4, 'min' => 1, 'margen' => [25, null]],

    // ── Baños y sanitarios ──────────────────────────────────────────
    'Inodoro de dos piezas blanco'  => ['cat' => 'Baños y sanitarios', 'marca' => null, 'und' => 'UND', 'costo' => 1650.00, 'stock' => 6, 'min' => 2, 'margen' => [30, null]],
    'Lavamanos de pedestal blanco'  => ['cat' => 'Baños y sanitarios', 'marca' => null, 'und' => 'UND', 'costo' => 1150.00, 'stock' => 4, 'min' => 1, 'margen' => [30, null]],
    'Asiento para inodoro'          => ['cat' => 'Baños y sanitarios', 'marca' => null, 'und' => 'UND', 'costo' => 260.00, 'stock' => 8, 'min' => 2, 'margen' => [40, null]],
    'Regadera cromada'              => ['cat' => 'Baños y sanitarios', 'marca' => null, 'und' => 'UND', 'costo' => 180.00, 'stock' => 10, 'min' => 3, 'margen' => [45, null]],
    'Manguera de abasto 1/2" x 16"' => ['cat' => 'Baños y sanitarios', 'marca' => null, 'und' => 'UND', 'costo' => 55.00, 'stock' => 30, 'min' => 8, 'margen' => [50, null]],
    'Sifón PVC 1 1/2"'              => ['cat' => 'Baños y sanitarios', 'marca' => 'Amanco', 'und' => 'UND', 'costo' => 45.00, 'stock' => 25, 'min' => 6, 'margen' => [50, null]],

    // ── Pinturas y acabados ─────────────────────────────────────────
    'Pintura látex blanco mate'       => ['cat' => 'Pinturas y acabados', 'marca' => 'Sherwin-Williams', 'und' => 'GAL', 'precio' => 420.00, 'mayoreo' => 399.00, 'costo' => 280.00, 'stock' => 60, 'min' => 12, 'pres' => [['CUB', 5, 'Cubeta 5 galones', 1950.00], ['QT', 0.25, 'Cuarto de galón', 125.00]]],
    'Anticorrosivo gris'              => ['cat' => 'Pinturas y acabados', 'marca' => 'Sherwin-Williams', 'und' => 'GAL', 'costo' => 330.00, 'stock' => 20, 'min' => 5, 'margen' => [35, 25], 'pres' => [['QT', 0.25, 'Cuarto de galón', null, 'contiene', 45]]],
    'Esmalte negro brillante'         => ['cat' => 'Pinturas y acabados', 'marca' => 'Sherwin-Williams', 'und' => 'GAL', 'costo' => 360.00, 'stock' => 20, 'min' => 5, 'margen' => [35, 25], 'pres' => [['QT', 0.25, 'Cuarto de galón', null, 'contiene', 45]]],
    'Impermeabilizante acrílico rojo' => ['cat' => 'Pinturas y acabados', 'marca' => null, 'und' => 'GAL', 'costo' => 310.00, 'stock' => 25, 'min' => 6, 'margen' => [35, 25], 'pres' => [['CUB', 5, 'Cubeta 5 galones', null, 'contiene', 25]]],
    'Thinner'                         => ['cat' => 'Pinturas y acabados', 'marca' => null, 'und' => 'GAL', 'precio' => 150.00, 'mayoreo' => 140.00, 'costo' => 98.00, 'stock' => 40, 'min' => 10],
    'Brocha 3"'                       => ['cat' => 'Pinturas y acabados', 'marca' => 'Truper', 'und' => 'UND', 'precio' => 45.00, 'mayoreo' => null, 'costo' => 26.00, 'stock' => 50, 'min' => 12],
    'Rodillo 9" con felpa'            => ['cat' => 'Pinturas y acabados', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 85.00, 'stock' => 25, 'min' => 6, 'margen' => [45, null]],

    // ── Adhesivos y selladores ──────────────────────────────────────
    'Pegamento para PVC 1/32 gal'   => ['cat' => 'Adhesivos y selladores', 'marca' => 'Amanco', 'und' => 'UND', 'precio' => 85.00, 'mayoreo' => null, 'costo' => 55.00, 'stock' => 40, 'min' => 10],
    'Silicón transparente 280 ml'   => ['cat' => 'Adhesivos y selladores', 'marca' => null, 'und' => 'UND', 'costo' => 65.00, 'stock' => 40, 'min' => 10, 'margen' => [45, null]],
    'Espuma expansiva 750 ml'       => ['cat' => 'Adhesivos y selladores', 'marca' => null, 'und' => 'UND', 'costo' => 140.00, 'stock' => 15, 'min' => 4, 'margen' => [45, null]],
    'Pegamento de contacto 1/4 gal' => ['cat' => 'Adhesivos y selladores', 'marca' => null, 'und' => 'UND', 'costo' => 120.00, 'stock' => 15, 'min' => 4, 'margen' => [45, null]],

    // ── Cerrajería y herrajes ───────────────────────────────────────
    'Candado de acero 40 mm'  => ['cat' => 'Cerrajería y herrajes', 'marca' => 'Pretul', 'und' => 'UND', 'precio' => 160.00, 'mayoreo' => null, 'costo' => 98.00, 'stock' => 20, 'min' => 5],
    'Chapa de pomo para baño' => ['cat' => 'Cerrajería y herrajes', 'marca' => 'Pretul', 'und' => 'UND', 'costo' => 190.00, 'stock' => 12, 'min' => 3, 'margen' => [45, null]],
    'Cerradura de sobreponer' => ['cat' => 'Cerrajería y herrajes', 'marca' => 'Pretul', 'und' => 'UND', 'costo' => 320.00, 'stock' => 8, 'min' => 2, 'margen' => [45, null]],
    'Bisagra de acero 3"'     => ['cat' => 'Cerrajería y herrajes', 'marca' => null, 'und' => 'PAR', 'costo' => 28.00, 'stock' => 60, 'min' => 15, 'margen' => [60, null]],
    'Cadena galvanizada 1/4"' => ['cat' => 'Cerrajería y herrajes', 'marca' => null, 'und' => 'M', 'costo' => 42.00, 'stock' => 80, 'min' => 20, 'margen' => [45, 30]],

    // ── Abrasivos y discos ──────────────────────────────────────────
    'Disco de corte metal 4 1/2"'           => ['cat' => 'Abrasivos y discos', 'marca' => 'Truper', 'und' => 'UND', 'precio' => 38.00, 'mayoreo' => 34.00, 'costo' => 22.00, 'stock' => 150, 'min' => 30, 'pres' => [['CJ', 25, 'Caja x25', 850.00]]],
    'Disco de desbaste metal 4 1/2"'        => ['cat' => 'Abrasivos y discos', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 30.00, 'stock' => 60, 'min' => 15, 'margen' => [45, 30]],
    'Disco flap 4 1/2" grano 60'            => ['cat' => 'Abrasivos y discos', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 42.00, 'stock' => 40, 'min' => 10, 'margen' => [45, 30]],
    'Disco diamantado para concreto 4 1/2"' => ['cat' => 'Abrasivos y discos', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 160.00, 'stock' => 15, 'min' => 4, 'margen' => [45, null]],
    'Lija de agua #100'                     => ['cat' => 'Abrasivos y discos', 'marca' => null, 'und' => 'PLG', 'precio' => 18.00, 'mayoreo' => null, 'costo' => 9.50, 'stock' => 200, 'min' => 50],
    'Lija para madera #80'                  => ['cat' => 'Abrasivos y discos', 'marca' => null, 'und' => 'PLG', 'costo' => 8.50, 'stock' => 150, 'min' => 40, 'margen' => [60, null]],

    // ── Seguridad industrial ────────────────────────────────────────
    'Guantes de carnaza'                => ['cat' => 'Seguridad industrial', 'marca' => 'Truper', 'und' => 'PAR', 'precio' => 75.00, 'mayoreo' => null, 'costo' => 44.00, 'stock' => 40, 'min' => 10],
    'Casco de seguridad'                => ['cat' => 'Seguridad industrial', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 95.00, 'stock' => 15, 'min' => 4, 'margen' => [45, null]],
    'Lentes de seguridad transparentes' => ['cat' => 'Seguridad industrial', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 38.00, 'stock' => 30, 'min' => 8, 'margen' => [50, null]],
    'Mascarilla N95'                    => ['cat' => 'Seguridad industrial', 'marca' => '3M', 'und' => 'UND', 'costo' => 18.00, 'stock' => 200, 'min' => 40, 'margen' => [60, 40], 'pres' => [['CJ', 20, 'Caja x20', null, 'contiene', 40]]],
    'Botas de hule'                     => ['cat' => 'Seguridad industrial', 'marca' => null, 'und' => 'PAR', 'costo' => 240.00, 'stock' => 12, 'min' => 3, 'margen' => [40, null]],
    'Chaleco reflectivo'                => ['cat' => 'Seguridad industrial', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 75.00, 'stock' => 15, 'min' => 4, 'margen' => [45, null]],
    'Cinta de precaución amarilla'      => ['cat' => 'Seguridad industrial', 'marca' => null, 'und' => 'M', 'costo' => 0.90, 'stock' => 900, 'min' => 300, 'margen' => [60, null], 'pres' => [['RLL', 300, 'Rollo 300 m', null, 'contiene', 40]]],

    // ── Jardinería y agro ───────────────────────────────────────────
    'Manguera para jardín 1/2"'  => ['cat' => 'Jardinería y agro', 'marca' => 'Truper', 'und' => 'M', 'precio' => 22.00, 'mayoreo' => null, 'costo' => 13.00, 'stock' => 150, 'min' => 30, 'pres' => [['RLL', 30, 'Rollo 30 m', 600.00]]],
    'Machete 22"'                => ['cat' => 'Jardinería y agro', 'marca' => 'Imacasa', 'und' => 'UND', 'costo' => 115.00, 'stock' => 30, 'min' => 8, 'margen' => [40, 30]],
    'Azadón con mango'           => ['cat' => 'Jardinería y agro', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 185.00, 'stock' => 12, 'min' => 4, 'margen' => [35, 25]],
    'Rastrillo de 14 dientes'    => ['cat' => 'Jardinería y agro', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 120.00, 'stock' => 10, 'min' => 3, 'margen' => [40, null]],
    'Tijera para podar'          => ['cat' => 'Jardinería y agro', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 160.00, 'stock' => 10, 'min' => 3, 'margen' => [40, null]],
    'Bomba de mochila 16 litros' => ['cat' => 'Jardinería y agro', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 780.00, 'stock' => 6, 'min' => 2, 'margen' => [30, null]],
    'Cadena para motosierra 18"' => ['cat' => 'Jardinería y agro', 'marca' => 'Truper', 'und' => 'UND', 'costo' => 420.00, 'stock' => 6, 'min' => 2, 'margen' => [40, null]],
    'Aceite 2 tiempos'           => ['cat' => 'Jardinería y agro', 'marca' => null, 'und' => 'LT', 'costo' => 160.00, 'stock' => 20, 'min' => 5, 'margen' => [35, null], 'pres' => [['BOT', 0.25, 'Botella 250 ml', null, 'contiene', 45]]],
    'Fertilizante 15-15-15'      => ['cat' => 'Jardinería y agro', 'marca' => null, 'und' => 'LB', 'costo' => 7.20, 'stock' => 1000, 'min' => 200, 'margen' => [25, 15], 'isv' => 'exento', 'pres' => [['QQ', 100, 'Quintal (saco 100 lb)', null, 'contiene', 15]]],
    'Cuerda de nylon 1/4"'       => ['cat' => 'Jardinería y agro', 'marca' => null, 'und' => 'M', 'costo' => 3.50, 'stock' => 300, 'min' => 100, 'margen' => [60, 40], 'pres' => [['RLL', 100, 'Rollo 100 m', null, 'contiene', 40]]],

    // ── Limpieza ────────────────────────────────────────────────────
    'Ácido muriático'          => ['cat' => 'Limpieza', 'marca' => null, 'und' => 'GAL', 'costo' => 70.00, 'stock' => 20, 'min' => 5, 'margen' => [45, null]],
    'Escoba plástica'          => ['cat' => 'Limpieza', 'marca' => null, 'und' => 'UND', 'costo' => 65.00, 'stock' => 20, 'min' => 5, 'margen' => [45, null]],
    'Balde plástico 12 litros' => ['cat' => 'Limpieza', 'marca' => null, 'und' => 'UND', 'costo' => 55.00, 'stock' => 20, 'min' => 5, 'margen' => [45, null]],

    // ── Servicios (no llevan inventario) ────────────────────────────
    'Corte de tubo o varilla'            => ['cat' => 'Servicios', 'marca' => null, 'und' => 'UND', 'precio' => 25.00, 'mayoreo' => null, 'costo' => 0, 'stock' => 0, 'min' => 0, 'servicio' => true],
    'Copia de llave'                     => ['cat' => 'Servicios', 'marca' => null, 'und' => 'UND', 'precio' => 40.00, 'mayoreo' => null, 'costo' => 0, 'stock' => 0, 'min' => 0, 'servicio' => true],
    'Entrega a domicilio (en la ciudad)' => ['cat' => 'Servicios', 'marca' => null, 'und' => 'UND', 'precio' => 100.00, 'mayoreo' => null, 'costo' => 0, 'stock' => 0, 'min' => 0, 'servicio' => true],
];
