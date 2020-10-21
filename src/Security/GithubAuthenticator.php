<?php

namespace App\Security;

use App\Repository\UserRepository;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class GithubAuthenticator extends SocialAuthenticator
{


  private RouterInterface $rooter;
  private ClientRegistry  $clientRegistry;
  private UserRepository  $repo;


  public function __construct(RouterInterface $rooter, ClientRegistry $clientRegistry, UserRepository $repo)
  {
    $this->rooter = $rooter;
    $this->clientRegistry = $clientRegistry;
    $this->repo = $repo;
  }



  public function supports(Request $request)
  {
    return 'oauth_check' === $request->attributes->get('_route') && $request->attributes->get('service') === 'github';
  }

  public function getCredentials(Request $request)
  {
    return $this->fetchAccessToken($this->clientRegistry->getClient('github'));
  }

  /**
   * Undocumented function
   *
   * @param AccessToken $credentials
   */
  public function getUser($credentials, UserProviderInterface $userProvider)
  {

    $githubUser = $this->clientRegistry->getClient('github')->fetchUserFromToken($credentials);

    $response = HttpClient::create()->request(
      'GET',
      'https://api.github.com/user/emails',
      [
        'headers' =>
        [
          'autorization' => "token {$credentials->getToken()} "
        ]
      ]

    );

    $emails = json_decode($response->getContent(), true);

    return $this->repo->findOrCreateFromOauth($githubUser);
  }

  public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
  {
  }

  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey)
  {
    return new RedirectResponse('/');
  }

  public function start(Request $request, ?AuthenticationException $authException = null)
  {

    return new RedirectResponse($this->rooter->generate('app_login'));
  }
}
