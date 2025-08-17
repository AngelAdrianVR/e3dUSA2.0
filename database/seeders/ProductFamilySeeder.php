<?php

namespace Database\Seeders;

use App\Models\ProductFamily;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductFamilySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $families = [
            ['name' => 'Emblema', 'key' => 'EM'],
            ['name' => 'Portaplaca', 'key' => 'PP'],
            ['name' => 'Llavero', 'key' => 'LL'],
            ['name' => 'Parasol', 'key' => 'PS'],
            ['name' => 'Tapete', 'key' => 'TP'],
            ['name' => 'Manta', 'key' => 'MT'],
            ['name' => 'Carpeta', 'key' => 'CP'],
            ['name' => 'Separador', 'key' => 'SP'],
            ['name' => 'Portadocumentos', 'key' => 'PD'],
            ['name' => 'Termo', 'key' => 'TM'],
            ['name' => 'Placa de estireno', 'key' => 'PE'],
            ['name' => 'Etiqueta', 'key' => 'ET'],
            ['name' => 'Overlay', 'key' => 'OV'],
            ['name' => 'Accesorio para llavero', 'key' => 'ALL'],
            ['name' => 'Pin', 'key' => 'PI'],
            ['name' => 'Prenda', 'key' => 'PR'],
            ['name' => 'Botella', 'key' => 'BT'],
            ['name' => 'Hielera', 'key' => 'HI'],
            ['name' => 'Funda para auto', 'key' => 'FA'],
            ['name' => 'Perfumero', 'key' => 'PF'],
            ['name' => 'Funda para llavero', 'key' => 'FLL'],
            ['name' => 'Bocina', 'key' => 'BC'],
            ['name' => 'Impresión', 'key' => 'IM'],
        ];

        foreach ($families as $family) {
            // Usa firstOrCreate para evitar duplicados.
            // Buscará una familia con la misma 'key', y si no la encuentra, la creará.
            ProductFamily::firstOrCreate(
                ['key' => $family['key']],
                ['name' => $family['name']]
            );
        }
    }
}
