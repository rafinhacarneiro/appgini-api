# v2 or v3? What's the difference?

## v2
If you're looking through a READ-only API, _v2_ is best suited for you. You can pull data from your application as JSON with little effort, either by ```GET``` or ```POST``` methods.

## v3
If you're looking through a more REST-full, CRUD operational API, _v3_ is your choice. Each HTTP method serves as one CRUD operation, so you can:
- ```GET``` to return data
- ```POST``` to add data
- ```PATCH``` to update data
- ```PUT``` to do an upsert
- ```DELETE``` to delete data

Beside that, one simple ```GET``` informs you about the possible table/fields to interact with.

## "What if I want both?"

You still can download and use both with no problems. Just remember this diference. :)
