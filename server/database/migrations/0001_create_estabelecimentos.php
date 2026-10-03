<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(Capsule $db): void
    {
        $db->schema()->create('estabelecimentos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120);
            $table->string('slug', 80)->unique();
        });
    }
};
