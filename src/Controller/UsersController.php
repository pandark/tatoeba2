<?php
/**
 * Tatoeba Project, free collaborative creation of multilingual corpuses project
 * Copyright (C) 2009  HO Ngoc Phuong Trang <tranglich@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 *
 * @category PHP
 * @package  Tatoeba
 * @author   HO Ngoc Phuong Trang <tranglich@gmail.com>
 * @license  Affero General Public License
 * @link     http://tatoeba.org
 */
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;
use Cake\Routing\Router;


/**
 * Controller for users.
 *
 * @category Users
 * @package  Controllers
 * @author   HO Ngoc Phuong Trang <tranglich@gmail.com>
 * @license  Affero General Public License
 * @link     http://tatoeba.org
 */
class UsersController extends AppController
{
    public $name = 'Users';
    public $helpers = array(
        'Html',
        'Form',
        'Date',
        'Logs',
        'Sentences',
        'Navigation',
        'Pagination'
    );
    public $components = array('Flash', 'Mailer', 'RememberMe');

    /**
     * Before filter.
     *
     * @return void
     */
    public function beforeFilter(Event $event)
    {
        // setting actions that are available to everyone, even guests
        // no need to allow login
        $this->Auth->allowedActions = array(
            'all',
            'search',
            'show',
            'login',
            'check_login',
            'logout',
            'register',
            'new_password',
            'check_username',
            'check_email',
            'for_language'
        );
        // prevent CSRF in this controller
        // since we're handling login and registration
        $this->Security->validatePost = true;

        return parent::beforeFilter($event);
    }

    /**
     * Index of users. For admin only.
     *
     * @return void
     */
    public function index()
    {
        $this->paginate = array(
            'limit' => 50,
            'order' => ['group_id'],
            'fields' => [
                'id', 'email', 'username', 'since', 'level'
            ],
            'contain' => [
                'Groups' => [
                    'fields' => ['name']
                ]
            ]
        );
        $this->set('users', $this->paginate());

    }


    /**
     * Edit user. Only for admin.
     *
     * @param int $id Id of user.
     *
     * @return void
     */
    public function edit($id = null)
    {
        $user = $this->Users->get($id);
        if (!$user) {
            $this->Flash->set('Invalid User');
            $this->redirect(array('action'=>'index'));
        }
        if (!empty($this->request->getData())) {

            $wasBlocked = $user->level == -1;
            $wasSuspended = $user->group_id == 6;
            $isBlocked = !$wasBlocked && $this->request->getData('level') == -1;
            $isSuspended = !$wasSuspended && $this->request->getData('group_id') == 6;

            $this->Users->patchEntity($user, $this->request->getData());
            if ($this->Users->save($user)) {
                $username = $this->request->getData('username');
                if ($isBlocked || $isSuspended) {
                    $this->Mailer->sendBlockedOrSuspendedUserNotif(
                        $username, $isSuspended
                    );
                }

                $this->Flash->set('The user information has been saved.');
            } else {
                $this->Flash->set(
                    'The user information could not be saved. Please try again.'
                );
            }
        }
        $groups = $this->Users->Groups->find('list');
        $this->set(compact('groups', 'user'));
    }


    /**
     * Delete user. Only for admin.
     *
     * @param int $id Id of user.
     *
     * @return void
     */
    public function delete($id = null)
    {
        $user = $this->Users->get($id);
        if (!$user) {
            $this->Flash->set('Invalid id for User');
        } elseif ($this->Users->delete($user)) {
            $this->Flash->set('User deleted');
        }
        $this->redirect(array('action'=>'index'));
    }


    /**
     * Login.
     *
     * @return void
     */
    public function login()
    {
        /*maybe factor in _common login too*/
        if (!$this->Auth->user()) {
            return;
        }
        $this->_common_login($this->Auth->redirectUrl());

    }


    /**
     * used by the element form
     *
     * @return void
     */
    public function check_login()
    {
        $user = $this->Auth->identify();

        $redirectUrl = $this->request->getQuery('redirectTo', $this->Auth->redirectUrl());
        $failedUrl = array(
            'action' => 'login',
            '?' => array('redirectTo' => $redirectUrl)
        );

        if ($user) {
            // group_id 5 => users is inactive
            if ($user['group_id'] == 5) {
                $this->flash(
                    __(
                        'This account has been marked inactive. '.
                        'You cannot log in with it anymore. '.
                        'Please contact an admin if this is a mistake.', true
                    ),
                    $failedUrl
                );
            }
            // group_id 6 => users is spammer
            else if ($user['group_id'] == 6) {
                $this->flash(
                    __(
                        'This account has been marked as a spammer. '.
                        'You cannot log in with it anymore. '.
                        'Please contact an admin if this is a mistake.', true
                    ),
                    $failedUrl
                );
            } else {
                $this->Auth->setUser($user);
                $this->_common_login($redirectUrl);
            }
        } else {
            if (empty($this->request->getData('username'))) {
                $this->flash(
                    __(
                        'You must fill in your '.
                        'username and password.', true
                    ),
                    $failedUrl
                );
            } else {
                $this->flash(
                    __(
                        'Login failed. Make sure that your Caps Lock '.
                        'and Num Lock are not unintentionally turned on. '.
                        'Your password is case-sensitive.', true
                    ),
                    $failedUrl
                );
            }
        }
    }

