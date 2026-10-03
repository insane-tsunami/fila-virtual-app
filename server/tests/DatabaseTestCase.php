<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
        self::garantirBancoDescartavel($config['db']);
        $this->db = Database::connect($config['db']);
        $this->db->schema()->dropAllTables();
    }

    /**
     * Os testes apagam TODAS as tabelas do banco a cada teste. Em SQLite (em memória
     * ou arquivo) isso é inofensivo; em MySQL só é permitido se o nome do banco
     * indicar que é de teste, para nunca apagar um banco real por engano (por
     * exemplo, com as variáveis DB_* de produção exportadas no shell).
     *
     * @param array<string, string> $db
     */
    public static function garantirBancoDescartavel(array $db): void
    {
        if (($db['driver'] ?? '') === 'sqlite') {
            return;
        }

        if (!str_contains(strtolower($db['database'] ?? ''), 'test')) {
            throw new RuntimeException(sprintf(
                'Recusando rodar os testes no banco "%s": os testes apagam todas as tabelas. '
                . 'Use SQLite (padrão) ou um banco MySQL cujo nome contenha "test".',
                $db['database'] ?? ''
            ));
        }
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
