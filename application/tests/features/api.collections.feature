@setsFixture @oauth2Skip
Feature: Testing the Sets API

	Scenario: Creating a Collection
		Given that I want to make a new "collection"
		And that the request "data" is:
			"""
			{
				"name":"Set One",
				"featured": 1,
				"view":"map",
				"view_options":[],
				"visible_to":[]
			}
			"""
		When I request "/collections"
		Then the response is JSON
		And the response has a "id" property
		And the type of the "id" property is "numeric"
		And the response has a "name" property
		And the "name" property equals "Set One"
		And the "featured" property equals "1"
		And the "search" property is false
		And the "view" property equals "map"
		Then the guzzle status code should be 200

	Scenario: Creating a Collection ignores SavedSearch properties
		Given that I want to make a new "collection"
		And that the request "data" is:
			"""
			{
				"name":"Set One",
				"featured": 1,
				"search": 1,
				"filter": {"q":"test"},
				"view":"map",
				"view_options":[],
				"visible_to":[]
			}
			"""
		When I request "/collections"
		Then the response is JSON
		And the response has a "id" property
		And the type of the "id" property is "numeric"
		And the response has a "name" property
		And the "search" property is false
		And the "filter" property is empty
		Then the guzzle status code should be 200

	Scenario: Updating a Collection
		Given that I want to update a "collection"
		And that the request "data" is:
			"""
			{
				"name":"Updated Set One"
			}
			"""
		And that its "id" is "1"
		When I request "/collections"
		Then the response is JSON
		And the response has a "id" property
		And the type of the "id" property is "numeric"
		And the "id" property equals "1"
		And the response has a "name" property
		And the "name" property equals "Updated Set One"
		Then the guzzle status code should be 200


	Scenario: Updating a non-existent Collection
		Given that I want to update a "collection"
		And that the request "data" is:
			"""
			{
				"name":"Updated Set",
				"filter":"updated filter"
			}
			"""
		And that its "id" is "20"
		When I request "/collections"
		Then the response is JSON
		And the response has a "errors" property
		Then the guzzle status code should be 404

	Scenario: Updating a SavedSearch via collections API fails
		Given that I want to update a "collection"
		And that the request "data" is:
			"""
			{
				"name":"Updated Set",
				"filter":"updated filter"
			}
			"""
		And that its "id" is "4"
		When I request "/collections"
		Then the response is JSON
		And the response has a "errors" property
		Then the guzzle status code should be 404

	Scenario: Non admin user trying to make a collection featured fails
		Given that I want to update a "collection"
		And that the request "Authorization" header is "Bearer testbasicuser2"
		And that the request "data" is:
			"""
			{
				"name":"Updated Set One",
				"filter":"updated set filter",
				"featured":1
			}
			"""
		And that its "id" is "2"
		When I request "/collections"
		Then the response is JSON
		Then the guzzle status code should be 403

	@resetFixture
	Scenario: Listing All Collections
		Given that I want to get all "Collections"
		When I request "/collections"
		Then the response is JSON
		And the response has a "count" property
		And the type of the "count" property is "numeric"
		And the "count" property equals "3"
		Then the guzzle status code should be 200

	@resetFixture
	Scenario: Listing All Collections as a normal user doesn't return admin set
		Given that I want to get all "Collections"
		And that the request "Authorization" header is "Bearer testbasicuser2"
		When I request "/collections"
		Then the response is JSON
		And the response has a "count" property
		And the type of the "count" property is "numeric"
		And the "count" property equals "2"
		Then the guzzle status code should be 200

	@resetFixture
	Scenario: Search All Collections
		Given that I want to get all "Collections"
		And that the request "query string" is:
			"""
			q=Explo
			"""
		When I request "/collections"
		Then the response is JSON
		And the "count" property equals "1"
		And the "results.0.name" property equals "Explosion"
		Then the guzzle status code should be 200

	Scenario: Finding a Collection
		Given that I want to find a "Collection"
		And that its "id" is "1"
		When I request "/collections"
		Then the response is JSON
		And the response has a "id" property
		And the type of the "id" property is "numeric"
		Then the guzzle status code should be 200

	Scenario: Finding a non-existent Collection
		Given that I want to find a "Collection"
		And that its "id" is "22"
		When I request "/collections"
		Then the response is JSON
		And the response has a "errors" property
		Then the guzzle status code should be 404

	Scenario: Finding a saved search via Collection should 404
		Given that I want to find a "Collection"
		And that its "id" is "4"
		When I request "/collections"
		Then the response is JSON
		And the response has a "errors" property
		Then the guzzle status code should be 404

	Scenario: Deleting a Collection
		Given that I want to delete a "Collection"
		And that its "id" is "1"
		When I request "/collections"
		Then the guzzle status code should be 200

	Scenario: Deleting a non-existent Collection
		Given that I want to delete a "Collection"
		And that its "id" is "22"
		When I request "/collection"
		And the response has a "errors" property
		Then the guzzle status code should be 404

	Scenario: Deleting a saved search via Collections api fails
		Given that I want to delete a "Collection"
		And that its "id" is "4"
		When I request "/collection"
		And the response has a "errors" property
		Then the guzzle status code should be 404

	@resetFixture
	Scenario: Get collection posts
		Given that I want to get all "Posts"
		When I request "/collections/1/posts"
		Then the response is JSON
		And the "count" property equals "3"
		Then the guzzle status code should be 200

	Scenario: Get non-existent collection posts should 404
		Given that I want to get all "Posts"
		When I request "/collections/22/posts"
		Then the response is JSON
		Then the guzzle status code should be 404

	@resetFixture
	Scenario: Add a post to a collection
		Given that I want to make a new "Post"
		And that the request "data" is:
			"""
			{
				"id":97
			}
			"""
		When I request "/collections/1/posts/"
		Then the response is JSON
		And the response has a "id" property
		And the type of the "id" property is "numeric"
		And the "id" property equals "97"
		Then the guzzle status code should be 200

	@resetFixture
	Scenario: Remove a post from a collection
		Given that I want to delete a "Post"
		And that its "id" is "1"
		When I request "/collections/1/posts"
		Then the response is JSON
		And the response has a "id" property
		And the type of the "id" property is "numeric"
		Then the guzzle status code should be 200

	Scenario: Add nonexistent post to collection fails
		Given that I want to make a new "Post"
		And that the request "data" is:
			"""
			{
				"id":75
			}
			"""
		When I request "/collections/1/posts/"
		Then the response is JSON
		And the response has a "errors" property
		Then the guzzle status code should be 400

