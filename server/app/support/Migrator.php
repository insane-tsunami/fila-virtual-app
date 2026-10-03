<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;

/**
 * Runner mínimo de migrações: executa em ordem os arquivos `NNNN_nome.php` de um
 * diretório que ainda não constam na tabela `migrations`. Cada arquivo retorna um
 * objeto com `up(Capsule $db): void`.
 */
final class Migrator
{
    public function __construct(
        private readonly Capsule $db,
        private readonly string $directory,
    ) {
    }

    /** @return list<string> nomes das migrações executadas nesta chamada */
    public function run(): array
    {
        $this->ensureMigrationsTable();
        $done = $this->db->table('migrations')->pluck('migration')->all();
        $executed = [];

        foreach ($this->files() as $name => $path) {
            if (in_array($name, $done, true)) {
                continue;
            }

            $migration = require $path;
            if (!is_object($migration) || !method_exists($migration, 'up')) {
                throw new RuntimeException("A migração $name deve retornar um objeto com up()");
            }

            $migration->up($this->db);
            $this->db->table('migrations')->insert([
                'migration' => $name,
                'executed_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $executed[] = $name;
        }

        return $executed;
    }

    private function ensureMigrationsTable(): void
    {
        $schema = $this->db->schema();
        if ($schema->hasTable('migrations')) {
            return;
        }

        $schema->create('migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('migration')->unique();
            $table->dateTime('executed_at');
        });
    }

    /** @return array<string, string> nome => caminho, em ordem */
    private function files(): array
    {
        $paths = glob(rtrim($this->directory, '/') . '/*.php') ?: [];
        sort($paths);

        $files = [];
        foreach ($paths as $path) {
            $files[basename($path, '.php')] = $path;
        }

        return $files;
    }
}
