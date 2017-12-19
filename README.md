# Client certificate authentication middleware for Laravel 5

Also known as X.509 client authentication.

### How does it work
1. You have a user in your app. For example, `Admin:admin@yourapp.tld`
2. You generate a certificate for that user. Make sure you're using `admin@yourapp.tld` for certificate's `emailAddress` field.
3. This package allows `Admin` to use your app without ever logging in.
4. All users including `Admin` can still use plain password auth.

> __Pro tip__: you can also [use any other certificate attributes](https://github.com/ingria/laravel-x509-auth/wiki/Using-other-cert-attributes) for authentication, not only `emailAddress` (like `id` or `username`). I don't think you need this package in that case, but anyway 🤷.

## Prerequisites

Please don't blindly copy-paste the commands. It's important for you to know what you're doing.

### 1. Generate CA and Client certificate
Generating Certificate Authority:
```bash
openssl genrsa -out ca.key 2048
openssl req -new -x509 -days 3650 -key ca.key -out ca.crt
```

Generating client certificate and signing it with your CA. When asked for the email, enter email of your app's user which will be autheticated with this certificate.
```bash
openssl req -new -utf8 -nameopt multiline,utf8 -newkey rsa:2048 -nodes -keyout client.key -out client.csr
openssl x509 -req -days 3650 -in client.csr -CA ca.crt -CAkey ca.key -set_serial 01 -out client.crt
```

Optionally, generate a PKCS certificate to be installed into the browser, mobile or whatever:
```bash
openssl pkcs12 -export -clcerts -in client.crt -inkey client.key -out client.p12
```

### 2. Configure your web-server
This example is for NGINX with FastCGI.
```conf
server {
    ...
    ssl_client_certificate /etc/nginx/certs/Your_CA_Public_Key.crt;
    ssl_verify_client optional;

    location ~ \.php$ {
        ...
        fastcgi_param SSL_CLIENT_VERIFY    $ssl_client_verify;
        fastcgi_param SSL_CLIENT_S_DN      $ssl_client_s_dn;
    }
}
```

You can also add pass some other useful params, see resources below.

#### Resources
- [NGINX docs on ssl_verify_client](https://nginx.org/en/docs/http/ngx_http_ssl_module.html#ssl_verify_client)
- [NGINX docs on SSL module variables](https://nginx.org/en/docs/http/ngx_http_ssl_module.html#var_ssl_client_verify)


## Installation

### 1. Install the package
This assumes that you have composer installed globally:
```bash
composer require ingria/laravel-x509-auth
```

### 2. Register middleware
Add `\Ingria\LaravelX509Auth\Middleware\AuthenticateWithClientCertificate::class` to your `routeMiddleware` array in `app/Http/Kernel.php`.

For example, you can call it `auth.x509`, by analogy with Laravel's `auth.basic` name:
```php
// app/Http/Kernel.php

...
protected $routeMiddleware = [
    // a whole bunch of middlewares...
    'auth.x509' => \Ingria\LaravelX509Auth\Middleware\AuthenticateWithClientCertificate::class,
];
```
#### Resources
- [Laravel docs on registering a middleware](https://laravel.com/docs/5.5/middleware#registering-middleware)

## Usage
Just add the middleware's name to any route or controller instead of default `auth`. For example:

```php
// routes/web.php

Route::get('/', 'YourController@method')->middleware('auth.x509');
```

#### Resources
- [Laravel docs on assigning a middleware](https://laravel.com/docs/5.5/middleware#assigning-middleware-to-routes)
- [Laravel docs on protecting routes](https://laravel.com/docs/5.5/authentication#protecting-routes)

## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
