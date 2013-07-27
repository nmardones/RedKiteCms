<?php
/**
 * This file is part of the AlphaLemon CMS Application and it is distributed
 * under the GPL LICENSE Version 2.0. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) AlphaLemon <webmaster@alphalemon.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.alphalemon.com
 *
 * @license    GPL LICENSE Version 2.0
 *
 */

namespace AlphaLemon\AlphaLemonCmsBundle\Controller;

use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\HttpFoundation\Response;
use AlphaLemon\AlphaLemonCmsBundle\Model\AlUser;
use AlphaLemon\AlphaLemonCmsBundle\Model\AlRole;
use AlphaLemon\AlphaLemonCmsBundle\Core\Form\Security\AlUserType;
use AlphaLemon\AlphaLemonCmsBundle\Core\Form\Security\AlRoleType;
use AlphaLemon\AlphaLemonCmsBundle\Core\Exception\General\RuntimeException;

/**
 * Implements the authentication action to grant the use of the CMS.
 *
 * @author alphalemon <webmaster@alphalemon.com>
 */
class SecurityController extends Base\BaseController
{
    private $factoryRepository = null;
    private $userRepository = null;
    private $roleRepository;

    public function loginAction()
    {
        $request = $this->container->get('request');
        $params = $this->checkRequestError();

        $response = null;
        $template = 'AlphaLemonCmsBundle:Security:graphical-login.html.twig';
        if ($request->isXmlHttpRequest()) {
            $response = new Response();
            $response->setStatusCode('403');
            $template = 'AlphaLemonCmsBundle:Security:login.html.twig';
        }

        $factoryRepository = $this->container->get('alpha_lemon_cms.factory_repository');
        $pageReporitory = $factoryRepository->createRepository('Page');
        $languageReporitory = $factoryRepository->createRepository('Language');

        $alPage = $pageReporitory->homePage();
        $alLanguage = $languageReporitory->mainLanguage();
        $params['target'] = '/backend/' . $alLanguage->getLanguageName() . '/' . $alPage->getPageName();
        
        return $this->container->get('templating')->renderResponse($template, $params, $response);
    }

    /**
     * @codeCoverageIgnore
     */
    public function securityCheckAction()
    {
        // The security layer will intercept this request
    }

    /**
     * @codeCoverageIgnore
     */
    public function logoutAction()
    {
        // The security layer will intercept this request
    }

    public function listUsersAction()
    {
        return $this->loadUsers();
    }

    public function listRolesAction()
    {
        return $this->loadRoles();
    }

    public function showUserAction()
    {
        $request = $this->container->get('request');
        $isNewUser = (null !== $request->get('id') && 0 != $request->get('id')) ? false : true;
        $user = (!$isNewUser) ? $this->userRepository()->fromPk($request->get('id')) : new AlUser();
        $form = $this->container->get('form.factory')->create(new AlUserType(), $user);

        $message = '';
        $errors = array();
        if ('POST' === $request->getMethod()) { 
            $userName = $request->get('al_username');
            if ($isNewUser && null !== $this->userRepository()->fromUsername($userName)) {
                throw new RuntimeException('The username already exists');
            }
            
            $alUser = $this->container->get('security.context')->getToken()->getUser();

            $user->setRoleId($request->get('al_role_id'));
            $user->setUsername($userName);
            $user->setPassword($request->get('al_password'));
            $user->setEmail($request->get('al_email'));

            $validator = $this->container->get('validator');
            $errors = $validator->validate($user);
            if (count($errors) == 0) {
                $factory = $this->container->get('security.encoder_factory');
                $encoder = $factory->getEncoder($alUser);
                $salt = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
                $password = $encoder->encodePassword($request->get('al_password'), $salt);

                $user->setSalt($salt);
                $user->setPassword($password);

                $message = "User has not been saved";
                if ($user->save() > 0) {
                    $message = "User has been saved";
                }
                $message = $this->translate($message);

                // Let's refresh the form with the saved data when the user is edited
                if ( ! $isNewUser) {
                    $form = $this->container->get('form.factory')->create(new AlUserType(), $user);
                }
            }
        }

        return $this->container->get('templating')->renderResponse('AlphaLemonCmsBundle:Security:user.html.twig', array(
            'form' => $form->createView(),
            'errors' => $errors,
            'message' => $message,
            'configuration' => $this->container->get('alpha_lemon_cms.configuration'),
        ));
    }

