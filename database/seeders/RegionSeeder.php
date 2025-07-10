<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RegionSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('villages')->truncate();
        DB::table('districts')->truncate();
        DB::table('regencies')->truncate();
        DB::table('provinces')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->importCsv('provinces', 'provinces.csv', ['id', 'name']);
        $this->importCsv('regencies', 'regencies.csv', ['id', 'province_id', 'name']);
        $this->importCsv('districts', 'districts.csv', ['id', 'regency_id', 'name']);
        $this->importCsv('villages', 'villages.csv', ['id', 'district_id', 'name']);
    }

    protected function importCsv(string $table, string $filename, array $columns): void
    {
        $path = database_path("data/{$filename}");
        if (!File::exists($path)) {
            $this->command->error("File {$filename} not found!");
            return;
        }

        $handle = fopen($path, 'r');
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($columns))
                continue;

            $data = array_combine($columns, $row);
            DB::table($table)->insert($data);
        }

        fclose($handle);
        $this->command->info(" Imported {$table} from {$filename}");
    }
}
