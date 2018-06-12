<?php
/**
 * @package     Mautic
 * @copyright   2015 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticSSOBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractSsoServiceIntegration;
use Mautic\UserBundle\Entity\User;
use OAuth2\Client;

/**
 * Class GithubAuthIntegration
 */
class WsuiteAuthIntegration extends AbstractSsoServiceIntegration
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'WsuiteAuth';
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return 'Wsuite';
    }
    
    /**
     * @return id
     */
    public function getClientId()
    {
        return '20';
    }
    
    /**
     * @return string
     */
    public function getClientSecret()
    {
        return 'ParU9FYnh9AYVDfbdXWamvWvaj6ZCi5o1gT9zP3Y';
    }

    /**
     * @return string
     */
    public function getAuthenticationType()
    {
        return 'oauth2';
    }

    /**
     * @return string
     */
    public function getAuthScope()
    {
        //return 'user:email';
    }

    /**
     * @return string
     */
    public function getAuthenticationUrl()
    {
        return 'https://login.wsuite.com/oauth/auth';
    }

    /**
     * @return string
     */
    public function getAccessTokenUrl()
    {
        return 'https://login.wsuite.com/oauth/token';
    }
    
    /**
     * @return string
     */
    public function getUserDataUrl()
    {
        return 'https://login.wsuite.com/user';
    }
    
    /*
    public function getAuthCallbackUrl()
    {
        return 'http://wpeople.wsuite.com/s/login';
    }*/

    /**
     * @param mixed $response
     *
     * @return mixed
     */
    public function getUser($response)
    {
        $client = new Client($this->getClientId(), $this->getClientSecret());
        $client->setAccessToken($response['access_token']);
        $client->setAccessTokenType(1);
        $userDetails = $client->fetch($this->getUserDataUrl(), array(),"GET");
        if (isset($userDetails['result'])) {
            $names = explode(' ', $userDetails['result']['name']);
            if (count($names) > 1) {
                $firstname = $names[0];
                unset($names[0]);
                $lastname = implode(' ', $names);
            } else {
                $firstname = $lastname = $names[0];
            }
            try{
                $user = $this->factory->getEntityManager()->getRepository('MauticUserBundle:User')->findBy(['email'=>$userDetails['result']['email']]);
                if(isset($user) && is_array($user) && count($user) > 0){
                    return $user[0];
                }
            }catch(\Exception $e){
                throw new \Exception("User not found");
            }
            
            
//            $user = new User();
//            $user->setUsername($userDetails['result']['email'])
//                ->setEmail($userDetails['result']['email'])
//                ->setFirstName($firstname)
//                ->setLastName($lastname)
//                ->setRole(
//                    $this->getUserRole()
//                );
//
//            return $user;
        }
        return false;
    }
}