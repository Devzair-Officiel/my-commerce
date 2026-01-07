<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Order;
use App\Entity\Address;
use App\Entity\OrderDetails;
use App\Form\EditUserFormType;
use App\Form\EditPasswordFormType;
use App\Form\RegistrationFormType;
use App\Repository\OrderRepository;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\OrderDetailsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account')]
    public function index(AddressRepository $addressRepo, OrderDetailsRepository $orderDetailsRepo, OrderRepository $orderRepo, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $orders = $orderRepo->findBy(['client_name' => $user->getFullName()]);
        }

        $order_detail = $orderDetailsRepo->findBy(['myOrder' => $orders]);

        $addresses = $addressRepo->findByUser($user);

        // Formulaire edit user
        $form = $this->createForm(EditUserFormType::class, $user);
        $form->handleRequest($request);
        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted() && $form->isValid()) {
                $em->persist($user);
                $em->flush();

                // Retourner une réponse JSON en cas de succès
                return new JsonResponse([
                    'status' => 'success',
                    'message' => 'Vos informations ont été mises à jour avec succès.'
                ]);
            } else {
                // Collecter les erreurs de formulaire et les renvoyer
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[$error->getOrigin()->getName()] = $error->getMessage();
                }

                // Retourner une réponse JSON en cas d'échec
                return new JsonResponse([
                    'status' => 'error',
                    'errors' => $errors
                ], 422);
            }
        }
        ////////////////////////

        return $this->render('account/index.html.twig', [
            'controller_name' => 'AccountController',
            'addresses' => $addresses,
            'user' => $user,
            'orders' => $orders,
            'order_detail' => $order_detail,
            'editForm' => $form->createView(),
        ]);
    }


    #[Route('/account/order/{id}', name: 'app_account_order_detail')]
    public function getOrderDetail(Order $order)
    {
        return $this->render('account/order_detail.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route("/api/change-password", name: 'api_change_password', methods: ['POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em)
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $currentPassword = $data['currentPassword'];
        $newPassword = $data['newPassword'] ?? '';

        if ($user instanceof User) {
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                return new JsonResponse(['status' => 'error', 'message' => 'Mot de passe actuel incorrect.'], 422);
            }

            if (strlen($newPassword) < 6) {
                return new JsonResponse(['status' => 'error', 'message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'], 422);
            }

            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $em->persist($user);
            $em->flush();

            return new JsonResponse(['status' => 'success', 'message' => 'Mot de passe mis à jour avec succès.']);
        }
    }
}
