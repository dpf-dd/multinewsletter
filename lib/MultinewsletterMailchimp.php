<?php

/**
 * This file is part of the Kreatif\Project package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 04.09.17
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class MultinewsletterMailchimp
{
    /** @var ?MultinewsletterMailchimp static instance of this class */
    private static ?MultinewsletterMailchimp $inst;

    /** @var string Mailchimp API key */
    private static string $api_key = '';

    /** @var string Mailchimp data center */
    private static string $data_center = '';

    /**
     * MultinewsletterMailchimp constructor.
     */
    private function __construct() {}

    /**
     * Creating a new instance of the class.
     * @return MultinewsletterMailchimp
     */
    public static function factory(): self
    {
        if (null === self::$inst) {
            self::$inst = new self();
            self::$api_key = (string) rex_addon::get('multinewsletter')->getConfig('mailchimp_api_key');
            [$a, self::$data_center] = explode('-', self::$api_key);
        }
        return self::$inst;
    }

    /**
     * Check if the Mailchimp API is active
     * @return bool true if API key is set
     */
    public static function isActive()
    {
        return strlen(self::$api_key) > 0 || strlen((string) rex_addon::get('multinewsletter')->getConfig('mailchimp_api_key')) > 0;
    }

    /**
     * Get all lists
     * @return array<string,string>|string Decoded JSON API response
     */
    public function getLists()
    {
        $result = $this->request('/lists');
        return $result['lists'];
    }

    /**
     * Add a user to a list
     * @param MultinewsletterUser $user
     * @param string $listId
     * @param string $status
     * @return array<string,string> Decoded JSON API response
     */
    public function addUserToList(MultinewsletterUser $user, $listId, $status = 'pending')
    {
        $hash = md5($user->email);

        // check user is not already signed
        try {
            $this->request("/lists/{$listId}/members/{$hash}");
            $result = $this->request("/lists/{$listId}/members/{$hash}", 'PATCH', ['status' => $status]);
        } catch (MultinewsletterMailchimpException $ex) {
            $result = $this->request("/lists/{$listId}/members/", 'POST', [
                'email_address' => $user->email,
                'status' => $status,
                'merge_fields' => [
                    'FNAME' => $user->firstname,
                    'LNAME' => $user->lastname,
                ],
            ]);
        }
        return $result;
    }

    /**
     * Unsubscribe a user from a list
     * @param MultinewsletterUser $User
     * @param string $listId
     * @return array<string,string|array<string,string>> Decoded JSON API response
     */
    public function unsubscribe(MultinewsletterUser $User, $listId)
    {
        $hash = md5($User->email);

        return $this->request("/lists/{$listId}/members/{$hash}", 'POST', [
            'status' => 'unsubscribed',
        ]);
    }

    /**
     * Request to Mailchimp API
     * @param string $path
     * @param string $type
     * @param array<string,string|array<string,string>> $fields
     * @return array<string,string|array<string,string>> Decoded JSON API response
     * @throws MultinewsletterMailchimpException
     */
    public function request($path, $type = 'GET', $fields = [])
    {
        // open connection
        $ch = curl_init();
        $url = 'https://' . self::$data_center . '.api.mailchimp.com/3.0' . $path;
        //        pr($url);

        // set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, 'anystring:' . self::$api_key);

        if ('GET' !== $type && false !== json_encode($fields)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "X-HTTP-Method-Override: {$type}",
            ]);
        }
        // execute post
        $result = curl_exec($ch);
        // close connection
        curl_close($ch);
        if(is_bool($result)) {
            throw new MultinewsletterMailchimpException('Mailchimp: Request Failed', 0);
        }

        $decoded_json = (array) json_decode($result, true);

        if (JSON_ERROR_NONE === json_last_error()) {
            $result = $decoded_json;
        } else {
            throw new MultinewsletterMailchimpException('Mailchimp: Request Not Found', 1);
        }
        if ('404' === $result['status']) {
            throw new MultinewsletterMailchimpException('Mailchimp: ' . $result['detail'], 2);
        }
        return $result;
    }
}

class MultinewsletterMailchimpException extends Exception
{
}
