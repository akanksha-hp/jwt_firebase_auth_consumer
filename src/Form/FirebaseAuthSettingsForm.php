<?php

namespace Drupal\jwt_firebase_auth_consumer\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\key\KeyRepositoryInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FirebaseAuthSettingsForm.
 *
 * @package Drupal\jwt_firebase_auth_consumer\Form
 */
class FirebaseAuthSettingsForm extends ConfigFormBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'jwt_firebase_auth_consumer_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jwt_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['firebase_settings'] = [
      '#type' => 'container',
      '#prefix' => '<div id="jwt-settings">',
      '#suffix' => '</div>',
      '#weight' => 10,
    ];

    $form['firebase_settings']['project_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Firebase project id'),
      '#default_value' => $this->config('jwt_firebase_auth_consumer_settings')->get('project_id'),
      '#description' => $this->t('Please enter your firebase project id. You can find this in Firebase console.'),
      '#required' => TRUE,
    ];

    $form['firebase_settings']['create_drupal_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create corresponding drupal user'),
      '#default_value' => $this->config('jwt_firebase_auth_consumer_settings')->get('create_drupal_user'),
      '#description' => $this->t('Check this option if you wish to create drupal users for your firebase users.'),
      '#states' => [
        'unchecked' => [
          ':input[name="firebase_drupal_uid"]' => ['filled' => TRUE],
        ],
        'required' => [
          ':input[name="firebase_drupal_uid"]' => ['filled' => FALSE],
        ],
      ],
    ];

    if (!empty($this->config('jwt_firebase_auth_consumer_settings')->get('firebase_drupal_uid'))) {
      $default_user = User::load($this->config('jwt_firebase_auth_consumer_settings')->get('firebase_drupal_uid'));
    }
    $form['firebase_settings']['firebase_drupal_uid'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#default_value' => $default_user ?? NULL,
      '#states' => [
        'required' => [
          ':input[name="create_drupal_user"]' => ['unchecked' => TRUE],
        ],
      ],
      '#title' => 'Select default user for authenticating with Firebase jwt',
      '#description' => $this->t('All api requests with firebase jwt will be executed as this user. You can assign a custom role to this user or there is a firebase_user role that you can use. Alternatively, you can allow user creation for every firebase user by checking the box above.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    foreach($values as $key => $value) {
      $this->config('jwt_firebase_auth_consumer_settings')->set($key, $value);
    }
    $this->config('jwt_firebase_auth_consumer_settings')->save();

    parent::submitForm($form, $form_state);
  }
}
