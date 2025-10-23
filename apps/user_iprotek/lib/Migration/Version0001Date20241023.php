<?php

declare(strict_types=1);

namespace OCA\OCA\UserIprotek\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

if (class_exists(__NAMESPACE__ . '\\Version0001Date20241023')) {
    return;
}

class Version0001Date20241023 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('user_iprotek_tokens')) {
            $table = $schema->createTable('user_iprotek_tokens');

            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);

            $table->addColumn('user_id', 'string', [
                'length' => 64,
                'notnull' => true,
            ]);

            $table->addColumn('token', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('refresh_token', 'string', [
                'length' => 255,
                'notnull' => false,
            ]);

            $table->addColumn('browser_id', 'string', [
                'length' => 128,
                'notnull' => false,
                'default' => null,
                'comment' => 'Unique identifier of browser or device',
            ]);

            $table->addColumn('expires_at', 'datetime', [
                'notnull' => false,
            ]);

            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
            ]);

            // Primary and indexes
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'token_user_idx');
            $table->addIndex(['browser_id'], 'token_browser_idx');
        }

        return $schema;
    }
}