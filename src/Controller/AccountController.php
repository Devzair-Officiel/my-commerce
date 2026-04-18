<?php

namespace App\Controller;

use App\Dto\EditUserInput;
use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\Shipment;
use App\Entity\User;
use App\Form\EditUserFormType;
use App\Repository\AddressRepository;
use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\Invoice\InvoicePdfRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Gère l'espace client : profil, historique des commandes, adresses et messages de contact.
 * Toutes les routes sont protégées par ROLE_USER.
 */
class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(
        AddressRepository $addressRepo,
        OrderRepository $orderRepo,
        ContactRepository $contactRepo,
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
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

        $input = EditUserInput::fromUser($user);
        $form = $this->createForm(EditUserFormType::class, $input);
        $form->handleRequest($request);

        // AJAX update profil
        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted() && $form->isValid()) {
                $user->setLastname($input->lastname);
                $user->setFirstname($input->firstname);
                $user->setCivility($input->civility);
                $user->setPhone($input->phone);

                $newEmail = mb_strtolower(trim($input->email));
                $emailChanged = $newEmail !== mb_strtolower(trim((string) $user->getEmail()));

                if ($emailChanged) {
                    // Vérifier que le nouvel email n'est pas déjà utilisé
                    $existing = $em->getRepository(User::class)->findOneBy(['email' => $newEmail]);
                    if ($existing !== null && $existing->getId() !== $user->getId()) {
                        return new JsonResponse([
                            'status' => 'error',
                            'errors' => ['email' => 'Cet email est déjà associé à un autre compte.'],
                        ], 422);
                    }

                    $token = $user->setPendingEmailChange($newEmail);
                    $em->flush();

                    $confirmUrl = $this->generateUrl(
                        'app_confirm_email_change',
                        ['token' => $token],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    );

                    $mailer->send(
                        (new TemplatedEmail())
                            ->from(new Address('contact@nidemiel.com', 'Nidemiel'))
                            ->to($newEmail)
                            ->subject('Confirmez votre nouvelle adresse e-mail')
                            ->htmlTemplate('emails/email_change_confirmation.html.twig')
                            ->context([
                                'user'       => $user,
                                'newEmail'   => $newEmail,
                                'confirmUrl' => $confirmUrl,
                            ])
                    );

                    return new JsonResponse([
                        'status'  => 'email_pending',
                        'message' => 'Vos informations ont été mises à jour. Un e-mail de confirmation a été envoyé à ' . $newEmail . '. Cliquez sur le lien pour valider votre nouvelle adresse.',
                    ]);
                }

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

    #[Route('/account/order/{orderId<\d+>}/tracking/{shipmentId<\d+>}', name: 'app_account_order_tracking', methods: ['GET'])]
    public function orderTracking(int $orderId, int $shipmentId, OrderRepository $orderRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $order = $orderRepo->find($orderId);
        if (!$order || $order->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        /** @var Shipment|null $shipment */
        $shipment = null;
        foreach ($order->getShipments() as $s) {
            if ($s->getId() === $shipmentId) {
                $shipment = $s;
                break;
            }
        }

        if (!$shipment) {
            throw $this->createNotFoundException('Expédition introuvable.');
        }

        // Trier les statuts du plus récent au plus ancien
        $statuses = $shipment->getShipmentStatuses()->toArray();
        usort($statuses, fn ($a, $b) => ($b->getOccuredAt() ?? new \DateTimeImmutable('0000-01-01'))
            <=> ($a->getOccuredAt() ?? new \DateTimeImmutable('0000-01-01')));

        return $this->render('account/order_tracking.html.twig', [
            'order'    => $order,
            'shipment' => $shipment,
            'statuses' => $statuses,
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
        if (!\is_array($payload)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Invalid JSON',
            ], 400);
        }

        $csrfToken = $request->headers->get('X-CSRF-TOKEN');

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid('change_password', $csrfToken)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Jeton CSRF invalide.',
            ], 419);
        }

        $currentPassword = $payload['currentPassword'] ?? null;
        $newPassword = $payload['newPassword'] ?? null;
        $confirmPassword = $payload['confirmPassword'] ?? null;

        if (!\is_string($newPassword) || !\is_string($confirmPassword)) {
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
            if (!\is_string($currentPassword) || $currentPassword === '') {
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

    #[Route('/account/confirm-email/{token}', name: 'app_confirm_email_change', methods: ['GET'])]
    public function confirmEmailChange(string $token, UserRepository $userRepo, EntityManagerInterface $em): Response
    {
        $user = $userRepo->findOneBy(['pendingEmailToken' => $token]);

        if ($user === null || !$user->isPendingEmailTokenValid($token)) {
            $this->addFlash('error', 'Ce lien de confirmation est invalide ou a expiré.');
            return $this->redirectToRoute('app_account');
        }

        // Vérifier que le nouvel email n'est pas entre-temps pris par quelqu'un d'autre
        $newEmail = $user->getPendingEmail();
        $existing = $userRepo->findOneBy(['email' => $newEmail]);
        if ($existing !== null && $existing->getId() !== $user->getId()) {
            $user->clearPendingEmail();
            $em->flush();
            $this->addFlash('error', 'Cette adresse e-mail est désormais associée à un autre compte.');
            return $this->redirectToRoute('app_account');
        }

        $user->applyPendingEmail();
        $em->flush();

        $this->addFlash('success', 'Votre adresse e-mail a bien été mise à jour.');
        return $this->redirectToRoute('app_account');
    }

    #[Route('/account/invoice/{id<\d+>}/download', name: 'app_account_invoice_download', methods: ['GET'])]
    public function downloadInvoice(Invoice $invoice, InvoicePdfRenderer $renderer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $order = $invoice->getCustomerOrder();
        if ($order === null || $order->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$invoice->isIssued()) {
            throw $this->createNotFoundException('Facture non disponible.');
        }

        $pdf = $renderer->render($invoice);
        $filename = $renderer->getFilename($invoice);

        return new Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
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
