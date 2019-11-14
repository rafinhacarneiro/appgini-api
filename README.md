# AppGini API
Based on CRUD operations, this is a READ-only API with Basic authentication. It uses most of AppGini functions and works with any AppGini's generated applications. It provides ```GET``` and ```POST``` support and all responses are in JSON format. Only Admin users can use this API.

This is a _v2_ of my code. It was first designed to integrate AppGini with Microsoft's Power BI. A _v3_, with all CRUD operations (and custom operations) is coming soon.

## How does it work?
On startup, the API class:
- Includes the ```lib.php``` file of AppGini on startup, so it can use the AppGini functions.
- Fetches the tables and columns of the application from ```INFORMATION_SCHEMA```, so it's always up to date with your application.
- Fetches a list of Admin users of your application.
- Validates the authentication provided by the user.
- If it's ok, get the request and do the query
- Returns data in JSON format.
