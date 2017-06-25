<?php

namespace AppBundle\Controller;

use Facebook\Facebook;
use Facebook\Exceptions;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class DefaultController extends Controller
{
    private $fb;
    private $accessToken;

    function __construct()
    {
        $this->fb = new Facebook([
            'app_id' => '1373450729357303',
            'app_secret' => 'fcac2c48211afeb45e59783f1b547989',
            'default_graph_version' => 'v2.9',
        ]);
    }


    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        return $this->render('default/index.html.twig', [
            'facebookLoginUrl' => $this->getFacebookLoginUrl()
        ]);
    }


    /**
     * @Route("/facebookLogin", name="facebookLogin")
     */
    public function facebookLoginAction(Request $request)
    {
        $user = $this->getFacebookUsername();

        return $this->render('default/index.html.twig', [
            'username' => $user['name']
        ]);
    }


    /**
     * @Route("/posts", name="getPosts")
     */
    public function getPostsAction()
    {
        return $this->render('default/index.html.twig', [
            'posts' => $this->getFacebookPosts()
        ]);
    }


    private function getFacebookUsername()
    {
        $response = $this->getFacebookResponse('/me?fields=id,name');
        return $user = $response->getGraphUser();
    }

    private function getFacebookPosts()
    {
        $response = $this->getFacebookResponse('/me/feed?limit=300');

        $regUrl = "@((https?://)?([-\\w]+\\.[-\\w\\.]+)+\\w(:\\d+)?(/([-\\w/_\\.]*(\\?\\S+)?)?)*)@";

        $decodedBody = $response->getDecodedBody();
        foreach ($decodedBody['data'] as $key => $d) {
            if (isset ($d['message']) && preg_match($regUrl, $d['message'], $url)) {
                if (stripos($url[0], 'http') !== 0) {
                    $url[0] = "http://" . $url[0];
                }
                $decodedBody['data'][$key]['message'] = preg_replace($regUrl, "<a href='$url[0]' target='blank'>" . $url[0] . "</a>", $decodedBody['data'][$key]['message']);
            }
        }

        return $decodedBody;
    }


    private function getFacebookResponse($endpoint)
    {
        $this->accessToken = $this->getFacebookAccessToken();
        try {
            // Returns a `Facebook\FacebookResponse` object
            return $this->fb->get($endpoint, $this->accessToken);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }
    }


    private function getFacebookAccessToken()
    {
        $session = new Session();
        if (empty($session->get('facebookAccessToken'))) {
            $helper = $this->fb->getRedirectLoginHelper();

            if (isset($_GET['state'])) {
                $helper->getPersistentDataHandler()->set('state', $_GET['state']);
            }

            try {
                $accessToken = $helper->getAccessToken();
            } catch (Facebook\Exceptions\FacebookResponseException $e) {
                // When Graph returns an error
                echo 'Graph returned an error: ' . $e->getMessage();
                exit;
            } catch (Facebook\Exceptions\FacebookSDKException $e) {
                // When validation fails or other local issues
                echo 'Facebook SDK returned an error: ' . $e->getMessage();
                exit;
            }

            if (!isset($accessToken)) {
                if ($helper->getError()) {
                    header('HTTP/1.0 401 Unauthorized');
                    echo "Error: " . $helper->getError() . "\n";
                    echo "Error Code: " . $helper->getErrorCode() . "\n";
                    echo "Error Reason: " . $helper->getErrorReason() . "\n";
                    echo "Error Description: " . $helper->getErrorDescription() . "\n";
                } else {
                    header('HTTP/1.0 400 Bad Request');
                    echo 'Bad request';
                }
                exit;
            }

            $newAccessToken = $accessToken->getValue();
            $session->set('facebookAccessToken', $newAccessToken);

            return $newAccessToken;
        } else {
            return $session->get('facebookAccessToken');
        }

    }

    private function getFacebookLoginUrl()
    {
        $helper = $this->fb->getRedirectLoginHelper();

        $permissions = ['email', 'user_posts'];

        $loginUrl = $helper->getLoginUrl('http://local.fbm.com/facebook_messages_downloader/web/app_dev.php/facebookLogin', $permissions);

        return $loginUrl;
    }
}
