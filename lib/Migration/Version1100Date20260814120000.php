<?php

declare(strict_types=1);

namespace OCA\Televisorium\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * @psalm-suppress UnusedClass
 */
class Version1100Date20260814120000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('televisorium_items')) {
			$table = $schema->createTable('televisorium_items');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('item_type', Types::STRING, [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('tmdb_id', Types::BIGINT, [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('year', Types::SMALLINT, [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('runtime', Types::BIGINT, [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('poster_url', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('backdrop_url', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('overview', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 20,
				'default' => 'watchlist',
			]);
			$table->addColumn('rating', Types::SMALLINT, [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('watched_seconds', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
				'default' => 0,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'tvs_items_user_id');
			$table->addIndex(['user_id', 'status'], 'tvs_items_user_status');
			$table->addUniqueIndex(['user_id', 'tmdb_id'], 'tvs_items_user_tmdb');
		}

		if (!$schema->hasTable('televisorium_episodes')) {
			$table = $schema->createTable('televisorium_episodes');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('item_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('season_number', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('episode_number', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('runtime', Types::BIGINT, [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('watched', Types::BOOLEAN, [
				'notnull' => true,
				'default' => false,
			]);
			$table->addColumn('watched_seconds', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
				'default' => 0,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['item_id'], 'tvs_eps_item_id');
			$table->addUniqueIndex(['item_id', 'season_number', 'episode_number'], 'tvs_eps_unique');
		}

		return $schema;
	}
}
