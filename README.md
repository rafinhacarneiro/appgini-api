# AppGini API
This is an API based on CRUD operations with Basic authentication. It uses most of AppGini functions and works with any AppGini's generated applications. It provides ```GET```, ```POST```, ```PUT```, ```PATCH``` and ```DELETE``` support and all responses are in JSON format. Only Admin users can use this API.

The _v2_ was first designed to integrate AppGini with Microsoft's Power BI, so its function is READ-only with ```GET```/```POST``` suport. The _v3_ its a full CRUD API. Custom operations are coming soon.

## How does it work?
On startup, the API class:
- Includes the ```lib.php``` file of AppGini on startup, so it can use the AppGini functions.
- Fetches the tables and columns of the application from ```INFORMATION_SCHEMA```, so it's always up to date with your app.
- Fetches a list of Admin users of your application.
- Validates the authentication provided by the user.
- If it's ok, get the request and do the query
- Returns data in JSON format.

## Installation
1) Put the ```api``` folder on your app's root folder
2) Enjoy your API :)

## Basic usage

### v2
Both ```GET``` and ```POST``` use the same endpoints.

- ```GET```: /api/index.php?tb=your-db-table
- ```POST```: {tb="your-db-table"}

More examples on the wiki.

### v3

- ```GET```: /api/index.php?read=your-db-table
- ```POST```:
```json
{
  "create": "your-db-table",
  "info": {
    "field": "value",
    ...
  }
}
```
