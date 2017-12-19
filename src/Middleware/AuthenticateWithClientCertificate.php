<?php

namespace Ingria\LaravelX509Auth\Middleware;

use function abort, redirect, preg_match, count;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use App\User;
use Closure;

class AuthenticateWithClientCertificate
{
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure                  $next
     * @param  null|string               $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if ($this->auth->guard($guard)->check()) {
            return $next($request);
        }

        if (! $request->secure()) {
            return abort(400, 'The Client Certificate auth requires a HTTPS connection.');
        }

        /** If the certificate is valid, log in and remember the user: */
        if ($request->server('SSL_CLIENT_VERIFY') === 'SUCCESS') {
            $this->auth->guard($guard)->login(static::getUserFromCert($request), true);

            return $next($request);
        }

        throw new AuthenticationException('Unauthenticated.');
    }

    /**
     * Gets the user from cert.
     *
     * @throws RuntimeException
     * @param  Request $request
     * @return App\User
     */
    protected static function getUserFromCert(Request $request)
    {
        /**
         * Probably misconfigured Nginx:
         * @see https://nginx.org/en/docs/http/ngx_http_ssl_module.html#var_ssl_client_s_dn
         */
        if (empty($subject = $request->server('SSL_CLIENT_S_DN'))) {
            throw new \RuntimeException('Missing SSL_CLIENT_S_DN param');
        }

        $email = self::getEmailFromDn($subject);

        if (empty($user = User::where('email', '=', $email)->first())) {
            return abort(403, 'User not found');
        }

        return $user;
    }

    /**
     * Parses the email address from client cert subject.
     *
     * @param  string $subject
     * @return string
     */
    protected static function getEmailFromDn(string $subject): string
    {
        preg_match('/emailAddress=([\w\+]+@[a-z\-\d]+\.[a-z\-\.\d]{2,})/i', $subject, $match);

        /**
         * emailAddress must be set.
         * @see http://www.ietf.org/rfc/rfc2459.txt
         */
        if (empty($match) || count($match) < 2) {
            return abort(400, 'Missing or invalid emailAddress in subject certificate');
        }

        return $match[1];
    }
}
