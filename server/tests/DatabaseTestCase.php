<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use Support\Database;
use Support\Migrator;

/**
 * Base dos testes que usam banco. O driver vem do ambiente (SQLite em memória por
 * padrão, MySQL quando o CI define DB_*) e as tabelas são recriadas a cada teste.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Capsule $db;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__ . '/../api/config.php';
        $this->db = Database::connect($config['db']);
        $this->db->schema()->dropAllTables();
    }

    protected function migrate(): void
    {
        (new Migrator($this->db, __DIR__ . '/../database/migrations'))->run();
    }

    /** Cria um estabelecimento de teste e devolve o id. */
    protected function criarEstabelecimento(string $slug = 'loja-teste', string $nome = 'Loja Teste'): int
    {
        return (int) $this->db->table('estabelecimentos')->insertGetId(['nome' => $nome, 'slug' => $slug]);
    }
}
