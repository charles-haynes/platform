<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi Set Repository
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Core\Entity;
use Ushahidi\Core\Entity\Set;
use Ushahidi\Core\Entity\SavedSearch;
use Ushahidi\Core\Entity\SetRepository;
use Ushahidi\Core\SearchData;
use Ushahidi\Core\Tool\JsonTranscode;

class Ushahidi_Repository_Set extends Ushahidi_Repository implements SetRepository
{
	protected $post_repo;
	protected $json_transcoder;
	protected $json_properties = ['filter', 'view_options', 'visible_to'];

	public function setTranscoder(JsonTranscode $transcoder)
	{
		$this->json_transcoder = $transcoder;
	}

	/**
	 * @var  Boolean  Return SavedSearches (when true) or vanilla Sets
	 **/
	protected $savedSearch = false;

	public function setSavedSearch($savedSearch)
	{
		$this->savedSearch = $savedSearch;
	}

	// Ushahidi_Repository
	protected function getTable()
	{
		return 'sets';
	}

	// Ushahidi_Repository
	public function getEntity(Array $data = null)
	{
		return $this->savedSearch ? new SavedSearch($data) : new Set($data);
	}

	/**
	 * Override selectQuery to enforce filtering by search=0/1
	 */
	protected function selectQuery(Array $where = [])
	{
		$query = parent::selectQuery($where);

		$query->where('search', '=', (int)$this->savedSearch);

		return $query;
	}

	/*
	 * Override core get/create/update/delete methods to only include
	 * saved searches or sets depending on $this->savedSearch
	 */

	// CreateRepository
	public function create(Entity $entity) {
		$record = array_filter($this->json_transcoder->encode(
			$entity->asArray(),
			$this->json_properties
		));
		$record['created'] = time();
		$record['search'] = (int)$this->savedSearch;
		return $this->executeInsert($this->removeNullValues($record));

	}

	// UpdateRepository
	public function update(Entity $entity)
	{
		$record = $entity->getChanged();
		$record['updated'] = time();

		return $this->executeUpdate([
			'id' => $entity->id,
			'search' => (int)$this->savedSearch
		], $entity->getChanged());
	}

	// DeleteRepository
	public function delete(Entity $entity)
	{
		return $this->executeDelete([
			'id' => $entity->id,
			'search' => (int)$this->savedSearch
		]);
	}

	// SearchRepository
	public function getSearchFields()
	{
		return [
			'user_id',
			'q', /* LIKE name */
			'featured',
		];
	}

	// SearchRepository
	public function setSearchParams(SearchData $search)
	{
		// Overriding so we can alter sorting logic
		// @todo make it easier to override just sorting

		$this->search_query = $this->selectQuery();

		$sorting = $search->getSorting();

		// Always return featured sets first
		// @todo make this optional
		$this->search_query->order_by('sets.featured', 'DESC');

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

	// Ushahidi_Repository
	protected function setSearchConditions(SearchData $search)
	{
		$sets_query = $this->search_query;

		if ($search->q)
		{
			$sets_query->where('name', 'LIKE', "%{$search->q}%");
		}

		if ($search->featured !== null)
		{
			$sets_query->where('featured', '=', (int)$search->featured);
		}

		if ($search->user_id)
		{
			$sets_query->where('user_id', '=', $search->user_id);
		}

		if (isset($search->search))
		{
			$sets_query->where('search', '=', (int)$search->search);
		}

		if ($search->id)
		{
			$sets_query->where('id', '=', $search->id);
		}
	}

	// SetRepository
	public function deleteSetPost($set_id, $post_id)
	{
		if ($this->savedSearch) {
			throw new \LogicException('deleteSetPost method is not available for Saved Searches');
		}

		DB::delete('posts_sets')
			->where('post_id', '=', $post_id)
			->where('set_id', '=', $set_id)
			->execute($this->db);
	}

	// SetRepository
	public function setPostExists($set_id, $post_id)
	{
		if ($this->savedSearch) {
			throw new \LogicException('setPostExists method is not available for Saved Searches');
		}

		$result = DB::select('posts_sets.*')
			->from('posts_sets')
			->where('post_id', '=', $post_id)
			->where('set_id', '=', $set_id)
			->execute($this->db)
			->as_array();

		return (bool) count($result);
	}

	// SetRepository
	public function addPostToSet($set_id, $post_id)
	{
		if ($this->savedSearch) {
			throw new \LogicException('addPostToSet method is not available for Saved Searches');
		}

		// Ensure post_id is an int
		// @todo this probably should have happened elsewhere
		$post_id = (int)$post_id;
		$set_id = (int)$set_id;

		DB::insert('posts_sets')
			->columns(['post_id', 'set_id'])
			->values(array_values(compact('post_id', 'set_id')))
			->execute($this->db);
	}

}
