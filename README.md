# cleanly-server

## User creation and login

Create a user by talking to the signup endpoint

~~~
curl -X POST http://localhost:8000/signup -d _email=[Email] -d _password=[Password]
~~~

Authentication is done via login_check and will return the jwt, that will be valid for 3600 seconds after creation and can be used in an authentication bearer.

~~~
curl -X POST -H "Content-Type: application/json" http://127.0.0.1:8000/api/login_check -d '{"username":"[Email]","password":"[Password]"}'
~~~