    /**
     * Used by the login functions
     *
     * @param mixed $redirectUrl URL to which user is redirected after logged in.
     *
     * @return void
     */

    private function _common_login($redirectUrl)
    {
        $userId = $this->Auth->user('id');

        // update the last login time
        $user = $this->Users->get($userId);
        $user->last_time_active = time();
        $this->Users->save($user);

        $plainTextPassword = $this->request->getData('password');
        $this->Users->updatePasswordVersion($userId, $plainTextPassword);

        if (empty($this->request->getData('rememberMe'))) {
            $this->RememberMe->delete();
        } else {
            $hashedPassword = $this->Users->get($userId, ['fields' => 'password'])->password;
            $this->RememberMe->remember(
                $this->request->getData('username'),
                $hashedPassword
            );
        }

        $this->redirect($redirectUrl);
    }


    /**
     * Logout.
     *
     * @return void
     */
    public function logout()
    {
        $this->RememberMe->delete();
        $this->request->getSession()->delete('last_used_lang');
        $this->redirect($this->Auth->logout());
    }


    /**
     * Register.
     *
     * @return void
     */
    public function register()
    {
        // --------------------------------------------------
        //   Cases where registration shouldn't work.
        // --------------------------------------------------

        // Already logged in
        if ($this->Auth->User('id')) {
            $this->redirect('/');
        }

        // No data
        if (empty($this->request->getData())) {
            $this->set('username', null);
            $this->set('email', null);
            $this->set('language', null);
            return;
        }

        $this->set('username', $this->request->getData('username'));
        $this->set('email', $this->request->getData('email'));
        $this->set('language', $this->request->getData('language'));

        // Password is empty
        if ($this->request->getData('password') == '') {
            $this->Flash->set(
                __('Password cannot be empty.')
            );
            $this->request = $this->request
                ->withoutData('password')
                ->withoutData('quiz');
            return;
        }

        // Did not answer the quiz properly
        $correctAnswer = mb_substr($this->request->getData('email'), 0, 5, 'UTF-8');
        if ($this->request->getData('quiz') != $correctAnswer) {
            $this->Flash->set(
                __('Wrong answer to the question.')
            );
            $this->request = $this->request
                ->withoutData('password')
                ->withoutData('quiz');
            return;
        }

        // Did not accept terms of use
        if (!$this->request->getData('acceptation_terms_of_use')) {
            $this->Flash->set(
                __('You did not accept the terms of use.')
            );
            $this->request = $this->request
                ->withoutData('password')
                ->withoutData('quiz');
            return;
        }

        // --------------------------------------------------


        // At this point, we're fine, so we can create the user
        $newUser = $this->Users->newEntity(
            $this->request->getData(),
            ['fields' => ['username', 'password', 'email']]
        );
        $newUser->since = date("Y-m-d H:i:s");
        $newUser->group_id = 4;

        // And we save
        if ($this->Users->save($newUser)) {
            $this->loadModel('UsersLanguages');
            // Save native language
            $language = $this->request->getData('language');
            if (!empty($language) && $language != 'none') {
                $userLanguage = $this->UsersLanguages->newEntity([
                    'of_user_id' => $newUser->id,
                    'by_user_id' => $newUser->id,
                    'level' => 5,
                    'language_code' => $language
                ]);
                $this->UsersLanguages->save($userLanguage);
            }

            $user = $this->Auth->identify();
            $this->Auth->setUser($user);

            $profileUrl = Router::url(
                array(
                    'controller' => 'user',
                    'action' => 'profile',
                    $this->Auth->user('username')
                )
            );
            $this->Flash->set(
                '<p><strong>'
                .__("Welcome to Tatoeba!")
                .'</strong></p><p>'
                .format(
                    __(
                        "To start things off, we encourage you to go to your ".
                        "<a href='{url}'>profile</a> and let us know which ".
                        "languages you speak or are interested in.",
                        true
                    ),
                    array('url' => $profileUrl)
                )
                .'</p>'
            );

            $this->redirect(
                array(
                    'controller' => 'pages',
                    'action' => 'index'
                )
            );
        }

    }


