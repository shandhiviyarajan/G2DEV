<?php

namespace Drupal\prlp\Controller;

use Drupal;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormState;
use Drupal\user\Controller\UserController;
use Drupal\user\Form\UserPasswordResetForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller routines for prlp routes.
 */
class PrlpController extends UserController {

  /**
   * Override resetPassLogin() method from parent object to validate and save
   * new password with.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param int $uid
   *   User ID of the user requesting reset.
   * @param int $timestamp
   *   The current timestamp.
   * @param string $hash
   *   Login link hash.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns parent result object.
   */
  public function prlpResetPassLogin(Request $request, $uid, $timestamp, $hash) {
    // The current user is not logged in, so check the parameters.
    $current = REQUEST_TIME;
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($uid);

    // Check if the hash is valid, but not if you were told by prlp to
    // skip checking it.
    $check_hash = !Drupal::request()->getSession()->get('prlp_skip_hash_check');
    // remove this session setting immediately to avoid security holes
    !Drupal::request()->getSession()->remove('prlp_skip_hash_check');
    $invalid_hash = $check_hash && !Crypt::hashEquals($hash, user_pass_rehash($user, $timestamp));
    // Verify that the user exists and is active.
    if ($user === NULL || !$user->isActive() || $invalid_hash) {
      // Blocked or invalid user ID, so deny access. The parameters will be in
      // the watchdog's URL for the administrator to check.
      throw new AccessDeniedHttpException();
    }

    if ($request->getMethod() == 'POST') {
      // Build form to call for validation and submit handlers.
      $timeout = $this->config('user.settings')->get('password_reset_timeout');
      // No time out for first time login.
      if (($user->getLastLoginTime() && $user->getLastLoginTime() > $timestamp) || $current - $timestamp > $timeout) {
        drupal_set_message($this->t('You have tried to use a one-time login link that has expired. Please request a new one using the form below.'), 'error');
        return $this->redirect('user.pass');
      }
      $expiration_date = $user->getLastLoginTime() ? $this->dateFormatter->format($timestamp + $timeout) : NULL;
      $form_state = new FormState();
      $form_state->addBuildInfo('args', array_values([
        $user, $expiration_date, $timestamp, $hash
      ]));
      $this->formBuilder()->buildForm(UserPasswordResetForm::class, $form_state);

      $session = $request->getSession();
      $session->set('pass_reset_hash', $hash);
      $session->set('pass_reset_timeout', $timestamp);
      return $this->redirect(
        'user.reset.form',
        ['uid' => $uid]
      );
    }

    // Time out, in seconds, until login URL expires.
    $timeout = $this->config('user.settings')->get('password_reset_timeout');
    // No time out for first time login.
    if ($user->getLastLoginTime() && $current - $timestamp > $timeout) {
      drupal_set_message($this->t('You have tried to use a one-time login link that has expired. Please request a new one using the form below.'), 'error');
      return $this->redirect('user.pass');
    }
    elseif ($user->isAuthenticated() && ($timestamp >= $user->getLastLoginTime()) && ($timestamp <= $current)) {
      user_login_finalize($user);
      $this->logger->notice('User %name used one-time login link and changed his password at time %timestamp.', ['%name' => $user->getDisplayName(), '%timestamp' => $timestamp]);
      drupal_set_message($this->t('You have just used your one-time login link. It is no longer necessary to use this link to log in.'));
      // Let the user's password be changed without the current password check.
      $token = Crypt::randomBytesBase64(55);
      $_SESSION['pass_reset_' . $user->id()] = $token;
      return $this->redirect(
        'entity.user.edit_form',
        ['user' => $user->id()]
      );
    }

    drupal_set_message($this->t('You have tried to use a one-time login link that has either been used or is no longer valid. Please request a new one using the form below.'), 'error');
    return $this->redirect('user.pass');
  }
}
