<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PgToSqlite extends Command
{
    protected $signature = 'db:pg-to-sqlite';
    protected $description = 'Copia datos de Supabase (pgsql_src) a SQLite';

    public function handle()
    {
        $src = DB::connection('pgsql_src');
        $dst = DB::connection('sqlite');
        $dst->statement('PRAGMA foreign_keys = OFF');

        $skip = ['migrations', 'sqlite_sequence'];

        foreach (Schema::connection('sqlite')->getTableListing() as $table) {
            $table = last(explode('.', $table));
            if (in_array($table, $skip)) continue;

            if (!Schema::connection('pgsql_src')->hasTable($table)) {
                $this->warn("Omitida (no existe en Supabase): $table");
                continue;
            }

            $dst->table($table)->delete();
            $cols = Schema::connection('sqlite')->getColumnListing($table);
            $buffer = [];
            $n = 0;

            foreach ($src->table($table)->cursor() as $row) {
                $buffer[] = array_intersect_key((array) $row, array_flip($cols));
                if (count($buffer) >= 200) {
                    $dst->table($table)->insert($buffer);
                    $n += count($buffer);
                    $buffer = [];
                }
            }

            if ($buffer) {
                $dst->table($table)->insert($buffer);
                $n += count($buffer);
            }

            $this->info("$table: $n filas");
        }

        $dst->statement('PRAGMA foreign_keys = ON');
        $this->info('Copia terminada.');
    }
}