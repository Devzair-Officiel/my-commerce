<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Form\EditUserFormType;
use App\Repository\AddressRepository;
use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(
        AddressRepository $addressRepo,
        OrderRepository $orderRepo,
        ContactRepository $contactRepo,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Pagination commandes liées au user
        $limit = 10;
        $page = 1;
        $pagination = $orderRepo->findPaginatedByUser($user, $page, $limit);
        $orders = $pagination['orders'];
        $ordersTotal = $pagination['total'];
        $ordersPage = $page;
        $ordersPages = (int) ceil($ordersTotal / $limit);

        $addresses = $addressRepo->findBy(['user' => $user], ['id' => 'DESC']);
        $contacts = $contactRepo->findBy(['user' => $user], ['createdAt' => 'DESC'], 10);

        $form = $this->createForm(EditUserFormType::class, $user);
        $form->handleRequest($request);

        // AJAX update profil
        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted() && $form->isValid()) {
                $em->flush();

                return new JsonResponse([
                    'status' => 'success',
                    'message' => 'Vos informations ont été mises à jour avec succès.'
                ]);
            }

            $errors = [];
            if ($form->isSubmitted()) {
                foreach ($form->getErrors(true) as $error) {
                    $origin = $error->getOrigin();
                    $name = $origin ? $origin->getName() : '_form';
                    $errors[$name] = $error->getMessage();
                }
            }

            return new JsonResponse([
                'status' => 'error',
                'errors' => $errors
            ], 422);
        }

        return $this->render('account/index.html.twig', [
            'addresses' => $addresses,
            'user' => $user,
            'orders' => $orders,
            'ordersTotal' => $ordersTotal,
            'ordersPage' => $ordersPage,
            'ordersPages' => $ordersPages,
            'contacts' => $contacts,
            'editForm' => $form->createView(),
        ]);
    }

    #[Route('/account/order/{id<\d+>}', name: 'app_account_order_detail', methods: ['GET'])]
    public function getOrderDetail(Order $order): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Ownership check
        if ($order->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        // On n’a pas besoin d’un repo OrderDetails : $order->getOrderDetails() suffit
        return $this->render('account/order_detail.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/account/message/{id<\d+>}', name: 'app_account_message_detail', methods: ['GET'])]
    public function getMessageDetail(Contact $contact): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Ownership check
        if ($contact->getUser() && $contact->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('account/message_detail.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/api/change-password', name: 'api_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Invalid JSON',
            ], 400);
        }

        $csrfToken = $request->headers->get('X-CSRF-TOKEN');

        if (!is_string($csrfToken) || !$this->isCsrfTokenValid('change_password', $csrfToken)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Jeton CSRF invalide.',
            ], 419);
        }

        $currentPassword = $payload['currentPassword'] ?? null;
        $newPassword = $payload['newPassword'] ?? null;
        $confirmPassword = $payload['confirmPassword'] ?? null;

        if (!is_string($newPassword) || !is_string($confirmPassword)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Invalid payload',
            ], 400);
        }

        $newPassword = trim($newPassword);
        $confirmPassword = trim($confirmPassword);

        if ($newPassword === '' || $confirmPassword === '') {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Veuillez remplir tous les champs requis.',
            ], 422);
        }

        if ($newPassword !== $confirmPassword) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Les deux mots de passe ne correspondent pas.',
            ], 422);
        }

        if (mb_strlen($newPassword) < 8) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            ], 422);
        }

        if (mb_strlen($newPassword) > 4096) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Le mot de passe est trop long.',
            ], 422);
        }

        if ($user->hasLocalPassword()) {
            if (!is_string($currentPassword) || $currentPassword === '') {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Veuillez saisir votre mot de passe actuel.',
                ], 422);
            }

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Mot de passe actuel incorrect.',
                ], 422);
            }
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $em->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => $user->hasGoogleLogin()
                ? 'Mot de passe enregistré avec succès. Vous pouvez maintenant vous connecter avec Google ou avec votre email et mot de passe.'
                : 'Mot de passe mis à jour avec succès.',
        ]);
    }

    #[Route('/account/orders/ajax', name: 'app_account_orders_ajax', methods: ['GET'])]
    public function ordersAjax(
        OrderRepository $orderRepo,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $pagination = $orderRepo->findPaginatedByUser($user, $page, $limit);
        $orders = $pagination['orders'];
        $ordersTotal = $pagination['total'];
        $ordersPage = $page;
        $ordersPages = (int) ceil($ordersTotal / $limit);

        return $this->render('account/_orders_table.html.twig', [
            'orders' => $orders,
            'ordersTotal' => $ordersTotal,
            'ordersPage' => $ordersPage,
            'ordersPages' => $ordersPages,
            'ajax' => true,
        ]);
    }
}
