<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi Repository
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Core\Data;
use Ushahidi\Core\SearchData;
use Ushahidi\Core\Usecase;
use Ushahidi\Core\Traits\CollectionLoader;

abstract class Ushahidi_Repository implements
	Usecase\CreateRepository,
	Usecase\ReadRepository,
	Usecase\UpdateRepository,
	Usecase\DeleteRepository,
	Usecase\SearchRepository
{

	use CollectionLoader;

	protected $db;
	protected $search_query;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	/**
	 * Get the entity for this repository.
	 * @param  Array  $data
	 * @return Ushahidi\Core\Entity
	 */
	abstract public function getEntity(Array $data = null);

	/**
	 * Get the table name for this repository.
	 * @return String
	 */
	abstract protected function getTable();

	/**
	 * Apply search conditions from input data.
	 * Must be overloaded to enable searching.
	 * @throws LogicException
	 * @param  SearchData $search
	 * @return void
	 */
	protected function setSearchConditions(SearchData $search)
	{
		throw new \LogicException('Not implemented by this repository');
	}

	// CreateRepository
	// ReadRepository
	// UpdateRepository
	// DeleteRepository
	public function get($id)
	{
		return $this->getEntity($this->selectOne(compact('id')));
	}

	// CreateRepository
	public function create(Data $input)
	{
		return $this->executeInsert($input->asArray());
	}

	// UpdateRepository
	public function update($id, Data $input)
	{
		return $this->executeUpdate(compact('id'), $input->asArray());
	}

	// DeleteRepository
	public function delete($id)
	{
		return $this->executeDelete(compact('id'));
	}

	// SearchRepository
	public function setSearchParams(SearchData $search)
	{
		$this->search_query = $this->selectQuery();

		// apply the sorting parameters
		$sorting = $search->getSortingParams();

		if (!empty($sorting['orderby'])) {
			$this->search_query->order_by(
				$this->getTable() . '.' . $sorting['orderby'],
				Arr::get($sorting, 'order')
			);
		}

		if (!empty($sorting['offset'])) {
			$this->search_query->offset($sorting['offset']);
		}

		if (!empty($sorting['limit'])) {
			$this->search_query->limit($sorting['limit']);
		}

		// apply the unique conditions of the search
		$this->setSearchConditions($search);
	}

	// SearchRepository
	public function getSearchResults()
	{
		$query = $this->getSearchQuery();

		$results = $query->distinct(TRUE)->execute($this->db);

		return $this->getCollection($results->as_array());
	}

	// SearchRepository
	public function getSearchTotal()
	{
		// Assume we can simply count the results to get a total
		$query = $this->getSearchQuery(true)
			->select([DB::expr('COUNT(*)'), 'total']);

		// Fetch the result and...
		$result = $query->execute($this->db);

		// ... return the total.
		return (int) $result->get('total', 0);
	}

	/**
	 * Get a copy of the current search query, optionally removing the LIMIT,
	 * OFFSET, and ORDER BY parameters (for query that can be COUNT'ed).
	 * @throws RuntimeException if called before search parameters are set
	 * @param  Boolean $countable  remove limit/offset/orderby
	 * @return Database_Query_Select
	 */
	protected function getSearchQuery($countable = false)
	{
		if (!$this->search_query) {
			throw new \RuntimeException('Cannot get search results until setSearchParams has been called');
		}

		// We always clone the query, because once search parameters have been set,
		// the query cannot be modified until new parameters are applied.
		$query = clone $this->search_query;

		if ($countable) {
			$query
				->limit(null)
				->offset(null);
		}

		return $query;
	}

	/**
	 * Get a single record meeting some conditions.
	 * @param  Array $where hash of conditions
	 * @return Array
	 */
	protected function selectOne(Array $where = [])
	{
		$result = $this->selectQuery($where)
			->limit(1)
			->execute($this->db);
		return $result->current();
	}

	/**
	 * Get a count of records meeting some conditions.
	 * @param  Array $where hash of conditions
	 * @return Integer
	 */
	protected function selectCount(Array $where = [])
	{
		$result = $this->selectQuery($where)
			->select([DB::expr('COUNT(*)'), 'total'])
			->execute($this->db);
		return $result->get('total') ?: 0;
	}

	/**
	 * Return a SELECT query, optionally with preconditions.
	 * @param  Array $where optional hash of conditions
	 * @return Database_Query_Builder_Select
	 */
	protected function selectQuery(Array $where = [])
	{
		$query = DB::select($this->getTable() . '.*')->from($this->getTable());
		foreach ($where as $column => $value)
		{
			$predicate = is_array($value) ? 'IN' : '=';
			$query->where($column, $predicate, $value);
		}
		return $query;
	}

	/**
	 * Create a single record from input and return the created ID.
	 * @param  Array $input hash of input
	 * @return Integer
	 */
	protected function executeInsert(Array $input)
	{
		if (!$input) {
			throw new RuntimeException(sprintf(
				'Cannot create an empty record in table "%s"',
				$this->getTable()
			));
		}

		$query = DB::insert($this->getTable())
			->columns(array_keys($input))
			->values(array_values($input))
			;

		list($id) = $query->execute($this->db);
		return $id;
	}

	/**
	 * Update records from input with conditions and return the number affected.
	 * @param  Array $where hash of conditions
	 * @param  Array $input hash of input
	 * @return Integer
	 */
	protected function executeUpdate(Array $where, Array $input)
	{
		if (!$where) {
			throw new RuntimeException(sprintf(
				'Cannot update every record in table "%s"',
				$this->getTable()
			));
		}

		if (!$input) {
			return 0; // nothing would be updated, just ignore
		}

		$query = DB::update($this->getTable())->set($input);
		foreach ($where as $column => $value)
		{
			$query->where($column, '=', $value);
		}

		$count = $query->execute($this->db);
		return $count;
	}

	/**
	 * Delete records with conditions and return the number affected.
	 * @param  Array $where hash of conditions
	 * @return Integer
	 */
	protected function executeDelete(Array $where)
	{
		if (!$where) {
			throw new RuntimeException(sprintf(
				'Cannot delete every record in table "%s"',
				$this->getTable()
			));
		}

		$query = DB::delete($this->getTable());
		foreach ($where as $column => $value)
		{
			$query->where($column, '=', $value);
		}

		$count = $query->execute($this->db);
		return $count;
	}

	/**
	 * Converts an array of results into an array of entities,
	 * indexed by the entity id.
	 * @param  Array $results
	 * @return Array
	 */
	protected function getCollection(Array $results)
	{
		$collection = [];
		foreach ($results as $row) {
			$entity = $this->getEntity($row);
			$collection[$entity->id] = $entity;
		}
		return $collection;
	}

	/**
	 * Check if an entity with the given id exists
	 * @param  int $id
	 * @return bool
	 */
	public function exists($id)
	{
		return (bool) $this->selectCount(compact('id'));
	}
}