    public function showRoleAction()
    {
        $request = $this->container->get('request');
        $isNewRole = (null !== $request->get('id') && 0 != $request->get('id')) ? false : true;
        $role = (null !== $request->get('id') && 0 != $request->get('id')) ? $this->roleRepository()->fromPK($request->get('id')) : new AlRole();
        $form = $this->container->get('form.factory')->create(new AlRoleType(), $role);

        $message = '';
        $errors = array();
        if ('POST' === $request->getMethod()) {
            $roleName = strtoupper($request->get('al_rolename'));
            if ($isNewRole && null !== $this->roleRepository()->fromRolename($roleName)) {
                throw new RuntimeException('The role already exists');
            }
            
            $role->setRole($roleName);
            $validator = $this->container->get('validator');
            $errors = $validator->validate($role);
            if (count($errors) == 0) {
                $message = "Role has not been saved";
                if ($role->save() > 0) {
                    $message = "Role has been saved";
                }
                $message = $this->translate($message);

                // Let's refresh the form with the saved data when the user is edited
                if (!$isNewRole) $form = $this->container->get('form.factory')->create(new AlRoleType(), $role);
            }
        }

        return $this->container->get('templating')->renderResponse('AlphaLemonCmsBundle:Security:role.html.twig', array(
            'form' => $form->createView(),
            'errors' => $errors,
            'message' => $message,
            'configuration' => $this->container->get('alpha_lemon_cms.configuration'),
        ));
    }

    public function deleteUserAction()
    {
        $request = $this->container->get('request');
        if (null !== $request->get('id')) {
            $user = $this->userRepository()->fromPk($request->get('id'));
            $user->delete();
        }

        return $this->loadUsers();
    }

    public function deleteRoleAction()
    {
        $request = $this->container->get('request');
        if (null !== $request->get('id')) {
            $user = $this->roleRepository()->fromPK($request->get('id'));
            $user->delete();
        }

        return $this->loadRoles();
    }
    
    protected function checkRequestError()
    {
        $request = $this->container->get('request');
        $session = $request->getSession();
        
        // get the error if any (works with forward and redirect -- see below)
        $error = '';
        if ($request->attributes->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(SecurityContext::AUTHENTICATION_ERROR);
        } elseif (null !== $session && $session->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $session->get(SecurityContext::AUTHENTICATION_ERROR);
            $session->remove(SecurityContext::AUTHENTICATION_ERROR);
        }

        if ($error) {
            // TODO: this is a potential security risk (see http://trac.symfony-project.org/ticket/9523)
            $error = $error->getMessage();
        }

        // last username entered by the user
        $lastUsername = (null === $session) ? '' : $session->get(SecurityContext::LAST_USERNAME);
        
        return array(
            "error" => $error,
            "last_username" => $lastUsername,
        );
    }

    private function loadUsers()
    {
        return $this->container->get('templating')->renderResponse('AlphaLemonCmsBundle:Security:users_list.html.twig', array(
            'users' => $this->userRepository()->activeUsers(),
            'configuration' => $this->container->get('alpha_lemon_cms.configuration'),
        ));
    }

    private function loadRoles()
    {
        return $this->container->get('templating')->renderResponse('AlphaLemonCmsBundle:Security:roles_list.html.twig', array(
            'roles' => $this->roleRepository()->activeRoles(),
            'configuration' => $this->container->get('alpha_lemon_cms.configuration'),
        ));
    }

    private function factoryRepository()
    {
        if (null === $this->factoryRepository) {
            $this->factoryRepository = $this->container->get('alpha_lemon_cms.factory_repository');
        }

        return $this->factoryRepository;
    }

    private function userRepository()
    {
        if (null === $this->userRepository) {
            $this->userRepository = $this->factoryRepository()->createRepository('User');
        }

        return $this->userRepository;
    }

    private function roleRepository()
    {
        if (null === $this->roleRepository) {
            $this->roleRepository = $this->factoryRepository()->createRepository('Role');
        }

        return $this->roleRepository;
    }
}
