<?php
namespace EllisLab\ExpressionEngine\Service\Model;

use EllisLab\ExpressionEngine\Service\Model\Query\Builder;

class DataStore {

	protected $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	// factories

	/**
	 * @param Object|String $name      The name of a model or an existing
	 *                                 model instance
	 * @param Frontend      $frontend  A frontend instance. The datastore
	 *                                 doesn't care about the frontend, but
	 *                                 the model and associations need it, so
	 *                                 we pass it in to keep things properly
	 *                                 isolated.
	 * @param Array         $data      The initial data to set on the object.
	 *                                 This will be marked as dirty! Use fill()
	 *                                 if you need clean data (i.e. from db).
	 */
	public function make($name, Frontend $frontend, array $data = array())
	{
		if ($name instanceOf Model)
		{
			$object = $name;
			$name = $this->getModelAlias(get_class($object));
		}
		else
		{
			$class = $this->expandModelAlias($name);
			$model = new $class($data);
		}

		$model->setName($name);
		$model->setFrontend($frontend);

		$this->initializeAssociationsOn($model);

		return $model;
	}

	/**
	 *
	 */
	public function get($name)
	{
		$object = NULL;

		if ($name instanceOf Model)
		{
			$object = $name;
			$name = $object->getName();
		}

		$builder = new Builder($name);

		$builder->setExisting($object);
		$builder->setDataStore($this);

		return $builder;
	}

	/**
	 *
	 */
	public function rawQuery()
	{
		return $this->db;
	}

	/**
	 *
	 */
	public function getMetaDataReader($name)
	{
		$class = $this->expandModelAlias($name);

		return new MetaDataReader($name, $class);
	}

	/**
	 *
	 */
	protected function initializeAssociationsOn(Model $model)
	{
		$from_reader = $this->getMetaDataReader($model->getName());
		$relationships = $from_reader->getRelationships();

		$result = array();

		foreach ($relationships as $name => $info)
		{
			$relation = $this->getRelation($model->getName(), $name);

			$assoc = $relation->createAssociation($model);
			$assoc->setRelation($relation); // todo move into relation

			$model->setAssociation($name, $assoc);
		}
	}

	/**
	 * TODO stinky
	 */
	public function getRelation($model_name, $name)
	{
		// TODO cache the options array.
		// we can recreate the readers easily and cheaply, but
		// there can be a lot involved in the options.

		$from_reader = $this->getMetaDataReader($model_name);
		$relationships = $from_reader->getRelationships();

		if ( ! isset($relationships[$name]))
		{
			// TODO use name as the model name and attempt to
			// look it up in the other direction
			throw new \Exception("Relationship {$name} not found in model definition.");
		}

		$options = $relationships[$name];

		$options = array_merge(
			array(
				'model' => $name,

				'from_key' => NULL,
				'from_table' => NULL,

				'to_key' => NULL,
				'to_table' => NULL
			),
			$options
		);

		$to_reader = $this->getMetaDataReader($options['model']);

		$options['from_primary_key'] = $from_reader->getPrimaryKey();
		$options['to_primary_key'] = $to_reader->getPrimaryKey();

		// pivot can either be an array or a gateway name.
		// if it is a gateway, then the lhs and rhs keys must
		// equal the pk's of the two models
		if (isset($options['pivot']))
		{
			$pivot = $options['pivot'];

			if ( ! is_array($pivot))
			{
				$gateway_tables = $from_reader->getTableNamesByGateway();
				$table = $gateway_tables[$pivot];

				$pivot = array(
					'table' => $table,
					'left'  => $options['from_primary_key'],
					'right' => $options['to_primary_key']
				);
			}
		}

		$type = $options['type'];
		$class = __NAMESPACE__."\\Relation\\{$type}";

		$relation = new $class($from_reader, $to_reader, $name, $options);
		$relation->setDataStore($this);

		return $relation;
	}

	// query strategies

	/**
	 *
	 */
	public function fetchQuery(Builder $qb)
	{
		return $this->runQuery('Select', $qb);
	}

	/**
	 *
	 */
	public function insertQuery(Builder $qb)
	{
		return $this->runQuery('Insert', $qb);
	}

	/**
	 *
	 */
	public function updateQuery(Builder $qb)
	{
		return $this->runQuery('Update', $qb);
	}

	/**
	 *
	 */
	public function deleteQuery(Builder $qb)
	{
		return $this->runQuery('Delete', $qb);
	}

	// helpers

	/**
	 *
	 */
	protected function runQuery($name, Builder $qb)
	{
		$class = __NAMESPACE__."\\Query\\{$name}";

		$worker = new $class($this, $qb);
		return $worker->run();
	}

	/**
	 *
	 */
	protected function getModelAlias($class)
	{
		return $name;
	}

	/**
	 *
	 */
	protected function expandModelAlias($name)
	{
		// TODO replace!!
		$aliases = array(
			'Template' => 'EllisLab\ExpressionEngine\Model\Template\Template',
			'TemplateGroup'  => 'EllisLab\ExpressionEngine\Model\Template\TemplateGroup',
		);

		return $aliases[$name];
	}
}