# ACL Tests
	Scenario: Adding a post we cannot access to a collection fails
		Given that I want to make a new "Post"
		And that the request "Authorization" header is "Bearer testbasicuser2"
		And that the request "data" is:
			"""
			{
				"id":111
			}
			"""
		When I request "/collections/1/posts/"
		Then the response is JSON
		And the response has a "errors" property
		Then the guzzle status code should be 403

	Scenario: Adding a post to a collection we cannot access fails
		Given that I want to make a new "Post"
		And that the request "Authorization" header is "Bearer testbasicuser2"
		And that the request "data" is:
			"""
			{
				"id":97
			}
			"""
		When I request "/collections/3/posts/"
		Then the response is JSON
		And the response has a "errors" property
		Then the guzzle status code should be 403

	@resetFixture
	Scenario: Admin can add a post to a collection
		Given that I want to make a new "Post"
		And that the request "Authorization" header is "Bearer testadminuser"
		And that the request "data" is:
			"""
			{
				"id":97
			}
			"""
		When I request "/collections/1/posts/"
		Then the response is JSON
		And the response has a "id" property
		And the type of the "id" property is "numeric"
		And the "id" property equals "97"
		Then the guzzle status code should be 200

	@resetFixture
	Scenario: User can view public and own private posts in a collection
		Given that I want to get all "Posts"
		And that the request "Authorization" header is "Bearer testbasicuser"
		And that the request "query string" is "status=all"
		When I request "/collections/1/posts"
		Then the guzzle status code should be 200
		And the response is JSON
		And the response has a "count" property
		And the type of the "count" property is "numeric"
		And the "count" property equals "4"

	@resetFixture
	Scenario: All users can view public posts in a collection
		Given that I want to get all "Posts"
		And that the request "Authorization" header is "Bearer testbasicuser2"
		And that the request "query string" is "status=all"
		When I request "/collections/1/posts"
		Then the guzzle status code should be 200
		And the response is JSON
		And the response has a "count" property
		And the type of the "count" property is "numeric"
		And the "count" property equals "2"

	@resetFixture
	Scenario: Admin user can view all posts in a collection
		Given that I want to get all "Posts"
		And that the request "Authorization" header is "Bearer testadminuser"
		And that the request "query string" is "status=all"
		When I request "/collections/1/posts"
		Then the guzzle status code should be 200
		And the response is JSON
		And the response has a "count" property
		And the type of the "count" property is "numeric"
		And the "count" property equals "6"