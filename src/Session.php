<?php
namespace Banker;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJarInterface;

class Session
{
    public function __construct(AuthenticationDetails $auth_details, Chase $chase, CookieJarInterface $cookies)
    {
        $this->auth_details = $auth_details;
        $this->chase = $chase;
        $this->cookies = $cookies;
        $this->client = new Client(array(
            'defaults' => array(
                'cookies' => $this->cookies
            )
        ));
    }

    public function attemptLogin()
    {
        $result = $this->chase->login($this->client, $this->auth_details->getUsername(), $this->auth_details->getPassword());

        if ($result == Chase::TWO_FACTOR_AUTH_BLOCKED) {
            return $result;
        }

        return Chase::LOGGED_IN;
    }

    public function getTwoFactorOptions()
    {
        return $this->chase->getTwoFactorOptions($this->client);
    }

    public function selectTwoFactorToken($choice)
    {
        return $this->chase->selectTwoFactorToken($this->client, $choice);
    }

    public function submitTwoFactorToken($token)
    {
        return $this->chase->submitTwoFactorToken($this->client, $token, $this->auth_details->getUsername(), $this->auth_details->getPassword());
    }
}