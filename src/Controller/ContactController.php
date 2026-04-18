<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Repository\SettingRepository;
use App\Seo\SeoResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Contrôleur public du formulaire de contact : traitement, envoi d'e-mail et limitation du débit (rate limiter).
 */
class ContactController extends AbstractController
{
    public function __construct(private readonly string $brandName) {}

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        SettingRepository $settingRepository,
        SeoResolver $seoResolver,
        #[Autowire(service: 'limiter.contact_form')]
        RateLimiterFactory $contactFormLimiter, // doit matcher "contact_form"
    ): Response {
        // Si ton business rule = contact réservé aux clients connectés
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Rate limit uniquement sur la soumission
        if ($request->isMethod('POST')) {
            $key = sprintf('user:%d', $user->getId()); // ou IP si tu préfères
            $limiter = $contactFormLimiter->create($key);
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                // 429
                throw new TooManyRequestsHttpException(
                    null,
                    'Trop de messages envoyés. Réessaie plus tard.'
                );
            }
        }

        $setting = $settingRepository->findOneBy([]);
        if (!$setting) {
            throw $this->createNotFoundException('Setting introuvable');
        }

        $contact = new Contact();
        $contact->setEmail($user->getEmail());

        $form = $this->createForm(\App\Form\ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact->setUser($user);

            $em->persist($contact);
            $em->flush();

            // Email
            $email = (new TemplatedEmail())
                ->from($setting->getEmailNoReply())
                ->replyTo($contact->getEmail())
                ->to($setting->getEmail())
                ->subject($contact->getSubject() ?? 'Nouveau message contact')
                ->htmlTemplate('contact/email.html.twig')
                ->context([
                    'contact' => $contact,
                    'user' => $user,
                ]);

            $mailer->send($email);

            $this->addFlash('success', 'Votre message a bien été pris en compte !');

            return $this->redirectToRoute('app_contact');
        }

        $seo = $seoResolver->forStaticPage([
            'title' => 'Contactez-nous | ' . $this->brandName,
            'description' => 'Une question sur nos miels naturels ? Contactez l\'équipe ' . $this->brandName . ', nous vous répondrons dans les plus brefs délais.',
            'route' => 'app_contact',
            'robots' => 'noindex,follow',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL)],
                ['name' => 'Contact', 'url' => $this->generateUrl('app_contact', [], UrlGeneratorInterface::ABSOLUTE_URL)],
            ],
        ], $request);

        return $this->render('contact/index.html.twig', [
            'form' => $form,
            'seo' => $seo,
        ]);
    }
}