    /**
     * Get new password, for those who have forgotten their password.
     * TODO HACKISH FUNCTION
     *
     * @return void
     */
    public function new_password()
    {
        $sentEmail = $this->request->getData('email');
        if (!empty($sentEmail)) {
            $user = $this->Users->findByEmail($sentEmail)->first();

            // check if user exists, if so :
            if ($user) {
                $newPassword = $this->Users->generatePassword();
                $user->password = $newPassword;

                if ($this->Users->save($user)) { // if saved
                    // prepare message
                    $this->Mailer->sendNewPassword(
                        $user->email,
                        $user->username,
                        $newPassword
                    );

                    $flashMessage = format(
                        __('Your new password has been sent to {email}.'),
                        array('email' => $user->email)
                    );
                    $flashMessage .= "<br/>";
                    $flashMessage .= __(
                        'You may need to check your spam folder '.
                        'to find this message.', true
                    );
                    $this->flash($flashMessage, '/users/login');
                }
            } else {
                $this->flash(
                    __(
                        'There is no registered user with this email address: ', true
                    ) . $sentEmail,
                    '/users/new_password'
                );
            }
        }
    }

    /**
     * Search for user given a username.
     *
     * @return void
     */
    public function search()
    {
        $userId = $this->Users->getIdFromUsername($this->request->getData('username'));

        if ($userId != null) {
            $this->redirect(array("action" => "show", $userId));
        } else {
            $this->flash(
                __(
                    'No user with this username: ', true
                ).$this->request->getData('username'),
                '/users/all/'
            );
        }
    }


    /**
     * Display information about a user.
     * NOTE : This should not be used anymore in the future.
     * We'll use user/profile/$username instead.
     *
     * @param int|string $id Id of user. For random user, parameter is 'random'.
     *
     * @return void
     */
    public function show($id)
    {
        if ($id == 'random') {
            $id = null;
        }

        $user = $this->Users->getUserByIdWithExtraInfo($id);

        if ($user != null) {
            $this->helpers[] = 'Wall';
            $this->helpers[] = 'Messages';
            $this->helpers[] = 'Members';

            $commentsPermissions = $this->Permissions->getCommentsOptions(
                $user->sentence_comments
            );

            $this->set('user', $user);
            $this->set('commentsPermissions', $commentsPermissions);
        } else {
            $this->request->getSession()->write('last_user_id', $id);
            $this->flash(
                format(
                    __('No user with this ID: {id}'),
                    array('id' => $id)
                ),
                '/users/all/'
            );
        }
    }

    /**
     * Display list of all members.
     *
     * @return void
     */
    public function all()
    {
        $this->helpers[] = 'Members';

        $this->loadModel('LastContributions');
        $currentContributors = $this->LastContributions->getCurrentContributors();
        $total = $this->LastContributions->getTotal($currentContributors);

        $this->set('currentContributors', $currentContributors);
        $this->set('total', $total);

        $this->paginate = array(
            'limit' => 20,
            'order' => array('group_id', 'id'),
            'fields' => array('id', 'username', 'since', 'image', 'group_id'),
        );

        $query = $this->Users->find()->where(['Users.group_id <' => 5]);
        $users = $this->paginate($query);
        $this->set('users', $users);
    }


    /**
     * Check if the username already exist or not.
     *
     * @param string $username Username to check.
     *
     * @return void
     */
    public function check_username($username)
    {
        $this->layout = null;
        $user = $this->Users->getIdFromUsername($username);

        if ($user) {
            $this->set('data', true);
        } else {
            $this->set('data', false);
        }
    }


    /**
     * Check if the email already exist or not.
     *
     * @param string $email Email to check.
     *
     * @return void
     */
    public function check_email($email)
    {
        $this->layout = null;
        $userId = $this->Users->getIdFromEmail($email);

        if ($userId) {
            $this->set('data', true);
        } else {
            $this->set('data', false);
        }
    }


    public function for_language($lang = null)
    {
        $this->helpers[] = 'Members';

        $this->loadModel('UsersLanguages');
        $usersLanguages = $this->UsersLanguages->getNumberOfUsersForEachLanguage();

        if (empty($lang)) {
            $lang = $usersLanguages->first()->language_code;
        }

        $this->paginate = $this->UsersLanguages->getUsersForLanguage($lang);
        $users = $this->paginate('UsersLanguages');

        $this->set('users', $users);
        $this->set('usersLanguages', $usersLanguages);
        $this->set('lang', $lang);
    }
}
