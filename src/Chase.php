<?php
namespace Banker;

use GuzzleHttp\ClientInterface;
use PHPHtmlParser\Dom;

class Chase
{
    private $login_url = 'https://mobilebanking.chase.com/Public/Home/LogOn';

    const TWO_FACTOR_AUTH_BLOCKED = 'two_factor_auth_blocked';
    const LOGGED_IN = 'logged_in';
    const TWO_FACTOR_TOKEN_REQUEST_SENT = 'two_factor_token_request_sent';
    const TWO_FACTOR_TOKEN_REQUEST_FAILED = 'two_factor_token_request_failed';
    const TWO_FACTOR_AUTH_FAILED = 'two_factor_auth_failed';

    public function login(ClientInterface $client, $username, $password)
    {
        $client->get($this->login_url);

        $response = $client->post("https://mfasa.chase.com/auth/fcc/login", array(
            'body' => array(
                'auth_siteId' => 'MWB',
                'auth_contextId' => 'login',
                'auth_userId' => $username,
                'auth_passwd' => $password
            )
        ));

        if (strpos($response->getEffectiveUrl(), 'auth_mode=OTP&auth_error=REQ')) {
            return static::TWO_FACTOR_AUTH_BLOCKED;
        }

        return static::LOGGED_IN;
    }

    public function getTwoFactorOptions(ClientInterface $client)
    {
        $response = $client->post("https://mobilebanking.chase.com/Public/Mfa/Update", array(
            'body' => array(
                'CurrentStep' => '1',
                'Next' => 'Next'
            )
        ));

        $options = array();

        $dom = new Dom();
        $dom->load((string)$response->getBody());

        $h3s = $dom->find('h3');
        $lis = $dom->find('li');

        $phone_exists = false;
        $email_exists = false;

        foreach ($h3s as $h3) {
            if (strpos($h3->text, 'your PHONE:')) {
                $phone_exists = $h3;
            } elseif (strpos($h3->text, 'your EMAIL:')) {
                $email_exists = $h3;
            }
        }

        if ($phone_exists && !empty($lis[0])) {
            $li = $lis[0];

            if (!empty($lis[1])) {
                $lis[0] = $lis[1];
                unset($lis[1]);
            }

            $match = preg_match('/\w+-\w+-\d+/i', $li->text, $result);

            if ($match) {
                $number = $result[0];

                $as = $li->find('a');

                foreach ($as as $a) {
                    $href = htmlspecialchars_decode($a->getAttribute('href'));
                    $options["{$number} ({$a->text})"] = "https://mobilebanking.chase.com{$href}";
                }
            }
        }

        if ($email_exists && !empty($lis[0])) {
            $li = $lis[0];

            $as = $li->find('a');

            foreach ($as as $a) {
                $href = htmlspecialchars_decode($a->getAttribute('href'));
                $options["{$a->text} (email)"] = "https://mobilebanking.chase.com{$href}";
            }
        }

        return $options;
    }

    public function selectTwoFactorToken(ClientInterface $client, $link)
    {
        $response = $client->get($link);

        if (strpos($response->getEffectiveUrl(), 'EnterActivationCode')) {
            return static::TWO_FACTOR_TOKEN_REQUEST_SENT;
        } else {
            return static::TWO_FACTOR_TOKEN_REQUEST_FAILED;
        }
    }

    public function submitTwoFactorToken(ClientInterface $client, $token, $username, $password)
    {
        $response = $client->post("https://mfasa.chase.com/auth/fcc/login", array(
            'body' => array(
                'auth_siteId' => 'MWB',
                'auth_contextId' => 'login',
                'auth_otpprefix' => 'uhe',
                'auth_otpreason' => '2',
                'auth_otp' => $token,
                'auth_userId' => $username,
                'auth_passwd' => $password
            )
        ));

        if (strpos($response->getEffectiveUrl(), 'Secure/Accounts/Index')) {
            return static::LOGGED_IN;
        } else {
            return static::TWO_FACTOR_AUTH_FAILED;
        }
    }
}