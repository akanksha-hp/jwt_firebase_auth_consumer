<?php

namespace Drupal\jwt_firebase_auth_consumer\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jwt\Authentication\Event\JwtAuthValidateEvent;
use Drupal\jwt\Authentication\Event\JwtAuthValidEvent;
use Drupal\jwt\Authentication\Event\JwtAuthEvents;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class JwtFirebaseAuthConsumerSubscriber.
 *
 * @package Drupal\jwt_firebase_auth_consumer
 */
class JwtFirebaseAuthConsumerSubscriber implements EventSubscriberInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;


  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[JwtAuthEvents::VALIDATE][] = ['validate'];
    $events[JwtAuthEvents::VALID][] = ['loadUser'];

    return $events;
  }

  /**
   * Validates that a uid is present in the JWT.
   *
   * This validates the format of the JWT and validate the uid is a
   * valid uid in the system.
   *
   * @param \Drupal\jwt\Authentication\Event\JwtAuthValidateEvent $event
   *   A JwtAuth event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function validate(JwtAuthValidateEvent $event) {
    $token = $event->getToken();

    // Get claims from payload.
    $expiry = $token->getClaim('exp');
    $issuedTime = $token->getClaim('iat');
    $firebaseProjectId = $token->getClaim('aud');
    $issuer = $token->getClaim('iss');
    $firebaseUid = $token->getClaim('sub');
    $authenticationTime = $token->getClaim('auth_time');
    $currentTime = time();

    $firebase_settings = $this->configFactory->get('jwt_firebase_auth_consumer_settings');

    /* Verify token according for firebase specs.
     exp	Expiration time	Must be in the future. The time is measured in seconds since the UNIX epoch.
     iat	Issued-at time	Must be in the past. The time is measured in seconds since the UNIX epoch.
     aud	Audience	Must be your Firebase project ID, the unique identifier for your Firebase project, which can be found in the URL of that project's console.
     iss	Issuer	Must be "https://securetoken.google.com/<projectId>", where <projectId> is the same project ID used for aud above.
     sub	Subject	Must be a non-empty string and must be the uid of the user or device.
     auth_time	Authentication time	Must be in the past. The time when the user authenticated.
    */
    if ($expiry < $currentTime
      && $issuedTime > $currentTime
      && $firebaseProjectId !== $firebase_settings['project_id']
      && $issuer !== 'https://securetoken.google.com/' . $firebaseProjectId
      && $authenticationTime > $currentTime
    )
    {
      $event->invalidate('Invalid token.');
      return;
    }

    // If drupal create user is true, check if user exists in DB, else load default firebase user.
    if ($firebase_settings['create_drupal_user']) {
      $uid = $firebase_settings['firebase_drupal_uid'];
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
    }
    else {
      $user = $this->entityTypeManager->getStorage('user')->loadByProperties(['firebase_user_id' => $firebaseUid]);
    }

    if (!($user instanceof UserInterface)) {
      $event->invalidate('No UID exists.');
      return;
    }
    if ($user->isBlocked()) {
      $event->invalidate('User is blocked.');
    }
  }

  /**
   * Load and set a Drupal user to be authentication based on the JWT's uid.
   *
   * @param \Drupal\jwt\Authentication\Event\JwtAuthValidEvent $event
   *   A JwtAuth event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadUser(JwtAuthValidEvent $event) {
    $token = $event->getToken();
    $user_storage = $this->entityTypeManager->getStorage('user');
    $firebaseUid = $token->getClaim('sub');
    $firebase_settings = $this->configFactory->get('jwt_firebase_auth_consumer_settings');
    // If drupal create user is true, check if user exists in DB, else load default firebase user.
    if ($firebase_settings['create_drupal_user']) {
      $uid = $firebase_settings['firebase_drupal_uid'];
      $user = $user_storage->load($uid);
    }
    else {
      $user = $user_storage->loadByProperties(['firebase_user_id' => $firebaseUid]);
    }
    if ($user instanceof  UserInterface) {
      $event->setUser($user);
    }
  }

}
