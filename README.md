Unified Reports API
===================

1) Installing and running the API
---------------------------------

You should be running a newer version of php that includes the built-in development server. It is recommended to use PHP 5.5 as we use bcrypt to hash passwords, this is only available in older PHP versions with an additional library. You will also need a local MySQL instance, have these details handy.

Clone or download the repository:

```
git clone git@github.com:tagcade/unified-reports-api.git
```

Alternatively, you can click the "Download ZIP" link to the right to download the code manually.

Generate your ssh keys to app/var/jwt (You can customize path to these files via setting in parameters.yml) 
```
$ mkdir -p app/var/jwt
$ openssl genrsa -out app/var/jwt/private.pem -aes256 4096
$ openssl rsa -pubout -in app/var/jwt/private.pem -out app/var/jwt/public.pem
```

Download and install composer:

For linux: https://getcomposer.org/doc/00-intro.md#installation-nix

For windows: https://getcomposer.org/doc/00-intro.md#installation-nix

Open a terminal and change directory to the unified-reports-api directory and run:

```
composer install
```

Enter your database connection details when prompted.

Then create your database tables and an existing user(as admin user):

```
php app/console doctrine:schema:create
php app/console fos:user:create --user-system=ur_user_system_admin tcadmin admin@tagcade.dev 123456
php app/console fos:user:promote --user-system=ur_user_system_admin tcadmin ROLE_ADMIN
```

Remember your user details and also note there are two test users built-in for testing. user:userpass and admin:adminpass (user and pass separated by a colon).

To start the API run:

```
php app/console serve:run
```

A message should come up displaying the hostname and port such as localhost:8000. This command uses the new built-in development server to run the application, so you don't need to setup a full web server such as apache. Note that this is a development server and not as performant as a real web server.

To test the API use a browser extension such as:

https://chrome.google.com/webstore/detail/postman-rest-client/fdmmgilgnpjigdojojpjoooidkmcomcm?hl=en

https://chrome.google.com/webstore/detail/advanced-rest-client/hgmloofddffdnphfgcellkdfbfbjeloo

Assuming your server is running at localhost:8000, send a post request to [this link](http://localhost:8000/api/getToken), ensure you are sending two parameters called 'username' and 'password'. Assuming you logged in with username=admin&password=adminpass, you should get a response such as:

```
{
    "token": "......",
    "roles": {}
}
```

The token is your json web token, you can now use this to request things from the API. Using this token, you can request a secured resource by sending it in a HTTP Authorization header in the form:

```
Authorization: Bearer ......
```

With this header, send a request to [this link](http://localhost:8000/api/test) and you should see a response:

"test"

If there is something wrong with the token, you will see a response code of 401 and/or a blank page.

In the Tagcade user interface, this complexity will be handled by AngularJS, however our customers will be able to use a provided PHP Library to also query our API.

2) Running API Tests (codeception tests)
---------------------------------
This function will be added later.
